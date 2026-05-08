<?php
/*
 * @Author:  lvtu@dabashan.cc
 * @Date: 2026-05-08 21:42:20
 * @LastEditTime: 2026-05-08 21:42:20
 * Copyright (c) 2025 by Dabashan.cc, All Rights Reserved.
 */


namespace Dabashan\DbsAdmin\Events;

/**
 * 插件状态变更事件
 *
 * 当插件安装/卸载/启用/禁用/升级时触发，
 * 宿主应用可监听此事件执行前端编译等后续操作。
 */
class PluginChanged
{
    /**
     * @param string $name 插件名称
     * @param string $action 操作类型：installed, uninstalled, enabled, disabled, upgraded
     */
    public function __construct(
        public readonly string $name,
        public readonly string $action,
    ) {}
}
