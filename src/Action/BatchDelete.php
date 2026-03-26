<?php

namespace Dabashan\DbsAdmin\Action;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * 批量删除操作
 */
class BatchDelete extends Action
{
    public function __construct()
    {
        parent::__construct('batch_delete', '批量删除');
        $this->confirm('确定要删除选中的记录吗？此操作不可恢复。');
    }

    /**
     * 执行批量删除
     */
    public function handle(Builder $query, Request $request): array
    {
        $ids = $request->get('ids', []);

        if (empty($ids)) {
            return [
                'success' => false,
                'message' => '请选择要删除的记录',
            ];
        }

        $count = $query->whereIn('id', $ids)->delete();

        return [
            'success' => true,
            'message' => "成功删除 {$count} 条记录",
            'count' => $count,
        ];
    }
}
