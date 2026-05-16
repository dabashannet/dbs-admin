# dbs-admin

基于 **Laravel 12 + Vue 3 (Arco Design Pro)** 的后台管理系统框架。采用**约定优于配置**理念，后端定义元数据、前端动态渲染，零 Vue 代码即可完成 CRUD。

---

## 目录

- [架构总览](#架构总览)
- [快速开始](#快速开始)
- [Grid 网格系统](#grid-网格系统)
- [Form 表单系统](#form-表单系统)
- [Show 详情展示](#show-详情展示)
- [Action 操作系统](#action-操作系统)
- [ActionGroup 操作分组](#actiongroup-操作分组)
- [HasImportExport 导入导出](#hasimportexport-导入导出)
- [插件开发指南](#插件开发指南)
- [代码生成器](#代码生成器)
- [Artisan 脚手架命令](#artisan-脚手架命令)
- [服务层](#服务层)
- [基础设施](#基础设施)
- [模型概览](#模型概览)

---

## 架构总览

```
dbs-admin/
├── src/
│   ├── Commands/                # Artisan 脚手架命令
│   ├── Controllers/             # 控制器基类 + 代码生成器
│   ├── Events/                  # 插件变更事件
│   ├── Form/                    # 表单系统
│   ├── Grid/                    # 网格系统
│   ├── Models/                  # 模型基类 + 后台模型
│   ├── Notifications/           # 通知系统（后端推送前端）
│   ├── Providers/               # 插件 ServiceProvider 基类
│   ├── Services/                # 插件管理、设置、注册表
│   ├── Show/                    # 详情展示
│   ├── Tasks/                   # 任务管理器（缓存驱动）
│   └── Traits/                  # 响应、文件生成等 Trait
└── stubs/                       # 代码生成模板
```

### 核心流程

PHP 定义 Grid/Form 元数据 → API 返回 JSON → 前端 DynamicCrud 自动渲染 Arco 组件。

### 目录职责

| 目录 | 说明 |
|------|------|
| `Commands/` | `make:admin`、`make:plugin`、`make:plugin-page` 三个脚手架命令 |
| `Controllers/` | `AdminController` 抽象基类、`AuthController` 认证、`CodeGeneratorController` 可视化代码生成器、`DatabaseController` 数据库维护、`HttpController` 扩展、`OperationLogController` 操作日志、`PluginController` 插件管理、`TaskController` 任务状态轮询 |
| `Services/` | `PluginManager` 插件发现与状态管理、`PluginService` 安装/卸载/升级、`PluginRegistryGenerator` 前端组件注册表生成、`SettingService` 系统设置键值存储 |
| `Traits/` | `HasApiResponse` 统一 JSON 响应、`HasFileGeneration` 命令文件生成工具 |
| `Tasks/` | `TaskManager` 基于 Cache 的任务跟踪（进度/日志/状态） |
| `Grid/` | `HasImportExport` Trait 提供导入导出/复制/软删除等快捷操作 |
| `Notifications/` | 类似 Filament 的流畅通知 API，后端推送前端弹窗 |
| `Providers/` | `PluginBaseProvider` 插件 ServiceProvider 基类 |

---

## 快速开始

### 安装

```bash
cd laravel12
composer require dabashan/dbs-admin
php artisan vendor:publish --provider="Dabashan\DbsAdmin\DbsAdminServiceProvider"
npm install          # web/ 目录下安装前端依赖
cp .env.example .env
php artisan key:generate
php artisan migrate
```

### 最简单的 CRUD

只需 3 步即可获得完整的增删改查：

**1. 创建模型和迁移**

```bash
php artisan make:model Category -m
```

**2. 创建控制器**

```bash
php artisan make:admin Category
```

**3. 注册路由**

```php
// app/Admin/route.php
Route::resource('categories', \App\Admin\Controllers\CategoryController::class);
```

前端只需创建一个 6 行的 Vue 页面：

```vue
<template>
  <DynamicCrud api-prefix="/admin/categories"
    :breadcrumb="['menu.system', 'menu.system.categories']"
    add-title="新增分类" edit-title="编辑分类" />
</template>
<script lang="ts" setup>
  import DynamicCrud from '@/components/dynamic/DynamicCrud.vue';
</script>
```

---

## Grid 网格系统

后端定义列和筛选器，前端自动渲染 Arco Table。

### 列定义

```php
protected function grid(): Grid
{
    return Grid::make(User::query())
        ->column('id', 'ID')
        ->column('name', '姓名')
        ->column('email', '邮箱')
        ->column('avatar', '头像')
        ->column('status', '状态')
        ->column('created_at', '创建时间');
}
```

### 显示类型

```php
// 徽章
->column('status', '状态')->badge(['1' => 'green', '0' => 'red'], 'light')

// 开关（表格内 AJAX 切换）
->column('status', '状态')->toggle()

// 图片缩略图
->column('avatar', '头像')->image(60, 60, circle: true)

// 进度条
->column('progress', '进度')->progress(max: 100)

// 可复制文本
->column('token', '令牌')->copyable()

// 日期格式化
->column('created_at', '创建时间')->date('Y-m-d')

// 金额
->column('price', '价格')->money('¥', 2)

// 标签（Tag 组件）
->column('tags', '标签')->tags('blue')
```

### 修饰方法

```php
->column('name', '姓名')
    ->width('200px')       // 列宽
    ->align('center')      // 对齐
    ->sortable()           // 可排序
    ->searchable()         // 可搜索
    ->limit(20)            // 文本截断
```

### 筛选器

```php
->filter('name', '姓名', 'like')                           // 模糊搜索
->filter('status', '状态', 'select')->options([...])       // 下拉选择
->filter('created_at', '创建时间', 'between_date')         // 日期范围
->filter('price', '价格', 'gt')                            // 大于
->filter('type', '类型', 'equal')                          // 精确等于
->filter('count', '数量', 'lt')                            // 小于
->filter('category_id', '分类', 'in')                      // IN 查询
```

### 操作按钮

```php
use Dabashan\DbsAdmin\Grid\Action;

protected function grid(): Grid
{
    return Grid::make(User::query())
        ->column('id', 'ID')
        ->createAction()
        ->editAction()
        ->deleteAction()
        ->batchDeleteAction()
        ->action(Action::make('export', '导出')->header()->type('success'));
}
```

### 导入导出快捷操作

使用 `HasImportExport` Trait 可快速添加导入导出、复制、软删除等操作：

```php
use Dabashan\DbsAdmin\Grid\HasImportExport;

class UserController extends AdminController
{
    use HasImportExport;

    protected function grid(): Grid
    {
        return Grid::make(User::query())
            ->column('id', 'ID')
            ->column('name', '姓名')
            // 导入按钮（头部）
            ->importAction()
            // 导出按钮（头部）
            ->exportAction()
            // 行复制按钮
            ->replicateAction()
            // 行软删除恢复按钮（需模型使用 SoftDeletes）
            ->restoreAction()
            // 行强制删除按钮
            ->forceDeleteAction()
            // 批量恢复
            ->batchRestoreAction()
            // 批量强制删除
            ->batchForceDeleteAction();
    }
}
```

### 操作分组 ActionGroup

将多个操作组合为下拉菜单或按钮组，减少行操作按钮冗余：

```php
use Dabashan\DbsAdmin\Grid\ActionGroup;
use Dabashan\DbsAdmin\Grid\Action;

protected function grid(): Grid
{
    return Grid::make(User::query())
        ->column('id', 'ID')
        ->actionGroup(
            ActionGroup::make([
                Action::make('approve', '通过')->type('success'),
                Action::make('reject', '驳回')->type('danger'),
                Action::make('reset', '重置')->type('warning'),
            ])
            ->label('审核操作')
            ->type('primary')
            ->dropdown()  // 下拉菜单模式（默认）
        );
}
```

支持 `dropdown`（下拉菜单）和 `modal`（弹窗列表）两种模式，可通过 `header()` / `row()` 设置分组位置。

### 性能优化

```php
// 仅查询需要的字段
->select(['id', 'name', 'status', 'created_at'])

// 预加载关联
->with(['category', 'author'])

// 自定义查询
->query(fn($q, $req) => $q->where('status', 1))

// 设置分页
->perPage(15)
```

---

## Form 表单系统

后端定义表单字段和验证，前端自动渲染 Arco Form。

### 字段类型

```php
protected function form(): Form
{
    return Form::make(User::class)
        ->text('name', '姓名')
        ->email('email', '邮箱')
        ->password('password', '密码')
        ->select('status', '状态')->options([1 => '启用', 0 => '禁用'])
        ->image('avatar', '头像')
        ->switch('status', '状态')->default(true)
        ->textarea('description', '描述')
        ->editor('content', '内容')          // wangEditor 富文本
        ->code('snippet', '代码')            // 代码编辑器
        ->icon('icon', '图标')               // 图标选择器
        ->number('price', '价格')
        ->divider('分隔标题');               // 分割线
}
```

### 验证

```php
->text('name', '姓名')->required()
->email('email', '邮箱')->rules('email|unique:users,email')
->password('password', '密码')->rules('required|min:6')
->number('age', '年龄')->min(1)->max(120)
```

更新场景下 `required` 规则自动替换为 `sometimes`，避免编辑时不需要重新填写已有值。

### 条件显示

```php
->text('reason', '原因')->displayWhen('status', '==', 1)
```

### 字段联动

```php
->select('province', '省份')->options([...])
->select('city', '城市')->depends(['province'])->optionsFrom(fn($province) => ...)
```

### 布局

```php
->tabs([
    ['label' => '基本信息', 'fields' => ['name', 'email']],
    ['label' => '扩展信息', 'fields' => ['description', 'status']],
])
->columns(2)    // 分栏
->section('联系信息', ['phone', 'email'])  // 区块
```

---

## Show 详情展示

后端定义详情页展示字段，支持关联预加载与字段裁剪：

```php
protected function detail($id): Show
{
    return Show::make(User::with('category')->findOrFail($id))
        ->field('id', 'ID')
        ->field('name', '姓名')
        ->field('email', '邮箱')
        ->field('status', '状态')
        ->field('created_at', '创建时间');
}
```

未指定字段时返回模型全部可访问属性。

---

## Action 操作系统

支持三种模式：**弹窗（Modal）**、**抽屉（Drawer）**、**新页面（Page）**。

```php
Action::make('edit', '编辑')->row()->modal(['width' => 600])
Action::make('edit', '编辑')->row()->drawer(['width' => 700])
Action::make('delete', '删除')->row()->type('danger')->confirm(true)
Action::make('export', '导出')->header()->type('success')
Action::make('approve', '批量审核')->bulk()->confirm(true)
```

### Action 属性链

| 方法 | 说明 |
|------|------|
| `header()` | 头部按钮 |
| `row()` | 行按钮 |
| `bulk()` | 批量勾选后显示 |
| `modal(['width'=>600])` | 弹窗模式 |
| `drawer(['width'=>700])` | 抽屉模式 |
| `page()` | 跳转新页面 |
| `type('primary|success|danger|warning')` | 按钮类型 |
| `confirm(true)` | 点击确认 |
| `icon('icon-name')` | 按钮图标 |
| `apiRoute('/custom/route')` | 自定义 API 路由 |

---

## ActionGroup 操作分组

将多个功能相关的操作组合为下拉菜单或按钮组，适用于审核、批量处理等场景：

```php
use Dabashan\DbsAdmin\Grid\ActionGroup;

// 头部操作组
ActionGroup::make([...])->label('工具')->header()->dropdown()

// 行操作组
ActionGroup::make([...])->label('更多')->row()->modal()
```

支持 `toArray()` 输出元数据结构供前端渲染。

---

## HasImportExport 导入导出

`HasImportExport` Trait 为 Grid 提供 7 个快捷操作方法：

| 方法 | 位置 | 说明 |
|------|------|------|
| `importAction()` | 头部 | 导入按钮（弹窗上传） |
| `exportAction()` | 头部 | 导出按钮 |
| `replicateAction()` | 行 | 复制创建新记录 |
| `forceDeleteAction()` | 行 | 永久删除（软删除时） |
| `restoreAction()` | 行 | 恢复软删除记录 |
| `batchRestoreAction()` | 批量 | 批量恢复 |
| `batchForceDeleteAction()` | 批量 | 批量永久删除 |

所有快捷操作内置确认提示和 Arco 图标。

---

## 插件开发指南

### 设计理念

插件采用**前后端完全内聚**架构，前端资源（Vue 页面、路由、静态资源）全部放在插件自己的 `resources/` 目录下，与 PHP 代码同属一个插件单元。

```
plugins/{PluginName}/                    # StudlyCase 命名
├── manifest.json                        # 插件元信息
├── PluginServiceProvider.php            # 服务提供者
├── Admin/                               # 后台管理
│   ├── Controllers/{Name}Controller.php
│   └── routes.php
├── Http/                                # 业务端 API
│   ├── Controllers/{Name}Controller.php
│   └── routes.php
├── Models/{Name}.php
├── resources/                           # 前端资源
│   ├── views/
│   │   ├── index.vue                    # 插件首页
│   │   └── {resource}/
│   │       └── index.vue                # 业务页面
│   ├── routes/
│   │   └── {resource}.ts                # 前端路由
│   └── static/
│       └── images/                      # 静态图片
├── database/migrations/
└── static/                              # 后端静态资源
```

### 插件 ServiceProvider 基类

所有插件的 ServiceProvider 应继承 `PluginBaseProvider`，只需设置 `$pluginName` 属性即可自动加载路由和迁移：

```php
namespace Plugins\Shop;

use Dabashan\DbsAdmin\Providers\PluginBaseProvider;

class ShopServiceProvider extends PluginBaseProvider
{
    protected string $pluginName = 'shop';

    public function boot(): void
    {
        parent::boot(); // 自动加载 Admin/routes.php、Http/routes.php 和迁移
        // 自定义 boot 逻辑...
    }
}
```

`PluginBaseProvider` 自动完成：
- 加载 `Admin/routes.php`（所有请求）
- 加载 `Http/routes.php`（所有请求）
- 加载 `database/migrations/`（仅 Console，Web 零损耗）

### 前端路由自动发现

Vite 构建时自动扫描 `plugins/*/resources/routes/*.ts`，已安装插件的前端路由无需手动注册。

路径别名：
- `@/` → `web/src/`（系统前端）
- `@resource/` → `resource/views/`（核心模块视图）
- `@plugins/` → `plugins/`（插件资源）

### 插件生命周期

插件安装流程（`PluginService::install()`）：
1. 验证插件配置文件 `manifest.json`
2. 检查依赖是否满足
3. 运行迁移
4. 注册 ServiceProvider（Laravel 自动发现缓存）
5. 生成前端插件注册表
6. 触发 `PluginChanged` 事件

禁用插件（`"enabled": false`）时，ServiceProvider 完全不会加载，**零性能损耗**。

### 快速创建插件

```bash
php artisan make:plugin shop
composer dump-autoload
php artisan migrate    # 如有迁移文件
```

---

## 代码生成器

系统内置可视化代码生成器（`维护 → 代码生成器`），支持两种生成模式：

### 核心模块模式

生成文件输出到：
- 后端：`app/Admin/Controllers/`、`app/Admin/Models/`
- 前端：`resource/views/system/{name}/`、`resource/routes/system-{name}.ts`

### 插件模块模式

生成文件输出到：
- 后端：`plugins/{Plugin}/Admin/`、`plugins/{Plugin}/Models/`、`plugins/{Plugin}/Http/`
- 前端：`plugins/{Plugin}/resources/views/`、`plugins/{Plugin}/resources/routes/`

插件模式生成的页面默认使用 `DynamicCrud` 渲染，在代码生成器中配置的字段、表格列、筛选器会完整体现在最终页面效果中。

插件后台路由额外生成 DynamicCrud 所需的接口：
- `GET {resource}/form-schema`
- `GET {resource}/grid-meta`
- `POST {resource}/batch-update`
- `POST {resource}/batch-destroy`
- `POST {resource}/{id}/toggle`
- `POST {resource}/{id}/replicate`
- `POST {resource}/{id}/restore`

### 使用流程

1. 选择生成类型（核心模块 / 插件模块）
2. 设置资源名称（如 `User`）
3. 在「字段定义」标签页中添加数据库字段
4. 配置「表格列」和「筛选器」
5. 点击「预览代码」查看生成结果
6. 点击「生成代码」写入文件

---

## Artisan 脚手架命令

### make:admin — 创建主系统 CRUD

```bash
php artisan make:admin User
php artisan make:admin User --migration    # 同时生成迁移
php artisan make:admin User --no-model     # 跳过 Model
php artisan make:admin User --no-web       # 跳过前端文件
```

生成文件：
```
app/Admin/Controllers/UserController.php
app/Admin/Models/AdminUser.php
resource/views/system/user/index.vue
resource/routes/system-user.ts
resource/views/system/user/locale/zh-CN.ts
resource/views/system/user/locale/en-US.ts
```

### make:plugin — 创建插件骨架

```bash
php artisan make:plugin shop
php artisan make:plugin demo_plugin --force  # 覆盖已有
```

生成完整的插件目录结构，包括 ServiceProvider、manifest.json、路由文件、迁移目录等。

### make:plugin-page — 在插件中创建页面

```bash
php artisan make:plugin-page shop product --vue       # 后台 Vue 页面
php artisan make:plugin-page shop order --http         # Http 控制器
php artisan make:plugin-page shop order --admin        # 后台控制器
```

---

## 服务层

### PluginManager

插件发现与状态管理核心服务。负责扫描 `plugins/` 目录、解析 `manifest.json`、管理启用/禁用状态、缓存插件元数据。

```php
use Dabashan\DbsAdmin\Services\PluginManager;

// 获取所有插件（已安装 + 未安装）
$plugins = PluginManager::all();

// 获取已启用插件
$enabled = PluginManager::enabled();

// 检查插件是否已启用
if (PluginManager::isEnabled('shop')) { ... }

// 从数据库查找已安装插件
$record = PluginManager::findFromDb('shop');

// 清除缓存
PluginManager::clearCache();
```

### PluginService

插件安装、卸载、升级的业务逻辑服务。

```php
use Dabashan\DbsAdmin\Services\PluginService;

$service = app(PluginService::class);

// 安装插件
$result = $service->install('shop');  // ['success' => true, 'message' => ...]

// 卸载插件
$result = $service->uninstall('shop');

// 升级插件
$result = $service->upgrade('shop', '1.0.0');

// 获取所有插件列表
$plugins = $service->getAllPlugins();
```

安装流程包含依赖检查、迁移执行、ServiceProvider 注册、前端注册表生成等完整步骤。

### PluginRegistryGenerator

前端插件组件注册表生成器。扫描已启用插件的 `resources/views/` 目录，生成 `web/src/plugin-registry.ts` 静态注册表文件，替代运行时全量扫描。

```php
use Dabashan\DbsAdmin\Services\PluginRegistryGenerator;

// 生成前端注册表，返回注册的组件数量
$count = PluginRegistryGenerator::generate();
```

生成的注册表包含所有插件的 Vue 组件路径，Vite 构建时自动打包。

### SettingService

系统设置键值存储服务，基于 `admin_settings` 数据库表，带 3600 秒缓存。

```php
use Dabashan\DbsAdmin\Services\SettingService;

// 读取设置
$value = SettingService::get('site_name', '默认值');

// 写入设置
SettingService::set('site_name', '我的站点', 'basic');

// 删除设置
SettingService::forget('site_name');
```

支持按 group 分组管理，写入时自动清除对应缓存。

---

## 基础设施

### TaskManager

基于 Cache 驱动的异步任务跟踪器。适用于长时间运行的后台操作（如安装、升级、数据导出）：

```php
use Dabashan\DbsAdmin\Tasks\TaskManager;

// 创建任务
$task = TaskManager::create(['name' => '数据导出']);

// 开始执行
TaskManager::start($task['task_id']);

// 追加日志
TaskManager::appendLog($task['task_id'], 'info', '正在查询数据...');

// 完成
TaskManager::finish($task['task_id'], $result, '导出完成');

// 失败
TaskManager::fail($task['task_id'], '查询超时');

// 取消
TaskManager::cancel($task['task_id']);

// 轮询日志（增量拉取）
$logs = TaskManager::logs($task['task_id'], $cursor);
// 返回 {task_id, cursor, next_cursor, done, lines: [{ts, level, message}]}

// 获取任务状态
$status = TaskManager::get($task['task_id']);
// 返回 {status, progress, stage, message, done, success, ...}
```

任务状态自动过期（默认 TTL=3600s），无需清理。

### PluginBaseProvider

插件 ServiceProvider 基类。所有插件的 ServiceProvider 继承此类即可自动获得路由和迁移加载能力：

```php
namespace Plugins\Shop;

use Dabashan\DbsAdmin\Providers\PluginBaseProvider;

class ShopServiceProvider extends PluginBaseProvider
{
    protected string $pluginName = 'shop';
}
```

自动加载行为：
| 资源 | 加载时机 |
|------|---------|
| `Admin/routes.php` | 所有请求 |
| `Http/routes.php` | 所有请求 |
| `database/migrations/` | 仅 Console（artisan） |

### PluginChanged Event

插件状态变更事件，在插件安装/卸载/启用/禁用/升级时触发：

```php
use Dabashan\DbsAdmin\Events\PluginChanged;

event(new PluginChanged('shop', 'installed'));
// action 可取: installed, uninstalled, enabled, disabled, upgraded
```

宿主应用可监听此事件执行前端编译、缓存刷新等后续操作。

### Notification

后端通知系统，类似 Filament Notifications 的流畅 API。后端推送通知到前端，Arco Vue 自动渲染弹窗：

```php
use Dabashan\DbsAdmin\Notifications\Notification;

Notification::make()
    ->title('操作成功')
    ->body('数据已保存')
    ->success()
    ->send();

// 其他类型
Notification::make()->title('警告')->body('磁盘空间不足')->warning()->send();
Notification::make()->title('错误')->body('网络异常')->error()->send();
Notification::make()->title('提示')->body('新订单')->info()->send();

// 自定义持续时间（毫秒）
Notification::make()->title('提示')->body('稍后消失')->duration(5000)->send();
```

### HasFileGeneration Trait

`HasFileGeneration` 为 Artisan 命令提供文件生成工具方法：

- `generateFile(path, stub, replacements)` — 从模板生成文件，自动创建目录，支持 `--force` 覆盖
- `writeFile(path, content)` — 写入文件并规范化文件头注释
- `normalizeHeader(path, content)` — 自动替换或添加文件头（PHP/Vue/TS/JS）

命令执行 `--force` 选项可覆盖已存在的文件。

### 模型层概览

| 模型 | 说明 |
|------|------|
| `BaseAuthenticatable` | 认证基类，基于 Laravel Authenticatable |
| `BaseAdminModel` | 后台模型基类，guarded 策略 + 常用作用域 |
| `BaseModel` | 普通模型基类 |
| `AdminUser` | 管理员用户 |
| `AdminRole` | 角色管理 |
| `AdminPermission` | 权限管理 |
| `AdminMenu` | 菜单管理 |
| `AdminSetting` | 系统设置（键值） |
| `AdminAttachment` | 文件附件 |
| `AdminAttachmentGroup` | 附件分组 |
| `AdminPaymentLog` | 支付日志 |
| `Plugin` | 插件安装记录 |
| `ApiGroup` | API 分组（代码生成器用） |
| `ApiEndpoint` | API 端点（代码生成器用） |
| `OperationLog` | 操作日志 |

### HasApiResponse Trait

所有控制器响应统一 JSON 格式 `{code, msg, data}`：

```php
return $this->success($data, '操作成功');      // code=20000
return $this->fail('参数错误', 40001);          // 业务错误
return $this->error('服务器错误', 50001);       // 系统错误
```

### 2026-05-15 插件运行迁移

- `PluginManager` 支持读取 `public/vendor/dbs-plugins/registry.json`，并要求同目录 `registry.json.sig` 通过本机密钥验签后才合并商业插件运行信息。
- `PluginBaseProvider` 在商业插件 boot 前调用 `Plugin::isValidInstallation()`，`installation_hash` 不匹配时拒绝注册路由。
- `Plugin` 模型增加 `installation_hash`、`installed_at` 字段支持，用于 agent 安装记录自检。
- 本地开发插件仍可使用 `plugins/{Name}`、`MakePluginCommand`、`PluginRegistryGenerator`；商业分发插件应由 `dbs-agent` 安装到 `public/vendor/dbs-plugins` 并通过 registry 加载。
