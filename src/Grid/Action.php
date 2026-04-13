<?php

namespace Dabashan\DbsAdmin\Grid;

/**
 * Grid 操作定义类
 *
 *  Action 的完整能力
 * 支持：行操作、批量操作、头部操作
 * 支持弹窗（Modal/Drawer）和新页面跳转
 */
class Action
{
    // 操作位置
    public const POSITION_HEADER = 'header';
    public const POSITION_ROW = 'row';
    public const POSITION_BULK = 'bulk';

    // 显示模式
    public const MODE_MODAL = 'modal';
    public const MODE_DRAWER = 'drawer';
    public const MODE_PAGE = 'page';

    protected string $key;
    protected string $label;
    protected string $position = self::POSITION_ROW;
    protected string $mode = self::MODE_PAGE;       // modal, drawer, page
    protected string $type = 'primary';             // 按钮颜色
    protected ?string $icon = null;
    protected ?string $route = null;               // 页面跳转路由
    protected ?string $apiRoute = null;            // API 路由
    protected bool $confirm = false;
    protected ?string $confirmText = null;
    protected bool $requiresSelection = false;      // 批量操作是否需要选中
    protected array $modalProps = [];               // 弹窗属性
    protected array $drawerProps = [];              // 抽屉属性
    protected ?\Closure $visibleCallback = null;    // 可见性回调
    protected array $extra = [];                   // 额外属性

    public function __construct(string $key, string $label)
    {
        $this->key = $key;
        $this->label = $label;
    }

    public static function make(string $key, string $label): self
    {
        return new self($key, $label);
    }

    // ==================== 位置 ====================

    /** 行操作 */
    public function row(): self
    {
        $this->position = self::POSITION_ROW;
        return $this;
    }

    /** 头部操作（工具栏） */
    public function header(): self
    {
        $this->position = self::POSITION_HEADER;
        return $this;
    }

    /** 批量操作 */
    public function bulk(): self
    {
        $this->position = self::POSITION_BULK;
        $this->requiresSelection = true;
        return $this;
    }

    // ==================== 显示模式 ====================

    /** 弹窗模式（Modal，居中弹窗，适合表单） */
    public function modal(array $props = []): self
    {
        $this->mode = self::MODE_MODAL;
        $this->modalProps = array_merge([
            'width' => 600,
            'maskClosable' => false,
        ], $props);
        return $this;
    }

    /** 抽屉模式（Drawer，侧边滑出，适合详情/编辑） */
    public function drawer(array $props = []): self
    {
        $this->mode = self::MODE_DRAWER;
        $this->drawerProps = array_merge([
            'width' => 600,
            'maskClosable' => false,
        ], $props);
        return $this;
    }

    /** 新页面模式（默认，适合复杂页面） */
    public function page(): self
    {
        $this->mode = self::MODE_PAGE;
        return $this;
    }

    // ==================== 外观 ====================

    public function type(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function icon(string $icon): self
    {
        $this->icon = $icon;
        return $this;
    }

    // ==================== 路由 ====================

    /** 设置跳转路由（page 模式） */
    public function route(string $route): self
    {
        $this->route = $route;
        return $this;
    }

    /** 设置 API 路由（modal/drawer 模式自动调用） */
    public function apiRoute(string $apiRoute): self
    {
        $this->apiRoute = $apiRoute;
        return $this;
    }

    // ==================== 确认 ====================

    /** 需要确认弹窗 */
    public function confirm(bool $value = true, ?string $text = null): self
    {
        $this->confirm = $value;
        $this->confirmText = $text;
        return $this;
    }

    // ==================== 可见性 ====================

    public function visible(\Closure $callback): self
    {
        $this->visibleCallback = $callback;
        return $this;
    }

    // ==================== 额外属性 ====================

    public function extra(array $extra): self
    {
        $this->extra = $extra;
        return $this;
    }

    // ==================== 获取 ====================

    public function getPosition(): string
    {
        return $this->position;
    }

    public function isVisible(mixed $record = null): bool
    {
        if ($this->visibleCallback === null) {
            return true;
        }
        return ($this->visibleCallback)($record);
    }

    public function toArray(): array
    {
        return array_filter([
            'key' => $this->key,
            'label' => $this->label,
            'position' => $this->position,
            'mode' => $this->mode,
            'type' => $this->type,
            'icon' => $this->icon,
            'route' => $this->route,
            'apiRoute' => $this->apiRoute,
            'confirm' => $this->confirm,
            'confirmText' => $this->confirmText,
            'requiresSelection' => $this->requiresSelection,
            'modalProps' => !empty($this->modalProps) ? $this->modalProps : null,
            'drawerProps' => !empty($this->drawerProps) ? $this->drawerProps : null,
            'extra' => !empty($this->extra) ? $this->extra : null,
        ], fn($v) => $v !== null);
    }
}
