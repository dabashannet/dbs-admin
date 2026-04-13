# dbs-admin 开发文档

基于 Laravel 12 + Vue 3 (Arco Design) 的后台管理系统框架，采用 **约定优于配置** 理念，类似 Dcat-Admin 的开发体验——后端定义元数据，前端动态渲染。

---

## 架构总览

```
laravel12/                          # Laravel 12 主项目
├── app/
│   ├── Admin/                      # 主系统后台
│   │   ├── Controllers/            # 控制器
│   │   ├── Models/                 # 模型
│   │   ├── Services/               # 服务类
│   │   ├── Middleware/             # 中间件
│   │   ├── Providers/              # 服务提供者
│   │   ├── route.php               # 后台路由
│   │   └── bootstrap.php           # 引导文件
│   └── Providers/
│       ├── AppServiceProvider.php
│       └── PluginServiceProvider.php  # 插件加载入口
├── plugins/                        # 插件目录（独立开发单元）
│   ├── DemoPlugin/                 # 示例插件
│   └── PLUGIN_DEV.md               # 插件开发指南
├── routes/                         # Laravel 默认路由
└── web/                            # Vue 3 前端项目（Arco Pro）

dbs-admin/                          # 核心扩展包
├── src/
│   ├── Commands/                   # Artisan 脚手架命令
│   ├── Controllers/                # 控制器基类
│   ├── Form/                       # 表单系统
│   ├── Grid/                       # 网格系统
│   ├── Show/                       # 详情系统
│   ├── Models/                     # 模型基类
│   ├── Action/                     # 操作类
│   └── Traits/                     # 共用 Trait
└── stubs/                          # 文件生成模板
```

---

## 快速开始

### 安装

```bash
cd laravel12
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run dev
```

### 核心概念：Grid + Form = CRUD

只需继承 `AdminController` 并实现两个方法，即可获得完整的增删改查功能：

```php
<?php

namespace App\Admin\Controllers;

use Dabashan\DbsAdmin\Controllers\AdminController;
use Dabashan\DbsAdmin\Grid\Grid;
use Dabashan\DbsAdmin\Form\Form;
use App\Admin\Models\User;

class UserController extends AdminController
{
    protected string $model = User::class;

    protected function grid(): Grid
    {
        return Grid::make(User::query())
            ->column('id', 'ID')->sortable()
            ->column('name', '姓名')->searchable()
            ->column('status', '状态')
            ->column('created_at', '创建时间')->sortable()
            ->filter('name', '姓名', 'like')
            ->filter('status', '状态', 'equal')
            ->perPage(15);
    }

    protected function form(): Form
    {
        return Form::make(User::class)
            ->text('name', '姓名')->required()
            ->text('email', '邮箱')->rules('email')
            ->switch('status', '状态')->default(true);
    }
}
```

后端返回 **元数据 JSON**，前端 Arco Table/Form 根据元数据动态渲染。无需手写 Vue 页面即可完成基础 CRUD。

---

## 脚手架命令（Scaffolding）

### 1. `make:admin` — 创建主系统后台资源

一键生成 Controller + Model + Vue 页面 + 路由 + 国际化文件：

```bash
php artisan make:admin User
php artisan make:admin Article --view-name=article-manage  # 自定义前端目录名
php artisan make:admin Log --no-web                         # 仅后端
php artisan make:admin Tag --no-model                         # 不生成模型
php artisan make:admin Order --migration                     # 同时生成迁移
php artisan make:admin Category --force                      # 覆盖已有文件
```

| 选项 | 说明 |
|------|------|
| `--view-name=` | 自定义 Vue 视图目录名（kebab-case） |
| `--no-model` | 跳过 Model 文件生成 |
| `--no-web` | 跳过 Vue 前端文件生成 |
| `--migration` | 生成数据库迁移文件 |
| `--force` | 覆盖已存在的文件 |

生成的文件清单：

| 文件 | 路径 |
|------|------|
| Controller | `app/Admin/Controllers/{Name}Controller.php` |
| Model | `app/Admin/Models/Admin{Name}.php` |
| Vue 页面 | `web/src/views/system/{view-name}/index.vue` |
| API 文件 | `web/src/api/{name}.ts` |
| 路由 | `web/src/router/routes/modules/system-{name}.ts` |
| 中文语言包 | `web/src/views/system/{view-name}/locale/zh-CN.ts` |
| 英文语言包 | `web/src/views/system/{view-name}/locale/en-US.ts` |

> **注意**：生成后需手动在语言包入口导入新增的 locale 文件。

---

### 2. `make:plugin` — 创建完整插件

一键生成插件骨架，包含完整的目录结构和示例代码：

```bash
php artisan make:plugin shop
php artisan make:plugin user-center
```

生成后的下一步：

```bash
# 1. 刷新自动加载
composer dump-autoload

# 2. 编辑 plugin.json，设置 enabled: true
# plugins/Shop/plugin.json

# 3. 如有迁移文件，执行迁移
php artisan migrate
```

生成的插件结构：

