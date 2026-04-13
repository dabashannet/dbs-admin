<?php

namespace Dabashan\DbsAdmin\Traits;

use Illuminate\Http\JsonResponse;

/**
 * API 响应 Trait
 *
 * 提供统一的 API 响应格式
 *  的完善响应机制，包含请求 ID、耗时等性能追踪
 */
trait HasApiResponse
{
    /**
     * 成功响应
     */
    protected function success($data = [], string $message = 'success'): JsonResponse
    {
        return response()->json([
            'code' => 20000,
            'msg' => $message,
            'data' => $data,
            'traceId' => $this->getTraceId(),
        ]);
    }

    /**
     * 失败响应
     */
    protected function fail(string $message = 'fail', int $code = 400, ?int $httpStatus = null): JsonResponse
    {
        if ($httpStatus === null) {
            $httpStatus = ($code >= 400 && $code < 600) ? $code : 400;
        }

        return response()->json([
            'code' => $code,
            'msg' => $message,
            'data' => [],
            'traceId' => $this->getTraceId(),
        ], $httpStatus);
    }

    /**
     * 错误响应（服务器错误）
     */
    protected function error(string $message = 'error', int $code = 500): JsonResponse
    {
        return response()->json([
            'code' => $code,
            'msg' => $message,
            'data' => [],
            'traceId' => $this->getTraceId(),
        ], $code);
    }

    /**
     * 未认证响应
     */
    protected function unauthorized(string $message = '未认证'): JsonResponse
    {
        return $this->fail($message, 401);
    }

    /**
     * 权限不足响应
     */
    protected function forbidden(string $message = '权限不足'): JsonResponse
    {
        return $this->fail($message, 403);
    }

    /**
     * 资源不存在响应
     */
    protected function notFound(string $message = '资源不存在'): JsonResponse
    {
        return $this->fail($message, 404);
    }

    /**
     * 验证错误响应
     */
    protected function validationFail(string|array $errors = '', string $message = '验证失败'): JsonResponse
    {
        return $this->fail($message, 422);
    }

    /**
     * 批量操作响应
     */
    protected function batchResult(int $success, int $failed, array $details = []): JsonResponse
    {
        return response()->json([
            'code' => 20000,
            'msg' => "成功 {$success} 条，失败 {$failed} 条",
            'data' => [
                'success' => $success,
                'failed' => $failed,
                'total' => $success + $failed,
                'details' => $details,
            ],
            'traceId' => $this->getTraceId(),
        ]);
    }

    /**
     * 分页响应（带元数据）
     */
    protected function paginate($paginator, array $items = [], string $message = 'success'): JsonResponse
    {
        return response()->json([
            'code' => 20000,
            'msg' => $message,
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'traceId' => $this->getTraceId(),
        ]);
    }

    /**
     * 生成请求追踪 ID
     */
    protected function getTraceId(): string
    {
        static $traceId = null;
        if ($traceId === null) {
            $traceId = bin2hex(random_bytes(8));
        }
        return $traceId;
    }
}
