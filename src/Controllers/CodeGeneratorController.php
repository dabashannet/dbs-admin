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
                    'core' => '核心模块（app/Admin + web/src/views/system）',
                    'plugin' => '插件模块（plugins/{Name} + web/src/views/plugin）',
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
            'indexes' => 'nullable|array',
            'icon' => 'nullable|string',
            'order' => 'nullable|integer',
        ]);

        $preview = $this->generatePreview($validated);

        return $this->success($preview);
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
        $pluginKebab = $plugin ? Str::kebab($plugin) : null;
        $parent = $validated['parent'] ?? 'system';
        $tables = $validated['tables'] ?? [];
        $table = $tables[0] ?? ($type === 'plugin'
            ? "p_{$pluginKebab}_" . Str::snake(Str::plural($name))
            : "admin_" . Str::snake(Str::plural($name)));

        $files = $this->getGeneratedFiles($name, $kebabName, $parent, $type, $plugin, $table, $pluginKebab);

        // 插件模式：追加 Http 路由和控制器文件
        if ($type === 'plugin' && $plugin) {
            $pluginStudly = Str::studly($plugin);
            $files[] = "plugins/{$pluginStudly}/Http/routes.php";
            $files[] = "plugins/{$pluginStudly}/Http/Controllers/{$name}Controller.php";
        }

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
        $pluginKebab = $plugin ? Str::kebab($plugin) : null;
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

        // 判断是已有插件还是新插件
        $isNewPlugin = false;
        if ($config['type'] === 'plugin' && $plugin) {
            $existingPlugins = $this->getExistingPluginNames();
            $isNewPlugin = !in_array($pluginKebab, $existingPlugins, true);
        }

        // 生成 Controller 代码
        $controllerCode = $this->generateControllerCode($name, $controllerName, $modelName, $config['type'], $fields, $gridColumns, $config['filters'] ?? [], $plugin);

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
                $pluginStudly = Str::studly($plugin);
                $pluginJson = $this->generatePluginJson($plugin, $pluginTitle, $name, $kebabName, $icon);
                $serviceProvider = $this->generatePluginServiceProvider($plugin, $name);
                $pluginIndexVue = $this->generatePluginIndexVue($pluginTitle ?: $pluginStudly);

                $pluginFiles['plugin_json'] = [
                    'path' => "plugins/{$pluginStudly}/plugin.json",
                    'content' => $pluginJson,
                ];
                $pluginFiles['service_provider'] = [
                    'path' => "plugins/{$pluginStudly}/PluginServiceProvider.php",
                    'content' => $serviceProvider,
                ];
                $pluginFiles['plugin_index_vue'] = [
                    'path' => "web/src/views/plugin/{$pluginKebab}/index.vue",
                    'content' => $pluginIndexVue,
                ];

                $files[] = "plugins/{$pluginStudly}/plugin.json";
                $files[] = "plugins/{$pluginStudly}/PluginServiceProvider.php";
                $files[] = "web/src/views/plugin/{$pluginKebab}/index.vue";
            }

            // 无论新旧都生成 Admin 路由文件和业务端页面
            $adminRoutes = $this->generatePluginAdminRoutes($plugin, $name, $kebabName);
            $businessVue = $this->generateBusinessVueCode($pluginKebab, $kebabName, $name);

            // 生成 Http 目录下的业务路由和控制器
            $httpRoutes = $this->generatePluginHttpRoutes($plugin, $name, $kebabName);
            $httpController = $this->generatePluginHttpController($plugin, $name);

            $pluginFiles['admin_routes'] = [
                'path' => "plugins/{$pluginStudly}/Admin/routes.php",
                'content' => $adminRoutes,
            ];
            $pluginFiles['business_vue'] = [
                'path' => "web/src/views/plugin/{$pluginKebab}/{$kebabName}.vue",
                'content' => $businessVue,
            ];
            $pluginFiles['http_routes'] = [
                'path' => "plugins/{$pluginStudly}/Http/routes.php",
                'content' => $httpRoutes,
            ];
            $pluginFiles['http_controller'] = [
                'path' => "plugins/{$pluginStudly}/Http/Controllers/{$name}Controller.php",
                'content' => $httpController,
            ];

            $files[] = "plugins/{$pluginStudly}/Admin/routes.php";
            $files[] = "web/src/views/plugin/{$pluginKebab}/{$kebabName}.vue";
            $files[] = "plugins/{$pluginStudly}/Http/routes.php";
            $files[] = "plugins/{$pluginStudly}/Http/Controllers/{$name}Controller.php";
        }

        // 迁移路径：插件放 plugin 自己的 database/migrations，核心放全局 database/migrations
        $timestamp = date('Y_m_d_His');
        $pluginStudly = $plugin ? Str::studly($plugin) : null;
        $migrationPath = $config['type'] === 'plugin'
            ? "plugins/{$pluginStudly}/database/migrations/{$timestamp}_create_{$tableName}_table.php"
            : "database/migrations/{$timestamp}_create_{$tableName}_table.php";

        return [
            'files' => $files,
            'preview' => array_merge([
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
                'router' => [
                    'path' => $this->getRouterPath($parent, $kebabName, $config['type'], $pluginKebab),
                    'content' => $routerCode,
                ],
            ], $pluginFiles),
        ];
    }

    protected function generateControllerCode(string $name, string $controllerName, string $modelName, string $type, array $fields, array $gridColumns, array $filters = [], ?string $plugin = null): string
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

        $date = $this->formatDate();

        return <<<PHP