```
plugins/Shop/
├── plugin.json                          # 插件元信息
├── config/shop.php                      # 独立配置
├── Providers/PluginServiceProvider.php  # 服务提供者（自动加载入口）
├── Admin/
│   ├── Controllers/ShopController.php   # 后台控制器
│   └── routes.php                       # 后台路由
├── Http/
│   ├── Controllers/ShopController.php   # 业务控制器
│   └── routes.php                       # 业务路由
├── Models/                              # 数据模型
├── Services/                            # 业务服务
├── Support/                             # 辅助类
├── database/migrations/                 # 迁移文件
└── static/                              # 静态资源
```

---

### 3. `make:plugin-page` — 给已有插件添加页面

为已存在的插件快速添加 CRUD 页面：

```bash
# 添加后台管理页面（Admin 端，默认）
php artisan make:plugin-page shop product

# 指定 Admin 控制器（继承 AdminController + Grid/Form）
php artisan make:plugin-page shop product --admin

# 指定 Http 控制器（普通业务端）
php artisan make:plugin-page shop product --http

# 生成 Vue 前端文件
php artisan make:plugin-page shop product --vue

# 不生成模型
php artisan make:plugin-page shop product --no-model

# 生成迁移
php artisan make:plugin-page shop product --migration

# 覆盖已有文件
php artisan make:plugin-page shop product --force --vue
```

| 选项 | 说明 |
|------|------|
| `--admin` | 生成 Admin 控制器（默认，继承 AdminController） |
| `--http` | 生成 Http 业务控制器 |
| `--no-model` | 跳过 Model 文件生成 |
| `--migration` | 生成数据库迁移文件 |
| `--vue` | 生成 Vue 前端文件（页面 + API + 路由 + 国际化） |
| `--force` | 覆盖已存在的文件 |

> **提示**：`--vue` 选项会生成完整的前端文件，包括 Vue 页面、API 文件、路由模块和国际化文件。生成后需要：
> 1. 在 `web/src/router/plugin.ts` 中注册路由
> 2. 在 `web/src/locale/zh-CN.ts` 和 `en-US.ts` 中导入语言包

---

## Grid 网格系统

### 基本用法

```php
protected function grid(): Grid
{
    return Grid::make(User::query())
        ->column('id', 'ID')->sortable()
        ->column('name', '姓名')->searchable()
        ->column('avatar', '头像')
        ->column('status', '状态')
        ->column('created_at', '创建时间')->sortable()
        ->with(['roles', 'profile'])       // 预加载关联
        ->filter('name', '姓名', 'like')   // 筛选器
        ->filter('status', '状态', 'select')->options([
            0 => '禁用',
            1 => '启用',
        ])
        ->filter('created_at', '创建时间', 'between_date')
        ->perPage(15);                     // 每页数量
}
```

### Grid 列显示类型（Column Display）

 TextColumn 的完整显示能力，通过链式方法直接渲染：

| 方法 | 渲染效果 | 示例 |
|------|---------|------|
| `badge($colors)` | 彩色标签 | `->column('status')->badge([1 => 'green', 0 => 'red'])` |
| `toggle()` | 开关切换 | `->column('enabled')->toggle()` |
| `image($w, $h)` | 缩略图 | `->column('avatar')->image(60, 60, true)` |
| `tags()` | 标签组 | `->column('tags')->tags()` |
| `progress($max)` | 进度条 | `->column('progress')->progress(100)` |
| `bar()` | 数值色条 | `->column('score')->bar()` |
| `color()` | 色块预览 | `->column('hex')->color()` |
| `copyable()` | 点击复制 | `->column('api_key')->copyable()` |
| `dot($colors)` | 圆点状态 | `->column('online')->dot([1 => 'green'])` |
| `date($fmt)` | 日期格式化 | `->column('created_at')->date('Y/m/d')` |
| `datetime($fmt)` | 日期时间格式化 | `->column('updated_at')->datetime()` |
| `money($sym, $dec)` | 货币格式化 | `->column('price')->money('¥', 2)` |
| `count()` | 计数格式化 | `->column('views')->count()` |
| `using($cb)` | 自定义回调 | `->column('name')->using(fn($v, $row) => strtoupper($v))` |

### Grid 列修饰方法

| 方法 | 说明 |
|------|------|
| `default($val)` | 空值默认显示 |
| `limit($len)` | 文本截断（超出显示...） |
| `clickable()` | 点击跳转详情 |
| `prefix($text)` | 前缀文本 |
| `suffix($text)` | 后缀文本 |
| `decimals($n)` | 小数位数 |
| `toggleable()` | 用户可切换显示/隐藏 |
| `wrap()` | 文本自动换行 |

### Grid 高级功能

```php
protected function grid(): Grid
{
    return Grid::make(User::query())
        // 性能优化：仅查询指定字段
        ->select(['id', 'name', 'email', 'status'])
        // 预加载关联
        ->with(['roles', 'profile'])
        // 条件筛选（ when）
        ->when($request->has('vip'), fn($q) => $q->where('is_vip', true))
        // 自定义查询回调
        ->query(function ($query, $request) {
            return $query->where('active', 1);
        })
        // 复杂筛选器（自定义查询回调）
        ->filterQuery('range', '金额范围', function ($query, $value) {
            $query->whereBetween('amount', $value);
        }, 'custom')
        // 每页选项
        ->perPageOptions([10, 20, 50, 100, 200])
        // 行操作按钮
        ->action('编辑', 'primary')
        ->action('删除', 'danger')
        // 批量操作
        ->batchAction('batch_delete', '批量删除', 'danger')
        ->batchAction('batch_enable', '批量启用', 'primary')
        // 导出支持
        ->exportable()
        // 刷新按钮
        ->refreshable()
        // 外观配置
        ->showSelectAll()
        ->showBorder()
        ->emptyText('暂无数据')
        ->defaultSort('created_at', 'desc')
        ->perPage(20);
}
```

