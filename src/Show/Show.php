<?php

namespace Dabashan\DbsAdmin\Show;

/**
 * Show 字段定义类
 *
 *  TextEntry 的完整显示能力
 */
class ShowField
{
    protected string $key;
    protected string $label;
    public ?string $displayType = null;
    public array $displayOptions = [];
    protected ?\Closure $formatCallback = null;
    protected ?string $default = null;
    protected ?string $copyableText = null;
    protected int $limit = 0;

    public function __construct(string $key, string $label)
    {
        $this->key = $key;
        $this->label = $label;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    // ==================== 显示类型 ====================

    public function badge(array $colors = [], string $variant = 'light'): self
    {
        $this->displayType = 'badge';
        $this->displayOptions = ['colors' => $colors, 'variant' => $variant];
        return $this;
    }

    public function label(): self
    {
        $this->displayType = 'label';
        return $this;
    }

    public function copyable(?string $text = null): self
    {
        $this->displayType = 'copyable';
        $this->copyableText = $text;
        return $this;
    }

    public function dot(array $colors = []): self
    {
        $this->displayType = 'dot';
        $this->displayOptions = ['colors' => $colors];
        return $this;
    }

    public function image(int $width = 120, int $height = 80, bool $circle = false): self
    {
        $this->displayType = 'image';
        $this->displayOptions = ['width' => $width, 'height' => $height, 'circle' => $circle];
        return $this;
    }

    public function color(): self
    {
        $this->displayType = 'color';
        return $this;
    }

    public function bar(array $colors = []): self
    {
        $this->displayType = 'bar';
        $this->displayOptions = ['colors' => $colors];
        return $this;
    }

    public function progress(int $max = 100, bool $showText = true): self
    {
        $this->displayType = 'progress';
        $this->displayOptions = ['max' => $max, 'showText' => $showText];
        return $this;
    }

    public function date(string $format = 'Y-m-d'): self
    {
        $this->displayType = 'date';
        $this->displayOptions = ['format' => $format];
        return $this;
    }

    public function datetime(string $format = 'Y-m-d H:i:s'): self
    {
        $this->displayType = 'datetime';
        $this->displayOptions = ['format' => $format];
        return $this;
    }

    public function money(string $symbol = '¥', int $decimals = 2): self
    {
        $this->displayType = 'money';
        $this->displayOptions = ['symbol' => $symbol, 'decimals' => $decimals];
        return $this;
    }

    public function tags(string $separator = ', '): self
    {
        $this->displayType = 'tags';
        $this->displayOptions = ['separator' => $separator];
        return $this;
    }

    public function using(\Closure $callback): self
    {
        $this->formatCallback = $callback;
        return $this;
    }

    public function limit(int $length = 100): self
    {
        $this->limit = $length;
        return $this;
    }

    public function default(?string $value): self
    {
        $this->default = $value;
        return $this;
    }

    public function toArray(): array
    {
        return array_filter([
            'key' => $this->key,
            'label' => $this->label,
            'displayType' => $this->displayType,
            'displayOptions' => !empty($this->displayOptions) ? $this->displayOptions : null,
            'default' => $this->default,
            'limit' => $this->limit ?: null,
            'hasCallback' => $this->formatCallback !== null,
        ], fn($v) => $v !== null);
    }

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
            default => $value,
        };
    }
}

/**
 * Show 详情展示组件
 *
 *  Infolist 的完整能力
 * 支持面板布局、Tab 分组、关联展示、字段格式化
 */
class Show
{
    protected $model;
    protected array $fields = [];
    protected array $with = [];
    protected array $layout = [];          // 布局: tabs, section
    protected string $layoutMode = 'default';
    protected ?string $title = null;       // 详情标题
    protected string $labelWidth = 'auto'; // 标签宽度

    public static function make($model): self
    {
        $instance = new self();
        $instance->model = $model;
        return $instance;
    }

    public function with(array|string $relations): self
    {
        $this->with = array_merge($this->with, (array) $relations);
        if ($this->model && !empty($this->with)) {
            $this->model->load($this->with);
        }
        return $this;
    }

    /**
     * 添加展示字段
     */
    public function field(string $key, string $label): ShowField
    {
        $field = new ShowField($key, $label);
        $this->fields[] = $field;
        return $field;
    }

    /**
     * 图标展示
     */
    public function icon(string $key, string $label): self
    {
        $field = new ShowField($key, $label);
        $field->displayType = 'icon';
        $this->fields[] = $field;
        return $this;
    }

    /**
     * 可重复项展示（嵌套数据）
     */
    public function repeatable(string $key, string $label, array $schema = []): self
    {
        $field = new ShowField($key, $label);
        $field->displayType = 'repeatable';
        $field->displayOptions = ['schema' => $schema];
        $this->fields[] = $field;
        return $this;
    }

    // ==================== 快捷方法（兼容旧 API） ====================

    /**
     * 添加普通字段（快捷方式）
     */
    public function text(string $key, string $label): self
    {
        $field = new ShowField($key, $label);
        $field->displayType = 'text';
        $this->fields[] = $field;
        return $this;
    }

