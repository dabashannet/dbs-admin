<?php

namespace Dabashan\DbsAdmin\Tasks;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TaskManager
{
    protected static function statusKey(string $taskId): string
    {
        return "dbs_admin_task_status:{$taskId}";
    }

    protected static function logsKey(string $taskId): string
    {
        return "dbs_admin_task_logs:{$taskId}";
    }

    public static function create(array $meta = [], int $ttlSeconds = 3600): array
    {
        $taskId = 'T' . now()->format('YmdHis') . '_' . Str::random(6);
        $status = array_merge([
            'task_id' => $taskId,
            'status' => 'queued',
            'progress' => 0,
            'stage' => '',
            'message' => '',
            'done' => false,
            'success' => null,
            'error' => null,
            'result' => null,
            'started_at' => now()->toDateTimeString(),
            'finished_at' => null,
            'updated_at' => now()->toDateTimeString(),
            'log_cursor' => 0,
        ], $meta);

        Cache::put(self::statusKey($taskId), $status, $ttlSeconds);
        Cache::put(self::logsKey($taskId), [], $ttlSeconds);

        return $status;
    }

    public static function get(string $taskId): ?array
    {
        $data = Cache::get(self::statusKey($taskId));
        return is_array($data) ? $data : null;
    }

    public static function update(string $taskId, array $patch, int $ttlSeconds = 3600): ?array
    {
        $current = self::get($taskId);
        if (!$current) {
            return null;
        }
        $next = array_merge($current, $patch);
        $next['updated_at'] = now()->toDateTimeString();
        Cache::put(self::statusKey($taskId), $next, $ttlSeconds);
        return $next;
    }

    public static function start(string $taskId, int $ttlSeconds = 3600): ?array
    {
        return self::update($taskId, ['status' => 'running'], $ttlSeconds);
    }

    public static function finish(string $taskId, mixed $result = null, string $message = 'success', int $ttlSeconds = 3600): ?array
    {
        return self::update($taskId, [
            'status' => 'success',
            'progress' => 100,
            'done' => true,
            'success' => true,
            'message' => $message,
            'result' => $result,
            'finished_at' => now()->toDateTimeString(),
        ], $ttlSeconds);
    }

    public static function fail(string $taskId, string $error, int $ttlSeconds = 3600): ?array
    {
        return self::update($taskId, [
            'status' => 'failed',
            'done' => true,
            'success' => false,
            'error' => $error,
            'finished_at' => now()->toDateTimeString(),
        ], $ttlSeconds);
    }

    public static function cancel(string $taskId, int $ttlSeconds = 3600): ?array
    {
        return self::update($taskId, [
            'status' => 'canceled',
            'done' => true,
            'success' => false,
            'error' => '已取消',
            'finished_at' => now()->toDateTimeString(),
        ], $ttlSeconds);
    }

    public static function appendLog(string $taskId, string $level, string $message, int $ttlSeconds = 3600): ?array
    {
        $current = self::get($taskId);
        if (!$current) {
            return null;
        }
        $logs = Cache::get(self::logsKey($taskId));
        if (!is_array($logs)) {
            $logs = [];
        }
        $cursor = (int)($current['log_cursor'] ?? count($logs));
        $cursor += 1;
        $logs[] = [
            'ts' => now()->toDateTimeString(),
            'level' => $level,
            'message' => $message,
        ];
        Cache::put(self::logsKey($taskId), $logs, $ttlSeconds);
        return self::update($taskId, ['log_cursor' => $cursor], $ttlSeconds);
    }

    public static function logs(string $taskId, int $cursor = 0, int $limit = 200): array
    {
        $logs = Cache::get(self::logsKey($taskId));
        if (!is_array($logs)) {
            $logs = [];
        }
        $cursor = max(0, $cursor);
        $limit = max(1, min(1000, $limit));
        $slice = array_slice($logs, $cursor, $limit);
        $nextCursor = $cursor + count($slice);
        $status = self::get($taskId);
        $done = $status ? (bool)($status['done'] ?? false) : true;
        return [
            'task_id' => $taskId,
            'cursor' => $cursor,
            'next_cursor' => $nextCursor,
            'done' => $done,
            'lines' => $slice,
        ];
    }
}