| 方法 | 说明 |
|------|------|
| `select([...])` | 仅查询指定字段（**性能优化**） |
| `when($cond, $cb)` | 条件筛选 |
| `query($cb)` | 完全自定义查询 |
| `filterQuery($key, $title, $cb)` | 复杂筛选器 |
| `perPageOptions([...])` | 每页选项 |
| `action($label, $type)` | 行操作按钮 |
| `batchAction($key, $label)` | 批量操作 |
| `exportable()` | 导出按钮 |
| `refreshable()` | 刷新按钮 |
| `showSelectAll()` | 全选复选框 |
| `showBorder()` | 表格边框 |
| `showPagination()` | 分页控制 |
| `emptyText($text)` | 空数据提示 |
| `defaultSort($field, $order)` | 默认排序 |

### 筛选器类型

| 类型 | SQL | 说明 |
|------|-----|------|
| `like` | `WHERE col LIKE '%val%'` | 模糊匹配 |
| `equal` / `select` | `WHERE col = 'val'` | 精确匹配 |
| `between_date` | `WHERE col BETWEEN start AND end` | 日期范围 |
| `in` | `WHERE col IN (...)` | 多选 |
| `gt` | `WHERE col > val` | 大于 |
| `lt` | `WHERE col < val` | 小于 |
| `between` | `WHERE col BETWEEN a AND b` | 数值范围 |
| `custom` | 自定义回调 | 完全自定义逻辑 |

### 筛选器增强方法

| 方法 | 说明 | 示例 |
|------|------|------|
| `options([...])` | 下拉选项 | `->filter('status', '状态', 'select')->options([...])` |
| `default($val)` | 默认值 | `->filter('status', '状态')->default(1)` |
| `multiple()` | 多选模式 | `->filter('tags', '标签', 'in')->multiple()` |
| `placeholder($text)` | 占位文本 | `->filter('name', '姓名')->placeholder('输入姓名')` |

### Grid 返回的 JSON 结构

```json
{
    "code": 20000,
    "msg": "success",
    "data": {
        "columns": [
            {"key": "id", "title": "ID", "sortable": true, "displayType": "number"},
            {"key": "name", "title": "姓名", "searchable": true, "displayType": "text"},
            {"key": "status", "title": "状态", "displayType": "badge", "displayOptions": {"colors": {"1": "green", "0": "red"}}},
            {"key": "avatar", "title": "头像", "displayType": "image", "displayOptions": {"width": 40, "height": 40}},
            {"key": "created_at", "title": "创建时间", "sortable": true, "displayType": "datetime"}
        ],
        "filters": [
            {"key": "name", "title": "姓名", "type": "like"},
            {"key": "status", "title": "状态", "type": "select", "options": {"0": "禁用", "1": "启用"}}
        ],
        "items": [
            {"id": 1, "name": "张三", "status": "1", "avatar": "/uploads/avatar.jpg", "created_at": "2026-04-13 10:00:00"}
        ],
        "current_page": 1,
        "total": 100,
        "per_page": 20,
        "last_page": 5,
        "per_page_options": [10, 20, 50, 100],
        "actions": [{"key": "export", "label": "导出"}],
        "batchActions": [{"key": "batch_delete", "label": "批量删除", "type": "danger", "confirm": true}],
        "showSelectAll": true,
        "showPagination": true,
        "showBorder": true,
        "emptyText": "暂无数据"
    },
    "traceId": "a1b2c3d4e5f6a7b8"
}
```

### 列表请求参数

| 参数 | 类型 | 说明 |
|------|------|------|
| `page` | int | 页码（默认 1） |
| `pageSize` | int | 每页数量 |
| `keyword` / `search` | string | 全局关键字搜索（匹配 searchable 列） |
| `sortField` | string | 排序字段 |
| `sortOrder` | string | 排序方向：`ascend` / `descend` |
| `{filterKey}` | any | 各筛选器的值 |

---

## Form 表单系统

### 基本用法

```php
protected function form(): Form
{
    return Form::make(User::class)
        ->text('username', '用户名')->required()->rules('unique:users')
        ->password('password', '密码')->required()->creationOnly()
        ->text('name', '姓名')->required()
        ->select('role_id', '角色')->options(Role::all()->pluck('name', 'id'))
        ->image('avatar', '头像')
        ->switch('status', '状态')->default(true)
        ->textarea('bio', '简介')->placeholder('请输入个人简介')->help('最多 200 字')
        ->columns(2); // 两列布局
}
```

### 字段类型（Filament 级别）

#### 基础输入