    /**
     * 图片展示
     */
    public function image(string $key, string $label, int $width = 200, int $height = 150): self
    {
        $field = new ShowField($key, $label);
        $field->image($width, $height);
        $this->fields[] = $field;
        return $this;
    }

    /**
     * 日期展示
     */
    public function date(string $key, string $label, string $format = 'Y-m-d H:i:s'): self
    {
        $field = new ShowField($key, $label);
        $field->datetime($format);
        $this->fields[] = $field;
        return $this;
    }

    /**
     * 金额展示
     */
    public function money(string $key, string $label, string $symbol = '¥'): self
    {
        $field = new ShowField($key, $label);
        $field->money($symbol);
        $this->fields[] = $field;
        return $this;
    }

    /**
     * 徽章展示
     */
    public function badge(string $key, string $label, array $colors = []): self
    {
        $field = new ShowField($key, $label);
        $field->badge($colors);
        $this->fields[] = $field;
        return $this;
    }

    /**
     * 日期时间展示
     */
    public function datetime(string $key, string $label, string $format = 'Y-m-d H:i:s'): self
    {
        $field = new ShowField($key, $label);
        $field->datetime($format);
        $this->fields[] = $field;
        return $this;
    }

    /**
     * 标签展示
     */
    public function tags(string $key, string $label, string $separator = ', '): self
    {
        $field = new ShowField($key, $label);
        $field->tags($separator);
        $this->fields[] = $field;
        return $this;
    }

    /**
     * 复制展示
     */
    public function copyable(string $key, string $label, ?string $text = null): self
    {
        $field = new ShowField($key, $label);
        $field->copyable($text);
        $this->fields[] = $field;
        return $this;
    }

    // ==================== 布局方法 ====================

    /**
     * Tab 分组展示
     */
    public function tabs(array $tabs): self
    {
        $this->layoutMode = 'tabs';
        $this->layout = $tabs;
        return $this;
    }

    /**
     * 分区块展示
     */
    public function section(string $title, array $fields = []): self
    {
        $this->layoutMode = 'section';
        $this->layout[] = ['title' => $title, 'fields' => $fields];
        return $this;
    }

    /**
     * 详情标题
     */
    public function title(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    /**
     * 标签宽度
     */
    public function labelWidth(string $width): self
    {
        $this->labelWidth = $width;
        return $this;
    }

    // ==================== 关联展示 ====================

    /**
     * 关联数据展示
     *
     * @param string $relation 关联方法名
     * @param string $label 区块标题
     * @param \Closure|null $callback 自定义展示回调
     */
    public function relation(string $relation, string $label, ?\Closure $callback = null): self
    {
        $this->layoutMode = 'relation';
        $this->layout[] = [
            'type' => 'relation',
            'relation' => $relation,
            'label' => $label,
            'hasCallback' => $callback !== null,
        ];
        return $this;
    }

    // ==================== 核心方法 ====================

    /**
     * 返回键值对数组，前端根据 schema.fields 元数据渲染
     */
    public function toArray(): array
    {
        $data = $this->model->toArray();

        // 如果定义了字段，只返回指定字段（含格式化后的值）
        if (!empty($this->fields)) {
            $result = [];
            foreach ($this->fields as $field) {
                $key = $field->getKey();
                $result[$key] = $field->formatValue(data_get($data, $key), $this->model);
            }
            return $result;
        }

        return $data;
    }

    /**
     * 返回键值对数组（同 toArray()，兼容旧版调用）
     */
    public function toKeyValue(): array
    {
        return $this->toArray();
    }

    public function getFields(): array
    {
        $model = $this->model;
        $modelClass = is_object($model) ? $model::class : null;
        $hasOptions = $modelClass && method_exists($modelClass, 'options');

        return array_map(function (ShowField $f) use ($hasOptions, $modelClass) {
            $arr = $f->toArray();
            $key = $arr['key'] ?? null;
            if ($hasOptions && is_string($key) && $key !== '') {
                $opts = $modelClass::options($key);
                if (is_array($opts) && !empty($opts)) {
                    $arr['options'] = $opts;
                }
            }
            return $arr;
        }, $this->fields);
    }

    public function getModel()
    {
        return $this->model;
    }

    public function getLayout(): array
    {
        return [
            'mode' => $this->layoutMode,
            'config' => $this->layout,
            'title' => $this->title,
            'labelWidth' => $this->labelWidth,
        ];
    }

    public function schema(): array
    {
        return [
            'fields' => !empty($this->fields) ? $this->getFields() : $this->autoFields(),
            'layout' => $this->getLayout(),
        ];
    }

    /**
     * 自动生成字段列表（默认排除 updated_at）
     */
    protected function autoFields(): array
    {
        $data = $this->model->toArray();
        return array_values(array_filter(
            array_map(fn($key) => [
                'key' => $key,
                'label' => $key,
                'displayType' => 'text',
            ], array_keys($data)),
            fn($field) => $field['key'] !== 'updated_at'
        ));
    }
}
