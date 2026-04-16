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

        if (is_dir($pluginPath)) {
            foreach (scandir($pluginPath) as $dir) {
                if ($dir === '.' || $dir === '..') {
                    continue;
                }
                $jsonPath = $pluginPath . '/' . $dir . '/plugin.json';
                if (file_exists($jsonPath)) {
                    $config = json_decode(file_get_contents($jsonPath), true);
                    if ($config && !empty($config['name'])) {
                        $plugins[] = [
                            'name' => $config['name'],
                            'title' => $config['title'] ?? $config['name'],
                            'description' => $config['description'] ?? '',
                            'version' => $config['version'] ?? '1.0.0',
                            'enabled' => $config['enabled'] ?? true,
                        ];
                    }
                }
            }
        }

        return $this->success($plugins);
    }

    /**
     * 获取已有插件的 kebab-case 名称列表
     */
    protected function getExistingPluginNames(): array
    {
        $pluginPath = base_path('plugins');
        $names = [];

        if (is_dir($pluginPath)) {
            foreach (scandir($pluginPath) as $dir) {
                if ($dir === '.' || $dir === '..') {
                    continue;
                }
                $jsonPath = $pluginPath . '/' . $dir . '/plugin.json';
                if (file_exists($jsonPath)) {
                    $config = json_decode(file_get_contents($jsonPath), true);
                    if ($config && !empty($config['name'])) {
                        $names[] = Str::kebab($config['name']);
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
            'parent' => 'nullable|string',
            'table' => 'nullable|string',
            'fillable' => 'nullable|array',
            'fields' => 'nullable|array',
            'grid_columns' => 'nullable|array',
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
            'parent' => 'nullable|string',
            'table' => 'nullable|string',
            'fillable' => 'nullable|array',
            'fields' => 'nullable|array',
            'grid_columns' => 'nullable|array',
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
        $tableName = $config['table'] ?? ($config['type'] === 'plugin'
            ? "plugin_{$config['plugin']}_{$pluralSnake}"
            : "admin_{$pluralSnake}");
        $modelName = $config['type'] === 'plugin' ? $name : "Admin{$name}";
        $controllerName = "{$name}Controller";
        $icon = $config['icon'] ?? 'icon-file';
        $order = $config['order'] ?? 90;
        $fields = $config['fields'] ?? [];
        $gridColumns = $config['grid_columns'] ?? [];
        $plugin = $config['plugin'] ?? null;
        $pluginKebab = $plugin ? Str::kebab($plugin) : null;

        // 判断是已有插件还是新插件
        $isNewPlugin = false;
        if ($config['type'] === 'plugin' && $plugin) {
            $existingPlugins = $this->getExistingPluginNames();
            $isNewPlugin = !in_array($pluginKebab, $existingPlugins, true);
        }

        // 生成 Controller 代码
        $controllerCode = $this->generateControllerCode($name, $controllerName, $modelName, $config['type'], $fields, $gridColumns, $plugin);

        // 生成 Model 代码
        $modelCode = $this->generateModelCode($modelName, $tableName, $config['fillable'] ?? [], $config['type'], $plugin);

        // 生成迁移代码
        $migrationCode = $this->generateMigrationCode($tableName, $fields);

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
                $pluginJson = $this->generatePluginJson($plugin, $name, $tableName, $parent, $kebabName, $icon, $order);
                $serviceProvider = $this->generatePluginServiceProvider($plugin, $name);

                $pluginFiles['plugin_json'] = [
                    'path' => "plugins/{$pluginKebab}/plugin.json",
                    'content' => $pluginJson,
                ];
                $pluginFiles['service_provider'] = [
                    'path' => "plugins/{$pluginKebab}/PluginServiceProvider.php",
                    'content' => $serviceProvider,
                ];

                $files[] = "plugins/{$pluginKebab}/plugin.json";
                $files[] = "plugins/{$pluginKebab}/PluginServiceProvider.php";
            }

            // 无论新旧都生成 Admin 路由文件和业务端页面
            $adminRoutes = $this->generatePluginAdminRoutes($plugin, $name, $parent, $kebabName);
            $businessVue = $this->generateBusinessVueCode($pluginKebab, $kebabName, $name);

            $pluginFiles['admin_routes'] = [
                'path' => "plugins/{$pluginKebab}/Admin/routes.php",
                'content' => $adminRoutes,
            ];
            $pluginFiles['business_vue'] = [
                'path' => "web/src/views/plugin/{$pluginKebab}/{$kebabName}/index.vue",
                'content' => $businessVue,
            ];

            $files[] = "plugins/{$pluginKebab}/Admin/routes.php";
            $files[] = "web/src/views/plugin/{$pluginKebab}/{$kebabName}/index.vue";
        }

        // 迁移路径：插件放 plugin 自己的 database/migrations，核心放全局 database/migrations
        $migrationPath = $config['type'] === 'plugin'
            ? "plugins/{$pluginKebab}/database/migrations/xxxx_xx_xx_xx_create_{$tableName}_table.php"
            : "database/migrations/xxxx_xx_xx_xx_create_{$tableName}_table.php";

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

    protected function generateControllerCode(string $name, string $controllerName, string $modelName, string $type, array $fields, array $gridColumns, ?string $plugin = null): string
    {
        $namespace = $type === 'plugin'
            ? "Plugins\\{$plugin}\\Admin\\Controllers"
            : 'App\\Admin\\Controllers';

        $modelNamespace = $type === 'plugin'
            ? "Plugins\\{$plugin}\\Models\\{$modelName}"
            : "App\\Admin\\Models\\{$modelName}";

        $gridColumnsCode = $this->formatGridColumns($gridColumns, $fields);
        $formFieldsCode = $this->formFields($fields);

        return <<<PHP
<?php

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
        return Grid::make({$modelName}::query())
            {$gridColumnsCode}
            ->perPage(15);
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
            $line = "->column('{$col['key']}', '{$col['label']}')";
            if (!empty($col['sortable'])) {
                $line .= '->sortable()';
            }
            if (!empty($col['searchable'])) {
                $line .= '->searchable()';
            }
            if (!empty($col['display_type']) && $col['display_type'] !== 'text') {
                $line .= "->{$col['display_type']}()";
            }
            if (!empty($col['width'])) {
                $line .= "->width('{$col['width']}')";
            }
            $lines[] = $line;
        }

        return implode("\n            ", $lines);
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
            'select' => 'select',
            'switch' => 'switch',
            'radio' => 'radio',
            'checkbox' => 'checkbox',
            'date' => 'date',
            'dateTime' => 'dateTime',
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
            $label = $field['label'] ?? $field['key'] ?? '';

            $line = "->{$formType}('{$field['key']}', '{$label}')";
            if (!empty($field['required'])) {
                $line .= '->required()';
            }
            if (!empty($field['default']) && $field['default'] !== '') {
                $line .= "->default({$field['default']})";
            }
            if (!empty($field['help'])) {
                $line .= "->help('{$field['help']}')";
            }
            if (!empty($field['placeholder'])) {
                $line .= "->placeholder('{$field['placeholder']}')";
            }
            $lines[] = $line;
        }

        return implode("\n            ", $lines);
    }

    protected function generateModelCode(string $modelName, string $tableName, array $fillable, string $type, ?string $plugin = null): string
    {
        $namespace = $type === 'plugin' ? "Plugins\\{$plugin}\\Models" : 'App\\Admin\\Models';
        $fillableCode = empty($fillable) ? '//' : "'" . implode("',\n        '", $fillable) . "',";
        $baseModel = $type === 'plugin' ? 'Illuminate\\Database\\Eloquent\\Model' : 'Dabashan\\DbsAdmin\\Models\\BaseAdminModel';

        return <<<PHP
<?php

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

    protected function generateVueCode(string $parent, string $kebabName, string $name, string $type = 'core', ?string $pluginKebab = null): string
    {
        // 插件模式下使用 Arco Design 组件，生成到插件视图目录
        if ($type === 'plugin' && $pluginKebab) {
            return <<<VUE
<template>
  <div class="{$kebabName}-container">
    <a-card class="general-card">
      <template #title>{{ \$t('menu.{$parent}.{$kebabName}') }}</template>

      <!-- 筛选区域 -->
      <a-form layout="inline" class="filter-form">
        <a-form-item field="keyword" label="关键词">
          <a-input v-model="queryParams.keyword" placeholder="请输入关键词" allow-clear @clear="handleSearch" />
        </a-form-item>
        <a-form-item>
          <a-space>
            <a-button type="primary" @click="handleSearch">
              <template #icon><icon-search /></template> 搜索
            </a-button>
            <a-button @click="handleReset">
              <template #icon><icon-refresh /></template> 重置
            </a-button>
          </a-space>
        </a-form-item>
      </a-form>

      <!-- 操作按钮 -->
      <div class="action-bar">
        <a-button type="primary" @click="handleAdd">
          <template #icon><icon-plus /></template> 新增{$name}
        </a-button>
      </div>

      <!-- 数据表格 -->
      <a-table
        :columns="columns"
        :data="tableData"
        :loading="loading"
        :pagination="pagination"
        @page-change="handlePageChange"
      >
        <template #actions="{ record }">
          <a-space>
            <a-button type="text" size="small" @click="handleEdit(record)">编辑</a-button>
            <a-popconfirm content="确定删除?" @ok="handleDelete(record)">
              <a-button type="text" status="danger" size="small">删除</a-button>
            </a-popconfirm>
          </a-space>
        </template>
      </a-table>
    </a-card>

    <!-- 新增/编辑弹窗 -->
    <a-modal
      v-model:visible="modalVisible"
      :title="modalTitle"
      @ok="handleSubmit"
      @cancel="modalVisible = false"
    >
      <a-form ref="formRef" :model="formData" auto-label-width>
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
  import axios from 'axios';

  const columns = [
    { title: 'ID', dataIndex: 'id', width: 80 },
    { title: '名称', dataIndex: 'name' },
    { title: '创建时间', dataIndex: 'created_at', width: 180 },
    { title: '操作', slotName: 'actions', width: 150 },
  ];

  const tableData = ref([]);
  const loading = ref(false);
  const pagination = reactive({ current: 1, pageSize: 15, total: 0, showTotal: true });
  const queryParams = reactive({ keyword: '' });

  const modalVisible = ref(false);
  const modalTitle = ref('新增{$name}');
  const formData = reactive({ id: null, name: '' });
  const formRef = ref();

  async function fetchData() {
    loading.value = true;
    try {
      const res = await axios.get('/plugin/{$pluginKebab}/admin/{$parent}/{$kebabName}', {
        params: { ...queryParams, page: pagination.current },
      });
      tableData.value = res.data.data || [];
      pagination.total = res.data.total || 0;
    } finally {
      loading.value = false;
    }
  }

  function handleSearch() { pagination.current = 1; fetchData(); }
  function handleReset() { queryParams.keyword = ''; handleSearch(); }
  function handlePageChange(page: number) { pagination.current = page; fetchData(); }

  function handleAdd() {
    modalTitle.value = '新增{$name}';
    Object.assign(formData, { id: null, name: '' });
    modalVisible.value = true;
  }

  function handleEdit(record: any) {
    modalTitle.value = '编辑{$name}';
    Object.assign(formData, record);
    modalVisible.value = true;
  }

  async function handleSubmit() {
    const errors = await formRef.value?.validate();
    if (errors) return;
    try {
      if (formData.id) {
        await axios.put('/plugin/{$pluginKebab}/admin/{$parent}/{$kebabName}/' + formData.id, formData);
        Message.success('更新成功');
      } else {
        await axios.post('/plugin/{$pluginKebab}/admin/{$parent}/{$kebabName}', formData);
        Message.success('创建成功');
      }
      modalVisible.value = false;
      fetchData();
    } catch (e: any) {
      Message.error(e.response?.data?.msg || '操作失败');
    }
  }

  async function handleDelete(record: any) {
    try {
      await axios.delete('/plugin/{$pluginKebab}/admin/{$parent}/{$kebabName}/' + record.id);
      Message.success('删除成功');
      fetchData();
    } catch (e: any) {
      Message.error(e.response?.data?.msg || '删除失败');
    }
  }

  onMounted(fetchData);
</script>

<style scoped>
  .{$kebabName}-container { padding: 20px; }
  .filter-form { margin-bottom: 16px; }
  .action-bar { margin-bottom: 16px; }
</style>
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
        // 插件模式：路由由动态加载器处理，不需要生成静态路由文件
        // 这里生成一个占位路由，实际路由在插件加载时动态注册
        if ($type === 'plugin' && $pluginKebab) {
            $pluginStudly = Str::studly($pluginKebab);
            return <<<TS
// 插件路由由 dynamic-plugin-loader.ts 动态注册
// 此文件仅作为类型声明参考
import { AppRouteRecordRaw } from '../types';

const {$name}Route: AppRouteRecordRaw = {
  path: '/plugin/{$pluginKebab}/{$kebabName}',
  name: 'Plugin{$pluginStudly}{$name}',
  component: () => import('@/views/plugin/{$pluginKebab}/{$kebabName}/index.vue'),
  meta: {
    locale: 'menu.plugin.{$kebabName}',
    requiresAuth: true,
    roles: ['*'],
  },
};

export default {$name}Route;
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
    protected function generatePluginJson(string $plugin, string $name, string $tableName, string $parent, string $kebabName, string $icon, int $order): string
    {
        $pluginStudly = Str::studly($plugin);
        $pluginKebab = Str::kebab($plugin);
        $json = [
            'name' => $pluginKebab,
            'title' => "{$pluginStudly} 插件",
            'description' => "{$name} 管理插件",
            'version' => '1.0.0',
            'author' => 'Code Generator',
            'enabled' => true,
            'icon' => $icon,
            'type' => 'local',
            'show_api' => true,
            'requires' => [],
            'providers' => [
                "Plugins\\\\{$pluginStudly}\\\\PluginServiceProvider",
            ],
            'admin_controllers' => [
                "Plugins\\\\{$pluginStudly}\\\\Admin\\\\Controllers\\\\{$name}Controller",
            ],
            'menus' => [
                [
                    'title' => "{$name}管理",
                    'icon' => $icon,
                    'uri' => "{$parent}/{$kebabName}",
                ],
            ],
            'permissions' => [
                "{$parent}.{$kebabName}",
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

        return <<<PHP
<?php

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
        \$migrationsPath = __DIR__ . '/../database/migrations';
        if (is_dir(\$migrationsPath)) {
            \$this->loadMigrationsFrom(\$migrationsPath);
        }

        // 加载后台路由
        \$adminRoutes = __DIR__ . '/../Admin/routes.php';
        if (file_exists(\$adminRoutes)) {
            \$this->loadRoutesFrom(\$adminRoutes);
        }
    }
}
PHP;
    }

    /**
     * 生成插件后台路由文件
     */
    protected function generatePluginAdminRoutes(string $plugin, string $name, string $parent, string $kebabName): string
    {
        $pluginStudly = Str::studly($plugin);
        $controllerNamespace = "Plugins\\{$pluginStudly}\\Admin\\Controllers\\{$name}Controller";

        return <<<PHP
<?php

use Illuminate\\Support\\Facades\\Route;
use {$controllerNamespace};

// 插件后台路由
Route::prefix('plugin/{$plugin}/admin')
    ->middleware(['api', 'auth:admin'])
    ->group(function () {
        Route::get('{$parent}/{$kebabName}', [{$name}Controller::class, 'index']);
        Route::post('{$parent}/{$kebabName}', [{$name}Controller::class, 'store']);
        Route::get('{$parent}/{$kebabName}/{id}', [{$name}Controller::class, 'show']);
        Route::put('{$parent}/{$kebabName}/{id}', [{$name}Controller::class, 'update']);
        Route::delete('{$parent}/{$kebabName}/{id}', [{$name}Controller::class, 'destroy']);
    });
PHP;
    }

    /**
     * 生成业务端 Vue 页面（插件前台）
     */
    protected function generateBusinessVueCode(string $plugin, string $kebabName, string $name): string
    {
        return <<<VUE
<template>
  <div class="{$kebabName}-page">
    <h1>{$name} List</h1>
    <a-table :columns="columns" :data="data" :loading="loading">
      <template #actions="{ record }">
        <a-space>
          <a-button type="text" size="small" @click="viewDetail(record)">查看</a-button>
        </a-space>
      </template>
    </a-table>
  </div>
</template>

<script lang="ts" setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const columns = [
  { title: 'ID', dataIndex: 'id' },
  { title: '名称', dataIndex: 'name' },
  { title: '创建时间', dataIndex: 'created_at' },
];

const data = ref([]);
const loading = ref(false);

async function fetchData() {
  loading.value = true;
  try {
    const res = await axios.get('/plugin/{$plugin}/{$kebabName}');
    data.value = res.data.data || [];
  } finally {
    loading.value = false;
  }
}

function viewDetail(record: any) {
  // TODO: 实现详情查看逻辑
}

onMounted(fetchData);
</script>
VUE;
    }

    protected function getGeneratedFiles(string $name, string $kebabName, string $parent, string $type, ?string $plugin, string $table, ?string $pluginKebab = null): array
    {
        $files = [];
        $files[] = $this->getControllerPath($name, $type, $plugin);
        $files[] = $this->getModelPath($name, $type, $plugin);
        $migrationPath = $type === 'plugin'
            ? "plugins/{$pluginKebab}/database/migrations/xxxx_xx_xx_xx_create_{$table}_table.php"
            : "database/migrations/xxxx_xx_xx_xx_create_{$table}_table.php";
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
            ? "plugins/" . Str::kebab($plugin) . "/Admin/Controllers/{$name}Controller.php"
            : "app/Admin/Controllers/{$name}Controller.php";
    }

    protected function getModelPath(string $name, string $type, ?string $plugin): string
    {
        $modelName = $type === 'plugin' ? $name : "Admin{$name}";
        return $type === 'plugin'
            ? "plugins/" . Str::kebab($plugin) . "/Models/{$modelName}.php"
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
            ? "web/src/router/routes/modules/plugin-{$pluginKebab}-{$kebabName}.ts"
            : "web/src/router/routes/modules/{$parent}-{$kebabName}.ts";
    }

    protected function doGenerate(array $config): array
    {
        $preview = $this->generatePreview($config);
        $basePath = base_path();
        $writtenFiles = [];

        foreach ($preview['preview'] as $key => $fileInfo) {
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

        return [
            'files' => $writtenFiles,
            'message' => '代码已生成',
        ];
    }
}
