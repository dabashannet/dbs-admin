<?php

namespace Dabashan\DbsAdmin\Grid;

/**
 * Grid 列定义类
 *
 * 用于定义表格列的属性，支持链式调用
 *  TextColumn 的完整显示能力
 */
class Column
{
    protected string $key;
    protected string $title;
    protected bool $sortable = false;
    protected bool $searchable = false;
    protected bool $hidden = false;
    protected ?string $width = null;
    protected ?string $align = null;
    protected ?string $displayType = null;
    protected array $displayOptions = [];
    protected ?\Closure $formatCallback = null;
    protected ?string $default = null;
    protected ?int $limit = null;
    protected bool $clickable = false;
    protected ?string $action = null;
    protected ?string $actionLabel = null;
    protected ?string $actionType = null;
    protected ?bool $toggleable = null;
    protected ?string $suffix = null;
    protected ?string $prefix = null;
    protected ?int $decimals = null;
    protected string $separator = ', ';
    protected bool $wrap = false;

    public function __construct(string $key, string $title)
    {
        $this->key = $key;
        $this->title = $title;
    }

    // ==================== 基础方法 ====================

    public function sortable(bool $value = true): self
    {
        $this->sortable = $value;
        return $this;
    }

    public function searchable(bool $value = true): self
    {
        $this->searchable = $value;
        return $this;
    }

    public function hidden(bool $value = true): self
    {
        $this->hidden = $value;
        return $this;
    }

    public function width(string $width): self
    {
        $this->width = $width;
        return $this;
    }

    public function align(string $align): self
    {
        $this->align = $align;
        return $this;
    }

    // ==================== 显示类型 ====================

    /**
     * 徽章显示（彩色标签）
     */
    public function badge(array $colors = [], string $variant = 'light'): self
    {
        $this->displayType = 'badge';
        $this->displayOptions = ['colors' => $colors, 'variant' => $variant];
        return $this;
    }

    public function options(array $options): self
    {
        $this->displayOptions['options'] = $options;
        return $this;
    }

    /**
     * 开关显示（toggle 组件）
     */
    public function toggle(?string $route = null): self
    {
        $this->displayType = 'switch';
        if ($route) {
            $this->displayOptions['route'] = $route;
        }
        return $this;
    }

    /**
     * 图片显示（缩略图）
     */
    public function image(int $width = 40, int $height = 40, bool $circle = false): self
    {
        $this->displayType = 'image';
        $this->displayOptions = ['width' => $width, 'height' => $height, 'circle' => $circle];
        return $this;
    }

    /**
     * 标签组显示（多个 tag）
     */
    public function tags(string $separator = ', '): self
    {
        $this->displayType = 'tags';
        $this->separator = $separator;
        return $this;
    }

    /**
     * 进度条显示
     */
    public function progress(int $max = 100, bool $showText = true, array $colors = []): self
    {
        $this->displayType = 'progress';
        $this->displayOptions = ['max' => $max, 'showText' => $showText, 'colors' => $colors];
        return $this;
    }

    /**
     * 数值条显示（水平色条）
     */
    public function bar(array $colors = []): self
    {
        $this->displayType = 'bar';
        $this->displayOptions = ['colors' => $colors];
        return $this;
    }

    /**
     * 颜色显示（色块预览）
     */
    public function color(): self
    {
        $this->displayType = 'color';
        return $this;
    }

    /**
     * 可复制显示（点击复制）
     */
    public function copyable(): self
    {
        $this->displayType = 'copyable';
        return $this;
    }

    /**
     * 圆点状态显示
     */
    public function dot(array $colors = []): self
    {
        $this->displayType = 'dot';
        $this->displayOptions = ['colors' => $colors];
        return $this;
    }

    /**
     * 日期显示（格式化）
     */
    public function date(string $format = 'Y-m-d'): self
    {
        $this->displayType = 'date';
        $this->displayOptions = ['format' => $format];
        return $this;
    }

    /**
     * 日期时间显示
     */
    public function datetime(string $format = 'Y-m-d H:i:s'): self
    {
        $this->displayType = 'datetime';
        $this->displayOptions = ['format' => $format];
        return $this;
    }

