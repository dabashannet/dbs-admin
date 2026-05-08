<?php
/*
 * @Author:  lvtu@dabashan.cc
 * @Date: 2026-05-08 21:42:20
 * @LastEditTime: 2026-05-08 21:42:20
 * Copyright (c) 2025 by Dabashan.cc, All Rights Reserved.
 */


namespace Dabashan\DbsAdmin\Models;

class OperationLog extends BaseAdminModel
{
    const UPDATED_AT = null;  // 没有 updated_at

    protected $fillable = [
        'user_id', 'event_type', 'detail', 'status', 'extra',
    ];

    protected $casts = [
        'extra' => 'array',
    ];

    const STATUS_SUCCESS = 'success';
    const STATUS_FAIL = 'fail';

    const EVENT_LOGIN = 'login';
    const EVENT_LOGOUT = 'logout';
    const EVENT_PASSWORD = 'password';
    const EVENT_CREATE = 'create';
    const EVENT_UPDATE = 'update';
    const EVENT_DELETE = 'delete';
    const EVENT_READ = 'read';
    const EVENT_OPERATION = 'operation';
    const EVENT_PAYMENT = 'payment';

    public function user()
    {
        return $this->belongsTo(AdminUser::class, 'user_id');
    }
}
