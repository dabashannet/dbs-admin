<?php

namespace Dabashan\DbsAdmin\Controllers;

use Dabashan\DbsAdmin\Form\Form;
use Dabashan\DbsAdmin\Grid\Grid;
use Dabashan\DbsAdmin\Traits\HasApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * 代码生成器控制器
 *
 * 参考 DCAT-Admin Scaffold 设计理念，提供可视化的代码生成界面
 * 支持核心模块/插件模式切换、字段构建器、代码预览、在线编译预览
 */
class CodeGeneratorController extends AdminController
{
    use HasApiResponse;

    // 不依赖 Model
    protected string $model = '';

    protected function grid(): Grid
    {
        return Grid::make(\Illuminate\Support\Collection::class)
            ->column('name', '资源名称')->searchable()
            ->column('type', '类型')->badge([
                'core' => 'primary',
                'plugin' => 'warning',
            ])
            ->column('table', '数据表')
            ->column('created_at', '创建时间')->sortable()
            ->defaultSort('created_at', 'desc');
    }

    protected function form(): Form
    {
        return Form::make()
            ->text('name', '资源名称')
                ->required()
                ->help('如 User、Order、ProductCategory')
            ->select('type', '生成类型')
                ->options([
                    'core' => '核心模块（app/Admin + resource/views）',
                    'plugin' => '插件模块（plugins/{Name} + resource/views）',
                ])
                ->default('core')
                ->required()
            ->text('plugin', '插件名称')
                ->help('生成类型为插件时填写，如 shop')
                ->displayWhen('type', '===', 'plugin');
    }

    /**
     * 代码生成器主页面（返回前端配置）
     */
    public function index(Request $request)
    {
        return $this->success([
            'columns' => [],
            'items' => [],
            'config' => $this->getGeneratorConfig(),
        ]);
    }

    /**
     * 获取可用插件列表（用于代码生成器下拉选择）
     */
    public function plugins(): \Illuminate\Http\JsonResponse
    {
        $pluginPath = base_path('plugins');
        $plugins = [];

        // 1. 从数据库读取已安装的插件
        if (class_exists('\App\Admin\Models\Plugin')) {
            try {
                $dbPlugins = \App\Admin\Models\Plugin::all();
                foreach ($dbPlugins as $record) {
                    $plugins[$record->name] = [
                        'name' => $record->name,
                        'title' => $record->title ?? $record->name,
                        'description' => $record->description ?? '',
                        'version' => $record->version ?? '1.0.0',
                        'enabled' => (bool) $record->enabled,
                        'installed' => true,
                    ];
                }
            } catch (\Exception) {
                // 数据库不存在或表不存在，忽略
            }
        }

        // 2. 扫描 plugins 目录，补充未安装的插件
        if (is_dir($pluginPath)) {
            foreach (scandir($pluginPath) as $dir) {
                if ($dir === '.' || $dir === '..') {
                    continue;
                }
                $jsonPath = $pluginPath . '/' . $dir . '/plugin.json';
                if (file_exists($jsonPath)) {
                    $config = json_decode(file_get_contents($jsonPath), true);
                    if ($config && !empty($config['name'])) {
                        $name = $config['name'];
                        // 如果数据库中已有，不重复添加
                        if (!isset($plugins[$name])) {
                            $plugins[$name] = [
                                'name' => $name,
                                'title' => $config['title'] ?? $name,
                                'description' => $config['description'] ?? '',
                                'version' => $config['version'] ?? '1.0.0',
                                'enabled' => $config['enabled'] ?? true,
                                'installed' => false,
                            ];
                        }
                    }
                }
            }
        }

        return $this->success(array_values($plugins));
    }

    /**
     * 获取已有插件的 kebab-case 名称列表
     */
    protected function getExistingPluginNames(): array
    {
        $names = [];

        // 1. 从数据库获取已安装的插件
        if (class_exists('\App\Admin\Models\Plugin')) {
            try {
                $dbPlugins = \App\Admin\Models\Plugin::all();
                foreach ($dbPlugins as $record) {
                    $names[] = Str::kebab($record->name);
                }
            } catch (\Exception $e) {
                // 忽略
            }
        }

        // 2. 扫描 plugins 目录，补充未安装的插件
        $pluginPath = base_path('plugins');
        if (is_dir($pluginPath)) {
            foreach (scandir($pluginPath) as $dir) {
                if ($dir === '.' || $dir === '..') {
                    continue;
                }
                $jsonPath = $pluginPath . '/' . $dir . '/plugin.json';
                if (file_exists($jsonPath)) {
                    $config = json_decode(file_get_contents($jsonPath), true);
                    if ($config && !empty($config['name'])) {
                        $kebabName = Str::kebab($config['name']);
                        if (!in_array($kebabName, $names, true)) {
                            $names[] = $kebabName;
                        }
                    }
                }
            }
        }

        return $names;
    }

    protected function pluginBaseInfo(string $plugin): array
    {
        $pluginSnake = Str::snake($plugin);
        $pluginStudly = Str::studly($pluginSnake);

        return [
            'snake' => $pluginSnake,
            'studly' => $pluginStudly,
            'dir' => base_path("plugins/{$pluginStudly}"),
            'json_path' => base_path("plugins/{$pluginStudly}/plugin.json"),
        ];
    }

    /**
     * 获取生成器配置（字段类型、可用选项等）
     */
    public function generatorConfig(): \Illuminate\Http\JsonResponse
    {
        return $this->success($this->getGeneratorConfig());
    }

