# dbs-admin

基于 **Laravel 12 + Vue 3 (Arco Design Pro)** 的后台管理系统框架。采用**约定优于配置**理念，后端定义元数据，前端动态渲染，零 Vue 代码即可完成 CRUD。

---

## 目录

- [架构总览](#架构总览)
- [快速开始](#快速开始)
- [Grid 网格系统](#grid-网格系统)
  - [列定义](#列定义)
  - [显示类型](#显示类型)
  - [修饰方法](#修饰方法)
  - [筛选器](#筛选器)
  - [操作按钮](#操作按钮)
  - [性能优化](#性能优化)
- [Form 表单系统](#form-表单系统)
  - [字段类型](#字段类型)
  - [验证](#验证)
  - [布局](#布局)
  - [条件显示](#条件显示)
  - [字段联动](#字段联动)
- [Show 详情系统](#show-详情系统)
- [Action 操作系统](#action-操作系统)
- [Notification 通知系统](#notification-通知系统)
- [API 响应格式](#api-响应格式)
- [Artisan 脚手架命令](#artisan-脚手架命令)
- [插件开发指南](#插件开发指南)
  - [创建插件](#创建插件)
  - [插件目录结构](#插件目录结构)
  - [插件路由](#插件路由)
  - [插件页面示例](#插件页面示例)
- [性能优化指南](#性能优化指南)
- [安全最佳实践](#安全最佳实践)
- [开发规范](#开发规范)

---

## 架构总览

```
laravel12/                          # Laravel 12 主项目
├── app/
│   ├── Admin/                      # 主系统后台
│   │   ├── Controllers/            # 控制器
│   │   ├── Models/                 # 模型
│   │   └── ...
├── plugins/                        # 插件目录
│   └── DemoPlugin/                 # 示例插件
└── web/                            # Vue 3 前端项目（Arco Pro）

dbs-admin/                          # 核心扩展包
├── src/
│   ├── Commands/                   # Artisan 脚手架
│   ├── Controllers/                # 控制器基类
│   ├── Form/                       # 表单系统
│   ├── Grid/                       # 网格系统
│   ├── Show/                       # 详情系统
│   ├── Notifications/              # 通知系统
│   └── Traits/                     # 共用 Trait
└── stubs/                          # 代码生成模板
```

**核心流程：** PHP 定义 Grid/Form/Action 元数据 → API 返回 JSON → 前端 DynamicCrud 自动渲染 Arco 组件。

---

## 快速开始

### 安装

```bash
cd laravel12
composer require dabashan/dbs-admin
php artisan vendor:publish --provider="Dabashan\DbsAdmin\DbsAdminServiceProvider"
npm install          # 安装前端依赖
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

通过链式调用为列指定显示类型，类似 Filament TextColumn 的完整能力：

```php
// 徽章（彩色标签）
->column('status', '状态')->badge([
    '1' => ['color' => 'green', 'label' => '启用'],
    '0' => ['color' => 'red', 'label' => '禁用'],
], 'light')

// 开关（表格内 AJAX 切换）
->column('status', '状态')->toggle()

// 图片缩略图
->column('avatar', '头像')->image(60, 60, circle: true)

// 标签组（数组/逗号分隔自动拆分）
->column('tags', '标签')->tags()

// 进度条
->column('progress', '进度')->progress(max: 100, showText: true)

// 数值条（水平色条）
->column('score', '评分')->bar()

// 颜色块
->column('color', '颜色')->color()

// 可复制文本
->column('token', '令牌')->copyable()

// 圆点状态
->column('is_vip', 'VIP')->dot(['1' => 'green', '0' => 'gray'])

// 日期格式化
->column('created_at', '创建时间')->date('Y-m-d')
->column('updated_at', '更新时间')->datetime('Y-m-d H:i:s')

// 金额（货币格式）
->column('price', '价格')->money('¥', 2)

// 计数（千分位）
->column('view_count', '浏览')->count()

// 自定义回调
->column('status', '状态')->using(fn ($value) => $value ? '正常' : '异常')
```

### 修饰方法

```php
->column('name', '姓名')
    ->width('200px')              // 列宽
    ->align('center')             // 对齐: left, center, right
    ->hidden()                    // 隐藏
    ->sortable()                  // 可排序
    ->searchable()                // 全局搜索
    ->default('-')                // 默认值
    ->limit(20)                   // 文本截断
    ->prefix('¥')                 // 前缀
    ->suffix('元')                // 后缀
    ->decimals(2)                 // 小数位数
    ->wrap()                      // 自动换行
    ->clickable()                 // 可点击
    ->action('编辑', 'link')      // 单元格操作
```

### 筛选器

```php
->filter('name', '姓名', 'like')                           // 模糊搜索
->filter('status', '状态', 'select')->options([1 => '启用', 0 => '禁用'])  // 下拉
->filter('created_at', '创建时间', 'between_date')         // 日期范围
->filter('price', '价格', 'gt')                            // 大于
->filterQuery('custom', '自定义', fn($q, $v) => $q->where('field', $v))  // 自定义回调
```

### 操作按钮

Filament 风格 Action 系统，支持三种模式：**弹窗（Modal）**、**抽屉（Drawer）**、**新页面（Page）**。

```php
use Dabashan\DbsAdmin\Grid\Action;

protected function grid(): Grid
{
    return Grid::make(User::query())
        ->column('id', 'ID')
        ->column('name', '姓名')
        // 默认操作：新增、编辑(drawer)、删除(确认)、批量删除
        ->createAction()
        ->editAction()
        ->deleteAction()
        ->batchDeleteAction()
        // 自定义操作
        ->action(Action::make('view', '查看')->row()->modal(['width' => 700]))
        ->action(Action::make('export', '导出')->header()->type('success')->icon('icon-download'))
        ->action(Action::make('audit', '审核')->row()->drawer(['width' => 800]));
}

// 覆写 configureActions 自定义
protected function configureActions(Grid $grid): void
{
    $grid->action(Action::make('export', '导出')
        ->header()
        ->type('success')
        ->icon('icon-download'));
}
```

**Action 位置：**
- `->row()` — 行操作（每行末尾）
- `->header()` — 头部操作（工具栏左侧）
- `->bulk()` — 批量操作（选中记录后显示）

**Action 模式：**
- `->modal()` — Arco Modal 弹窗，适合简短表单
- `->drawer()` — Arco Drawer 抽屉，适合编辑表单
- `->page()` — 新页面跳转（默认）
- `->confirm()` — 确认弹窗（危险操作）

### 性能优化

```php
// 仅查询需要的字段（减少 70%+ 内存）
->select(['id', 'name', 'status', 'created_at'])

// 预加载关联（N+1 问题）
->with(['category', 'author'])

// 条件查询
->when($request->filled('vip'), fn($q) => $q->where('is_vip', true))

// 自定义查询
->query(fn($q, $req) => $q->where('status', 1))

// 设置分页
->perPage(15)
->perPageOptions([10, 20, 50, 100])
```

---

## Form 表单系统

后端定义表单字段和验证，前端自动渲染 Arco Form。

### 字段类型

```php
protected function form(): Form
{
    return Form::make(User::class)
        // 基础输入
        ->text('name', '姓名')
        ->password('password', '密码')
        ->email('email', '邮箱')
        ->url('website', '网站')
        ->number('sort', '排序')
        ->textarea('description', '描述')
        ->hidden('user_id')

        // 下拉选择
        ->select('status', '状态')->options([1 => '启用', 0 => '禁用'])
        ->radio('gender', '性别')->options([1 => '男', 2 => '女'])
        ->checkbox('hobbies', '爱好')->options([...])

        // 高级选择
        ->treeSelect('category_id', '分类')->options([...])
        ->autoComplete('tag', '标签')->options([...])
        ->cascader('region', '地区')->options([...])

        // 日期时间
        ->date('birthday', '生日')
        ->datetime('publish_at', '发布时间')
        ->time('start_time', '开始时间')
        ->dateRange('date_range', '日期范围')

        // 上传
        ->image('avatar', '头像')
        ->images('gallery', '相册')
        ->file('attachment', '附件')
        ->files('attachments', '多个附件')

        // 交互
        ->switch('status', '状态')
        ->slider('progress', '进度')
        ->rate('rating', '评分')
        ->color('theme_color', '主题色')
        ->tags('tags', '标签')

        // 富文本和代码
        ->editor('content', '内容')        // wangEditor 富文本
        ->code('snippet', '代码')          // 代码编辑器
        ->icon('icon', '图标')             // 图标选择器
        ->html('<hr>')                     // HTML 分隔
        ->divider('分隔标题')              // 分割线
}
```

### 验证

```php
->text('name', '姓名')->required()
->text('email', '邮箱')->rules('email|unique:users,email')
->text('password', '密码')->rules('required|min:6|confirmed')
->number('age', '年龄')->min(1)->max(120)
->text('phone', '手机号')->rules('required|regex:/^1[3-9]\d{9}$/')
->image('avatar', '头像')->required()
```

### 字段修饰

```php
->text('name', '姓名')
    ->required()                          // 必填
    ->placeholder('请输入姓名')
    ->help('2-20 个字符')
    ->maxLength(20)
    ->minLength(2)
    ->prefix('¥')                         // 前缀（输入框内）
    ->suffix('元')                        // 后缀
    ->prepend('http://')                  // 前置标签
    ->append('.com')                      // 后置标签
    ->disabled()                          // 禁用
    ->readonly()                          // 只读
    ->default('default_value')            // 默认值
    ->rows(5)                             // textarea 行数
```

### Select 增强

```php
->select('category_id', '分类')
    ->options([...])
    ->searchableOptions()                 // 可搜索
    ->allowCreate()                       // 允许创建新选项
    ->clearable()                         // 显示清除按钮
    ->multiple()                          // 多选
```

### 上传增强

```php
->image('avatar', '头像')
    ->disk('public')                      // 存储磁盘
    ->path('avatars')                     // 存储路径
    ->accept(['jpg', 'png', 'gif'])       // 允许的文件类型
    ->maxUpload(5)                        // 最大上传数（多图片）
```

### 布局

```php
// Tab 分组
->tabs([
    ['label' => '基本信息', 'fields' => ['name', 'email', 'avatar']],
    ['label' => '扩展信息', 'fields' => ['description', 'tags', 'status']],
])

// 分栏布局
->columns(2)                              // 两列

// 分区块
->section('联系信息', ['phone', 'email', 'address'])
```

### 条件显示

```php
// 当 status == 1 时显示
->text('reason', '原因')
    ->displayWhen('status', '==', 1)

// 当 type in ['A', 'B'] 时显示
->text('extra', '额外字段')
    ->displayWhen('type', 'in', ['A', 'B'])
```

### 字段联动

```php
// 城市选项随省份变化
->select('province', '省份')
    ->options([...])
->select('city', '城市')
    ->depends(['province'])
    ->optionsFrom(fn($province) => City::where('province_id', $province)->pluck('name', 'id'))
```

---

## Show 详情系统

```php
protected function detail($id): Show
{
    return Show::make(User::findOrFail($id))
        ->field('id', 'ID')
        ->field('name', '姓名')
        ->field('email', '邮箱')->copyable()
        ->field('avatar', '头像')->image(100, 100)
        ->field('status', '状态')->badge()
        ->field('balance', '余额')->money()
        ->field('created_at', '创建时间')->date();
}
```

**Show 字段修饰：** `badge()`, `copyable()`, `image()`, `date()`, `money()`, `using()`

---

## Action 操作系统

Action 是类似 Filament Action 的完整操作抽象，支持三种展示模式：

```php
use Dabashan\DbsAdmin\Grid\Action;

// 行操作 — 弹窗编辑
Action::make('edit', '编辑')
    ->row()
    ->modal(['width' => 600, 'maskClosable' => false])

// 行操作 — 抽屉编辑
Action::make('edit', '编辑')
    ->row()
    ->drawer(['width' => 700])

// 行操作 — 新页面跳转
Action::make('view', '查看')
    ->row()
    ->page()
    ->route('user.show')

// 行操作 — 确认删除
Action::make('delete', '删除')
    ->row()
    ->type('danger')
    ->confirm(true, '确定要删除吗？此操作不可恢复。')

// 头部操作 — 工具栏按钮
Action::make('export', '导出')
    ->header()
    ->type('success')
    ->icon('icon-download')

// 批量操作
Action::make('batch-approve', '批量审核')
    ->bulk()
    ->type('primary')
    ->confirm(true, '确定要批量审核选中的 {count} 条记录吗？')

// 可见性控制
Action::make('audit', '审核')
    ->row()
    ->visible(fn($record) => $record->status === 'pending')
```

---

## Notification 通知系统

参考 Filament Notifications 设计的轻量通知系统，结合 Arco Design Message/Notification 组件。

### 用法

```php
use Dabashan\DbsAdmin\Notifications\Notification;

// 链式调用
Notification::make()
    ->title('操作成功')
    ->body('数据已保存')
    ->success()
    ->send();

// 快捷方法
Notification::success('保存成功');
Notification::error('操作失败', '请检查输入');
Notification::warning('注意', '该操作不可逆');
Notification::info('提示', '有 3 条待处理消息');

// 自定义通知
Notification::make('提醒')
    ->body('您有新的订单待处理')
    ->type('warning')
    ->duration(5000)
    ->closable(false)
    ->send();
```

### 前端自动渲染

前端 DynamicTable 组件在 API 响应中自动获取 `notifications` 数组并使用 Arco Notification 组件渲染。无需额外配置。

---

## API 响应格式

所有 API 响应使用统一格式：

```json
{
    "code": 20000,
    "msg": "success",
    "data": { ... },
    "traceId": "a1b2c3d4e5f6g7h8"
}
```

**响应方法（HasApiResponse Trait）：**

```php
$this->success($data, '成功消息')           // 200
$this->fail('错误消息', 400)               // 400
$this->error('服务器错误', 500)            // 500
$this->unauthorized()                      // 401
$this->forbidden()                         // 403
$this->notFound()                          // 404
$this->validationFail()                    // 422
$this->batchResult(5, 1, [...])            // 批量操作
$this->paginate($paginator, $items)        // 分页
```

---

## Artisan 脚手架命令

### make:admin — 创建主系统 CRUD

```bash
# 完整生成（Controller + Model + Vue 页面 + 路由 + 语言包）
php artisan make:admin User

# 选项
php artisan make:admin User --migration    # 同时生成迁移
php artisan make:admin User --no-model     # 跳过 Model
php artisan make:admin User --no-web       # 跳过前端文件
php artisan make:admin Order --view-name=order-mgmt  # 自定义视图目录
```

生成内容：
```
app/Admin/Controllers/UserController.php
app/Admin/Models/AdminUser.php
web/src/views/system/user/index.vue       # 6 行 DynamicCrud 页面
web/src/router/routes/modules/system-user.ts
web/src/views/system/user/locale/zh-CN.ts
web/src/views/system/user/locale/en-US.ts
```

### make:plugin — 创建插件骨架

```bash
php artisan make:plugin shop
php artisan make:plugin demo_plugin --force  # 覆盖已有
```

### make:plugin-page — 在插件中创建页面

```bash
# 在现有插件中添加页面
php artisan make:plugin-page shop product --vue
php artisan make:plugin-page shop category --vue --migration
php artisan make:plugin-page shop order --http  # Http 控制器
php artisan make:plugin-page shop tag --no-model --vue
```

---

## 插件开发指南

插件是独立的开发单元，拥有独立的 Controller、Model、Route、Config 和 ServiceProvider。

### 创建插件

```bash
php artisan make:plugin shop
```

生成结构：
```
plugins/Shop/
├── plugin.json                      # 插件描述（启用/禁用控制）
├── config/shop.php                  # 独立配置
├── Providers/
│   └── PluginServiceProvider.php    # 服务提供者（路由/中间件）
├── Admin/
│   ├── Controllers/
│   │   └── ShopController.php       # 后台管理控制器
│   └── routes.php                   # 后台路由
├── Http/
│   ├── Controllers/
│   │   └── ShopController.php       # 前端 API 控制器
│   └── routes.php                   # 前端路由
├── Models/                          # 模型
├── Services/                        # 服务层
├── Support/                         # 辅助类
├── database/migrations/             # 数据库迁移
└── static/                          # 静态资源
```

### 插件启用/禁用

编辑 `plugins/Shop/plugin.json`：

```json
{
    "name": "shop",
    "title": "商城插件",
    "version": "1.0.0",
    "enabled": true
}
```

设置 `"enabled": false` 后，插件的 ServiceProvider **完全不会加载**，零性能损耗。

### 插件路由

```php
// plugins/Shop/Admin/routes.php
use Illuminate\Support\Facades\Route;

Route::prefix('admin/plugin/shop')
    ->middleware(['admin'])
    ->group(function () {
        Route::apiResource('products', \Plugins\Shop\Admin\Controllers\ProductController::class);
        Route::apiResource('categories', \Plugins\Shop\Admin\Controllers\CategoryController::class);
    });
```

在 ServiceProvider 中注册：

```php
// plugins/Shop/Providers/PluginServiceProvider.php
public function boot(): void
{
    if (!$this->isEnabled()) return;

    $this->loadRoutesFrom(__DIR__ . '/../Admin/routes.php');
    $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    $this->mergeConfigFrom(__DIR__ . '/../config/shop.php', 'shop');
}
```

### 插件页面示例：商品管理

```bash
php artisan make:plugin-page shop product --vue
```

生成的控制器：

```php
// plugins/Shop/Admin/Controllers/ProductController.php
<?php

namespace Plugins\Shop\Admin\Controllers;

use Dabashan\DbsAdmin\Controllers\AdminController;
use Dabashan\DbsAdmin\Grid\Grid;
use Dabashan\DbsAdmin\Form\Form;
use Dabashan\DbsAdmin\Grid\Action;
use Plugins\Shop\Models\Product;

class ProductController extends AdminController
{
    protected string $model = Product::class;

    protected function grid(): Grid
    {
        return Grid::make(Product::query())
            ->select(['id', 'name', 'price', 'stock', 'status', 'image', 'created_at'])
            ->with(['category'])
            ->column('id', 'ID')->width('80px')
            ->column('image', '图片')->image(50, 50, circle: true)
            ->column('name', '商品名称')->searchable()
            ->column('price', '价格')->money()->align('right')
            ->column('stock', '库存')->badge([
                '0' => ['color' => 'red', 'label' => '缺货'],
            ])->align('center')
            ->column('status', '状态')->toggle()
            ->column('created_at', '创建时间')->date()->sortable()
            ->filter('name', '商品名称', 'like')
            ->filter('status', '状态', 'select')
                ->options([1 => '上架', 0 => '下架'])
            ->createAction()
            ->editAction()
            ->deleteAction()
            ->batchDeleteAction()
            ->action(Action::make('export', '导出')
                ->header()->type('success')->icon('icon-download'))
            ->perPage(20);
    }

    protected function form(): Form
    {
        return Form::make(Product::class)
            ->tabs([
                ['label' => '基本信息', 'fields' => ['name', 'category_id', 'image', 'price', 'stock']],
                ['label' => '详情', 'fields' => ['description', 'content']],
            ])
            ->text('name', '商品名称')->required()->maxLength(100)
            ->select('category_id', '分类')
                ->optionsFrom(fn() => \Plugins\Shop\Models\Category::pluck('name', 'id'))
                ->searchableOptions()
                ->required()
            ->image('image', '主图')->required()
            ->images('gallery', '相册')->maxUpload(10)
            ->number('price', '价格')->required()->precision(2)->min(0)
            ->number('stock', '库存')->required()->min(0)->default(0)
            ->switch('status', '状态')->default(true)
            ->textarea('description', '商品描述')->rows(3)
            ->editor('content', '商品详情');
    }

    protected function configureActions(Grid $grid): void
    {
        $grid->action(Action::make('view', '查看')
            ->row()->modal(['width' => 800]));
    }
}
```

生成的 Vue 页面（仅需 6 行）：

```vue
<template>
  <DynamicCrud api-prefix="/admin/plugin/shop/products"
    :breadcrumb="['menu.plugin.shop', 'menu.plugin.shop.product']"
    add-title="新增商品" edit-title="编辑商品" />
</template>
<script lang="ts" setup>
  import DynamicCrud from '@/components/dynamic/DynamicCrud.vue';
</script>
```

生成的模型：

```php
// plugins/Shop/Models/Product.php
<?php

namespace Plugins\Shop\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $table = 'plugin_shop_products';

    protected $fillable = [
        'name', 'category_id', 'image', 'price', 'stock',
        'status', 'description', 'content',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
        'status' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
}
```

### 通知在插件中的使用

```php
// 在控制器钩子中发送通知
protected function afterSave(Request $request, $model): void
{
    Notification::success('商品 "' . $model->name . '" 已保存');
}
```

---

## 性能优化指南

### 1. 仅查询需要的字段

```php
// 避免 SELECT *
Grid::make(User::query())
    ->select(['id', 'name', 'email', 'status', 'created_at'])
```

**效果：减少 70%+ 内存和网络传输**

### 2. 预加载关联

```php
// 避免 N+1 查询
Grid::make(Order::query())
    ->with(['user', 'items.product'])
```

### 3. 后端预格式化

框架内置 `formatRow()` 和 `formatValue()`，在后端完成日期格式化、金额格式化、文本截断等，前端直接渲染，减少 3-5 倍前端 JS 处理时间。

### 4. 分页优化

```php
->perPage(20)
->perPageOptions([10, 20, 50, 100])
```

默认 20 条，避免一次加载过多数据。

### 5. 插件零性能损耗

禁用的插件（`"enabled": false`）不会加载 ServiceProvider、不会注册路由、不会加载模型。**零代码执行、零内存占用。**

### 6. 索引建议

- 所有 `where`/`filter` 字段添加数据库索引
- 所有 `order by` 字段添加索引
- 复合查询使用联合索引

---

## 安全最佳实践

### 1. 模型批量赋值保护

```php
// 使用 $guarded = [] 时需确保 Controller 有验证
class User extends Model
{
    protected $guarded = [];
}

// Form 系统自动进行 Laravel 验证
->text('name', '姓名')->rules('required|max:255')
```

### 2. XSS 防护

- 所有用户输入在后端通过 `htmlspecialchars()` 转义
- `html()` 字段仅渲染白名单内容
- 富文本编辑器使用 HTML Purifier 过滤

### 3. SQL 注入防护

- 所有查询使用 Eloquent ORM 参数绑定
- 筛选器值自动进行类型验证
- 禁止用户直接操作 SQL

### 4. CSRF 保护

- 后台 API 自动应用 CSRF 中间件
- 所有 POST/PUT/DELETE 请求需要 CSRF Token

### 5. 权限控制

```php
// 在 Controller 中覆写
public function destroy($id)
{
    if (!auth()->user()->can('delete', $this->model)) {
        return $this->forbidden();
    }
    return parent::destroy($id);
}
```

---

## 开发规范

### 命名规范

- 控制器：`XxxController`（StudlyCase）
- 模型：`Xxx`（StudlyCase）
- 数据表：`admin_xxx`（snake_case，主系统）/ `plugin_{plugin}_{resource}`（插件）
- 路由：kebab-case
- Vue 组件：PascalCase

### 代码风格

```php
// Grid: 链式调用，每行一个方法
protected function grid(): Grid
{
    return Grid::make(Model::query())
        ->column('id', 'ID')->sortable()
        ->column('name', '名称')->searchable()
        ->perPage(20);
}

// Form: 字段定义一行一个
protected function form(): Form
{
    return Form::make(Model::class)
        ->text('name', '名称')->required()
        ->switch('status', '状态')->default(true);
}
```

### Git 提交规范

```
feat: 新增用户管理模块
fix: 修复筛选器日期范围问题
perf: 优化 Grid 查询使用 select 减少字段
docs: 更新插件开发文档
refactor: 重构 Action 系统
```
