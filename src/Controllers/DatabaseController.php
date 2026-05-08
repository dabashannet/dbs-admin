<?php
/*
 * @Author:  lvtu@dabashan.cc
 * @Date: 2026-05-08 21:42:20
 * @LastEditTime: 2026-05-08 21:42:20
 * Copyright (c) 2025 by Dabashan.cc, All Rights Reserved.
 */


namespace Dabashan\DbsAdmin\Controllers;

use Dabashan\DbsAdmin\Traits\HasApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DatabaseController extends Controller
{
    use HasApiResponse;

    /**
     * 验证表名白名单，防止 SQL 注入
     */
    private function validateTables(array $tables): array
    {
        $validTables = collect(DB::select('SHOW TABLES'))
            ->map(fn($row) => current((array) $row))
            ->flip();

        return collect($tables)
            ->filter(fn($t) => $validTables->has($t))
            ->values()
            ->all();
    }

    /**
     * 列出所有数据表状态
     */
    public function tables()
    {
        $tables = DB::select('SHOW TABLE STATUS');
        $result = collect($tables)->map(function ($table) {
            return [
                'name' => $table->Name,
                'engine' => $table->Engine,
                'rows' => $table->Rows,
                'data_length' => $table->Data_length,
                'index_length' => $table->Index_length,
                'total_size' => $table->Data_length + $table->Index_length,
                'collation' => $table->Collation,
                'create_time' => $table->Create_time,
                'update_time' => $table->Update_time,
                'comment' => $table->Comment,
            ];
        });

        return $this->success($result);
    }

    /**
     * 备份选中表
     */
    public function backup(Request $request)
    {
        $request->validate([
            'tables' => 'required|array|min:1',
            'name' => 'nullable|string|max:100',
        ]);

        $tables = $this->validateTables($request->input('tables'));
        if (empty($tables)) {
            return $this->fail('无效的表名');
        }

        if ($name = $request->input('name')) {
            $safeName = preg_replace('/[^\w\x{4e00}-\x{9fa5}-]/u', '_', $name);
            $safeName = trim($safeName, '_');
            if (empty($safeName)) {
                $safeName = 'backup_' . date('Y-m-d_His');
            }
            $filename = $safeName . '.sql';

            $counter = 1;
            while (Storage::disk('local')->exists('backups/' . $filename)) {
                $filename = $safeName . '_' . $counter . '.sql';
                $counter++;
            }
        } else {
            $filename = date('Y-m-d_His') . '_' . implode('_', array_slice($tables, 0, 3));
            if (count($tables) > 3) {
                $filename .= '_etc' . count($tables);
            }
            $filename .= '.sql';
        }

        $sql = $this->generateSql($tables);

        if (!Storage::disk('local')->exists('backups')) {
            Storage::disk('local')->makeDirectory('backups');
        }

        Storage::disk('local')->put("backups/{$filename}", $sql);

        return $this->success([
            'filename' => $filename,
            'size' => strlen($sql),
            'tables' => $tables,
        ], '备份成功');
    }

    /**
     * 修复表
     */
    public function repair(Request $request)
    {
        $request->validate(['tables' => 'required|array|min:1']);
        $tables = $this->validateTables($request->input('tables'));
        if (empty($tables)) {
            return $this->fail('无效的表名');
        }

        $results = [];
        foreach ($tables as $table) {
            $result = DB::select("REPAIR TABLE `{$table}`");
            $results[$table] = $result[0]->Msg_text ?? 'OK';
        }

        return $this->success($results, '修复完成');
    }

    /**
     * 优化表
     */
    public function optimize(Request $request)
    {
        $request->validate(['tables' => 'required|array|min:1']);
        $tables = $this->validateTables($request->input('tables'));
        if (empty($tables)) {
            return $this->fail('无效的表名');
        }

        $results = [];
        foreach ($tables as $table) {
            $result = DB::select("OPTIMIZE TABLE `{$table}`");
            $results[$table] = $result[0]->Msg_text ?? 'OK';
        }

        return $this->success($results, '优化完成');
    }

    /**
     * 备份文件列表
     */
    public function backupList()
    {
        if (!Storage::disk('local')->exists('backups')) {
            return $this->success([]);
        }

        $files = Storage::disk('local')->files('backups');
        $result = collect($files)
            ->filter(fn($f) => str_ends_with($f, '.sql'))
            ->map(function ($file) {
                return [
                    'filename' => basename($file),
                    'size' => Storage::disk('local')->size($file),
                    'created_at' => date('Y-m-d H:i:s', Storage::disk('local')->lastModified($file)),
                ];
            })
            ->sortByDesc('created_at')
            ->values();

        return $this->success($result);
    }

    /**
     * 恢复备份
     */
    public function restore(Request $request)
    {
        $request->validate(['filename' => 'required|string']);
        $filename = $request->input('filename');

        // 防止目录遍历攻击
        if (str_contains($filename, '/') || str_contains($filename, '\\') || str_contains($filename, '..')) {
            return $this->fail('非法文件名');
        }

        // 仅允许 .sql 后缀
        if (!str_ends_with(strtolower($filename), '.sql')) {
            return $this->fail('仅支持 .sql 格式的备份文件');
        }

        $path = "backups/{$filename}";
        if (!Storage::disk('local')->exists($path)) {
            return $this->fail('备份文件不存在');
        }

        $sql = Storage::disk('local')->get($path);

        // 验证备份文件来源
        if (!str_starts_with(trim($sql), '-- Database Backup')) {
            return $this->fail('备份文件格式不正确，仅支持系统生成的备份文件');
        }

        DB::unprepared($sql);

        return $this->success(null, '恢复成功');
    }

    /**
     * 删除备份文件
     */
    public function deleteBackup(string $filename)
    {
        if (str_contains($filename, '/') || str_contains($filename, '..')) {
            return $this->fail('非法文件名');
        }

        $path = "backups/{$filename}";
        if (Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }

        return $this->success(null, '删除成功');
    }

    /**
     * 生成 SQL 导出内容
     * 使用游标分块读取大数据表，避免 OOM
     */
    protected function generateSql(array $tables): string
    {
        $sql = "-- Database Backup\n";
        $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $sql .= "-- Tables: " . implode(', ', $tables) . "\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $table) {
            $createTable = DB::select("SHOW CREATE TABLE `{$table}`");
            if (empty($createTable)) {
                continue;
            }

            $sql .= "-- ----------------------------\n";
            $sql .= "-- Table structure for {$table}\n";
            $sql .= "-- ----------------------------\n";
            $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $createSql = $createTable[0]->{'Create Table'} ?? '';
            $sql .= $createSql . ";\n\n";

            // 使用游标分块读取，防止大表 OOM
            $sql .= "-- ----------------------------\n";
            $sql .= "-- Records of {$table}\n";
            $sql .= "-- ----------------------------\n";

            DB::table($table)->orderBy('id')->chunk(500, function ($rows) use (&$sql, $table) {
                foreach ($rows as $row) {
                    $data = (array) $row;
                    $values = collect($data)->map(function ($value) {
                        if (is_null($value)) return 'NULL';
                        return "'" . addslashes((string) $value) . "'";
                    })->implode(', ');
                    $columns = collect(array_keys($data))->map(fn($c) => "`{$c}`")->implode(', ');
                    $sql .= "INSERT INTO `{$table}` ({$columns}) VALUES ({$values});\n";
                }
            });
            $sql .= "\n";
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        return $sql;
    }
}