<?php

/**
 * {$controllerName} 控制器
 *
 * @Author: Author dabashan.cc
 * @Date: {$date}
 * @LastEditTime: {$date}
 * @Copyright: Copyright (c) 2026 by Dabashan.cc, All Rights Reserved.
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
            return "->column('id', 'ID')->sortable()\n            ->column('created_at', '创建时间')->sortable()";
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
            if (!empty($col['default'])) {
                $line .= "->default('{$col['default']}')";
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
            'image' => '->image(' . ($options['width'] ?? 40) . ', ' . ($options['height'] ?? 40) . (isset($options['circle']) && $options['circle'] ? ', true' : '') . ')',

            // 标签组：tags('分隔符')
            'tags' => !empty($options['separator']) ? "->tags('{$options['separator']}')" : '->tags()',

            // 进度条：progress(最大值, 显示文字)
            'progress' => '->progress(' . ($options['max'] ?? 100) . ', ' . (isset($options['showText']) && !$options['showText'] ? 'false' : 'true') . ')',

            // 数值条：bar()
            'bar' => '->bar()',

            // 色块：color()
            'color' => '->color()',

            // 可复制：copyable()
            'copyable' => '->copyable()',

            // 圆点状态：dot()
            'dot' => '->dot()',

            // 日期：date('格式')
            'date' => !empty($options['format']) ? "->date('{$options['format']}')" : '->date()',

            // 日期时间：datetime('格式')
            'datetime' => !empty($options['format']) ? "->datetime('{$options['format']}')" : '->datetime()',

            // 金额：money('符号', 小数位)
            'money' => '->money(' . (!empty($options['symbol']) ? "'{$options['symbol']}', " : '') . ($options['decimals'] ?? 2) . ')',

            // 计数：count()
            'count' => '->count()',

            default => '',
        };
    }

    protected function formatGridFilters(array $filters, array $fields): string
    {
        if (empty($filters)) {
            return '// 无筛选器';
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
 * @Author: Author dabashan.cc
 * @Date: {$date}
 * @LastEditTime: {$date}
 * @Copyright: Copyright (c) 2026 by Dabashan.cc, All Rights Reserved.
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
        // 插件模式下使用 Arco Design 组件，生成到插件视图目录
        if ($type === 'plugin' && $pluginKebab) {
            $stubPath = __DIR__ . '/../../stubs/vue-plugin.stub';
            $template = file_get_contents($stubPath);
            if ($template === false) {
                throw new \Exception('无法读取 vue-plugin.stub 模板文件');
            }

            $now = date('Y-m-d H:i:s');
            $itemType = $name . 'Item';

            $replacements = [
                '{{now}}' => $now,
                '{{name}}' => $name,
                '{{itemType}}' => $itemType,
                '{{pluginKebab}}' => $pluginKebab,
                '{{parent}}' => $parent,
                '{{kebabName}}' => $kebabName,
            ];

            return str_replace(array_keys($replacements), array_values($replacements), $template);
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
        $pluginStudly = Str::studly($plugin);
        $pluginKebab = Str::kebab($plugin);
        $title = $pluginTitle ?: "{$pluginStudly} 插件";
        $json = [
            'name' => $pluginStudly,
            'title' => $title,
            'description' => "{$name} 管理插件",
            'version' => '1.0.0',
            'author' => 'Code Generator',
            'enabled' => true,
            'icon' => $icon,
            'type' => 'local',
            'show_api' => true,
            'requires' => [],
            'providers' => [
                "Plugins\\{$pluginStudly}\\PluginServiceProvider",
            ],
            'admin_controllers' => [
                "Plugins\\{$pluginStudly}\\Admin\\Controllers\\{$name}Controller",
            ],
            'menus' => [
                [
                    'title' => "{$name}管理",
                    'icon' => $icon,
                    'uri' => "admin/{$kebabName}",
                ],
            ],
            'permissions' => [
                [
                    'slug' => "{$pluginKebab}.{$kebabName}",
                    'name' => "{$name}管理",
                    'http_method' => [],
                    'http_path' => "/plugin/{$pluginKebab}/admin/{$kebabName}/*",
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
        $pluginStudly = Str::studly($plugin);
        $date = $this->formatDate();

        return <<<PHP
<?php

/**
 * 插件服务提供者
 * @Date: {$date}
 * @LastEditTime: {$date}
 * @Source: Dbs-Admin 代码生成器快速生成
 * @Wiki: 更多问题请查看 wiki.dabashan.cc
 */