| 方法 | Arco 组件 | 说明 |
|------|-----------|------|
| `text($key, $label)` | `<a-input>` | 文本输入 |
| `password($key, $label)` | `<a-input-password>` | 密码输入 |
| `textarea($key, $label)` | `<a-textarea>` | 多行文本 |
| `number($key, $label)` | `<a-input-number>` | 数字输入 |
| `email($key, $label)` | `<a-input type="email">` | 邮箱输入 |
| `url($key, $label)` | `<a-input type="url">` | URL 输入 |
| `hidden($key)` | `<input type="hidden">` | 隐藏字段 |

#### 选择类

| 方法 | Arco 组件 | 说明 |
|------|-----------|------|
| `select($key, $label)` | `<a-select>` | 下拉选择 |
| `radio($key, $label)` | `<a-radio-group>` | 单选按钮组 |
| `checkbox($key, $label)` | `<a-checkbox-group>` | 多选框组 |
| `treeSelect($key, $label)` | `<a-tree-select>` | 树形选择 |
| `autoComplete($key, $label)` | `<a-auto-complete>` | 自动补全 |
| `cascader($key, $label)` | `<a-cascader>` | 级联选择 |

#### 日期时间

| 方法 | Arco 组件 | 说明 |
|------|-----------|------|
| `date($key, $label)` | `<a-date-picker>` | 日期选择 |
| `dateTime($key, $label)` | `<a-date-picker showTime>` | 日期时间选择 |
| `time($key, $label)` | `<a-time-picker>` | 时间选择 |
| `dateRange($key, $label)` | `<a-range-picker>` | 日期范围选择 |
| `year($key, $label)` | `<a-date-picker mode="year">` | 年份选择 |
| `month($key, $label)` | `<a-date-picker mode="month">` | 月份选择 |

#### 上传类

| 方法 | Arco 组件 | 说明 |
|------|-----------|------|
| `image($key, $label)` | `<a-upload>` 单图 | 图片上传 |
| `images($key, $label)` | `<a-upload>` 多图 | 多图上传 |
| `file($key, $label)` | `<a-upload>` 单文件 | 文件上传 |
| `files($key, $label)` | `<a-upload>` 多文件 | 多文件上传 |

#### 特殊类

| 方法 | Arco 组件 | 说明 |
|------|-----------|------|
| `switch($key, $label)` | `<a-switch>` | 开关 |
| `slider($key, $label)` | `<a-slider>` | 滑块 |
| `rate($key, $label)` | `<a-rate>` | 评分 |
| `color($key, $label)` | `<a-color-picker>` | 颜色选择 |
| `tags($key, $label)` | `<a-input-tag>` | 标签输入 |
| `editor($key, $label)` | 富文本编辑器 | 富文本（wangEditor/tinymce） |
| `code($key, $label)` | 代码编辑器 | 代码输入 |
| `icon($key, $label)` | 图标选择器 | 图标选择 |
| `html($content)` | HTML | 静态 HTML |
| `divider($text)` | 分割线 | 分隔线 |

### 字段方法

| 方法 | 说明 |
|------|------|
| `required()` | 必填 |
| `rules($rules)` | Laravel 验证规则字符串 |
| `creationOnly()` | 仅创建时显示 |
| `updateOnly()` | 仅更新时显示 |
| `default($value)` | 默认值 |
| `placeholder($text)` | 占位文本 |
| `help($text)` | 帮助提示 |
| `options($array)` | 下拉选项 |
| `multiple()` | 多选 |
| `max($value)` | 最大值/长度 |
| `min($value)` | 最小值/长度 |
| `disabled()` | 禁用 |
| `readonly()` | 只读 |
| `clearable()` | 显示清除按钮 |
| `searchableOptions()` | Select 可搜索 |
| `allowCreate()` | Select 允许创建新选项 |
| `prefix($text)` | 前缀文本（input 内前置） |
| `suffix($text)` | 后缀文本（input 内后置） |
| `prepend($text)` | 前置 addon（input 外） |
| `append($text)` | 后置 addon（input 外） |
| `maxLength($len)` | 最大长度 |
| `step($v)` | 数字输入步长 |
| `precision($v)` | 数字输入精度 |
| `format($fmt)` | 日期时间格式 |
| `rows($n)` | textarea 行数 |
| `disk($name)` | 上传磁盘 |
| `path($path)` | 上传路径 |
| `optionsFrom($cb)` | 动态选项（运行时回调） |

### 条件显示与联动

```php
Form::make(Model::class)
    // 条件显示：当 status 字段等于 1 时显示
    ->text('vip_expire', 'VIP 到期')
        ->displayWhen('status', '==', 1)

    // 字段联动：当 category 变化时，动态加载 sub_category 选项
    ->select('sub_category', '子分类')
        ->depends(['category'])
        ->optionsFrom(fn() => SubCategory::where('parent_id', request('category'))->pluck('name', 'id'))

    // Select 可搜索 + 允许创建新选项
    ->select('tag', '标签')
        ->options(Tag::all()->pluck('name', 'id'))
        ->searchableOptions()
        ->allowCreate();
```

### 布局方法

