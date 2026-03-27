<?php

namespace Dabashan\DbsAdmin\Traits;

use Illuminate\Http\JsonResponse;

/**
 * API 响应 Trait
 *
 * 提供统一的 API 响应格式
 */
trait HasApiResponse
{
    /**
     * 成功响应
     *
     * @param mixed $data 响应数据
     * @param string $message 提示信息
     * @return JsonResponse
     */
    protected function success($data = [], string $message = 'success'): JsonResponse
    {
        return response()->json([
            'code' => 20000,
            'msg' => $message,
            'data' => $data,
        ]);
    }

    /**
     * 失败响应
     *
     * @param string $message 错误信息
     * @param int $code 错误码
     * @param int|null $httpStatus HTTP 状态码
     * @return JsonResponse
     */
    protected function fail(string $message = 'fail', int $code = 400, ?int $httpStatus = null): JsonResponse
    {
        // 如果未指定 HTTP 状态码，使用 code 作为 HTTP 状态码
        // 但限制在有效的 HTTP 状态码范围内（400-599）
        if ($httpStatus === null) {
            $httpStatus = ($code >= 400 && $code < 600) ? $code : 400;
        }

        return response()->json([
            'code' => $code,
            'msg' => $message,
            'data' => [],
        ], $httpStatus);
    }

    /**
     * 错误响应（服务器错误，返回实际 HTTP 状态码）
     *
     * @param string $message 错误信息
     * @param int $code 错误码
     * @return JsonResponse
     */
    protected function error(string $message = 'error', int $code = 500): JsonResponse
    {
        return response()->json([
            'code' => $code,
            'msg' => $message,
            'data' => [],
        ], $code);
    }

    /**
     * 未认证响应
     *
     * @param string $message 错误信息
     * @return JsonResponse
     */
    protected function unauthorized(string $message = '未认证'): JsonResponse
    {
        return $this->fail($message, 401);
    }

    /**
     * 权限不足响应
     *
     * @param string $message 错误信息
     * @return JsonResponse
     */
    protected function forbidden(string $message = '权限不足'): JsonResponse
    {
        return $this->fail($message, 403);
    }

    /**
     * 资源不存在响应
     *
     * @param string $message 错误信息
     * @return JsonResponse
     */
    protected function notFound(string $message = '资源不存在'): JsonResponse
    {
        return $this->fail($message, 404);
    }
}
