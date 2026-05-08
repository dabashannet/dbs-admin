<?php
/*
 * @Author:  lvtu@dabashan.cc
 * @Date: 2026-05-08 21:42:20
 * @LastEditTime: 2026-05-08 21:42:20
 * Copyright (c) 2025 by Dabashan.cc, All Rights Reserved.
 */


namespace Dabashan\DbsAdmin\Models;

class ApiEndpoint extends BaseAdminModel
{
    protected $fillable = [
        'group_id', 'method', 'path', 'name', 'description',
        'parameters', 'response_example', 'headers', 'sort',
    ];

    protected $casts = [
        'parameters' => 'array',
        'response_example' => 'array',
        'headers' => 'array',
    ];

    public function group()
    {
        return $this->belongsTo(ApiGroup::class, 'group_id');
    }
}