```php
Form::make(Model::class)
    // 两列布局
    ->columns(2)

    // 或者 Tab 分组
    ->tabs([
        ['label' => '基础信息', 'fields' => ['name', 'email']],
        ['label' => '扩展信息', 'fields' => ['bio', 'avatar']],
    ])

    // 或者分区块
    ->section('基础信息', ['name', 'email'])
    ->section('扩展信息', ['bio', 'avatar']);

// 表单配置
Form::make(Model::class)
    ->submitText('保存')
    ->resetText('重置')
    ->labelWidth('120px')
    ->showReset(true);
```

### 验证流程

```
store (POST) → Form::validate($request, 'create')
               → 收集所有字段规则，过滤 updateOnly 字段
               → $request->validate($rules)

update (PUT) → Form::validate($request, 'update')
               → 收集所有字段规则，过滤 creationOnly 字段
               → 将 required 改为 sometimes
               → $request->validate($rules)
```

---

## Show 详情系统

### 基本用法

```php
protected function detail($id): Show
{
    return Show::make($this->model::findOrFail($id))
        ->with(['roles', 'profile']);  // 预加载关联
        // ->field('id', 'ID')          // 可选：指定展示字段
        // ->field('name', '姓名');
}
```

### 字段显示类型

```php
Show::make($model)
    ->field('id', 'ID')->label()
    ->field('name', '姓名')
    ->field('avatar', '头像')->image(200, 150)
    ->field('status', '状态')->badge([1 => 'green', 0 => 'red'])
    ->field('created_at', '创建时间')->datetime()
    ->field('price', '价格')->money('¥', 2)
    ->field('api_key', '密钥')->copyable()
    ->field('hex_color', '颜色')->color()
    ->field('tags', '标签')->tags()
    ->field('bio', '简介')->limit(200)
    ->field('custom', '自定义')->using(fn($v, $row) => strtoupper($v));

// 快捷方法（链式）
Show::make($model)
    ->text('name', '姓名')
    ->date('created_at', '创建时间')
    ->money('price', '价格', '¥')
    ->badge('status', '状态', [1 => 'green', 0 => 'red'])
    ->image('avatar', '头像', 200, 150);
```

### 布局与关联展示

```php
Show::make($model)
    ->title('用户详情')
    ->labelWidth('120px')
    // Tab 分组
    ->tabs([
        ['label' => '基础信息', 'fields' => ['id', 'name', 'email']],
        ['label' => '扩展信息', 'fields' => ['bio', 'avatar']],
    ])
    // 关联数据展示
    ->relation('orders', '订单列表')
    ->relation('logs', '操作日志');
```

不指定 `field()` 时，返回模型全部数据；指定后只返回指定字段。后端会预格式化 display 字段（日期、金额等），减轻前端负担。

---

## API 响应格式

### 统一响应格式

所有继承 `AdminController` 的控制器使用统一响应格式：

```json
{
    "code": 20000,
    "msg": "success",
    "data": { ... },
    "traceId": "a1b2c3d4e5f6a7b8"
}
```

错误响应：

```json
{
    "code": 422,
    "msg": "验证失败",
    "data": [],
    "traceId": "a1b2c3d4e5f6a7b8"
}
```

### 响应方法

| 方法 | 用途 |
|------|------|
| `$this->success($data, $msg)` | 成功响应 |
| `$this->fail($msg, $code)` | 失败响应 |
| `$this->error($msg, $code)` | 服务器错误 |
| `$this->unauthorized($msg)` | 未认证 |
| `$this->forbidden($msg)` | 权限不足 |
| `$this->notFound($msg)` | 资源不存在 |
| `$this->validationFail($errors, $msg)` | 验证错误 |
| `$this->batchResult($success, $failed)` | 批量操作结果 |
| `$this->paginate($paginator, $items)` | 分页响应（带 meta） |

### AdminController 新增接口

| 路由 | 方法 | 说明 |
|------|------|------|
| `GET /{resource}` | `index` | 列表（含 columns/filters/items） |
| `POST /{resource}` | `store` | 创建 |
| `GET /{resource}/{id}` | `show` | 详情（含 data + schema） |
| `PUT /{resource}/{id}` | `update` | 更新 |
| `DELETE /{resource}/{id}` | `destroy` | 删除（支持批量） |
| `GET /{resource}/form-schema` | `formSchema` | 表单 Schema |
| `GET /{resource}/show-schema/{id}` | `showSchema` | 详情 Schema |
| `GET /{resource}/grid-meta` | `gridMeta` | Grid 元数据（不含数据） |
| `POST /{resource}/batch-update` | `batchUpdate` | 批量更新 |
| `POST /{resource}/batch-destroy` | `batchDestroy` | 批量删除 |
| `POST /{resource}/{id}/toggle` | `toggle` | 状态切换（AJAX switch） |

---

## 动态渲染引擎（Dynamic Rendering）

整个框架的核心设计理念：**后端定义元数据，前端自动渲染**。开发者只需写 PHP，不需要碰 Vue。

### 工作原理

```
PHP Controller                    Vue 前端
┌──────────────────┐              ┌─────────────────────────┐
│ grid() → Grid    │ ──GET /api──→│ DynamicTable.vue        │
│  - columns       │              │  - 读取 columns 元数据   │
│  - filters       │              │  - 自动渲染筛选器        │
│  - items         │              │  - 自动渲染 Arco Table  │
│                  │              │  - 自动处理分页/排序     │
│ form() → Form    │ ←GET schema─ │ DynamicForm.vue         │
│  - fields        │              │  - 读取 fields 元数据    │
│  - layout        │              │  - 自动渲染 Arco Form    │
│  - config        │              │  - 30+ 字段类型映射      │
└──────────────────┘              └─────────────────────────┘
```

