<?php
/*
 * @Author:  lvtu@dabashan.cc
 * @Date: 2026-05-08 21:42:20
 * @LastEditTime: 2026-05-08 21:42:20
 * Copyright (c) 2025 by Dabashan.cc, All Rights Reserved.
 */


namespace Dabashan\DbsAdmin\Controllers;

use Dabashan\DbsAdmin\Tasks\TaskManager;
use Dabashan\DbsAdmin\Traits\HasApiResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskController
{
    use HasApiResponse;

    public function status(string $id)
    {
        $task = TaskManager::get($id);
        if (!$task) {
            return $this->fail('任务不存在', 404);
        }
        return $this->success($task);
    }

    public function logs(Request $request, string $id)
    {
        $task = TaskManager::get($id);
        if (!$task) {
            return $this->fail('任务不存在', 404);
        }
        $cursor = (int)$request->query('cursor', 0);
        $limit = (int)$request->query('limit', 200);
        return $this->success(TaskManager::logs($id, $cursor, $limit));
    }

    public function cancel(string $id)
    {
        $task = TaskManager::cancel($id);
        if (!$task) {
            return $this->fail('任务不存在', 404);
        }
        return $this->success($task, '已请求取消');
    }

    public function stream(Request $request, string $id)
    {
        $task = TaskManager::get($id);
        if (!$task) {
            return $this->fail('任务不存在', 404);
        }

        $cursor = (int)$request->query('cursor', 0);
        $interval = (int)$request->query('interval', 1000);
        $interval = max(300, min(5000, $interval));

        $response = new StreamedResponse(function () use ($id, $cursor, $interval) {
            @set_time_limit(0);
            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', '0');

            $send = function (string $event, array $payload) {
                echo "event: {$event}\n";
                echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\n";
                if (function_exists('ob_flush')) {
                    @ob_flush();
                }
                if (function_exists('flush')) {
                    @flush();
                }
            };

            $localCursor = max(0, $cursor);
            while (!connection_aborted()) {
                $status = TaskManager::get($id);
                if (!$status) {
                    $send('error', ['message' => '任务不存在']);
                    break;
                }

                $send('status', $status);

                $logs = TaskManager::logs($id, $localCursor, 200);
                $lines = $logs['lines'] ?? [];
                if (is_array($lines) && !empty($lines)) {
                    foreach ($lines as $line) {
                        if (!is_array($line)) {
                            continue;
                        }
                        $send('log', $line);
                    }
                    $localCursor = (int)($logs['next_cursor'] ?? $localCursor);
                }

                if (!empty($status['done'])) {
                    $send('done', ['task_id' => $id]);
                    break;
                }

                echo ": ping\n\n";
                if (function_exists('ob_flush')) {
                    @ob_flush();
                }
                if (function_exists('flush')) {
                    @flush();
                }
                usleep($interval * 1000);
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }
}
