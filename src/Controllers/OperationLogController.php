<?php
/*
 * @Author:  lvtu@dabashan.cc
 * @Date: 2026-05-08 21:42:20
 * @LastEditTime: 2026-05-08 21:42:20
 * Copyright (c) 2025 by Dabashan.cc, All Rights Reserved.
 */


namespace Dabashan\DbsAdmin\Controllers;

use Dabashan\DbsAdmin\Models\OperationLog;
use Dabashan\DbsAdmin\Traits\HasApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class OperationLogController extends Controller
{
    use HasApiResponse;

    /**
     * 日志列表，支持多条件筛选
     */
    public function index(Request $request)
    {
        $query = OperationLog::with('user:id,username,name');

        if ($eventType = $request->input('event_type')) {
            $query->where('event_type', $eventType);
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($startDate = $request->input('start_date')) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate = $request->input('end_date')) {
            $query->where('created_at', '<=', $endDate . ' 23:59:59');
        }

        $data = $query->orderByDesc('id')->paginate($request->input('pageSize', 20));
        return $this->success($data);
    }

    /**
     * 删除单条日志
     */
    public function destroy(int $id)
    {
        OperationLog::destroy($id);
        return $this->success(null, '删除成功');
    }

    /**
     * 批量删除日志，支持按 ID 列表或按事件类型
     */
    public function batchDestroy(Request $request)
    {
        $query = OperationLog::query();

        if ($ids = $request->input('ids')) {
            $query->whereIn('id', $ids);
        } elseif ($eventType = $request->input('event_type')) {
            $query->where('event_type', $eventType);
        }

        $count = $query->count();
        $query->delete();

        return $this->success(['deleted' => $count], "已清空 {$count} 条日志");
    }
}
