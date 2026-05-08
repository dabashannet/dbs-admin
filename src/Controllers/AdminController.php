<?php
/*
 * @Author:  lvtu@dabashan.cc
 * @Date: 2026-05-08 21:42:20
 * @LastEditTime: 2026-05-08 21:42:20
 * Copyright (c) 2025 by Dabashan.cc, All Rights Reserved.
 */


namespace Dabashan\DbsAdmin\Controllers;

use Dabashan\DbsAdmin\Grid\Grid;
use Dabashan\DbsAdmin\Grid\Action;
use Dabashan\DbsAdmin\Form\Form;
use Dabashan\DbsAdmin\Notifications\Notification;
use Dabashan\DbsAdmin\Show\Show;
use Dabashan\DbsAdmin\Traits\HasApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * 后台管理 CRUD 控制器基类
 *
 * 提供通用的 CRUD 方法，子类只需覆写 grid() 和 form() 方法
 *  Resource 的完整能力
 */
abstract class AdminController extends Controller
{
    use HasApiResponse;

    protected string $model;

    protected function grid(): Grid
    {
        return Grid::make($this->model);
    }

    /**
     * 配置操作（子类可覆写自定义操作）
     *
     * 示例:
     * protected function configureActions(Grid $grid): void
     * {
     *     $grid->action(Action::make('export', '导出')->header()->type('success'));
     *     $grid->action(Action::make('view', '查看')->row()->modal(['width' => 700]));
     * }
     */
    protected function configureActions(Grid $grid): void {}

    protected function defaultGridActions(): array
    {
        return ['create', 'edit', 'view', 'delete', 'batch-delete'];
    }

    protected function applyGridConfiguration(Grid $grid): void
    {
        $this->configureActions($grid);
        $this->ensureDefaultActions($grid);
    }

    protected function ensureDefaultActions(Grid $grid): void
    {
        $existingKeys = [];
        foreach ($grid->getActions() as $a) {
            $k = $a['key'] ?? null;
            if (is_string($k) && $k !== '') {
                $existingKeys[$k] = true;
            }
        }

        foreach ($this->defaultGridActions() as $key) {
            if (!is_string($key) || $key === '' || isset($existingKeys[$key])) {
                continue;
            }

            if ($key === 'create') {
                $grid->createAction();
            } elseif ($key === 'edit') {
                $grid->editAction();
            } elseif ($key === 'view') {
                $grid->viewAction();
            } elseif ($key === 'delete') {
                $grid->deleteAction();
            } elseif ($key === 'batch-delete') {
                $grid->batchDeleteAction();
            }
        }
    }

    abstract protected function form(): Form;

    protected function detail($id): Show
    {
        $model = $this->model::findOrFail($id);
        $show = Show::make($model);

        // 默认生成全部可见字段（排除更新时间）
        foreach (array_keys($model->toArray()) as $key) {
            if ($key === 'updated_at') {
                continue;
            }
            $show->text($key, $key);
        }

        return $show;
    }

    // ==================== CRUD 接口 ====================

    public function index(Request $request)
    {
        $grid = $this->grid();
        $this->applyGridConfiguration($grid);

        // 自动为"查看"操作注入 API 路由（从当前请求路径推导资源 base URL）
        $grid->upgradeViewAction('/' . ltrim($request->path(), '/'));

        $data = $grid->resolve($request);
        // 附加 Session 通知
        $data['notifications'] = Notification::pull();

        // 如果模型支持软删除，附加软删除计数
        $model = new $this->model;
        if (method_exists($model, 'bootSoftDeletes')) {
            $data['trashedCount'] = $this->model::onlyTrashed()->count();
        }

        return $this->success($data);
    }

    public function store(Request $request)
    {
        $form = $this->form();
        $data = $this->normalizeFormData($form, $form->validate($request, 'create'), 'create');
        try {
            $model = $this->model::create($data);
            $this->afterSave($request, $model);
            return $this->success($model);
        } catch (\Illuminate\Database\QueryException $e) {
            return $this->fail('数据保存失败: ' . $this->formatDbError($e), 422);
        }
    }

