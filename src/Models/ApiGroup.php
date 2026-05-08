<?php
/*
 * @Author:  lvtu@dabashan.cc
 * @Date: 2026-05-08 21:42:20
 * @LastEditTime: 2026-05-08 21:42:20
 * Copyright (c) 2025 by Dabashan.cc, All Rights Reserved.
 */


namespace Dabashan\DbsAdmin\Models;

class ApiGroup extends BaseAdminModel
{
    protected $fillable = ['name', 'type', 'plugin', 'description', 'tag', 'tag_color', 'sort'];

    const TYPE_SYSTEM = 'system';
    const TYPE_PLUGIN_ADMIN = 'plugin_admin';
    const TYPE_PLUGIN_HTTP = 'plugin_http';

    public function endpoints()
    {
        return $this->hasMany(ApiEndpoint::class, 'group_id');
    }
}