    /**
     * 金额显示（货币格式）
     */
    public function money(string $symbol = '¥', int $decimals = 2): self
    {
        $this->displayType = 'money';
        $this->displayOptions = ['symbol' => $symbol, 'decimals' => $decimals];
        return $this;
    }

    /**
     * 计数显示
     */
    public function count(): self
    {
        $this->displayType = 'count';
        return $this;
    }

    /**
     * 自定义格式化回调
     */
    public function using(\Closure $callback): self
    {
        $this->formatCallback = $callback;
        return $this;
    }

    /**
     * 自定义显示内容（通过闭包处理值转换）
     */
    public function display(\Closure $callback): self
    {
        $this->formatCallback = $callback;
        return $this;
    }

    // ==================== 修饰方法 ====================

    public function default(?string $value): self
    {
        $this->default = $value;
        return $this;
    }

    public function limit(int $length = 50): self
    {
        $this->limit = $length;
        return $this;
    }

    public function clickable(bool $value = true): self
    {
        $this->clickable = $value;
        return $this;
    }

    public function action(string $label, string $type = 'link'): self
    {
        $this->action = $this->key;
        $this->actionLabel = $label;
        $this->actionType = $type;
        return $this;
    }

    public function prefix(string $text): self
    {
        $this->prefix = $text;
        return $this;
    }

    public function suffix(string $text): self
    {
        $this->suffix = $text;
        return $this;
    }

    public function decimals(int $value): self
    {
        $this->decimals = $value;
        return $this;
    }

    public function toggleable(bool $value = true): self
    {
        $this->toggleable = $value;
        return $this;
    }

    public function wrap(bool $value = true): self
    {
        $this->wrap = $value;
        return $this;
    }

    // ==================== 获取方法 ====================

    public function getKey(): string
    {
        return $this->key;
    }

    public function isSortable(): bool
    {
        return $this->sortable;
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    public function isHidden(): bool
    {
        return $this->hidden;
    }

    public function isClickable(): bool
    {
        return $this->clickable;
    }

    public function toArray(): array
    {
        $data = array_filter([
            'key' => $this->key,
            'title' => $this->title,
            'sortable' => $this->sortable ?: null,
            'searchable' => $this->searchable ?: null,
            'hidden' => $this->hidden ?: null,
            'width' => $this->width,
            'align' => $this->align,
            'displayType' => $this->displayType,
            'displayOptions' => !empty($this->displayOptions) ? $this->displayOptions : null,
            'default' => $this->default,
            'limit' => $this->limit,
            'clickable' => $this->clickable ?: null,
            'action' => $this->action,
            'actionLabel' => $this->actionLabel,
            'actionType' => $this->actionType,
            'toggleable' => $this->toggleable,
            'prefix' => $this->prefix,
            'suffix' => $this->suffix,
            'decimals' => $this->decimals,
            'wrap' => $this->wrap ?: null,
        ], fn($v) => $v !== null);

        if ($this->formatCallback !== null) {
            $data['displayType'] = 'custom';
            $data['displayOptions'] = ['hasCallback' => true];
        }

        return $data;
    }

    /**
     * 格式化单个单元格的值（后端预格式化，减轻前端负担）
     */
    public function formatValue(mixed $value, ?object $row = null): mixed
    {
        if ($value === null || $value === '') {
            return $this->default;
        }

        if ($this->formatCallback !== null && $row !== null) {
            return ($this->formatCallback)($value, $row);
        }

        return match ($this->displayType) {
            'date' => is_string($value) ? date($this->displayOptions['format'] ?? 'Y-m-d', strtotime($value)) : $value,
            'datetime' => is_string($value) ? date($this->displayOptions['format'] ?? 'Y-m-d H:i:s', strtotime($value)) : $value,
            'money' => ($this->displayOptions['symbol'] ?? '¥') . number_format((float) $value, $this->displayOptions['decimals'] ?? 2),
            'count' => number_format((int) $value),
            default => $value,
        };
    }
}