    public function show($id)
    {
        $detail = $this->detail($id);
        return $this->success([
            'data' => $detail->toArray(),
            'schema' => $detail->schema(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $instance = $this->model::findOrFail($id);
        $form = $this->form();
        $data = $this->normalizeFormData($form, $form->validate($request, 'update'), 'update');
        try {
            $instance->update($data);
            $this->afterSave($request, $instance);
            return $this->success($instance);
        } catch (\Illuminate\Database\QueryException $e) {
            return $this->fail('数据更新失败: ' . $this->formatDbError($e), 422);
        }
    }

    protected function normalizeFormData(Form $form, array $data, string $context): array
    {
        try {
            $casts = [];
            try {
                $instance = new $this->model;
                if ($instance instanceof \Illuminate\Database\Eloquent\Model) {
                    $casts = $instance->getCasts();
                }
            } catch (\Throwable $e) {
                $casts = [];
            }

            $schema = $form->schema($context);
            $fields = $schema['fields'] ?? [];
            foreach ($fields as $f) {
                $key = $f['key'] ?? null;
                if (!is_string($key) || $key === '' || !array_key_exists($key, $data)) {
                    continue;
                }
                $type = $f['type'] ?? null;
                $isArrayField = in_array($type, ['images', 'files', 'checkbox', 'keyValue', 'repeater'], true);
                $value = $data[$key];

                $cast = (string) ($casts[$key] ?? '');
                $wantsArray = $cast !== '' && preg_match('/(array|json|collection|object)/i', $cast);

                if (is_string($value)) {
                    $value = trim($value);
                    if ($value !== '' && preg_match('#^https?://#i', $value)) {
                        $value = rtrim($value, ", \t\n\r\0\x0B");
                    }
                    if ($isArrayField) {
                        if ($value === '') {
                            $value = [];
                        } else {
                            $decoded = json_decode($value, true);
                            if (is_array($decoded)) {
                                $value = $decoded;
                            } else {
                                $parts = array_values(array_filter(array_map('trim', explode(',', $value)), fn($v) => $v !== ''));
                                $value = $parts;
                            }
                        }
                    }
                }

                if (is_array($value)) {
                    $value = array_values(array_filter(array_map(function ($v) {
                        if (is_string($v)) {
                            $v = trim($v);
                            if ($v !== '' && preg_match('#^https?://#i', $v)) {
                                $v = rtrim($v, ", \t\n\r\0\x0B");
                            }
                        }
                        return $v;
                    }, $value), fn($v) => $v !== null && $v !== '' && $v !== []));

                    if ($wantsArray) {
                        $data[$key] = $value;
                    } else {
                        $data[$key] = json_encode($value, JSON_UNESCAPED_UNICODE);
                    }
                    continue;
                }

                $data[$key] = $value;
            }
        } catch (\Throwable $e) {
            return $data;
        }

        return $data;
    }

    public function destroy($id)
    {
        $ids = is_array($id) ? $id : explode(',', (string) $id);
        $ids = array_filter($ids, fn($v) => is_numeric($v) && $v > 0);

        if (empty($ids)) {
            return $this->fail('无效的 ID 参数', 422);
        }

        $count = count($ids);
        $model = new $this->model;

        // 检测模型是否使用了 SoftDeletes 软删除
        $usesSoftDeletes = in_array(
            \Illuminate\Database\Eloquent\SoftDeletes::class,
            class_uses_recursive($model)
        );

        if ($usesSoftDeletes) {
            $this->model::destroy($ids);
            return $this->success([], "已软删除 {$count} 条记录");
        }

        $this->model::whereIn('id', $ids)->forceDelete();

        return $this->success([], "已永久删除 {$count} 条记录");
    }

    // ==================== Schema 接口 ====================

    /**
     * 获取表单 Schema（用于动态渲染表单）
     */
    public function formSchema(?string $context = null)
    {
        return $this->success($this->form()->schema($context));
    }

    /**
     * 获取详情 Schema（用于动态渲染详情页）
     */
    public function showSchema($id)
    {
        return $this->success($this->detail($id)->schema());
    }

    /**
     * 获取 Grid 元数据（不含数据，用于动态渲染表格结构）
     */
    public function gridMeta()
    {
        $grid = $this->grid();
        $this->applyGridConfiguration($grid);

        // 自动推导资源 base URL（去除 /grid-meta 后缀）
        $path = '/' . ltrim(request()->path(), '/');
        $path = preg_replace('#/grid-meta$#', '', $path);
        $grid->upgradeViewAction($path);

        return $this->success([
            'columns' => $grid->getColumns(),
            'filters' => $grid->getFilters(),
            ...$grid->resolveActionsByPosition(),
        ]);
    }

    // ==================== 批量操作 ====================

    /**
     * 批量更新
     */
    public function batchUpdate(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|integer',
            'field' => 'required|string',
            'value' => 'required',
        ]);

        $ids = $request->input('ids');
        $field = $request->input('field');
        $value = $request->input('value');

        $count = $this->model::whereIn('id', $ids)->update([$field => $value]);

        return $this->success(['count' => $count], "已更新 {$count} 条记录");
    }

    /**
     * 批量删除
     */
    public function batchDestroy(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|integer',
        ]);