    /**
     * 预览生成的代码
     */
    public function preview(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'type' => 'required|in:core,plugin',
            'plugin' => 'nullable|string',
            'plugin_title' => 'nullable|string',
            'parent' => 'nullable|string',
            'table' => 'nullable|string',
            'fillable' => 'nullable|array',
            'fields' => 'nullable|array',
            'grid_columns' => 'nullable|array',
            'filters' => 'nullable|array',
            'filter_layout' => 'nullable|array',
            'indexes' => 'nullable|array',
            'icon' => 'nullable|string',
            'order' => 'nullable|integer',
        ]);

        $preview = $this->generatePreview($validated);

        return $this->success($preview);
    }

    public function previewAll(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:plugin',
            'plugin' => 'required|string',
            'plugin_title' => 'nullable|string',
            'parent' => 'nullable|string',
            'icon' => 'nullable|string',
            'order' => 'nullable|integer',
            'tables' => 'required|array|min:1',
            'tables.*.name' => 'required|string',
            'tables.*.table' => 'nullable|string',
            'tables.*.fillable' => 'nullable|array',
            'tables.*.fields' => 'nullable|array',
            'tables.*.grid_columns' => 'nullable|array',
            'tables.*.filters' => 'nullable|array',
            'tables.*.filter_layout' => 'nullable|array',
            'tables.*.indexes' => 'nullable|array',
            'tables.*.icon' => 'nullable|string',
            'tables.*.order' => 'nullable|integer',
        ]);

        $virtualFiles = [];
        $allFiles = [];
        $allPreview = [];

        $base = [
            'type' => 'plugin',
            'plugin' => $validated['plugin'],
            'plugin_title' => $validated['plugin_title'] ?? null,
            'parent' => $validated['parent'] ?? null,
            'icon' => $validated['icon'] ?? null,
            'order' => $validated['order'] ?? null,
        ];

        foreach ($validated['tables'] as $tableCfg) {
            $config = array_merge($base, [
                'name' => $tableCfg['name'],
                'table' => $tableCfg['table'] ?? null,
                'fillable' => $tableCfg['fillable'] ?? null,
                'fields' => $tableCfg['fields'] ?? null,
                'grid_columns' => $tableCfg['grid_columns'] ?? null,
                'filters' => $tableCfg['filters'] ?? null,
                'filter_layout' => $tableCfg['filter_layout'] ?? null,
                'indexes' => $tableCfg['indexes'] ?? null,
                'icon' => $tableCfg['icon'] ?? ($base['icon'] ?? null),
                'order' => $tableCfg['order'] ?? ($base['order'] ?? null),
                '_virtual_files' => $virtualFiles,
            ]);

            $preview = $this->generatePreview($config);

            foreach (($preview['files'] ?? []) as $path) {
                $allFiles[$path] = true;
            }

            foreach (($preview['preview'] ?? []) as $fileInfo) {
                $allPreview[$fileInfo['path']] = [
                    'path' => $fileInfo['path'],
                    'content' => $fileInfo['content'],
                ];
                $virtualFiles[$fileInfo['path']] = $fileInfo['content'];
            }
        }

        return $this->success([
            'files' => array_keys($allFiles),
            'preview' => $allPreview,
        ]);
    }

    /**
     * 生成并保存代码
     */
    public function generate(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'type' => 'required|in:core,plugin',
            'plugin' => 'nullable|string',
            'plugin_title' => 'nullable|string',
            'parent' => 'nullable|string',
            'table' => 'nullable|string',
            'fillable' => 'nullable|array',
            'fields' => 'nullable|array',
            'grid_columns' => 'nullable|array',
            'filters' => 'nullable|array',
            'filter_layout' => 'nullable|array',
            'indexes' => 'nullable|array',
            'icon' => 'nullable|string',
            'order' => 'nullable|integer',
            'force' => 'nullable|boolean',
        ]);

        try {
            $result = $this->doGenerate($validated);
            return $this->success($result, '代码生成成功');
        } catch (\Exception $e) {
            return $this->fail('代码生成失败: ' . $e->getMessage());
        }
    }

    /**
     * 删除生成的代码（插件模式）
     */
    public function delete(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'plugin' => 'required|string',
            'name' => 'required|string',
        ]);

        try {
            $result = $this->doDelete($validated);
            return $this->success($result, '代码删除成功');
        } catch (\Exception $e) {
            return $this->fail('代码删除失败: ' . $e->getMessage());
        }
    }

    /**
     * 根据历史记录参数返回生成的文件列表
     */
    public function files(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'type' => 'required|in:core,plugin',
            'plugin' => 'nullable|string',
            'parent' => 'nullable|string',
            'tables' => 'nullable|array',
        ]);

        $name = Str::studly($validated['name']);
        $kebabName = Str::kebab($validated['name']);
        $type = $validated['type'];
        $plugin = $validated['plugin'] ?? null;
        $pluginKebab = $plugin ? Str::snake($plugin) : null;
        $parent = $validated['parent'] ?? 'system';
        $tables = $validated['tables'] ?? [];
        $table = $tables[0] ?? ($type === 'plugin'
            ? "p_{$pluginKebab}_" . Str::snake(Str::plural($name))
            : "admin_" . Str::snake(Str::plural($name)));

        $files = $this->getGeneratedFiles($name, $kebabName, $parent, $type, $plugin, $table, $pluginKebab);

        return $this->success(['files' => $files]);
    }

    // ==================== 内部方法 ====================

    protected function getGeneratorConfig(): array
    {
        return [
            'field_types' => [
                'text' => ['label' => '文本输入', 'icon' => 'icon-input'],
                'password' => ['label' => '密码输入', 'icon' => 'icon-lock'],
                'textarea' => ['label' => '多行文本', 'icon' => 'icon-edit'],
                'number' => ['label' => '数字输入', 'icon' => 'icon-number'],
                'email' => ['label' => '邮箱输入', 'icon' => 'icon-email'],
                'url' => ['label' => 'URL 输入', 'icon' => 'icon-link'],
                'select' => ['label' => '下拉选择', 'icon' => 'icon-select'],
                'radio' => ['label' => '单选按钮', 'icon' => 'icon-radio'],
                'checkbox' => ['label' => '复选框', 'icon' => 'icon-checkbox'],
                'switch' => ['label' => '开关', 'icon' => 'icon-switch'],
                'date' => ['label' => '日期选择', 'icon' => 'icon-calendar'],
                'dateTime' => ['label' => '日期时间', 'icon' => 'icon-clock-circle'],
                'time' => ['label' => '时间选择', 'icon' => 'icon-clock-circle'],
                'dateRange' => ['label' => '日期范围', 'icon' => 'icon-calendar'],
                'image' => ['label' => '单图上传', 'icon' => 'icon-image'],
                'images' => ['label' => '多图上传', 'icon' => 'icon-image'],
                'file' => ['label' => '文件上传', 'icon' => 'icon-file'],
                'files' => ['label' => '多文件上传', 'icon' => 'icon-file'],
                'editor' => ['label' => '富文本编辑器', 'icon' => 'icon-edit'],
                'markdown' => ['label' => 'Markdown 编辑器', 'icon' => 'icon-markdown'],
                'code' => ['label' => '代码编辑器', 'icon' => 'icon-code'],
                'color' => ['label' => '颜色选择', 'icon' => 'icon-bg-colors'],
                'slider' => ['label' => '滑块', 'icon' => 'icon-slider'],
                'rate' => ['label' => '评分', 'icon' => 'icon-star'],
                'tags' => ['label' => '标签输入', 'icon' => 'icon-tag'],
                'icon' => ['label' => '图标选择', 'icon' => 'icon-face-smile-fill'],
                'treeSelect' => ['label' => '树形选择', 'icon' => 'icon-tree'],
                'cascader' => ['label' => '级联选择', 'icon' => 'icon-skip-right'],
                'autoComplete' => ['label' => '自动完成', 'icon' => 'icon-search'],
                'keyValue' => ['label' => '键值对输入', 'icon' => 'icon-list'],
                'repeater' => ['label' => '可重复项', 'icon' => 'icon-copy'],
                'toggleButtons' => ['label' => '切换按钮组', 'icon' => 'icon-swap'],
                'hidden' => ['label' => '隐藏字段', 'icon' => 'icon-eye-invisible'],
            ],
            'column_display_types' => [
                'badge' => '徽章',
                'dot' => '圆点',
                'image' => '图片',
                'tags' => '标签组',
                'progress' => '进度条',
                'bar' => '数值条',
                'color' => '色块',
                'copyable' => '可复制',
                'date' => '日期',
                'datetime' => '日期时间',
                'money' => '金额',
                'count' => '计数',
                'toggle' => '开关',
                'custom' => '自定义',
            ],
            'filter_types' => [
                'like' => '模糊搜索',
                'select' => '下拉选择',
                'equal' => '精确匹配',
                'between' => '区间',
                'between_date' => '日期区间',
                'gt' => '大于',
                'lt' => '小于',
                'in' => '多选',
            ],
            'parents' => [
                'system' => '系统管理',
                'user' => '用户管理',
                'order' => '订单管理',
                'payment' => '支付管理',
                'setting' => '系统设置',
                'attachment' => '附件管理',
            ],
            'icons' => [
                'icon-user', 'icon-settings', 'icon-shopping', 'icon-pay',
                'icon-file', 'icon-image', 'icon-storage', 'icon-apps',
                'icon-list', 'icon-dashboard', 'icon-notification', 'icon-lock',
            ],
            'db_types' => [
                'string' => ['label' => '字符串', 'migration' => "string('{name}')"],
                'text' => ['label' => '长文本', 'migration' => "text('{name}')"],
                'integer' => ['label' => '整数', 'migration' => "integer('{name}')"],
                'bigInteger' => ['label' => '大整数', 'migration' => "bigInteger('{name}')"],
                'decimal' => ['label' => '小数', 'migration' => "decimal('{name}', 10, 2)"],
                'float' => ['label' => '浮点数', 'migration' => "float('{name}')"],
                'boolean' => ['label' => '布尔值', 'migration' => "boolean('{name}')"],
                'date' => ['label' => '日期', 'migration' => "date('{name}')"],
                'dateTime' => ['label' => '日期时间', 'migration' => "dateTime('{name}')"],
                'timestamp' => ['label' => '时间戳', 'migration' => "timestamp('{name}')"],
                'json' => ['label' => 'JSON', 'migration' => "json('{name}')"],
            ],
        ];
    }

    protected function generatePreview(array $config): array
    {
        $name = Str::studly($config['name']);
        $kebabName = Str::kebab($config['name']);
        $pluralSnake = Str::snake(Str::plural($name));
        $parent = $config['parent'] ?? 'system';
        $plugin = $config['plugin'] ?? null;
        $pluginKebab = $plugin ? Str::snake($plugin) : null;
        $tableName = $config['table'] ?? ($config['type'] === 'plugin'
            ? "p_{$pluginKebab}_{$pluralSnake}"
            : "admin_{$pluralSnake}");
        $modelName = $config['type'] === 'plugin' ? $name : "Admin{$name}";
        $controllerName = "{$name}Controller";
        $icon = $config['icon'] ?? 'icon-file';
        $order = $config['order'] ?? 90;
        $fields = $config['fields'] ?? [];
        $gridColumns = $config['grid_columns'] ?? [];
        $indexes = $config['indexes'] ?? [];

        $virtualFiles = $config['_virtual_files'] ?? [];

        // 判断是已有插件还是新插件
        $isNewPlugin = false;
        if ($config['type'] === 'plugin' && $plugin) {
            $info = $this->pluginBaseInfo($plugin);
            $virtualPluginJsonKey = "plugins/{$info['studly']}/plugin.json";
            $isNewPlugin = !file_exists($info['json_path']) && !array_key_exists($virtualPluginJsonKey, $virtualFiles);
        }

        // 生成 Controller 代码
        $controllerCode = $this->generateControllerCode(
            $name,
            $controllerName,
            $modelName,
            $config['type'],
            $fields,
            $gridColumns,
            $config['filters'] ?? [],
            $config['filter_layout'] ?? [],
            $plugin
        );

        // 生成 Model 代码
        $modelCode = $this->generateModelCode($modelName, $tableName, $config['fillable'] ?? [], $config['type'], $plugin);

        // 生成迁移代码
        $migrationCode = $this->generateMigrationCode($tableName, $fields);

        // 生成索引迁移代码
        $indexMigrationCode = $this->generateIndexMigrationCode($tableName, $indexes);
        if ($indexMigrationCode) {
            // 将索引代码追加到迁移文件的 Schema::create 块内
            // 使用更精确的替换：在 $table->softDeletes(); 之后追加
            $search = '$table->softDeletes();';
            $replace = '$table->softDeletes();' . "\n\n            // 索引\n            {$indexMigrationCode};";
            $migrationCode = str_replace($search, $replace, $migrationCode);
        }

        // 生成 Vue 页面代码
        $vueCode = $this->generateVueCode($parent, $kebabName, $name, $config['type'], $pluginKebab);

        // 生成路由代码
        $routerCode = $this->generateRouterCode($parent, $kebabName, $name, $icon, $order, $config['type'], $pluginKebab);

        // 生成文件列表
        $files = $this->getGeneratedFiles($name, $kebabName, $parent, $config['type'], $plugin, $tableName, $pluginKebab);

        // 插件模式额外处理
        $pluginFiles = [];
        if ($config['type'] === 'plugin' && $plugin) {
            // 新插件才生成 plugin.json、ServiceProvider 等脚手架文件
            if ($isNewPlugin) {
                $pluginTitle = $config['plugin_title'] ?? '';
                $pluginStudly = Str::studly(Str::snake($plugin));
                $pluginJson = $this->generatePluginJson($plugin, $pluginTitle, $name, $kebabName, $icon);
                $serviceProvider = $this->generatePluginServiceProvider($plugin, $name);
                $pluginIndexVue = $this->generatePluginIndexVue($pluginTitle ?: $pluginStudly);

                $pluginFiles['plugin_json'] = [
                    'path' => "plugins/{$pluginStudly}/plugin.json",
                    'content' => $pluginJson,
                ];
                $pluginFiles['service_provider'] = [
                    'path' => "plugins/{$pluginStudly}/Providers/PluginServiceProvider.php",
                    'content' => $serviceProvider,
                ];
                $pluginFiles['plugin_index_vue'] = [
                    'path' => "plugins/{$pluginStudly}/resources/views/index.vue",
                    'content' => $pluginIndexVue,
                ];

                $files[] = "plugins/{$pluginStudly}/plugin.json";
                $files[] = "plugins/{$pluginStudly}/Providers/PluginServiceProvider.php";
                $files[] = "plugins/{$pluginStudly}/resources/views/index.vue";
            }

            // 无论新旧都生成 Admin 路由文件和业务端页面
            $pluginTitle = $config['plugin_title'] ?? null;
            $pluginStudly = Str::studly(Str::snake($plugin));
            $adminRoutesKey = "plugins/{$pluginStudly}/Admin/routes.php";
            $httpRoutesKey = "plugins/{$pluginStudly}/Http/routes.php";
            $pluginJsonKey = "plugins/{$pluginStudly}/plugin.json";
            $pluginRoutesKey = "plugins/{$pluginStudly}/resources/routes/index.ts";

            $adminRoutes = $this->mergePluginAdminRoutesForResource(
                $plugin,
                $name,
                $kebabName,
                $virtualFiles[$adminRoutesKey] ?? null
            );

            // 生成 Http 目录下的业务路由和控制器
            $httpRoutes = $this->mergePluginHttpRoutesForResource(
                $plugin,
                $name,
                $kebabName,
                $virtualFiles[$httpRoutesKey] ?? null
            );
            $httpController = $this->generatePluginHttpController($plugin, $name);
            $pluginJsonUpdate = $this->mergePluginJsonForResource(
                $plugin,
                $pluginTitle,
                $name,
                $kebabName,
                $icon,
                $virtualFiles[$pluginJsonKey] ?? null
            );

            $pluginFiles['admin_routes'] = [
                'path' => "plugins/{$pluginStudly}/Admin/routes.php",
                'content' => $adminRoutes,
            ];
            $pluginFiles['http_routes'] = [
                'path' => "plugins/{$pluginStudly}/Http/routes.php",
                'content' => $httpRoutes,
            ];
            $pluginFiles['http_controller'] = [
                'path' => "plugins/{$pluginStudly}/Http/Controllers/{$name}Controller.php",
                'content' => $httpController,
            ];
            $pluginFiles['plugin_routes'] = [
                'path' => $pluginRoutesKey,
                'content' => $this->generatePluginRoutesIndex($pluginStudly, $pluginKebab, $kebabName, $virtualFiles),
            ];
            if (!$isNewPlugin) {
                $pluginFiles['plugin_json_update'] = [
                    'path' => "plugins/{$pluginStudly}/plugin.json",
                    'content' => $pluginJsonUpdate,
                ];
                $files[] = "plugins/{$pluginStudly}/plugin.json";
            }

            $files[] = "plugins/{$pluginStudly}/Admin/routes.php";
            $files[] = "plugins/{$pluginStudly}/resources/views/{$kebabName}/index.vue";
            $files[] = "plugins/{$pluginStudly}/resources/routes/index.ts";
            $files[] = "plugins/{$pluginStudly}/Http/routes.php";
            $files[] = "plugins/{$pluginStudly}/Http/Controllers/{$name}Controller.php";
        }

        // 迁移路径：插件放 plugin 自己的 database/migrations，核心放全局 database/migrations
        $timestamp = date('Y_m_d_His');
        $pluginStudly = $plugin ? Str::studly(Str::snake($plugin)) : null;
        $migrationPath = $config['type'] === 'plugin'
            ? "plugins/{$pluginStudly}/database/migrations/{$timestamp}_create_{$tableName}_table.php"
            : "database/migrations/{$timestamp}_create_{$tableName}_table.php";

        // 基础预览文件
        $basePreview = [
            'controller' => [
                'path' => $this->getControllerPath($name, $config['type'], $plugin),
                'content' => $controllerCode,
            ],
            'model' => [
                'path' => $this->getModelPath($modelName, $config['type'], $plugin),
                'content' => $modelCode,
            ],
            'migration' => [
                'path' => $migrationPath,
                'content' => $migrationCode,
            ],
            'vue' => [
                'path' => $this->getVuePath($parent, $kebabName, $config['type'], $plugin),
                'content' => $vueCode,
            ],
        ];

        // 插件模式不使用 router 键（路由由 plugin_routes 键提供）
        if ($config['type'] !== 'plugin') {
            $basePreview['router'] = [
                'path' => $this->getRouterPath($parent, $kebabName, $config['type'], $pluginKebab),
                'content' => $routerCode,
            ];
        }

        return [
            'files' => $files,
            'preview' => array_merge($basePreview, $pluginFiles),
        ];
    }

    protected function generateControllerCode(string $name, string $controllerName, string $modelName, string $type, array $fields, array $gridColumns, array $filters = [], array $filterLayout = [], ?string $plugin = null): string
    {
        $pluginStudly = $plugin ? Str::studly($plugin) : null;
        $namespace = $type === 'plugin'
            ? "Plugins\\{$pluginStudly}\\Admin\\Controllers"
            : 'App\\Admin\\Controllers';

        $modelNamespace = $type === 'plugin'
            ? "Plugins\\{$pluginStudly}\\Models\\{$modelName}"
            : "App\\Admin\\Models\\{$modelName}";

        $gridColumnsCode = $this->formatGridColumns($gridColumns, $fields);
        $formFieldsCode = $this->formFields($fields);
        $filterCode = $this->formatGridFilters($filters, $fields);
        $filterLayoutCode = '';
        if (!empty($filterLayout) && is_array($filterLayout)) {
            $filterLayoutCode = "\n        \$grid->filterLayout(" . var_export($filterLayout, true) . ");";
        }

        $date = $this->formatDate();

        return <<<PHP
<?php

/**
 * {$controllerName} 控制器
 *
 * @Author: DbsAdmin Generator
 * @Date: {$date}
 * @LastEditTime: {$date}
 * @Source: Dbs-Admin 代码生成器快速生成
 * @Wiki: 更多问题请查看 wiki.dabashan.cc
 */

namespace {$namespace};

use Dabashan\DbsAdmin\\Controllers\AdminController;
use Dabashan\DbsAdmin\Grid\Grid;
use Dabashan\DbsAdmin\Form\Form;
use {$modelNamespace};

class {$controllerName} extends AdminController
{
    protected string \$model = {$modelName}::class;

    protected function grid(): Grid
    {
        return Grid::make({$modelName}::class)
            {$gridColumnsCode}
            ->perPage(15);
    }

    protected function configureActions(Grid \$grid): void
    {
        {$filterCode}
        {$filterLayoutCode}
    }

    protected function form(): Form
    {
        return Form::make({$modelName}::class)
            {$formFieldsCode};
    }
}
PHP;
    }

    protected function formatGridColumns(array $gridColumns, array $fields): string
    {
        if (empty($gridColumns)) {
            $lines = [];
            $lines[] = "->column('id', 'ID')->sortable()";
            foreach ($fields as $field) {
                $key = $field['key'] ?? '';
                if (!$key || in_array($key, ['id', 'created_at', 'updated_at', 'deleted_at'], true)) {
                    continue;
                }
                $label = $field['label'] ?? $key;
                $lines[] = "->column('{$key}', '{$label}')";
            }
            $lines[] = "->column('created_at', '创建时间')->sortable()";
            return implode("\n            ", $lines);
        }

        $lines = [];
        foreach ($gridColumns as $col) {
            $key = $col['key'] ?? '';
            $label = $col['label'] ?? $key;
            $line = "->column('{$key}', '{$label}')";

            // 可排序
            if (!empty($col['sortable'])) {
                $line .= '->sortable()';
            }
            // 可搜索
            if (!empty($col['searchable'])) {
                $line .= '->searchable()';
            }
            // 隐藏列
            if (!empty($col['hidden'])) {
                $line .= '->hidden()';
            }
            // 宽度
            if (!empty($col['width'])) {
                $line .= "->width('{$col['width']}')";
            }
            // 对齐方式
            if (!empty($col['align'])) {
                $line .= "->align('{$col['align']}')";
            }
            // 显示类型（支持所有 Column 方法）
            $displayType = $col['display_type'] ?? '';
            if ($displayType && $displayType !== 'text') {
                $displayOptions = $col['display_options'] ?? [];
                // 如果 display_options 是 JSON 字符串，解析为数组
                if (is_string($displayOptions) && !empty($displayOptions)) {
                    $decoded = json_decode($displayOptions, true);
                    $displayOptions = is_array($decoded) ? $decoded : [];
                }
                $line .= $this->formatDisplayType($displayType, $displayOptions);
            }
            // 默认值
            if (array_key_exists('default', $col) && $col['default'] !== '' && $col['default'] !== null) {
                $default = trim((string) $col['default']);
                if (is_numeric($default)) {
                    $line .= "->default({$default})";
                } elseif (strtolower($default) === 'null') {
                    $line .= "->default(null)";
                } elseif (in_array(strtolower($default), ['true', 'false'], true)) {
                    $val = strtolower($default) === 'true' ? 'true' : 'false';
                    $line .= "->default({$val})";
                } else {
                    $escaped = str_replace("'", "\\'", $default);
                    $line .= "->default('{$escaped}')";
                }
            }
            // 字数限制
            if (!empty($col['limit']) && is_numeric($col['limit'])) {
                $line .= "->limit({$col['limit']})";
            }
            // 前缀
            if (!empty($col['prefix'])) {
                $line .= "->prefix('{$col['prefix']}')";
            }
            // 后缀
            if (!empty($col['suffix'])) {
                $line .= "->suffix('{$col['suffix']}')";
            }
            // 小数位数
            if (isset($col['decimals']) && is_numeric($col['decimals'])) {
                $line .= "->decimals({$col['decimals']})";
            }
            // 自动换行
            if (!empty($col['wrap'])) {
                $line .= '->wrap()';
            }
            // 可切换（toggleable）
            if (!empty($col['toggleable'])) {
                $line .= '->toggleable()';
            }

            $lines[] = $line;
        }

        return implode("\n            ", $lines);
    }

    /**
     * 格式化列显示类型（支持 Column 类的所有显示方法）
     */
    protected function formatDisplayType(string $type, array $options = []): string
    {
        return match ($type) {
            // 徽章：badge(['颜色映射'], '变体')
            'badge' => '->badge(' . (!empty($options['colors']) ? var_export($options['colors'], true) . (isset($options['variant']) ? ", '{$options['variant']}'" : '') : (isset($options['variant']) ? "[], '{$options['variant']}'" : '[]')) . ')',

            // 开关：toggle()
            'switch', 'toggle' => '->toggle()',

            // 图片：image(宽, 高, 圆形)
            'image' => '->image(' . ($options['width'] ?? ($options['w'] ?? 40)) . ', ' . ($options['height'] ?? ($options['h'] ?? 40)) . (isset($options['circle']) && $options['circle'] ? ', true' : (isset($options['is_circle']) && $options['is_circle'] ? ', true' : '')) . ')',

            // 标签组：tags('分隔符')
            'tags' => !empty($options['separator']) ? "->tags('{$options['separator']}')" : (!empty($options['sep']) ? "->tags('{$options['sep']}')" : '->tags()'),

            // 进度条：progress(最大值, 显示文字)
            'progress' => '->progress(' . ($options['max'] ?? ($options['maximum'] ?? 100)) . ', ' . (((isset($options['showText']) && !$options['showText']) || (isset($options['show_text']) && !$options['show_text'])) ? 'false' : 'true') . ')',

            // 数值条：bar()
            'bar' => '->bar()',

            // 色块：color()
            'color' => '->color()',

            // 可复制：copyable()
            'copyable' => '->copyable()',

            // 圆点状态：dot()
            'dot' => '->dot()',

            // 日期：date('格式')
            'date' => !empty($options['format']) ? "->date('{$options['format']}')" : (!empty($options['fmt']) ? "->date('{$options['fmt']}')" : '->date()'),

            // 日期时间：datetime('格式')
            'datetime' => !empty($options['format']) ? "->datetime('{$options['format']}')" : (!empty($options['fmt']) ? "->datetime('{$options['fmt']}')" : '->datetime()'),

            // 金额：money('符号', 小数位)
            'money' => '->money(' . (!empty($options['symbol']) ? "'{$options['symbol']}', " : (!empty($options['currency']) ? "'{$options['currency']}', " : '')) . ($options['decimals'] ?? ($options['decimal'] ?? 2)) . ')',

            // 计数：count()
            'count' => '->count()',

            default => '',
        };
    }

    protected function formatGridFilters(array $filters, array $fields): string
    {
        if (empty($filters)) {
            $lines = [];
            foreach ($fields as $field) {
                $key = $field['key'] ?? '';
                if (!$key || in_array($key, ['id', 'created_at', 'updated_at', 'deleted_at'], true)) {
                    continue;
                }

                $label = $field['label'] ?? $key;
                $fieldType = $field['type'] ?? '';
                $dbType = $field['db_type'] ?? '';
                $type = 'like';

                if (in_array($fieldType, ['date', 'dateTime', 'time'], true) || in_array($dbType, ['date', 'dateTime', 'timestamp'], true)) {
                    $type = 'between_date';
                } elseif (in_array($fieldType, ['select', 'radio', 'checkbox', 'switch', 'toggleButtons', 'treeSelect'], true) || $dbType === 'boolean') {
                    $type = 'select';
                } elseif (in_array($fieldType, ['number', 'integer', 'decimal', 'float', 'slider', 'rate'], true) || in_array($dbType, ['integer', 'bigInteger', 'decimal', 'float'], true)) {
                    $type = 'between';
                } elseif ($fieldType === 'tags') {
                    $type = 'in';
                } elseif (in_array($fieldType, ['email', 'url'], true)) {
                    $type = 'equal';
                }

                $line = "\$grid->filter('{$key}', '{$label}', '{$type}')";

                if (in_array($type, ['select', 'in'], true)) {
                    $options = $field['options'] ?? null;
                    $decoded = null;
                    if (is_string($options) && trim($options) !== '') {
                        $decoded = json_decode($options, true);
                    } elseif (is_array($options)) {
                        $decoded = $options;
                    }
                    if (is_array($decoded) && !empty($decoded)) {
                        $optionsStr = var_export($decoded, true);
                        $line .= "->options({$optionsStr})";
                    } elseif ($dbType === 'boolean') {
                        $line .= "->options(" . var_export(['1' => '启用', '0' => '禁用'], true) . ")";
                    }
                }

                if (in_array($type, ['like'], true)) {
                    $line .= "->placeholder('请输入{$label}')";
                } elseif (in_array($type, ['select', 'equal', 'in'], true)) {
                    $line .= "->placeholder('请选择{$label}')";
                }

                if (in_array($fieldType, ['checkbox'], true) && in_array($type, ['select', 'equal'], true)) {
                    $line .= '->multiple()';
                }

                $lines[] = $line . ';';
            }

            if (empty($lines)) {
                return '// 无筛选器';
            }

            return implode("\n        ", $lines);
        }

        $lines = [];
        foreach ($filters as $filter) {
            $key = $filter['key'] ?? '';
            $label = $filter['label'] ?? $key;
            $type = $filter['type'] ?? 'like';

            $line = "\$grid->filter('{$key}', '{$label}', '{$type}')";

            // 选项数据（用于 select/equal 类型）
            if (!empty($filter['options'])) {
                $options = $filter['options'];
                if (is_string($options)) {
                    $decoded = json_decode($options, true);
                    if (is_array($decoded)) {
                        $optionsStr = var_export($decoded, true);
                        $line .= "->options({$optionsStr})";
                    }
                } elseif (is_array($options)) {
                    $optionsStr = var_export($options, true);
                    $line .= "->options({$optionsStr})";
                }
            }
            // 默认值
            if (isset($filter['default']) && $filter['default'] !== '') {
                $defaultVal = is_string($filter['default']) ? "'{$filter['default']}'" : $filter['default'];
                $line .= "->default({$defaultVal})";
            }
            // 占位文本
            if (!empty($filter['placeholder'])) {
                $line .= "->placeholder('{$filter['placeholder']}')";
            }
            // 多选模式
            if (!empty($filter['multiple'])) {
                $line .= '->multiple()';
            }

            $extra = [];
            if (!empty($filter['width'])) {
                $extra['width'] = $filter['width'];
            }
            if (!empty($filter['hidden'])) {
                $extra['hidden'] = true;
            }
            if (!empty($filter['collapsed_hidden'])) {
                $extra['collapsedHidden'] = true;
            }
            if (!empty($extra)) {
                $line .= '->extra(' . var_export($extra, true) . ')';
            }

            $lines[] = $line . ';';
        }

        if (empty($lines)) {
            return '// 无筛选器';
        }

        return implode("\n        ", $lines);
    }

    protected function formFields(array $fields): string
    {
        if (empty($fields)) {
            return "->text('name', '名称')->required()";
        }

        // 字段类型到表单组件的映射
        $typeMap = [
            'text' => 'text',
            'password' => 'password',
            'textarea' => 'textarea',
            'number' => 'number',
            'email' => 'email',
            'url' => 'url',
            'select' => 'select',
            'switch' => 'switch',
            'radio' => 'radio',
            'checkbox' => 'checkbox',
            'date' => 'date',
            'dateTime' => 'dateTime',
            'dateRange' => 'dateRange',
            'time' => 'time',
            'image' => 'image',
            'images' => 'images',
            'file' => 'file',
            'files' => 'files',
            'editor' => 'editor',
            'markdown' => 'markdown',
            'code' => 'code',
            'color' => 'color',
            'slider' => 'slider',
            'rate' => 'rate',
            'tags' => 'tags',
            'icon' => 'icon',
            'treeSelect' => 'treeSelect',
            'cascader' => 'cascader',
            'autoComplete' => 'autoComplete',
            'keyValue' => 'keyValue',
            'repeater' => 'repeater',
            'toggleButtons' => 'toggleButtons',
            'hidden' => 'hidden',
        ];

        $lines = [];
        foreach ($fields as $field) {
            $fieldType = $field['type'] ?? 'text';
            $formType = $typeMap[$fieldType] ?? 'text';
            $key = $field['key'] ?? '';
            $label = $field['label'] ?? $key;

            $line = "->{$formType}('{$key}', '{$label}')";

            // 可空
            if (!empty($field['nullable'])) {
                $line .= '->nullable()';
            }

            // 必填
            if (!empty($field['required'])) {
                $line .= '->required()';
            }

            // 默认值
            if (isset($field['default']) && $field['default'] !== '') {
                // 判断是否为数字
                if (is_numeric($field['default'])) {
                    $line .= "->default({$field['default']})";
                } else {
                    $line .= "->default('{$field['default']}')";
                }
            }

            // 注释作为帮助文本
            if (!empty($field['comment'])) {
                $line .= "->help('{$field['comment']}')";
            }

            // 选项数据（select/radio/checkbox 等）
            if (!empty($field['options'])) {
                $options = $field['options'];
                if (is_string($options)) {
                    $decoded = json_decode($options, true);
                    if (is_array($decoded)) {
                        $line .= "->options(" . var_export($decoded, true) . ')';
                    }
                } elseif (is_array($options)) {
                    $line .= "->options(" . var_export($options, true) . ')';
                }
            }

            $lines[] = $line;
        }

        return implode("\n            ", $lines);
    }

    protected function generateModelCode(string $modelName, string $tableName, array $fillable, string $type, ?string $plugin = null): string
    {
        $pluginStudly = $plugin ? Str::studly($plugin) : null;
        $namespace = $type === 'plugin' ? "Plugins\\{$pluginStudly}\\Models" : 'App\\Admin\\Models';
        $fillableCode = empty($fillable) ? '//' : "'" . implode("',\n        '", $fillable) . "',";
        $baseModel = $type === 'plugin' ? '\\Illuminate\\Database\\Eloquent\\Model' : '\\Dabashan\\DbsAdmin\\Models\\BaseAdminModel';
        $date = $this->formatDate();

        return <<<PHP
<?php

/**
 * {$modelName} 模型
 *
 * @Author: DbsAdmin Generator
 * @Date: {$date}
 * @LastEditTime: {$date}
 * @Source: Dbs-Admin 代码生成器快速生成
 * @Wiki: 更多问题请查看 wiki.dabashan.cc
 */

namespace {$namespace};

use {$baseModel};

/**
 * {$modelName} Model
 */
class {$modelName} extends {$baseModel}
{
    protected \$table = '{$tableName}';

    protected \$fillable = [
        {$fillableCode}
    ];
}
PHP;
    }

    protected function generateMigrationCode(string $tableName, array $fields): string
    {
        $fieldLines = "\$table->id();\n";
        foreach ($fields as $field) {
            $dbType = $field['db_type'] ?? 'string';
            $migrationLine = "\$table->{$dbType}('{$field['key']}')";
            if (!empty($field['nullable'])) {
                $migrationLine .= '->nullable()';
            }
            if (array_key_exists('default', $field) && $field['default'] !== '' && $field['default'] !== null) {
                $default = $field['default'];
                $defaultStr = trim((string) $default);
                if (in_array($dbType, ['timestamp', 'dateTime'], true) && in_array(strtolower($defaultStr), ['current_timestamp', 'current_timestamp()', 'now()'], true)) {
                    $migrationLine .= '->useCurrent()';
                } elseif (in_array($dbType, ['integer', 'bigInteger', 'decimal', 'float', 'double'], true) && is_numeric($defaultStr)) {
                    $migrationLine .= "->default({$defaultStr})";
                } elseif ($dbType === 'boolean' && in_array($defaultStr, ['0', '1'], true)) {
                    $migrationLine .= "->default({$defaultStr})";
                } elseif (strtolower($defaultStr) === 'null') {
                    $migrationLine .= '->default(null)';
                } else {
                    $escaped = str_replace("'", "\\'", $defaultStr);
                    $migrationLine .= "->default('{$escaped}')";
                }
            }
            if (!empty($field['comment'])) {
                $migrationLine .= "->comment('{$field['comment']}')";
            }
            $fieldLines .= "            {$migrationLine};\n";
        }
        $fieldLines .= "            \$table->timestamps();\n            \$table->softDeletes();";

        return <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('{$tableName}')) {
            return;
        }
        Schema::create('{$tableName}', function (Blueprint \$table) {
            {$fieldLines}
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$tableName}');
    }
};
PHP;
    }

    /**
     * 生成索引迁移代码
     */
    protected function generateIndexMigrationCode(string $tableName, array $indexes): string
    {
        if (empty($indexes)) {
            return '';
        }

        $lines = [];
        foreach ($indexes as $index) {
            $fields = $index['fields'] ?? [];
            if (empty($fields)) continue;

            $type = $index['type'] ?? 'index';
            $indexName = $index['name'] ?? '';

            if (count($fields) === 1) {
                // 单字段索引
                $field = $fields[0];
                if ($type === 'unique') {
                    $line = "\$table->unique('{$field}'";
                } elseif ($type === 'fulltext') {
                    $line = "\$table->fullText('{$field}'";
                } else {
                    $line = "\$table->index('{$field}'";
                }
                if ($indexName) {
                    $line .= ", '{$indexName}'";
                }
                $line .= ')';
            } else {
                // 复合索引
                $fieldsStr = "['" . implode("', '", $fields) . "']";
                if ($type === 'unique') {
                    $line = "\$table->unique({$fieldsStr}";
                } elseif ($type === 'fulltext') {
                    $line = "\$table->fullText({$fieldsStr}";
                } else {
                    $line = "\$table->index({$fieldsStr}";
                }
                if ($indexName) {
                    $line .= ", '{$indexName}'";
                }
                $line .= ')';
            }
            $lines[] = $line;
        }

        return implode(";\n            ", $lines);
    }

    protected function generateVueCode(string $parent, string $kebabName, string $name, string $type = 'core', ?string $pluginKebab = null): string
    {
        if ($type === 'plugin' && $pluginKebab) {
            return <<<VUE
<template>
  <DynamicCrud
    api-prefix="/plugin/{$pluginKebab}/admin/{$kebabName}"
    :breadcrumb="['menu.plugin', 'menu.plugin.{$pluginKebab}', 'menu.plugin.{$pluginKebab}.{$kebabName}']"
    add-title="新增{$name}"
    edit-title="编辑{$name}"
  />
</template>

<script lang="ts" setup>
  import DynamicCrud from '@/components/dynamic/DynamicCrud.vue';
</script>
VUE;
        }

        // 核心模块
        return <<<VUE
<template>
  <DynamicCrud
    api-prefix="/admin/{$parent}/{$kebabName}"
    :breadcrumb="['menu.{$parent}', 'menu.{$parent}.{$kebabName}']"
    add-title="新增{$name}"
    edit-title="编辑{$name}"
  />
</template>

<script lang="ts" setup>
  import DynamicCrud from '@/components/dynamic/DynamicCrud.vue';
</script>
VUE;
    }

    protected function generateRouterCode(string $parent, string $kebabName, string $name, string $icon, int $order, string $type = 'core', ?string $pluginKebab = null): string
    {
        // 插件模式：返回 child route 片段（追加到 plugin.ts）
        if ($type === 'plugin' && $pluginKebab) {
            $pluginStudly = Str::studly($pluginKebab);
            return <<<TS
{
  path: '{$kebabName}',
  name: '{$pluginStudly}{$name}',
  component: () => import('@/views/plugin/{$pluginKebab}/{$kebabName}/index.vue'),
  meta: {
    locale: 'menu.plugin.{$pluginKebab}.{$kebabName}',
    requiresAuth: true,
    roles: ['*'],
    hideInMenu: true,
  },
},
TS;
        }

        $parentStudly = Str::studly($parent);

        return <<<TS
import { DEFAULT_LAYOUT } from '../base';
import { AppRouteRecordRaw } from '../types';

const {$name}Route: AppRouteRecordRaw = {
  path: '/{$parent}',
  name: '{$parentStudly}Parent',
  component: DEFAULT_LAYOUT,
  meta: {
    locale: 'menu.{$parent}',
    icon: 'icon-apps',
    requiresAuth: true,
    order: {$order},
  },
  children: [
    {
      path: '{$kebabName}',
      name: '{$name}List',
      component: () => import('@/views/{$parent}/{$kebabName}/index.vue'),
      meta: {
        locale: 'menu.{$parent}.{$kebabName}',
        requiresAuth: true,
        roles: ['*'],
        permissions: ['{$parent}.{$kebabName}'],
      },
    },
  ],
};

export default {$name}Route;
TS;
    }

    /**
     * 生成插件 manifest（plugin.json）
     */
    protected function generatePluginJson(string $plugin, string $pluginTitle, string $name, string $kebabName, string $icon): string
    {
        $pluginSnake = Str::snake($plugin);
        $pluginStudly = Str::studly($pluginSnake);
        $title = $pluginTitle ?: "{$pluginStudly} 插件";
        $json = [
            'name' => $pluginSnake,
            'title' => $title,
            'description' => "{$name} 管理插件",
            'version' => '1.0.0',
            'author' => 'DbsAdmin Generator',
            'enabled' => true,
            'icon' => $icon,
            'type' => 'local',
            'show_api' => true,
            'requires' => [],
            'providers' => [
                "Plugins\\{$pluginStudly}\\Providers\\PluginServiceProvider",
            ],
            'admin_controllers' => [
                "Plugins\\{$pluginStudly}\\Admin\\Controllers\\{$name}Controller",
            ],
            'menus' => [
                [
                    'title' => "{$name}管理",
                    'icon' => $icon,
                    'uri' => "admin/{$kebabName}",
                    'component' => "{$pluginSnake}/{$kebabName}",
                ],
            ],
            'permissions' => [
                [
                    'slug' => "{$pluginSnake}.{$kebabName}",
                    'name' => "{$name}管理",
                    'http_method' => [],
                    'http_path' => "/plugin/{$pluginSnake}/admin/{$kebabName}/*",
                ],
            ],
        ];

        return json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * 生成插件服务提供者
     */
    protected function generatePluginServiceProvider(string $plugin, string $name): string
    {
        $pluginSnake = Str::snake($plugin);
        $pluginStudly = Str::studly($pluginSnake);
        $date = $this->formatDate();

        return <<<PHP
<?php

/**
 * 插件服务提供者
 *
 * @Author: DbsAdmin Generator
 * @Date: {$date}
 * @LastEditTime: {$date}
 * @Source: Dbs-Admin 代码生成器快速生成
 * @Wiki: 更多问题请查看 wiki.dabashan.cc
 */

namespace Plugins\\{$pluginStudly}\\Providers;

use Illuminate\\Support\\ServiceProvider;

class PluginServiceProvider extends ServiceProvider
{
    protected string \$pluginName = '{$pluginSnake}';

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // 加载数据库迁移
        \$migrationsPath = dirname(__DIR__) . '/database/migrations';
        if (is_dir(\$migrationsPath)) {
            \$this->loadMigrationsFrom(\$migrationsPath);
        }

        // 加载后台路由
        \$adminRoutes = dirname(__DIR__) . '/Admin/routes.php';
        if (file_exists(\$adminRoutes)) {
            \$this->loadRoutesFrom(\$adminRoutes);
        }

        // 加载业务路由
        \$httpRoutes = dirname(__DIR__) . '/Http/routes.php';
        if (file_exists(\$httpRoutes)) {
            \$this->loadRoutesFrom(\$httpRoutes);
        }
    }
}
PHP;
    }

    /**
     * 生成插件后台路由文件
     */
    protected function generatePluginAdminRoutes(string $plugin, string $name, string $kebabName): string
    {
        $pluginSnake = Str::snake($plugin);
        $pluginStudly = Str::studly($pluginSnake);
        $controllerNamespace = "Plugins\\{$pluginStudly}\\Admin\\Controllers\\{$name}Controller";
        $date = $this->formatDate();

        return <<<PHP
<?php

/**
 * 插件后台路由配置
 *
 * @Author: DbsAdmin Generator
 * @Date: {$date}
 * @LastEditTime: {$date}
 * @Source: Dbs-Admin 代码生成器快速生成
 * @Wiki: 开发文档 wiki.dabashan.cc
 */

use Illuminate\\Support\\Facades\\Route;
use {$controllerNamespace};

/*
|--------------------------------------------------------------------------
| 插件后台路由（Admin 端）
|--------------------------------------------------------------------------
| 前缀: /plugin/{$pluginSnake}/admin
| 中间件: api, auth:admin
|
*/

Route::prefix('plugin/{$pluginSnake}/admin')
    ->middleware(['api', 'auth:admin'])
    ->group(function () {
        Route::get('{$kebabName}/form-schema', [{$name}Controller::class, 'formSchema']);
        Route::get('{$kebabName}/grid-meta', [{$name}Controller::class, 'gridMeta']);
        Route::post('{$kebabName}/batch-update', [{$name}Controller::class, 'batchUpdate']);
        Route::post('{$kebabName}/batch-destroy', [{$name}Controller::class, 'batchDestroy']);
        Route::post('{$kebabName}/{id}/toggle', [{$name}Controller::class, 'toggle']);
        Route::post('{$kebabName}/{id}/replicate', [{$name}Controller::class, 'replicate']);
        Route::post('{$kebabName}/{id}/restore', [{$name}Controller::class, 'restore']);
        Route::apiResource('{$kebabName}', {$name}Controller::class);
    });
PHP;
    }

    /**
     * 生成插件业务端路由文件（Http 目录）
     */
    protected function generatePluginHttpRoutes(string $plugin, string $name, string $kebabName): string
    {
        $pluginSnake = Str::snake($plugin);
        $pluginStudly = Str::studly($pluginSnake);
        $controllerNamespace = "Plugins\\{$pluginStudly}\\Http\\Controllers\\{$name}Controller";
        $date = $this->formatDate();

        return <<<PHP
<?php

/**
 * 插件业务端路由配置
 *
 * @Author: DbsAdmin Generator
 * @Date: {$date}
 * @LastEditTime: {$date}
 * @Source: Dbs-Admin 代码生成器快速生成
 * @Wiki: 开发文档 wiki.dabashan.cc
 */

use Illuminate\\Support\\Facades\\Route;
use {$controllerNamespace};

/*
|--------------------------------------------------------------------------
| 插件业务路由（Http 端）
|--------------------------------------------------------------------------
| 前缀: /plugin/{$pluginSnake}/api
| 中间件: api
| 业务端独立，无强制约束
|
*/

Route::prefix('plugin/{$pluginSnake}/api')
    ->middleware('api')
    ->group(function () {
        // 公开接口（限速 60 次/分钟）
        Route::middleware('throttle:60,1')->group(function () {
            Route::apiResource('{$kebabName}', {$name}Controller::class);
        });
    });
PHP;
    }

    /**
     * 生成插件业务端控制器（Http 目录）
     */
    protected function generatePluginHttpController(string $plugin, string $name): string
    {
        $pluginStudly = Str::studly($plugin);
        $modelName = $name;
        $date = $this->formatDate();

        return <<<PHP
<?php

/**
 * {$name} 业务端控制器
 *
 * @Author: DbsAdmin Generator
 * @Date: {$date}
 * @LastEditTime: {$date}
 * @Source: Dbs-Admin 代码生成器快速生成
 * @Wiki: 开发文档 wiki.dabashan.cc
 */

namespace Plugins\\{$pluginStudly}\\Http\\Controllers;

use Illuminate\\Http\\Request;
use Illuminate\\Routing\\Controller;
use Plugins\\{$pluginStudly}\\Models\\{$modelName};

class {$name}Controller extends Controller
{
    /**
     * 获取列表
     */
    public function index(Request \$request)
    {
        \$perPage = \$request->input('per_page', 15);
        \$items = {$modelName}::paginate(\$perPage);

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => \$items,
        ]);
    }

    /**
     * 获取详情
     */
    public function show(\$id)
    {
        \$item = {$modelName}::find(\$id);
        if (!\$item) {
            return response()->json([
                'code' => 404,
                'message' => '数据不存在',
            ], 404);
        }

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => \$item,
        ]);
    }

    /**
     * 创建
     */
    public function store(Request \$request)
    {
        \$validated = \$request->validate([
            'name' => 'required|string|max:255',
        ]);

        \$item = {$modelName}::create(\$validated);

        return response()->json([
            'code' => 0,
            'message' => '创建成功',
            'data' => \$item,
        ]);
    }

    /**
     * 更新
     */
    public function update(Request \$request, \$id)
    {
        \$item = {$modelName}::find(\$id);
        if (!\$item) {
            return response()->json([
                'code' => 404,
                'message' => '数据不存在',
            ], 404);
        }

        \$validated = \$request->validate([
            'name' => 'sometimes|string|max:255',
        ]);

        \$item->update(\$validated);

        return response()->json([
            'code' => 0,
            'message' => '更新成功',
            'data' => \$item,
        ]);
    }

    /**
     * 删除
     */
    public function destroy(\$id)
    {
        \$item = {$modelName}::find(\$id);
        if (!\$item) {
            return response()->json([
                'code' => 404,
                'message' => '数据不存在',
            ], 404);
        }

        \$item->delete();

        return response()->json([
            'code' => 0,
            'message' => '删除成功',
        ]);
    }
}
PHP;
    }

    /**
     * 生成业务端 Vue 页面（插件后台管理）
     * 参考 DemoPlugin 的标准格式
     */
    protected function generateBusinessVueCode(string $plugin, string $kebabName, string $name): string
    {
        $pluginKebab = Str::snake($plugin);
        $date = $this->formatDate();
        return <<<VUE
<!--
 * {$name} 业务端页面
 *
 * @Author: DbsAdmin Generator
 * @Date: {$date}
 * @LastEditTime: {$date}
 * @Source: Dbs-Admin 代码生成器快速生成
 * @Wiki: 开发文档 wiki.dabashan.cc
-->
<template>
  <div class="container">
    <a-card class="general-card" title="{$name}管理">
      <template #extra>
        <a-button type="primary" @click="handleAdd">
          <template #icon><icon-plus /></template>
          新增
        </a-button>
      </template>
      <a-table
        :columns="columns"
        :data="tableData"
        :loading="loading"
        :pagination="pagination"
        @page-change="onPageChange"
      >
        <template #actions="{ record }">
          <a-space>
            <a-button type="text" size="small" @click="handleEdit(record)">编辑</a-button>
            <a-popconfirm content="确定要删除吗？" @ok="handleDelete(record.id)">
              <a-button type="text" status="danger" size="small">删除</a-button>
            </a-popconfirm>
          </a-space>
        </template>
      </a-table>
    </a-card>

    <a-modal
      v-model:visible="modalVisible"
      :title="isEdit ? '编辑{$name}' : '新增{$name}'"
      @ok="handleSubmit"
      @cancel="handleCancel"
    >
      <a-form ref="formRef" :model="formData" layout="vertical">
        <a-form-item field="name" label="名称" :rules="[{ required: true, message: '请输入名称' }]">
          <a-input v-model="formData.name" placeholder="请输入名称" />
        </a-form-item>
      </a-form>
    </a-modal>
  </div>
</template>

<script lang="ts" setup>
  import { ref, reactive, onMounted } from 'vue';
  import { Message } from '@arco-design/web-vue';
  import { IconPlus } from '@arco-design/web-vue/es/icon';
  import axios from 'axios';

  interface {$name}Item {
    id: number;
    name: string;
    created_at: string;
  }

  const API_BASE = '/plugin/{$pluginKebab}/admin/{$kebabName}';

  const loading = ref(false);
  const tableData = ref<{$name}Item[]>([]);
  const modalVisible = ref(false);
  const isEdit = ref(false);
  const editId = ref<number | null>(null);
  const formRef = ref();

  const pagination = reactive({
    current: 1,
    pageSize: 15,
    total: 0,
  });

  const formData = reactive({
    name: '',
  });

  const columns = [
    { title: 'ID', dataIndex: 'id', width: 80 },
    { title: '名称', dataIndex: 'name' },
    { title: '创建时间', dataIndex: 'created_at', width: 180 },
    { title: '操作', slotName: 'actions', width: 150, fixed: 'right' },
  ];

  const fetchData = async () => {
    loading.value = true;
    try {
      const res = await axios.get(API_BASE, {
        params: {
          page: pagination.current,
          per_page: pagination.pageSize,
        },
      });
      const data = res.data;
      const result = data?.data || data;
      tableData.value = result.data || result || [];
      pagination.total = result.total || tableData.value.length;
    } catch (err) {
      Message.error('获取数据失败');
    } finally {
      loading.value = false;
    }
  };

  const onPageChange = (page: number) => {
    pagination.current = page;
    fetchData();
  };

  const resetForm = () => {
    formData.name = '';
    editId.value = null;
    isEdit.value = false;
  };

  const handleAdd = () => {
    resetForm();
    modalVisible.value = true;
  };

  const handleEdit = (record: {$name}Item) => {
    isEdit.value = true;
    editId.value = record.id;
    formData.name = record.name;
    modalVisible.value = true;
  };

  const handleSubmit = async () => {
    try {
      const valid = await formRef.value?.validate();
      if (valid) return;

      if (isEdit.value && editId.value) {
        await axios.put(`\${API_BASE}/\${editId.value}`, formData);
        Message.success('更新成功');
      } else {
        await axios.post(API_BASE, formData);
        Message.success('创建成功');
      }
      modalVisible.value = false;
      fetchData();
    } catch (err) {
      Message.error(isEdit.value ? '更新失败' : '创建失败');
    }
  };

  const handleCancel = () => {
    modalVisible.value = false;
    resetForm();
  };

  const handleDelete = async (id: number) => {
    try {
      await axios.delete(`\${API_BASE}/\${id}`);
      Message.success('删除成功');
      fetchData();
    } catch (err) {
      Message.error('删除失败');
    }
  };

  onMounted(() => {
    fetchData();
  });
</script>

<style scoped lang="less">
  .container {
    padding: 10px;
  }
</style>
VUE;
    }

    /**
     * 生成插件主页 Vue 页面
     */
    protected function generatePluginIndexVue(string $pluginTitle): string
    {
        return <<<VUE
<template>
  <div class="plugin-home">
    <a-card class="welcome-card">
      <div class="welcome-content">
        <icon-apps class="welcome-icon" />
        <h2 class="welcome-title">{{ pluginTitle }}</h2>
        <p class="welcome-desc">欢迎使用本插件，请从左侧菜单选择功能模块</p>
      </div>
    </a-card>
  </div>
</template>

<script lang="ts" setup>
import { ref } from 'vue';

const pluginTitle = ref('{$pluginTitle}');
</script>

<style scoped lang="less">
.plugin-home {
  padding: 20px;
  min-height: calc(100vh - 100px);
}

.welcome-card {
  text-align: center;
  padding: 60px 20px;
}

.welcome-icon {
  font-size: 64px;
  color: var(--color-primary-6);
  margin-bottom: 24px;
}

.welcome-title {
  font-size: 24px;
  font-weight: 500;
  color: var(--color-text-1);
  margin: 0 0 12px 0;
}

.welcome-desc {
  font-size: 14px;
  color: var(--color-text-3);
  margin: 0;
}
</style>
VUE;
    }

    /**
     * 生成插件资源路由文件（放在插件自己的 resources/routes/ 目录下）
     */
    protected function generatePluginResourceRoutes(string $pluginStudly, string $pluginKebab, string $kebabName, string $name): string
    {
        return <<<TS
import { DEFAULT_LAYOUT } from '@/router/routes/base';
import { AppRouteRecordRaw } from '@/router/routes/types';

const {$name}: AppRouteRecordRaw = {
  path: '/plugin/{$pluginKebab}',
  name: '{$pluginStudly}',
  component: () => import('@plugins/{$pluginStudly}/resources/views/index.vue'),
  redirect: '/plugin/{$pluginKebab}/{$kebabName}',
  meta: {
    locale: 'menu.plugin.{$pluginKebab}',
    icon: 'icon-apps',
    requiresAuth: true,
    order: 80,
    hideChildrenInMenu: true,
  },
  children: [
    {
      path: '{$kebabName}',
      name: '{$name}',
      component: () => import('@plugins/{$pluginStudly}/resources/views/{$kebabName}/index.vue'),
      meta: {
        locale: 'menu.plugin.{$pluginKebab}.{$kebabName}',
        requiresAuth: true,
        roles: ['*'],
        hideInMenu: true,
      },
    },
  ],
};

export default {$name};
TS;
    }

    protected function generatePluginRoutesIndex(
        string $pluginStudly,
        string $pluginSnake,
        ?string $extraKebabName = null,
        array $virtualFiles = []
    ): string
    {
        $routes = $this->collectPluginViewRoutes($pluginStudly, $extraKebabName, $virtualFiles);
        $childrenCode = implode(",\n", array_map(function ($item) use ($pluginStudly, $pluginSnake) {
            $kebab = $item['kebab'];
            $studly = $item['studly'];
            if ($kebab === 'index') {
                return <<<TS
    {
      path: 'index',
      name: 'Plugin{$pluginStudly}Index',
      component: () => import('@plugins/{$pluginStudly}/resources/views/index.vue'),
      meta: {
        locale: 'menu.plugin.{$pluginSnake}',
        requiresAuth: true,
        roles: ['*'],
        hideInMenu: true,
      },
    }
TS;
            }

            return <<<TS
    {
      path: '{$kebab}',
      name: 'Plugin{$pluginStudly}{$studly}',
      component: () => import('@plugins/{$pluginStudly}/resources/views/{$kebab}/index.vue'),
      meta: {
        locale: 'menu.plugin.{$pluginSnake}.{$kebab}',
        requiresAuth: true,
        roles: ['*'],
        hideInMenu: true,
      },
    }
TS;
        }, $routes));

        $redirect = '/plugin/' . $pluginSnake . '/index';
        foreach ($routes as $item) {
            if ($item['kebab'] !== 'index') {
                $redirect = '/plugin/' . $pluginSnake . '/' . $item['kebab'];
                break;
            }
        }

        return <<<TS
import { DEFAULT_LAYOUT } from '@/router/routes/base';
import { AppRouteRecordRaw } from '@/router/routes/types';

const PluginRoutes: AppRouteRecordRaw = {
  path: '/plugin/{$pluginSnake}',
  name: 'Plugin{$pluginStudly}',
  component: DEFAULT_LAYOUT,
  redirect: '{$redirect}',
  meta: {
    locale: 'menu.plugin.{$pluginSnake}',
    icon: 'icon-apps',
    requiresAuth: true,
    order: 80,
    hideChildrenInMenu: true,
  },
  children: [
{$childrenCode}
  ],
};

export default PluginRoutes;
TS;
    }

    protected function collectPluginViewRoutes(string $pluginStudly, ?string $extraKebabName = null, array $virtualFiles = []): array
    {
        $viewsDir = base_path("plugins/{$pluginStudly}/resources/views");
        $routes = [];

        if (is_dir($viewsDir) && file_exists($viewsDir . '/index.vue')) {
            $routes[] = ['kebab' => 'index', 'studly' => 'Index'];
        }

        $virtualPrefix = "plugins/{$pluginStudly}/resources/views/";
        foreach ($virtualFiles as $path => $content) {
            if (!is_string($path)) continue;
            if ($path === $virtualPrefix . 'index.vue') {
                $routes[] = ['kebab' => 'index', 'studly' => 'Index'];
                continue;
            }
            if (!str_starts_with($path, $virtualPrefix)) continue;
            if (!str_ends_with($path, '/index.vue')) continue;
            $sub = substr($path, strlen($virtualPrefix));
            $kebab = substr($sub, 0, -strlen('/index.vue'));
            if ($kebab === '' || $kebab === 'locale') continue;
            $routes[] = ['kebab' => $kebab, 'studly' => Str::studly($kebab)];
        }

        if (is_dir($viewsDir)) {
            foreach (glob($viewsDir . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
                $kebab = basename($dir);
                if ($kebab === 'locale') continue;
                if (!file_exists($dir . '/index.vue')) continue;
                $routes[] = ['kebab' => $kebab, 'studly' => Str::studly($kebab)];
            }
        }

        if ($extraKebabName) {
            $exists = false;
            foreach ($routes as $r) {
                if ($r['kebab'] === $extraKebabName) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $routes[] = ['kebab' => $extraKebabName, 'studly' => Str::studly($extraKebabName)];
            }
        }

        usort($routes, function ($a, $b) {
            if ($a['kebab'] === 'index') return -1;
            if ($b['kebab'] === 'index') return 1;
            return strcmp($a['kebab'], $b['kebab']);
        });

        $unique = [];
        $seen = [];
        foreach ($routes as $r) {
            $k = $r['kebab'] ?? '';
            if ($k === '' || isset($seen[$k])) continue;
            $seen[$k] = true;
            $unique[] = $r;
        }
        return $unique;
    }

    protected function getGeneratedFiles(string $name, string $kebabName, string $parent, string $type, ?string $plugin, string $table, ?string $pluginKebab = null): array
    {
        $files = [];

        if ($type === 'plugin' && $plugin) {
            $pluginStudly = Str::studly(Str::snake($plugin));
            $pluginJsonPath = base_path("plugins/{$pluginStudly}/plugin.json");
            $isNewPlugin = !file_exists($pluginJsonPath);

            // 插件模式：Admin 控制器和 Model
            $files[] = "plugins/{$pluginStudly}/Admin/Controllers/{$name}Controller.php";
            $files[] = "plugins/{$pluginStudly}/Models/{$name}.php";

            // 迁移文件
            $timestamp = date('Y_m_d_His');
            $files[] = "plugins/{$pluginStudly}/database/migrations/{$timestamp}_create_{$table}_table.php";

            // Admin 路由
            $files[] = "plugins/{$pluginStudly}/Admin/routes.php";

            // 插件资源：Vue 页面（插件首页 + 业务页面）
            $files[] = "plugins/{$pluginStudly}/resources/views/index.vue";
            $files[] = "plugins/{$pluginStudly}/resources/views/{$kebabName}/index.vue";

            // 插件资源：路由文件
            $files[] = "plugins/{$pluginStudly}/resources/routes/index.ts";

            // Http 路由和控制器
            $files[] = "plugins/{$pluginStudly}/Http/routes.php";
            $files[] = "plugins/{$pluginStudly}/Http/Controllers/{$name}Controller.php";

            // plugin.json（新增资源会追加到同一个文件）
            $files[] = "plugins/{$pluginStudly}/plugin.json";
            if ($isNewPlugin) {
                $files[] = "plugins/{$pluginStudly}/Providers/PluginServiceProvider.php";
            }
        } else {
            // 核心模块
            $files[] = $this->getControllerPath($name, $type, $plugin);
            $files[] = $this->getModelPath($name, $type, $plugin);
            $timestamp = date('Y_m_d_His');
            $files[] = "database/migrations/{$timestamp}_create_{$table}_table.php";
            $files[] = $this->getVuePath($parent, $kebabName, $type, $plugin);
            $files[] = $this->getRouterPath($parent, $kebabName, $type, $pluginKebab);
            $files[] = "resource/views/{$parent}/{$kebabName}/locale/zh-CN.ts";
            $files[] = "resource/views/{$parent}/{$kebabName}/locale/en-US.ts";
        }

        return $files;
    }

    protected function getControllerPath(string $name, string $type, ?string $plugin): string
    {
        return $type === 'plugin'
            ? "plugins/" . Str::studly(Str::snake($plugin)) . "/Admin/Controllers/{$name}Controller.php"
            : "app/Admin/Controllers/{$name}Controller.php";
    }

    protected function getModelPath(string $name, string $type, ?string $plugin): string
    {
        $modelName = $type === 'plugin' ? $name : "Admin{$name}";
        return $type === 'plugin'
            ? "plugins/" . Str::studly(Str::snake($plugin)) . "/Models/{$modelName}.php"
            : "app/Admin/Models/{$modelName}.php";
    }

    protected function getVuePath(string $parent, string $kebabName, string $type, ?string $plugin): string
    {
        return $type === 'plugin'
            ? "plugins/" . Str::studly(Str::snake($plugin)) . "/resources/views/{$kebabName}/index.vue"
            : "resource/views/{$parent}/{$kebabName}/index.vue";
    }

    protected function getRouterPath(string $parent, string $kebabName, string $type, ?string $pluginKebab = null): string
    {
        return $type === 'plugin'
            ? "plugins/" . Str::studly($pluginKebab) . "/resources/routes/index.ts"
            : "resource/routes/{$parent}-{$kebabName}.ts";
    }

    protected function formatDate(): string
    {
        return date('Y-m-d H:i:s');
    }

    protected function doGenerate(array $config): array
    {
        $preview = $this->generatePreview($config);
        $basePath = base_path();
        $writtenFiles = [];
        $isPlugin = $config['type'] === 'plugin';
        $force = $config['force'] ?? false;
        $pluginInfo = null;
        if ($isPlugin && !empty($config['plugin'])) {
            $pluginInfo = $this->pluginBaseInfo($config['plugin']);
        }
        $allowOverwritePaths = [];
        if ($pluginInfo) {
            $allowOverwritePaths = [
                $pluginInfo['json_path'],
                $pluginInfo['dir'] . '/Admin/routes.php',
                $pluginInfo['dir'] . '/Http/routes.php',
                $pluginInfo['dir'] . '/resources/routes/index.ts',
            ];
        }

        $resourceStudly = Str::studly($config['name'] ?? '');
        $resourceKebab = Str::kebab($config['name'] ?? '');
        $pluginSnake = $pluginInfo['snake'] ?? null;
        $pluginStudly = $pluginInfo['studly'] ?? null;

        foreach ($preview['preview'] as $fileInfo) {
            $path = $fileInfo['path'];
            $content = $fileInfo['content'];
            $fullPath = $basePath . '/' . $path;

            // 确保目录存在
            $dir = dirname($fullPath);
            if (!is_dir($dir)) {
                $ok = @mkdir($dir, 0755, true);
                if (!$ok && !is_dir($dir)) {
                    throw new \Exception("无法创建目录 {$dir}");
                }
            }

            // 检查文件是否已存在
            if (file_exists($fullPath) && !$force) {
                if ($pluginInfo) {
                    if (in_array($fullPath, $allowOverwritePaths, true)) {
                        // allow overwrite (merge content)
                    } else {
                        throw new \Exception("文件 {$path} 已存在，请使用 force 参数覆盖");
                    }
                } else {
                throw new \Exception("文件 {$path} 已存在，请使用 force 参数覆盖");
                }
            }

            if ($pluginInfo && in_array($fullPath, $allowOverwritePaths, true)) {
                $fp = fopen($fullPath, 'c+');
                if ($fp === false) {
                    throw new \Exception("无法打开文件 {$path}");
                }
                if (!flock($fp, LOCK_EX)) {
                    fclose($fp);
                    throw new \Exception("无法锁定文件 {$path}");
                }
                rewind($fp);
                $existing = stream_get_contents($fp);
                if ($existing === false) {
                    $existing = '';
                }

                if ($pluginSnake && $pluginStudly) {
                    if ($fullPath === $pluginInfo['json_path']) {
                        $content = $this->mergePluginJsonForResource(
                            $config['plugin'],
                            $config['plugin_title'] ?? null,
                            $resourceStudly,
                            $resourceKebab,
                            $config['icon'] ?? 'icon-apps',
                            $existing
                        );
                    } elseif ($fullPath === $pluginInfo['dir'] . '/Admin/routes.php') {
                        $content = $this->mergePluginAdminRoutesForResource(
                            $config['plugin'],
                            $resourceStudly,
                            $resourceKebab,
                            $existing
                        );
                    } elseif ($fullPath === $pluginInfo['dir'] . '/Http/routes.php') {
                        $content = $this->mergePluginHttpRoutesForResource(
                            $config['plugin'],
                            $resourceStudly,
                            $resourceKebab,
                            $existing
                        );
                    } elseif ($fullPath === $pluginInfo['dir'] . '/resources/routes/index.ts') {
                        $content = $this->generatePluginRoutesIndex($pluginStudly, $pluginSnake, $resourceKebab);
                    }
                }

                ftruncate($fp, 0);
                rewind($fp);
                $result = fwrite($fp, $content);
                fflush($fp);
                flock($fp, LOCK_UN);
                fclose($fp);

                if ($result === false) {
                    throw new \Exception("无法写入文件 {$path}");
                }
            } else {
                $result = file_put_contents($fullPath, $content);
                if ($result === false) {
                    throw new \Exception("无法写入文件 {$path}");
                }
            }

            $writtenFiles[] = $path;
        }

        // 插件模式：执行 composer dump-autoload 确保新类能被自动加载
        if ($isPlugin) {
            $composer = base_path('vendor/bin/composer') ?: 'composer';
            exec("{$composer} dump-autoload -q 2>&1");
        }

        return [
            'files' => $writtenFiles,
            'message' => '代码生成成功',
        ];
    }

    /**
     * 删除插件生成的代码
     */
    protected function doDelete(array $config): array
    {
        $plugin = $config['plugin'];
        $pluginStudly = Str::studly($plugin);
        $name = Str::studly($config['name']);
        $kebabName = Str::kebab($config['name']);
        $basePath = base_path();
        $deletedFiles = [];

        // 安全检查：如果插件已安装，阻止删除
        if (class_exists('\App\Admin\Models\Plugin')) {
            $existing = \App\Admin\Models\Plugin::where('name', $pluginStudly)->first();
            if ($existing) {
                throw new \Exception('该插件已安装（名称：' . $existing->title . '），请先在插件管理中卸载后再删除代码');
            }
        }

        $pluginDir = $basePath . '/plugins/' . $pluginStudly;

        // 1. 删除 Controller 文件
        $controllerPath = $pluginDir . '/Admin/Controllers/' . $name . 'Controller.php';
        if (file_exists($controllerPath)) {
            unlink($controllerPath);
            $deletedFiles[] = 'plugins/' . $pluginStudly . '/Admin/Controllers/' . $name . 'Controller.php';
        }

        // 2. 删除 Model 文件
        $modelPath = $pluginDir . '/Models/' . $name . '.php';
        if (file_exists($modelPath)) {
            unlink($modelPath);
            $deletedFiles[] = 'plugins/' . $pluginStudly . '/Models/' . $name . '.php';
        }

        // 3. 删除 Migration 文件（按表名匹配）
        $migrationDir = $pluginDir . '/database/migrations';
        if (is_dir($migrationDir)) {
            $snakeName = Str::snake($name);
            foreach (scandir($migrationDir) as $file) {
                if ($file === '.' || $file === '..') continue;
                if (stripos($file, 'create') !== false && stripos($file, $snakeName) !== false) {
                    unlink($migrationDir . '/' . $file);
                    $deletedFiles[] = 'plugins/' . $pluginStudly . '/database/migrations/' . $file;
                }
            }
        }

        // 4. 删除 Vue 视图文件（插件资源目录）
        $businessVuePath = $pluginDir . '/resources/views/' . $kebabName . '/index.vue';
        if (file_exists($businessVuePath)) {
            unlink($businessVuePath);
            $deletedFiles[] = 'plugins/' . $pluginStudly . '/resources/views/' . $kebabName . '/index.vue';
        }

        $pluginIndexVuePath = $pluginDir . '/resources/views/index.vue';
        if (file_exists($pluginIndexVuePath)) {
            unlink($pluginIndexVuePath);
            $deletedFiles[] = 'plugins/' . $pluginStudly . '/resources/views/index.vue';
        }

        // 删除插件路由文件
        $pluginRoutePath = $pluginDir . '/resources/routes/' . $kebabName . '.ts';
        if (file_exists($pluginRoutePath)) {
            unlink($pluginRoutePath);
            $deletedFiles[] = 'plugins/' . $pluginStudly . '/resources/routes/' . $kebabName . '.ts';
        }

        // 5. 删除 Admin/routes.php 中该资源的路由，如果无其他路由则删除整个文件
        $adminRoutesPath = $pluginDir . '/Admin/routes.php';
        if (file_exists($adminRoutesPath)) {
            $content = file_get_contents($adminRoutesPath);
            if ($content !== false) {
                $escapedName = preg_quote($kebabName, '#');
                $pattern = '#.*[\'"].*[/]' . $escapedName . '[\'"].*[\n\r]*#';
                $newContent = preg_replace($pattern, '', $content);
                $hasRoutes = preg_match("/Route::(get|post|put|delete|patch|apiResource)/", $newContent);
                if (!$hasRoutes) {
                    unlink($adminRoutesPath);
                    $deletedFiles[] = 'plugins/' . $pluginStudly . '/Admin/routes.php';
                } else {
                    file_put_contents($adminRoutesPath, $newContent);
                    $deletedFiles[] = 'plugins/' . $pluginStudly . '/Admin/routes.php';
                }
            }
        }

        // 6. 删除 Http 目录下的业务路由和控制器
        $httpRoutesPath = $pluginDir . '/Http/routes.php';
        if (file_exists($httpRoutesPath)) {
            unlink($httpRoutesPath);
            $deletedFiles[] = 'plugins/' . $pluginStudly . '/Http/routes.php';
        }

        $httpControllerPath = $pluginDir . '/Http/Controllers/' . $name . 'Controller.php';
        if (file_exists($httpControllerPath)) {
            unlink($httpControllerPath);
            $deletedFiles[] = 'plugins/' . $pluginStudly . '/Http/Controllers/' . $name . 'Controller.php';
        }

        // 7. 从 plugin.json 中移除对应的菜单和权限
        $pluginJsonPath = $pluginDir . '/plugin.json';
        if (file_exists($pluginJsonPath)) {
            $jsonContent = file_get_contents($pluginJsonPath);
            if ($jsonContent !== false) {
                $json = json_decode($jsonContent, true);
                if ($json) {
                    if (!empty($json['menus'])) {
                        $json['menus'] = array_values(array_filter($json['menus'], function ($menu) use ($kebabName) {
                            if (is_array($menu)) {
                                return empty($menu['uri']) || !str_ends_with($menu['uri'], '/' . $kebabName);
                            }
                            return true;
                        }));
                    }
                    if (!empty($json['permissions'])) {
                        $json['permissions'] = array_values(array_filter($json['permissions'], function ($perm) use ($kebabName) {
                            if (is_array($perm)) {
                                return empty($perm['slug']) || !str_ends_with($perm['slug'], '.' . $kebabName);
                            }
                            return !str_ends_with($perm, '.' . $kebabName);
                        }));
                    }
                    if (!empty($json['admin_controllers'])) {
                        $json['admin_controllers'] = array_values(array_filter($json['admin_controllers'], function ($ctrl) use ($name) {
                            if (is_string($ctrl)) {
                                return !str_ends_with($ctrl, $name . 'Controller');
                            }
                            return true;
                        }));
                    }
                    file_put_contents($pluginJsonPath, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    $deletedFiles[] = 'plugins/' . $pluginStudly . '/plugin.json';
                }
            }
        }

        // 8. 递归清理空目录
        $this->removeEmptyDirectoryRecursive($pluginDir);

        // 9. 执行 composer dump-autoload 清理自动加载
        $composer = base_path('vendor/bin/composer') ?: 'composer';
        exec("{$composer} dump-autoload -q 2>&1");

        return [
            'files' => $deletedFiles,
            'message' => '代码已删除',
        ];
    }

    protected function mergePluginJsonForResource(
        string $plugin,
        ?string $pluginTitle,
        string $name,
        string $kebabName,
        string $icon,
        ?string $existingRaw = null
    ): string
    {
        $info = $this->pluginBaseInfo($plugin);
        $fallbackBaseJson = function () use ($info, $pluginTitle, $icon): array {
            return [
                'name' => $info['snake'],
                'title' => $pluginTitle ?? $info['studly'],
                'description' => '',
                'version' => '1.0.0',
                'author' => 'DbsAdmin Generator',
                'enabled' => true,
                'icon' => $icon ?: 'icon-apps',
                'type' => 'local',
                'show_api' => true,
                'requires' => [],
                'providers' => [
                    "Plugins\\{$info['studly']}\\Providers\\PluginServiceProvider",
                ],
                'admin_controllers' => [],
                'menus' => [],
                'permissions' => [],
            ];
        };

        if (is_string($existingRaw) && trim($existingRaw) !== '') {
            $json = json_decode($existingRaw, true);
            if (is_array($json)) {
                return json_encode(
                    $this->mergePluginJsonArray($info, $json, $pluginTitle, $name, $kebabName, $icon),
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                );
            }
            return json_encode(
                $this->mergePluginJsonArray($info, $fallbackBaseJson(), $pluginTitle, $name, $kebabName, $icon),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            );
        }

        if (!file_exists($info['json_path'])) {
            if (class_exists('\App\Admin\Models\Plugin')) {
                try {
                    $record = \App\Admin\Models\Plugin::where('name', $info['snake'])
                        ->orWhere('name', $info['studly'])
                        ->first();
                    if ($record) {
                        $base = $record->toArray();
                        $json = [
                            'name' => $base['name'] ?? $info['snake'],
                            'title' => $base['title'] ?? ($pluginTitle ?? $info['studly']),
                            'description' => $base['description'] ?? '',
                            'version' => $base['version'] ?? '1.0.0',
                            'author' => $base['author'] ?? '',
                            'enabled' => (bool) ($base['enabled'] ?? true),
                            'icon' => $base['icon'] ?? 'icon-apps',
                            'type' => $base['type'] ?? 'local',
                            'show_api' => (bool) ($base['show_api'] ?? true),
                            'requires' => [],
                            'providers' => $base['providers'] ?? [
                                "Plugins\\{$info['studly']}\\Providers\\PluginServiceProvider",
                            ],
                            'admin_controllers' => [],
                            'menus' => $base['menus'] ?? [],
                            'permissions' => $base['permissions'] ?? [],
                        ];
                        return $this->mergePluginJsonArray($info, $json, $pluginTitle, $name, $kebabName, $icon);
                    }
                } catch (\Throwable) {
                    // ignore
                }
            }

            return $this->generatePluginJson($plugin, $pluginTitle ?? '', $name, $kebabName, $icon);
        }

        $raw = file_get_contents($info['json_path']);
        if ($raw === false) {
            throw new \Exception('无法读取 plugin.json');
        }
        if (trim($raw) === '') {
            return json_encode(
                $this->mergePluginJsonArray($info, $fallbackBaseJson(), $pluginTitle, $name, $kebabName, $icon),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            );
        }

        $json = json_decode($raw, true);
        if (!is_array($json)) {
            return json_encode(
                $this->mergePluginJsonArray($info, $fallbackBaseJson(), $pluginTitle, $name, $kebabName, $icon),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            );
        }

        return json_encode(
            $this->mergePluginJsonArray($info, $json, $pluginTitle, $name, $kebabName, $icon),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        );
    }

    protected function mergePluginJsonArray(
        array $info,
        array $json,
        ?string $pluginTitle,
        string $name,
        string $kebabName,
        string $icon
    ): array {
        $currentName = $json['name'] ?? $info['snake'];
        $pluginSnake = Str::snake($currentName);
        $pluginStudly = Str::studly($pluginSnake);

        $json['name'] = $pluginSnake;

        if (!isset($json['admin_controllers']) || !is_array($json['admin_controllers'])) {
            $json['admin_controllers'] = [];
        }
        if (!isset($json['menus']) || !is_array($json['menus'])) {
            $json['menus'] = [];
        }
        if (!isset($json['permissions']) || !is_array($json['permissions'])) {
            $json['permissions'] = [];
        }
        if (!isset($json['providers']) || !is_array($json['providers']) || empty($json['providers'])) {
            $json['providers'] = [
                "Plugins\\{$pluginStudly}\\Providers\\PluginServiceProvider",
            ];
        }

        $controllerClass = "Plugins\\{$pluginStudly}\\Admin\\Controllers\\{$name}Controller";
        if (!in_array($controllerClass, $json['admin_controllers'], true)) {
            $json['admin_controllers'][] = $controllerClass;
        }

        $this->syncPluginJsonFromFilesystem($info, $json, $icon);

        $menuUri = "admin/{$kebabName}";
        $menuExists = false;
        foreach ($json['menus'] as $m) {
            if (is_array($m) && ($m['uri'] ?? null) === $menuUri) {
                $menuExists = true;
                break;
            }
        }
        if (!$menuExists) {
            $json['menus'][] = [
                'title' => "{$name}管理",
                'icon' => $icon,
                'uri' => $menuUri,
                'component' => "{$pluginSnake}/{$kebabName}",
            ];
        }

        $permissionSlug = "{$pluginSnake}.{$kebabName}";
        $permExists = false;
        foreach ($json['permissions'] as $p) {
            if (is_array($p) && ($p['slug'] ?? null) === $permissionSlug) {
                $permExists = true;
                break;
            }
        }
        if (!$permExists) {
            $json['permissions'][] = [
                'slug' => $permissionSlug,
                'name' => "{$name}管理",
                'http_method' => [],
                'http_path' => "/plugin/{$pluginSnake}/admin/{$kebabName}/*",
            ];
        }

        if (!empty($pluginTitle) && empty($json['title'])) {
            $json['title'] = $pluginTitle;
        }

        return $json;
    }

    protected function syncPluginJsonFromFilesystem(array $info, array &$json, string $defaultIcon): void
    {
        $controllersDir = $info['dir'] . '/Admin/Controllers';
        if (!is_dir($controllersDir)) return;

        $pluginSnake = Str::snake((string) ($json['name'] ?? $info['snake']));
        $pluginStudly = Str::studly($pluginSnake);

        foreach (glob($controllersDir . '/*Controller.php') ?: [] as $file) {
            $base = basename($file, '.php');
            if (!str_ends_with($base, 'Controller')) continue;
            $resource = substr($base, 0, -10);
            if ($resource === '') continue;

            $controllerClass = "Plugins\\{$pluginStudly}\\Admin\\Controllers\\{$resource}Controller";
            if (!in_array($controllerClass, $json['admin_controllers'], true)) {
                $json['admin_controllers'][] = $controllerClass;
            }

            $kebab = Str::kebab($resource);
            $menuUri = "admin/{$kebab}";
            $menuExists = false;
            foreach ($json['menus'] as $m) {
                if (is_array($m) && ($m['uri'] ?? null) === $menuUri) {
                    $menuExists = true;
                    break;
                }
            }
            if (!$menuExists) {
                $json['menus'][] = [
                    'title' => "{$resource}管理",
                    'icon' => $defaultIcon,
                    'uri' => $menuUri,
                    'component' => "{$pluginSnake}/{$kebab}",
                ];
            }

            $slug = "{$pluginSnake}.{$kebab}";
            $permExists = false;
            foreach ($json['permissions'] as $p) {
                if (is_array($p) && ($p['slug'] ?? null) === $slug) {
                    $permExists = true;
                    break;
                }
            }
            if (!$permExists) {
                $json['permissions'][] = [
                    'slug' => $slug,
                    'name' => "{$resource}管理",
                    'http_method' => [],
                    'http_path' => "/plugin/{$pluginSnake}/admin/{$kebab}/*",
                ];
            }
        }
    }

    protected function mergePluginAdminRoutesForResource(
        string $plugin,
        string $name,
        string $kebabName,
        ?string $existingContent = null,
        bool $syncFromFilesystem = true
    ): string
    {
        $info = $this->pluginBaseInfo($plugin);
        $path = $info['dir'] . '/Admin/routes.php';
        if ($existingContent === null && !file_exists($path)) {
            return $this->generatePluginAdminRoutes($plugin, $name, $kebabName);
        }

        $content = $existingContent ?? file_get_contents($path);
        if ($content === false) {
            return $this->generatePluginAdminRoutes($plugin, $name, $kebabName);
        }
        if (trim($content) === '') {
            $content = $this->generatePluginAdminRoutes($plugin, $name, $kebabName);
            $syncFromFilesystem = true;
        }

        $pluginStudly = $info['studly'];
        $controllerNamespace = "Plugins\\{$pluginStudly}\\Admin\\Controllers\\{$name}Controller";
        $useLine = "use {$controllerNamespace};";
        if (strpos($content, $useLine) === false) {
            if (preg_match_all('/^use\s+[^;]+;\s*$/m', $content, $m, PREG_OFFSET_CAPTURE) && !empty($m[0])) {
                $last = end($m[0]);
                $insertPos = $last[1] + strlen($last[0]);
                $content = substr($content, 0, $insertPos) . "\n" . $useLine . substr($content, $insertPos);
            } else {
                $content = preg_replace('/^<\?php\s*/', "<?php\n\n{$useLine}\n", $content) ?? $content;
            }
        }

        $routeLines = [
            "        Route::get('{$kebabName}/form-schema', [{$name}Controller::class, 'formSchema']);",
            "        Route::get('{$kebabName}/grid-meta', [{$name}Controller::class, 'gridMeta']);",
            "        Route::post('{$kebabName}/batch-update', [{$name}Controller::class, 'batchUpdate']);",
            "        Route::post('{$kebabName}/batch-destroy', [{$name}Controller::class, 'batchDestroy']);",
            "        Route::post('{$kebabName}/{id}/toggle', [{$name}Controller::class, 'toggle']);",
            "        Route::post('{$kebabName}/{id}/replicate', [{$name}Controller::class, 'replicate']);",
            "        Route::post('{$kebabName}/{id}/restore', [{$name}Controller::class, 'restore']);",
            "        Route::apiResource('{$kebabName}', {$name}Controller::class);",
        ];

        $apiResourceLine = "        Route::apiResource('{$kebabName}', {$name}Controller::class);";
        $anchorPos = strpos($content, $apiResourceLine);
        if ($anchorPos === false) {
            $anchorPos = strrpos($content, '    });');
        }

        foreach ($routeLines as $line) {
            if (strpos($content, $line) !== false) {
                continue;
            }

            if ($anchorPos !== false) {
                $content = substr($content, 0, $anchorPos) . $line . "\n" . substr($content, $anchorPos);
                $anchorPos += strlen($line) + 1;
            } else {
                $content .= "\n{$line}\n";
            }
        }

        if ($syncFromFilesystem) {
            $controllersDir = $info['dir'] . '/Admin/Controllers';
            foreach (glob($controllersDir . '/*Controller.php') ?: [] as $file) {
                $base = basename($file, 'Controller.php');
                if ($base === '' || $base === $name) continue;
                $kebab = Str::kebab($base);
                $apiResourceLine2 = "        Route::apiResource('{$kebab}', {$base}Controller::class);";
                if (strpos($content, $apiResourceLine2) !== false) continue;
                $content = $this->mergePluginAdminRoutesForResource($plugin, $base, $kebab, $content, false);
            }
        }

        return $content;
    }

    protected function mergePluginHttpRoutesForResource(
        string $plugin,
        string $name,
        string $kebabName,
        ?string $existingContent = null,
        bool $syncFromFilesystem = true
    ): string
    {
        $info = $this->pluginBaseInfo($plugin);
        $path = $info['dir'] . '/Http/routes.php';
        if ($existingContent === null && !file_exists($path)) {
            return $this->generatePluginHttpRoutes($plugin, $name, $kebabName);
        }

        $content = $existingContent ?? file_get_contents($path);
        if ($content === false) {
            return $this->generatePluginHttpRoutes($plugin, $name, $kebabName);
        }
        if (trim($content) === '') {
            $content = $this->generatePluginHttpRoutes($plugin, $name, $kebabName);
            $syncFromFilesystem = true;
        }

        $pluginStudly = $info['studly'];
        $controllerNamespace = "Plugins\\{$pluginStudly}\\Http\\Controllers\\{$name}Controller";
        $useLine = "use {$controllerNamespace};";
        if (strpos($content, $useLine) === false) {
            if (preg_match_all('/^use\s+[^;]+;\s*$/m', $content, $m, PREG_OFFSET_CAPTURE) && !empty($m[0])) {
                $last = end($m[0]);
                $insertPos = $last[1] + strlen($last[0]);
                $content = substr($content, 0, $insertPos) . "\n" . $useLine . substr($content, $insertPos);
            } else {
                $content = preg_replace('/^<\?php\s*/', "<?php\n\n{$useLine}\n", $content) ?? $content;
            }
        }

        $routeLine = "            Route::apiResource('{$kebabName}', {$name}Controller::class);";
        if (strpos($content, $routeLine) === false) {
            $anchor = "Route::middleware('throttle:60,1')->group(function () {";
            $start = strpos($content, $anchor);
            if ($start !== false) {
                $end = strpos($content, "\n        });", $start);
                if ($end !== false) {
                    $content = substr($content, 0, $end) . "\n" . $routeLine . substr($content, $end);
                } else {
                    $content .= "\n{$routeLine}\n";
                }
            } else {
                $pos = strrpos($content, '    });');
                if ($pos !== false) {
                    $content = substr($content, 0, $pos) . $routeLine . "\n" . substr($content, $pos);
                } else {
                    $content .= "\n{$routeLine}\n";
                }
            }
        }

        if ($syncFromFilesystem) {
            $controllersDir = $info['dir'] . '/Http/Controllers';
            foreach (glob($controllersDir . '/*Controller.php') ?: [] as $file) {
                $base = basename($file, 'Controller.php');
                if ($base === '' || $base === $name) continue;
                $kebab = Str::kebab($base);
                $routeLine2 = "            Route::apiResource('{$kebab}', {$base}Controller::class);";
                if (strpos($content, $routeLine2) !== false) continue;
                $content = $this->mergePluginHttpRoutesForResource($plugin, $base, $kebab, $content, false);
            }
        }

        return $content;
    }

    /**
     * 递归删除空目录
     */
    protected function removeEmptyDirectoryRecursive(string $dir): void
    {
        if (!is_dir($dir)) return;

        // 先递归删除子目录中的空目录
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $fullPath = $dir . '/' . $file;
            if (is_dir($fullPath)) {
                $this->removeEmptyDirectoryRecursive($fullPath);
            }
        }

        // 再次检查是否为空目录
        $remainingFiles = array_diff(scandir($dir), ['.', '..']);
        if (empty($remainingFiles)) {
            rmdir($dir);
        }
    }

    /**
     * 删除空目录
     */
    protected function removeEmptyDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), ['.', '..']);
        if (empty($files)) {
            rmdir($dir);
        }
    }
}