namespace Plugins\\{$pluginStudly};

use Illuminate\\Support\\ServiceProvider;

class PluginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // 加载数据库迁移
        \$migrationsPath = __DIR__ . '/database/migrations';
        if (is_dir(\$migrationsPath)) {
            \$this->loadMigrationsFrom(\$migrationsPath);
        }

        // 加载后台路由
        \$adminRoutes = __DIR__ . '/Admin/routes.php';
        if (file_exists(\$adminRoutes)) {
            \$this->loadRoutesFrom(\$adminRoutes);
        }

        // 加载业务路由
        \$httpRoutes = __DIR__ . '/Http/routes.php';
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
        $pluginStudly = Str::studly($plugin);
        $pluginKebab = Str::kebab($plugin);
        $controllerNamespace = "Plugins\\{$pluginStudly}\\Admin\\Controllers\\{$name}Controller";
        $date = $this->formatDate();

        return <<<PHP
<?php

/**
 * 插件后台路由配置
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
| 前缀: /plugin/{$pluginKebab}/admin
| 中间件: api, auth:admin
|
*/

Route::prefix('plugin/{$pluginKebab}/admin')
    ->middleware(['api', 'auth:admin'])
    ->group(function () {
        Route::apiResource('{$kebabName}', {$name}Controller::class);
    });
