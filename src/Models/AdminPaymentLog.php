<?php
/*
 * @Author:  lvtu@dabashan.cc
 * @Date: 2026-05-08 21:42:20
 * @LastEditTime: 2026-05-08 21:42:20
 * Copyright (c) 2025 by Dabashan.cc, All Rights Reserved.
 */


namespace Dabashan\DbsAdmin\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * 后台支付日志模型
 *
 * 记录支付请求和回调的完整数据，支持微信/支付宝等多渠道。
 *
 * @property int $id
 * @property string $channel 支付渠道（wechat/alipay）
 * @property string $gateway 支付网关（默认 yansongda）
 * @property string $type 支付方式（mp/app/h5/web）
 * @property string $order_no 商户订单号
 * @property string|null $transaction_id 第三方交易号
 * @property float $amount 支付金额
 * @property string $status 支付状态
 * @property array|null $payload 请求参数
 * @property array|null $response 响应数据
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @method static Builder byChannel(string $channel) 按支付渠道筛选
 * @method static Builder byStatus(string $status) 按支付状态筛选
 * @method static Builder byOrderNo(string $orderNo) 按订单号筛选
 */
class AdminPaymentLog extends BaseAdminModel
{
    protected $table = 'admin_payment_logs';

    protected $casts = [
        'payload' => 'json',
        'response' => 'json',
    ];

    /**
     * 按支付渠道筛选
     */
    public function scopeByChannel(Builder $query, string $channel): Builder
    {
        return $query->where('channel', $channel);
    }

    /**
     * 按支付状态筛选
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * 按订单号筛选
     */
    public function scopeByOrderNo(Builder $query, string $orderNo): Builder
    {
        return $query->where('order_no', $orderNo);
    }
}
