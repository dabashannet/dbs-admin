# dbs-admin

基于 **Laravel 12 + Vue 3 (Arco Design Pro)** 的后台管理系统框架。采用**约定优于配置**理念，后端定义元数据，前端动态渲染，零 Vue 代码即可完成 CRUD。

---

## 目录

- [架构总览](#架构总览)
- [快速开始](#快速开始)
- [Grid 网格系统](#grid-网格系统)
- [Form 表单系统](#form-表单系统)
- [Action 操作系统](#action-操作系统)
- [插件开发指南](#插件开发指南)
- [代码生成器](#代码生成器)
- [Artisan 脚手架命令](#artisan-脚手架命令)

---

## 架构总览

```
dbs-admin/                          # 核心扩展包
├── src/
│   ├── Commands/                   # Artisan 脚手架命令
│   ├── Controllers/                # 控制器（含代码生成器）
│   ├── Form/                       # 表单系统
│   ├── Grid/                       # 网格系统
│   └── Traits/                     # 共用 Trait（HasApiResponse 等）
└── stubs/                          # 代码生成模板
```

**核心流程：** PHP 定义 Grid/Form 元数据 → API 返回 JSON → 前端 DynamicCrud 自动渲染 Arco 组件。

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

## Action 操作系统

支持三种模式：**弹窗（Modal）**、**抽屉（Drawer）**、**新页面（Page）**。

```php
Action::make('edit', '编辑')->row()->modal(['width' => 600])
Action::make('edit', '编辑')->row()->drawer(['width' => 700])
Action::make('delete', '删除')->row()->type('danger')->confirm(true)
Action::make('export', '导出')->header()->type('success')
Action::make('approve', '批量审核')->bulk()->confirm(true)
```

---

## 插件开发指南

### 设计理念

插件采用**前后端完全内聚**架构，前端资源（Vue 页面、路由、静态资源）全部放在插件自己的 `resources/` 目录下，与 PHP 代码同属一个插件单元。

```
plugins/{PluginName}/                    # StudlyCase 命名
├── plugin.json                          # 插件元信息
├── PluginServiceProvider.php            # 服务提供者
├── Admin/                               # 后台管理
│   ├── Controllers/{Name}Controller.php
│   └── routes.php
├── Http/                                # 业务端 API
│   ├── Controllers/{Name}Controller.php
│   └── routes.php
├── Models/{Name}.php
├── resources/                           # 前端资源（新增）
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

### 前端路由自动发现

Vite 构建时自动扫描 `plugins/*/resources/routes/*.ts`，已安装插件的前端路由无需手动注册，开箱即用。

路径别名：
- `@/` → `web/src/`（系统前端）
- `@resource/` → `resource/views/`（核心模块视图）
- `@plugins/` → `plugins/`（插件资源）

### 快速创建插件

```bash
php artisan make:plugin shop
composer dump-autoload
php artisan migrate    # 如有迁移文件
```

### 插件启用/禁用

```json
{
    "name": "shop",
    "title": "商城插件",
    "version": "1.0.0",
    "enabled": true
}
```

禁用插件（`"enabled": false`）时，ServiceProvider 完全不会加载，**零性能损耗**。

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

插件模式生成的页面默认使用 `DynamicCrud` 渲染（而不是静态表格/表单模板），因此在代码生成器中配置的：
- 字段（Form）
- 表格列（Grid Columns）
- 筛选器（Grid Filters）
会完整体现在最终页面效果中。

插件后台路由会额外生成 DynamicCrud 所需的接口（在 `Route::apiResource()` 之前注册，避免被 `{id}` 路由误匹配）：
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

### make:plugin-page — 在插件中创建页面

```bash
php artisan make:plugin-page shop product --vue
php artisan make:plugin-page shop order --http  # Http 控制器
```