        $ids = $request->input('ids');
        $count = count($ids);
        $this->model::whereIn('id', $ids)->delete();

        return $this->success(['count' => $count], "已删除 {$count} 条记录");
    }

    // ==================== 状态切换 ====================

    /**
     * 切换字段状态（用于表格中 switch 组件的 AJAX 切换）
     */
    public function toggle(Request $request, $id)
    {
        $request->validate([
            'field' => 'required|string',
            'value' => 'required',
        ]);

        $instance = $this->model::findOrFail($id);
        $field = $request->input('field');
        $value = $request->input('value');

        $instance->{$field} = $value;
        $instance->save();

        return $this->success(['id' => $id, 'field' => $field, 'value' => $value], '状态已更新');
    }

    // ==================== 复制 ====================

    /**
     * 复制记录（基于原记录创建新记录）
     */
    public function replicate($id)
    {
        $original = $this->model::findOrFail($id);
        $data = $original->toArray();

        // 移除主键和时间戳
        unset($data['id'], $data['created_at'], $data['updated_at']);
        // 软删除字段
        if (isset($data['deleted_at'])) {
            unset($data['deleted_at']);
        }

        $newModel = $this->model::create($data);
        $this->afterSave(request(), $newModel);

        return $this->success($newModel, '复制成功');
    }

    // ==================== 软删除恢复 ====================

    /**
     * 恢复软删除的记录
     */
    public function restore($id)
    {
        $ids = is_array($id) ? $id : explode(',', (string) $id);
        $ids = array_filter($ids, fn($v) => is_numeric($v) && $v > 0);

        if (empty($ids)) {
            return $this->fail('无效的 ID 参数', 422);
        }

        $count = $this->model::onlyTrashed()->whereIn('id', $ids)->restore();

        return $this->success([], "已恢复 {$count} 条记录");
    }

    // ==================== 强制删除 ====================

    /**
     * 强制删除记录（软删除模式下永久删除）
     */
    public function forceDestroy($id)
    {
        $ids = is_array($id) ? $id : explode(',', (string) $id);
        $ids = array_filter($ids, fn($v) => is_numeric($v) && $v > 0);

        if (empty($ids)) {
            return $this->fail('无效的 ID 参数', 422);
        }

        $count = count($ids);
        $this->model::onlyTrashed()->whereIn('id', $ids)->forceDelete();

        return $this->success([], "已永久删除 {$count} 条记录");
    }

    // ==================== 导入导出 ====================

    /**
     * 导出处理（子类可覆写）
     */
    public function export(Request $request)
    {
        return $this->fail('导出功能请在控制器中实现 export() 方法', 501);
    }

    /**
     * 导入处理（子类可覆写）
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        return $this->fail('导入功能请在控制器中实现 import() 方法', 501);
    }

    // ==================== 钩子 ====================

    /**
     * 保存后钩子（子类可覆写）
     */
    protected function afterSave(Request $request, $model): void
    {
        // 默认不做任何操作
    }

    protected function formatDbError(\Illuminate\Database\QueryException $e): string
    {
        if (app()->environment('production')) {
            if ($e->getCode() == 23000) {
                return '数据已存在（唯一约束冲突）';
            }
            return '数据库操作失败';
        }
        return $e->getMessage();
    }
}
