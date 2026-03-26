<?php

namespace Dabashan\DbsAdmin\Action;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * 批量更新操作
 */
class BatchUpdate extends Action
{
    public function __construct()
    {
        parent::__construct('batch_update', '批量更新');
        $this->confirm('确定要更新选中的记录吗？');
    }

    /**
     * 执行批量更新
     *
     * @param Builder $query 查询构建器
     * @param Request $request 请求对象（需包含 ids 和 params）
     * @return array 执行结果
     */
    public function handle(Builder $query, Request $request): array
    {
        $ids = $request->get('ids', []);
        $params = $request->get('params', []);

        if (empty($ids)) {
            return [
                'success' => false,
                'message' => '请选择要更新的记录',
                'count' => 0,
            ];
        }

        if (empty($params)) {
            return [
                'success' => false,
                'message' => '没有提供更新数据',
                'count' => 0,
            ];
        }

        $count = $query->whereIn('id', $ids)->update($params);

        return [
            'success' => true,
            'message' => "成功更新 {$count} 条记录",
            'count' => $count,
        ];
    }
}