### 动态组件说明

#### DynamicCrud.vue — CRUD 主页面

所有 CRUD 页面共用这一个组件。`make:admin` 和 `make:plugin-page --vue` 生成的 Vue 页面仅 6 行代码：

```vue
<template>
  <DynamicCrud
    api-prefix="/admin/users"
    :breadcrumb="['menu.system', 'menu.system.user']"
    add-title="新增用户"
    edit-title="编辑用户"
  />
</template>
<script lang="ts" setup>
  import DynamicCrud from '@/components/dynamic/DynamicCrud.vue';
</script>
```

功能包括：
- 自动从 `/admin/users` 获取 Grid 数据并渲染表格
- 自动从 `/admin/users/form-schema` 获取表单定义并渲染
- 筛选器、分页、排序自动生效
- 新增/编辑弹窗自动渲染
- 删除（含确认弹窗）、批量操作自动生效
- 状态切换（表格内 switch）自动 AJAX 更新

#### DynamicTable.vue — 动态表格

| PHP Grid 定义 | 前端渲染效果 |
|--------------|-------------|
| `->column('name')->badge([...])` | Arco Tag 彩色标签 |
| `->column('avatar')->image()` | Arco Image 缩略图 |
| `->column('status')->toggle()` | Arco Switch 开关（AJAX 切换） |
| `->column('progress')->progress(100)` | Arco Progress 进度条 |
| `->column('color')->color()` | Arco Color Picker 色块 |
| `->column('api_key')->copyable()` | Arco Typography 可复制 |
| `->column('created_at')->datetime()` | 自动日期格式化 |
| `->column('price')->money('¥', 2)` | 红色金额显示 |

#### DynamicForm.vue — 动态表单

| PHP Form 定义 | 前端渲染效果 |
|--------------|-------------|
| `->text('name', '名称')` | Arco Input |
| `->select('role', '角色')->options(...)` | Arco Select（支持 searchable/allowCreate） |
| `->date('birthday', '生日')` | Arco DatePicker |
| `->image('avatar', '头像')` | Arco Upload 单图 |
| `->switch('status', '状态')` | Arco Switch |
| `->rate('score', '评分')` | Arco Rate |
| `->editor('content', '内容')` | 富文本编辑器 |

**条件显示**：`->displayWhen('status', '==', 1)` → 当 status 字段为 1 时才显示该字段

**字段联动**：`->depends(['category'])->optionsFrom(fn() => ...)` → 依赖字段变化时重新加载选项

---

## 模型基类

### `BaseAdminModel`

```php
use Dabashan\DbsAdmin\Models\BaseAdminModel;

class User extends BaseAdminModel
{
    protected $table = 'admin_users';
    protected $fillable = ['name', 'email', 'status'];
}
```

提供的全局作用域：

| 作用域 | 说明 |
|--------|------|
| `latestFirst()` | 按 created_at 倒序 |
| `oldestFirst()` | 按 created_at 正序 |
| `createdBetween($start, $end)` | 按创建时间范围筛选 |

策略：使用 `protected $guarded = []`（全开放批量赋值），由 Controller 层的 Form 验证控制字段白名单。

---

## 插件系统

### 核心概念

插件是独立的开发单元，放置在 `plugins/` 目录。每个插件拥有：

- **独立的 ServiceProvider** — 自动注册配置、迁移、路由
- **独立的目录结构** — Models、Services、Controllers、Views
- **隔离的路由** — Admin 端 `/admin/plugin/{name}/`，业务端 `/api/plugin/{name}/`
- **隔离的数据表** — 建议前缀 `plugin_{name}_`
- **启用/禁用** — 通过 `plugin.json` 的 `enabled` 字段控制

### 插件加载机制

Laravel 启动流程：

```
Laravel 启动
    ↓
PluginServiceProvider::boot()
    ↓
PluginManager::boot($app)
    ↓
扫描 plugins/ 目录，读取 plugin.json
    ↓
enabled: false → 跳过
enabled: true → 注册 PluginServiceProvider
    ↓
加载路由、配置、迁移
```

当 `enabled: false` 时，插件完全不加载，对主系统零性能损耗。

### plugin.json 配置

```json
{
    "name": "shop",
    "title": "商城插件",
    "description": "电商管理功能模块",
    "version": "1.0.0",
    "author": "Your Name",
    "enabled": true,
    "icon": "icon-shopping-cart",
    "permissions": [
        "plugin:shop:view",
        "plugin:shop:manage"
    ],
    "requires": {
        "payment": ">=1.0.0"
    },
    "menus": [
        {
            "title": "商城管理",
            "icon": "icon-shopping-cart",
            "uri": "plugin/shop",
            "children": [
                {
                    "title": "商品管理",
                    "uri": "plugin/shop/products"
                }
            ]
        }
    ]
}
```

### 插件目录规范