PHP;
    }

    /**
     * 生成插件业务端路由文件（Http 目录）
     */
    protected function generatePluginHttpRoutes(string $plugin, string $name, string $kebabName): string
    {
        $pluginStudly = Str::studly($plugin);
        $pluginKebab = Str::kebab($plugin);
        $controllerNamespace = "Plugins\\{$pluginStudly}\\Http\\Controllers\\{$name}Controller";
        $date = $this->formatDate();

        return <<<PHP
<?php

/**
 * 插件业务端路由配置
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
| 前缀: /plugin/{$pluginKebab}/api
| 中间件: api
| 业务端独立，无强制约束
|
*/

Route::prefix('plugin/{$pluginKebab}/api')
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
        $pluginKebab = Str::kebab($plugin);
        $date = $this->formatDate();
        return <<<VUE
<!--
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
    padding: 0 20px 20px 20px;
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

    protected function getGeneratedFiles(string $name, string $kebabName, string $parent, string $type, ?string $plugin, string $table, ?string $pluginKebab = null): array
    {
        $files = [];
        $files[] = $this->getControllerPath($name, $type, $plugin);
        $files[] = $this->getModelPath($name, $type, $plugin);
        $timestamp = date('Y_m_d_His');
        $pluginStudly = $plugin ? Str::studly($plugin) : null;
        $migrationPath = $type === 'plugin'
            ? "plugins/{$pluginStudly}/database/migrations/{$timestamp}_create_{$table}_table.php"
            : "database/migrations/{$timestamp}_create_{$table}_table.php";
        $files[] = $migrationPath;
        $files[] = $this->getVuePath($parent, $kebabName, $type, $plugin);
        $files[] = $this->getRouterPath($parent, $kebabName, $type, $pluginKebab);
        if ($type === 'core') {
            $files[] = "web/src/views/{$parent}/{$kebabName}/locale/zh-CN.ts";
            $files[] = "web/src/views/{$parent}/{$kebabName}/locale/en-US.ts";
        }
        return $files;
    }

    protected function getControllerPath(string $name, string $type, ?string $plugin): string
    {
        return $type === 'plugin'
            ? "plugins/" . Str::studly($plugin) . "/Admin/Controllers/{$name}Controller.php"
            : "app/Admin/Controllers/{$name}Controller.php";
    }

    protected function getModelPath(string $name, string $type, ?string $plugin): string
    {
        $modelName = $type === 'plugin' ? $name : "Admin{$name}";
        return $type === 'plugin'
            ? "plugins/" . Str::studly($plugin) . "/Models/{$modelName}.php"
            : "app/Admin/Models/{$modelName}.php";
    }

    protected function getVuePath(string $parent, string $kebabName, string $type, ?string $plugin): string
    {
        return $type === 'plugin'
            ? "web/src/views/plugin/" . Str::kebab($plugin) . "/{$kebabName}/index.vue"
            : "web/src/views/{$parent}/{$kebabName}/index.vue";
    }

    protected function getRouterPath(string $parent, string $kebabName, string $type, ?string $pluginKebab = null): string
    {
        return $type === 'plugin'
            ? "web/src/router/routes/modules/plugin-{$pluginKebab}.ts"
            : "web/src/router/routes/modules/{$parent}-{$kebabName}.ts";
    }

    /**
     * 将插件路由追加到 plugin-{pluginKebab}.ts 文件
     */
    protected function appendPluginRouteToPluginTs(string $pluginKebab, string $childRouteCode, bool $isNewPlugin): string
    {
        $pluginTsPath = base_path('web/src/router/routes/modules/plugin-' . $pluginKebab . '.ts');
        $pluginStudly = Str::studly($pluginKebab);

        if ($isNewPlugin || !file_exists($pluginTsPath)) {
            // 新插件：创建独立的 plugin-{pluginKebab}.ts 文件
            $content = <<<TS
/*
 * @Author: Author dabashan.cc
 * @Date: {$this->formatDate()}
 * @LastEditTime: {$this->formatDate()}
 * @LastEditors: LastEditors
 * @Copyright: Copyright (c) 2026 by Dabashan.cc, All Rights Reserved.
 */
import { DEFAULT_LAYOUT } from '../base';
import { AppRouteRecordRaw } from '../types';

const PLUGIN: AppRouteRecordRaw = {
  path: '/plugin',
  name: 'Plugin',
  component: DEFAULT_LAYOUT,
  redirect: '/plugin/index',
  meta: {
    locale: 'menu.plugin',
    icon: 'icon-apps',
    requiresAuth: true,
    order: 80,
    hideChildrenInMenu: true,
  },
  children: [
    // {$pluginStudly} Plugin
    {
      path: '{$pluginKebab}',
      name: '{$pluginStudly}',
      component: () => import('@/views/plugin/components/PluginLayout.vue'),
      redirect: '/plugin/{$pluginKebab}/index',
      meta: {
        locale: 'menu.plugin.{$pluginKebab}',
        requiresAuth: true,
        roles: ['*'],
        hideInMenu: true,
      },
      children: [
        {
          path: 'index',
          name: '{$pluginStudly}Index',
          component: () => import('@/views/plugin/{$pluginKebab}/index.vue'),
          meta: {
            locale: 'menu.plugin.{$pluginKebab}.config',
            requiresAuth: true,
            roles: ['*'],
            hideInMenu: true,
          },
        },
        {$childRouteCode}
      ],
    },
  ],
};

export default PLUGIN;
TS;
        } else {
            // 已有插件：在该文件的 plugin group children 数组末尾追加
            $content = file_get_contents($pluginTsPath);
            if ($content === false) {
                throw new \Exception("无法读取 plugin-{$pluginKebab}.ts 文件");
            }

            // 逐行解析，找到 plugin group 的 children 数组，在 ], 前追加
            $lines = explode("\n", $content);
            $inGroup = false;
            $groupFound = false;
            $insertIndex = -1;
            $childrenBracketFound = false;

            foreach ($lines as $i => $line) {
                $trimmed = trim($line);

                // 检测是否进入目标 plugin group
                if (!$groupFound && preg_match('/name:\s*\'' . preg_quote($pluginStudly, '/') . '\'/', $trimmed)) {
                    $inGroup = true;
                    continue;
                }

                if ($inGroup) {
                    // 检测 children: [ 的开始
                    if (!$childrenBracketFound && strpos($trimmed, 'children: [') !== false) {
                        $childrenBracketFound = true;
                        // 计算缩进，找到应该插入的位置
                        continue;
                    }

                    if ($childrenBracketFound) {
                        // 找到 children 数组的结束位置
                        if (str_starts_with($trimmed, '],')) {
                            $insertIndex = $i;
                            break;
                        }
                    }

                    // 如果遇到了同级或更浅的 }, 说明 children 数组结束了
                    if ($trimmed === '},' || $trimmed === '},') {
                        if (!$childrenBracketFound) {
                            // 没有 children 数组，跳过这个 group
                            $inGroup = false;
                            continue;
                        }
                    }
                }
            }

            if ($insertIndex > 0) {
                $indent = str_repeat(' ', 8);
                $newLine = $indent . trim($childRouteCode);
                array_splice($lines, $insertIndex, 0, [$newLine]);
                $content = implode("\n", $lines);
            }
        }

        // 确保目录存在
        $dir = dirname($pluginTsPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $result = file_put_contents($pluginTsPath, $content);
        if ($result === false) {
            throw new \Exception("无法写入 plugin-{$pluginKebab}.ts 文件");
        }

        return $pluginTsPath;
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

        foreach ($preview['preview'] as $key => $fileInfo) {
            // 插件模式跳过 router 键（使用动态路由加载）
            if ($key === 'router' && $isPlugin) {
                continue;
            }

            $path = $fileInfo['path'];
            $content = $fileInfo['content'];
            $fullPath = $basePath . '/' . $path;

            // 确保目录存在
            $dir = dirname($fullPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // 检查文件是否已存在
            $force = $config['force'] ?? false;
            if (file_exists($fullPath) && !$force) {
                throw new \Exception("文件 {$path} 已存在，请使用 force 参数覆盖");
            }

            // 写入文件
            $result = file_put_contents($fullPath, $content);
            if ($result === false) {
                throw new \Exception("无法写入文件 {$path}");
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

        $pluginKebab = Str::kebab($plugin);
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

        // 3. 删除 Migration 文件（按名称匹配）
        $migrationDir = $pluginDir . '/database/migrations';
        if (is_dir($migrationDir)) {
            foreach (scandir($migrationDir) as $file) {
                if ($file === '.' || $file === '..') continue;
                if (stripos($file, 'create') !== false && stripos($file, $kebabName) !== false) {
                    unlink($migrationDir . '/' . $file);
                    $deletedFiles[] = 'plugins/' . $pluginStudly . '/database/migrations/' . $file;
                }
            }
        }

        // 4. 删除 Vue 视图文件
        $businessVuePath = $basePath . '/web/src/views/plugin/' . $pluginKebab . '/' . $kebabName . '.vue';
        if (file_exists($businessVuePath)) {
            unlink($businessVuePath);
            $deletedFiles[] = 'web/src/views/plugin/' . $pluginKebab . '/' . $kebabName . '.vue';
        }

        $crudVuePath = $basePath . '/web/src/views/plugin/' . $pluginKebab . '/' . $kebabName . '/index.vue';
        if (file_exists($crudVuePath)) {
            unlink($crudVuePath);
            $deletedFiles[] = 'web/src/views/plugin/' . $pluginKebab . '/' . $kebabName . '/index.vue';
        }

        $pluginIndexVuePath = $basePath . '/web/src/views/plugin/' . $pluginKebab . '/index.vue';
        if (file_exists($pluginIndexVuePath)) {
            unlink($pluginIndexVuePath);
            $deletedFiles[] = 'web/src/views/plugin/' . $pluginKebab . '/index.vue';
        }

        $pluginRoutePath = $basePath . '/web/src/router/routes/modules/plugin-' . $pluginKebab . '.ts';
        if (file_exists($pluginRoutePath)) {
            unlink($pluginRoutePath);
            $deletedFiles[] = 'web/src/router/routes/modules/plugin-' . $pluginKebab . '.ts';
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
        $this->removeEmptyDirectory($basePath . '/web/src/views/plugin/' . $pluginKebab);

        // 9. 执行 composer dump-autoload 清理自动加载
        $composer = base_path('vendor/bin/composer') ?: 'composer';
        exec("{$composer} dump-autoload -q 2>&1");

        return [
            'files' => $deletedFiles,
            'message' => '代码已删除',
        ];
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
