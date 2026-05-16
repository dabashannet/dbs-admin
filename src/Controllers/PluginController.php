<?php
/*
 * @Author:  lvtu@dabashan.cc
 * @Date: 2026-05-08 21:42:20
 * @LastEditTime: 2026-05-08 21:42:20
 * Copyright (c) 2025 by Dabashan.cc, All Rights Reserved.
 */


namespace Dabashan\DbsAdmin\Controllers;

use Dabashan\DbsAdmin\Models\AdminPermission;
use Dabashan\DbsAdmin\Services\PluginManager;
use Dabashan\DbsAdmin\Services\PluginService;
use Dabashan\DbsAdmin\Traits\HasApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\File;

class PluginController extends Controller
{
    use HasApiResponse;

    protected PluginService $pluginService;

    public function __construct(PluginService $pluginService)
    {
        $this->pluginService = $pluginService;
    }

    /**
     * 获取插件列表，含权限注入到菜单
     */
    public function index()
    {
        $plugins = $this->pluginService->getAllPlugins();

        foreach ($plugins as &$plugin) {
            $plugin['icon_url'] = url("/admin/plugins/{$plugin['name']}/icon");

            $permissions = $plugin['permissions'] ?? [];
            $permByName = [];
            foreach ($permissions as $perm) {
                if (isset($perm['name'], $perm['slug'])) {
                    $permByName[$perm['name']] = $perm['slug'];
                }
            }

            if (!empty($plugin['menus']) && !empty($permByName)) {
                $injectPermission = function (array &$menus) use ($permByName, &$injectPermission) {
                    foreach ($menus as &$menu) {
                        if (isset($menu['title'], $permByName[$menu['title']])) {
                            $menu['permission'] = $permByName[$menu['title']];
                        }
                        if (!empty($menu['children'])) {
                            $injectPermission($menu['children']);
                        }
                    }
                };
                $injectPermission($plugin['menus']);
            }
        }

        return $this->success($plugins);
    }

    /**
     * 获取插件图标
     */
    public function icon(string $name)
    {
        $pluginPath = PluginManager::getPluginPath($name);

        $iconPaths = [
            $pluginPath . '/resources/static/images/icon.png',
            $pluginPath . '/static/images/icon.png',
            $pluginPath . '/resources/images/icon.png',
            $pluginPath . '/images/icon.png',
        ];

        foreach ($iconPaths as $iconPath) {
            if (File::exists($iconPath)) {
                return response()->file($iconPath, [
                    'Cache-Control' => 'public, max-age=86400',
                ]);
            }
        }

        abort(404);
    }

    /**
     * 安装插件，完成后自动同步权限
     */
    public function install(string $name)
    {
        $result = $this->pluginService->install($name);

        if ($result['success']) {
            try {
                $this->syncPermissions($name);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("插件安装后同步权限失败: {$e->getMessage()}");
            }

            return $this->success($result['plugin'] ?? null, $result['message']);
        }

        return $this->error($result['message']);
    }

    /**
     * 切换插件启用/禁用状态
     */
    public function toggleEnabled(Request $request, string $name)
    {
        $enabled = $request->boolean('enabled', true);
        $result = $this->pluginService->toggleEnabled($name, $enabled);

        if ($result['success']) {
            return $this->success($result['plugin'] ?? null, $result['message']);
        }

        return $this->error($result['message']);
    }

    /**
     * 卸载插件
     */
    public function uninstall(Request $request, string $name)
    {
        $clearData = $request->boolean('clear_data', false);
        $result = $this->pluginService->uninstall($name, $clearData);

        if ($result['success']) {
            return $this->success(null, $result['message']);
        }

        return $this->error($result['message']);
    }

    /**
     * 更新插件配置
     */
    public function update(Request $request, string $name)
    {
        $result = $this->pluginService->update($name, $request->all());

        if ($result['success']) {
            return $this->success($result['plugin'] ?? null, $result['message']);
        }

        return $this->error($result['message']);
    }

    /**
     * 升级插件
     */
    public function upgrade(string $name)
    {
        $result = $this->pluginService->upgrade($name);

        if ($result['success']) {
            return $this->success($result['plugin'] ?? null, $result['message']);
        }

        return $this->error($result['message']);
    }

    /**
     * 同步插件权限到权限表
     *
     * 按 slug 唯一标识同步，使用 updateOrCreate 确保幂等性。
     */
    public function syncPermissions(string $name)
    {
        $pluginPath = PluginManager::getPluginPath($name);
        $jsonPath = $pluginPath . '/manifest.json';

        $config = null;
        if (File::exists($jsonPath)) {
            $config = json_decode(File::get($jsonPath), true);
        }

        if (!$config || empty($config['permissions'])) {
            $plugin = PluginManager::findFromDb($name);
            if ($plugin && !empty($plugin->permissions)) {
                $config = ['permissions' => $plugin->permissions];
            }
        }

        if (!$config || empty($config['permissions'])) {
            return $this->success([], '无需同步，插件无权限定义');
        }

        $synced = [];
        foreach ($config['permissions'] as $perm) {
            $slug = $perm['slug'] ?? '';
            if (!$slug) {
                continue;
            }

            $httpMethod = $perm['http_method'] ?? [];
            $httpPath = $perm['http_path'] ?? '';

            $record = AdminPermission::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $perm['name'] ?? $slug,
                    'http_method' => is_array($httpMethod) ? $httpMethod : [],
                    'http_path' => $httpPath,
                ]
            );
            $synced[] = $record;
        }

        return $this->success([
            'synced' => count($synced),
            'permissions' => $synced,
        ], "同步完成，共 " . count($synced) . " 个权限");
    }
}