| 目录/文件 | 必须 | 说明 |
|-----------|------|------|
| `plugin.json` | ✅ | 插件元信息 |
| `Providers/PluginServiceProvider.php` | ✅ | 服务提供者 |
| `Admin/` | ❌ | 后台管理接口 |
| `Http/` | ❌ | 业务端接口 |
| `Models/` | ❌ | 数据模型 |
| `Services/` | ❌ | 业务服务（双端共用） |
| `config/` | ❌ | 独立配置 |
| `database/migrations/` | ❌ | 独立迁移 |
| `Support/` | ❌ | 辅助类 |
| `static/` | ❌ | 静态资源 |

### 命名规范

| 项目 | 规范 | 示例 |
|------|------|------|
| 插件目录 | StudlyCase | `Shop`, `UserCenter` |
| 插件标识 | snake_case | `shop`, `user_center` |
| 控制器 | StudlyCase + Controller | `ProductController` |
| 模型 | StudlyCase | `Product`, `Order` |
| 数据表 | snake_case，前缀 `plugin_` | `plugin_shop_products` |
| 配置键 | snake_case | `shop.api_key` |
| 权限标识 | `plugin:{name}:{action}` | `plugin:shop:view` |

---

## 完整开发流程示例

### 场景：创建一个「博客」插件

#### 第 1 步：生成插件骨架

```bash
php artisan make:plugin blog
composer dump-autoload
```

#### 第 2 步：创建数据表迁移

```bash
php artisan make:migration create_plugin_blog_posts_table \
    --path=plugins/Blog/database/migrations
```

编辑迁移文件，定义表结构：

```php
Schema::create('plugin_blog_posts', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->text('content');
    $table->string('cover')->nullable();
    $table->tinyInteger('status')->default(1);
    $table->integer('sort')->default(0);
    $table->timestamps();
});
```

#### 第 3 步：运行迁移

```bash
php artisan migrate
```

#### 第 4 步：添加管理页面

```bash
php artisan make:plugin-page blog post --admin --vue --migration
```

#### 第 5 步：编辑控制器

打开 `plugins/Blog/Admin/Controllers/PostController.php`：

```php
<?php

namespace Plugins\Blog\Admin\Controllers;

use Dabashan\DbsAdmin\Controllers\AdminController;
use Dabashan\DbsAdmin\Grid\Grid;
use Dabashan\DbsAdmin\Form\Form;
use Plugins\Blog\Models\Post;

class PostController extends AdminController
{
    protected string $model = Post::class;

    protected function grid(): Grid
    {
        return Grid::make(Post::query())
            ->column('id', 'ID')->sortable()
            ->column('title', '标题')->searchable()
            ->column('cover', '封面')
            ->column('status', '状态')
            ->column('sort', '排序')->sortable()
            ->column('created_at', '创建时间')->sortable()
            ->filter('title', '标题', 'like')
            ->filter('status', '状态', 'select')->options([
                0 => '草稿',
                1 => '已发布',
            ])
            ->perPage(15);
    }

    protected function form(): Form
    {
        return Form::make(Post::class)
            ->text('title', '标题')->required()
            ->textarea('content', '内容')->required()
            ->image('cover', '封面')
            ->select('status', '状态')->options([
                0 => '草稿',
                1 => '已发布',
            ])->default(1)
            ->number('sort', '排序')->default(0);
    }
}
```

#### 第 6 步：注册路由

编辑 `plugins/Blog/Admin/routes.php`：

```php
<?php

use Illuminate\Support\Facades\Route;
use Plugins\Blog\Admin\Controllers\PostController;

Route::prefix('admin/plugin/blog')
    ->middleware(['api', 'auth:admin'])
    ->group(function () {
        Route::apiResource('posts', PostController::class);
        Route::get('posts/form-schema', [PostController::class, 'formSchema']);
    });
```

#### 第 7 步：前端 Vue 页面

`make:plugin-page --vue` 已自动生成。页面仅 6 行代码：

```vue
<template>
  <DynamicCrud
    api-prefix="/admin/plugin/blog/posts"
    :breadcrumb="['menu.plugin.blog', 'menu.plugin.blog.posts']"
    add-title="新增文章"
    edit-title="编辑文章"
  />
</template>
<script lang="ts" setup>
  import DynamicCrud from '@/components/dynamic/DynamicCrud.vue';
</script>
```

**不需要手动编写**表格、筛选器、表单、弹窗等任何 UI 代码。所有渲染都由 PHP 的 `grid()` 和 `form()` 元数据驱动。

#### 第 8 步：完善 plugin.json

```json
{
    "name": "blog",
    "title": "博客插件",
    "description": "博客内容管理",
    "version": "1.0.0",
    "enabled": true,
    "icon": "icon-edit",
    "menus": [
        {
            "title": "博客管理",
            "icon": "icon-edit",
            "uri": "plugin/blog",
            "children": [
                {
                    "title": "文章管理",
                    "uri": "plugin/blog/posts"
                }
            ]
        }
    ]
}
```

完成！访问后台管理界面即可对博客文章进行增删改查。

---

## 插件事件/钩子系统

插件之间通过 `PluginHook` 实现松耦合通信，基于 Laravel Event 门面。

### 基本用法

