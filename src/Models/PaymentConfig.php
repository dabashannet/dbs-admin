<?php
/*
 * @Author:  lvtu@dabashan.cc
 * @Date: 2026-05-08 21:42:20
 * @LastEditTime: 2026-05-08 21:42:20
 * Copyright (c) 2025 by Dabashan.cc, All Rights Reserved.
 */


namespace Dabashan\DbsAdmin\Models;

class PaymentConfig extends BaseAdminModel
{
    protected $fillable = [
        'channel', 'name', 'icon', 'mode', 'config', 'enabled', 'sort', 'remark',
    ];

    protected $casts = [
        'config' => 'array',
        'enabled' => 'boolean',
    ];

    // 渠道常量
    const CHANNEL_WECHAT = 'wechat';
    const CHANNEL_ALIPAY = 'alipay';
    const CHANNEL_DOUYIN = 'douyin';
    const CHANNEL_UNIPAY = 'unipay';
    const CHANNEL_PAYPAL = 'paypal';
    const CHANNEL_STRIPE = 'stripe';
    const CHANNEL_JSB = 'jsb';
    const CHANNEL_BALANCE = 'balance';

    const CHANNELS = [
        self::CHANNEL_WECHAT => '微信支付',
        self::CHANNEL_ALIPAY => '支付宝',
        self::CHANNEL_DOUYIN => '抖音支付',
        self::CHANNEL_UNIPAY => '银联支付',
        self::CHANNEL_PAYPAL => 'PayPal',
        self::CHANNEL_STRIPE => 'Stripe',
        self::CHANNEL_JSB => '江苏银行',
        self::CHANNEL_BALANCE => '余额支付',
    ];

    const MODE_NORMAL = 'normal';
    const MODE_SERVICE_PROVIDER = 'service_provider';

    /**
     * 需要模式选择的渠道
     */
    public static function channelsWithMode(): array
    {
        return [self::CHANNEL_WECHAT, self::CHANNEL_ALIPAY];
    }
}
