<?php
/*
 * @Author:  lvtu@dabashan.cc
 * @Date: 2026-05-08 21:42:20
 * @LastEditTime: 2026-05-08 21:42:20
 * Copyright (c) 2025 by Dabashan.cc, All Rights Reserved.
 */


namespace Dabashan\DbsAdmin\Controllers;

use Dabashan\DbsAdmin\Traits\HasApiResponse;
use Illuminate\Routing\Controller;

/**
 * 公共 HTTP 端基础控制器
 *
 * 提供统一的响应格式和安全响应头，面向业务端（非后台管理）。
 * 所有业务控制器（含插件）应继承此类。
 *
 * 后台管理控制器请继承 AdminController。
 */
abstract class HttpController extends Controller
{
    use HasApiResponse;

    /**
     * 安全响应头
     */
    protected array $securityHeaders = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'X-XSS-Protection' => '1; mode=block',
    ];

    protected function success($data = [], string $message = 'success'): \Illuminate\Http\JsonResponse
    {
        return parent::success($data, $message)->withHeaders($this->securityHeaders);
    }

    protected function fail(string $message = 'fail', int $code = 400, ?int $httpStatus = null): \Illuminate\Http\JsonResponse
    {
        return parent::fail($message, $code, $httpStatus)->withHeaders($this->securityHeaders);
    }

    protected function error(string $message = 'error', int $code = 500): \Illuminate\Http\JsonResponse
    {
        return parent::error($message, $code)->withHeaders($this->securityHeaders);
    }

    protected function paginate($paginator, array $items = [], string $message = 'success'): \Illuminate\Http\JsonResponse
    {
        return parent::paginate($paginator, $items, $message)->withHeaders($this->securityHeaders);
    }
}