```php
use App\Admin\Services\PluginHook;

// 监听事件
PluginHook::listen('user.created', function ($user) {
    // 用户创建后的回调
});

// 触发事件
PluginHook::fire('user.created', $user);

// 注册过滤器（管道模式）
PluginHook::filter('content.render', function ($content) {
    return str_replace('[shop_banner]', renderBanner(), $content);
});

// 应用过滤器
$content = PluginHook::apply('content.render', $originalContent);
```

### 可用系统事件

| 事件 | 触发时机 | 参数 |
|------|---------|------|
| `user.created` | 管理员用户创建后 | `AdminUser` |
| `user.deleted` | 管理员用户删除后 | `AdminUser` |
| `plugin.installed` | 插件安装后 | `Plugin` |
| `plugin.uninstalled` | 插件卸载后 | `Plugin` |
| `plugin.upgraded` | 插件升级后 | `Plugin` |

---

## 常见问题

### Q: 插件路由不生效？

1. 检查 `plugin.json` 中 `enabled` 是否为 `true`
2. 执行 `composer dump-autoload`
3. 清除路由缓存：`php artisan route:clear`

### Q: 插件配置不生效？

1. 确保 `PluginServiceProvider` 中正确调用 `mergeConfigFrom()`
2. 清除配置缓存：`php artisan config:clear`

### Q: 如何调试插件？

1. 在 `PluginServiceProvider::boot()` 中添加日志
2. 使用 `dd()` 或 `dump()` 调试
3. 查看 `storage/logs/laravel.log`

### Q: `make:admin` 生成的 Model 报错？

`make:admin` 生成的 Model 继承 `BaseAdminModel`（位于 `Dabashan\DbsAdmin\Models`），确保 `dabashan/dbs-admin` 包已正确安装。如果主系统有自己的 `BaseAdminModel`（位于 `app/Admin/Models/BaseAdminModel.php`），可以修改生成的 Model 继承关系。

### Q: 前端页面如何注册路由？

1. 生成的路由文件在 `web/src/router/routes/modules/` 目录下
2. 确保主路由文件正确自动加载
3. 检查 Vue 页面路径是否与路由中的 `import()` 路径一致

---

## 性能优化指南

整个系统从设计层面就追求快速响应，以下是关键优化点和最佳实践：

### 1. Grid 查询优化（后端）

```php
protected function grid(): Grid
{
    return Grid::make(User::query())
        // ✅ 仅查询需要的字段（大幅减少内存和网络）
        ->select(['id', 'name', 'email', 'avatar', 'status'])
        // ✅ 预加载关联（避免 N+1 查询）
        ->with(['roles', 'profile'])
        // ✅ 使用条件筛选代替全表扫描
        ->when($request->boolean('active'), fn($q) => $q->where('active', true))
        ->perPage(20);
}
```

| 优化项 | 效果 | 方法 |
|--------|------|------|
| `select([...])` | 减少 60-80% 数据传输量 | Grid |
| `with([...])` | 消除 N+1 查询 | Grid |
| `when($cond, $cb)` | 按需加查询，避免无意义 where | Grid |
| `perPage(20)` | 控制每页数据量，默认 20 | Grid |
| 后端预格式化 display | 前端无需循环处理 | Column::formatValue() |

### 2. 后端预格式化

所有 display 类型（date、money、badge 等）在后端 `formatValue()` 方法中预格式化，**前端直接渲染**，无需额外处理。这比前端 JS 格式化快 3-5 倍。

### 3. 响应精简

| 优化项 | 说明 |
|--------|------|
| `traceId` | 每个响应自带追踪 ID，方便调试定位慢请求 |
| `gridMeta` 接口 | 仅获取列/筛选器元数据（不含数据），可缓存 |
| `formSchema` 接口 | 仅获取表单定义，可缓存 |
| `showSchema` 接口 | 仅获取详情定义，可缓存 |

### 4. 前端建议

| 优化项 | 说明 |
|--------|------|
| 缓存 Schema | `formSchema`/`gridMeta` 响应变化极少，建议前端缓存（sessionStorage） |
| 按需渲染 | 根据 `displayType` 元数据动态渲染对应组件，避免全量组件挂载 |
| 虚拟滚动 | 大数据量表格使用 Arco Table 的虚拟滚动 |
| 防抖搜索 | keyword 搜索使用 300ms 防抖 |

### 5. 数据库建议

| 优化项 | 说明 |
|--------|------|
| 索引 | 所有 searchable/filter 列加索引 |
| 覆盖索引 | `select([...])` 配合覆盖索引，避免回表 |
| 分区表 | 百万级数据按时间分区 |

---

## 开发规范

1. **遵循 PSR-4** 自动加载规范
2. **类型声明** — 参数和返回值声明类型
3. **输入验证** — 所有用户输入必须验证
4. **权限校验** — 后台接口必须检查权限
5. **SQL 防注入** — 使用 Eloquent 或参数绑定
6. **XSS 防护** — 输出数据需转义

---

## 参考资源

- [Grid/Form 前后端映射流程](../laravel12/GRID_FORM_FLOW.md) — 详细的 Grid/Form 工作原理
- [插件开发指南](../laravel12/plugins/PLUGIN_DEV.md) — 更详细的插件规范
- [Laravel 文档](https://laravel.com/docs)
- [Arco Design Vue](https://arco.design/vue)
