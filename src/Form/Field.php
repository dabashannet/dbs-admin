<?php

namespace Dabashan\DbsAdmin\Form;

/**
 * Form 字段定义类
 *
 *  Form Field 的完整能力
 * 支持所有常见输入类型、验证、条件显示、布局嵌套
 */
class Field
{
    protected string $key;
    protected string $title;
    protected string $type;
    protected bool $isRequired = false;
    protected string|array $validationRules = [];
    protected array $options = [];
    protected mixed $defaultValue = null;
    protected bool $creationOnlyFlag = false;
    protected bool $updateOnlyFlag = false;
    protected bool $multipleFlag = false;
    protected ?int $maxValue = null;
    protected ?int $minValue = null;
    protected ?string $placeholder = null;
    protected ?string $help = null;
    protected bool $disabled = false;
    protected bool $readonly = false;
    protected ?int $maxLength = null;
    protected ?int $minLength = null;
    protected ?string $prefix = null;
    protected ?string $suffix = null;
    protected ?string $append = null;
    protected ?string $prepend = null;
    protected array $displayWhen = [];       // 条件显示: ['status', '==', 1]
    protected array $depends = [];           // 联动字段: 依赖哪些字段变化
    protected ?\Closure $optionsCallback = null; // 动态选项回调
    protected bool $searchable = false;      // select 是否可搜索
    protected bool $allowCreate = false;     // select 是否允许创建新选项
    protected bool $clearable = true;        // 显示清除按钮
    protected ?int $step = null;             // 数字输入步长
    protected ?int $precision = null;        // 数字输入精度
    protected ?string $format = null;        // 日期/时间格式
    protected ?string $colorMode = 'hex';    // 颜色模式: hex, rgb, hsl
    protected ?int $rateCount = 5;           // 评分数量
    protected ?bool $showScore = true;       // 显示评分分数
    protected ?int $rows = 4;                // textarea 行数
    protected ?string $editor = null;        // 富文本编辑器: tinymce, wangEditor
    protected bool $isHidden = false;        // hidden 类型
    protected ?string $html = null;          // html 类型内容
    protected string $uploadDisk = 'public'; // 上传磁盘
    protected ?string $uploadPath = null;    // 上传路径
    protected array $uploadAccept = [];      // 接受的文件类型
    protected int $maxUpload = 10;           // 最大上传数（多图）
    protected ?string $iconPrefix = 'icon';  // 图标前缀

    // ==================== 高级字段类型 ====================

    protected array $keyValueKeyLabel = [];   // KeyValue 键名标签
    protected array $keyValueValueLabel = []; // KeyValue 键值标签
    protected bool $keyValueReorderable = false;  // KeyValue 是否可排序
    protected array $repeaterSchema = [];      // Repeater 子字段定义
    protected string $repeaterCollapsibleLabel = '';  // Repeater 折叠标签
    protected bool $repeaterCollapsible = false;     // Repeater 是否可折叠
    protected int $repeaterMinItems = 0;       // Repeater 最小项数
    protected int $repeaterMaxItems = 0;       // Repeater 最大项数
    protected bool $repeaterCloneable = true;  // Repeater 是否可克隆
    protected bool $repeaterReorderable = true; // Repeater 是否可排序
    protected string $repeaterItemLabel = '项'; // Repeater 项标签
    protected ?string $markdownEditorHeight = null;  // Markdown 编辑器高度
    protected array $toggleButtonsOptions = [];  // 切换按钮选项
    protected bool $toggleButtonsMultiple = false; // 切换按钮多选
    protected ?string $markdownUploadDisk = 'public';  // Markdown 上传磁盘
    protected ?string $markdownUploadPath = 'markdown'; // Markdown 上传路径

    public function __construct(string $key, string $title, string $type = 'text')
    {
        $this->key = $key;
        $this->title = $title;
        $this->type = $type;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function type(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    // ==================== 验证方法 ====================

    public function required(bool $value = true): self
    {
        $this->isRequired = $value;
        return $this;
    }

    public function rules(string|array $rules): self
    {
        $this->validationRules = $rules;
        return $this;
    }

    public function rule(string $rule): self
    {
        if (is_string($this->validationRules)) {
            $this->validationRules = $this->validationRules ? explode('|', $this->validationRules) : [];
        }
        $this->validationRules[] = $rule;
        return $this;
    }

    // ==================== 选项方法 ====================

    public function options($options): self
    {
        if ($options instanceof \Illuminate\Support\Collection) {
            $options = $options->toArray();
        }
        $this->options = $options;
        return $this;
    }

    /**
     * 动态选项（运行时从数据库/配置获取）
     */
    public function optionsFrom(\Closure $callback): self
    {
        $this->optionsCallback = $callback;
        return $this;
    }

    public function default(mixed $value): self
    {
        $this->defaultValue = $value;
        return $this;
    }

    public function max(int $value): self
    {
        $this->maxValue = $value;
        return $this;
    }

    public function min(int $value): self
    {
        $this->minValue = $value;
        return $this;
    }

    public function multiple(bool $value = true): self
    {
        $this->multipleFlag = $value;
        return $this;
    }

    public function createOnly(bool $value = true): self
    {
        $this->creationOnlyFlag = $value;
        return $this;
    }

    public function creationOnly(bool $value = true): self
    {
        return $this->createOnly($value);
    }

    public function updateOnly(bool $value = true): self
    {
        $this->updateOnlyFlag = $value;
        return $this;
    }

    public function placeholder(string $placeholder): self
    {
        $this->placeholder = $placeholder;
        return $this;
    }

    public function help(string $help): self
    {
        $this->help = $help;
        return $this;
    }

    // ==================== 状态方法 ====================

    public function disabled(bool $value = true): self
    {
        $this->disabled = $value;
        return $this;
    }

    public function readonly(bool $value = true): self
    {
        $this->readonly = $value;
        return $this;
    }

    // ==================== 文本修饰 ====================

    public function maxLength(int $length): self
    {
        $this->maxLength = $length;
        return $this;
    }

    public function minLength(int $length): self
    {
        $this->minLength = $length;
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

    public function prepend(string $text): self
    {
        $this->prepend = $text;
        return $this;
    }

    public function append(string $text): self
    {
        $this->append = $text;
        return $this;
    }

    // ==================== 条件显示（ hidden/visibleWhen） ====================

    /**
     * 条件显示
     *
     * @param string $field 依赖的字段名
     * @param string $operator 操作符: ==, !=, >, <, >=, <=, in, notIn, contains
     * @param mixed $value 比较值
     */
    public function displayWhen(string $field, string $operator, mixed $value): self
    {
        $this->displayWhen = ['field' => $field, 'operator' => $operator, 'value' => $value];
        return $this;
    }

    /**
     * 依赖字段（字段联动）
     */
    public function depends(array $fields): self
    {
        $this->depends = $fields;
        return $this;
    }

    // ==================== Select 增强 ====================

    /**
     * Select 可搜索
     */
    public function searchableOptions(bool $value = true): self
    {
        $this->searchable = $value;
        return $this;
    }

    /**
     * Select 允许创建新选项
     */
    public function allowCreate(bool $value = true): self
    {
        $this->allowCreate = $value;
        return $this;
    }

    public function clearable(bool $value = true): self
    {
        $this->clearable = $value;
        return $this;
    }

    // ==================== 数字增强 ====================

    public function step(int $value): self
    {
        $this->step = $value;
        return $this;
    }

    public function precision(int $value): self
    {
        $this->precision = $value;
        return $this;
    }

    // ==================== 日期增强 ====================

    public function format(string $format): self
    {
        $this->format = $format;
        return $this;
    }

    // ==================== 颜色增强 ====================

    public function colorMode(string $mode): self
    {
        $this->colorMode = $mode;
        return $this;
    }

    // ==================== 评分增强 ====================

    public function rateCount(int $count): self
    {
        $this->rateCount = $count;
        return $this;
    }

    public function showScore(bool $value = true): self
    {
        $this->showScore = $value;
        return $this;
    }

    // ==================== 文本域增强 ====================

    public function rows(int $count): self
    {
        $this->rows = $count;
        return $this;
    }

    // ==================== 富文本编辑器 ====================

    /**
     * 富文本编辑器
     *
     * @param string $driver tinymce, wangEditor
     */
    public function editor(string $driver = 'wangEditor'): self
    {
        $this->type = 'editor';
        $this->editor = $driver;
        return $this;
    }

    // ==================== 文件上传 ====================

    public function disk(string $disk): self
    {
        $this->uploadDisk = $disk;
        return $this;
    }

    public function path(string $path): self
    {
        $this->uploadPath = $path;
        return $this;
    }

    public function accept(array $types): self
    {
        $this->uploadAccept = $types;
        return $this;
    }

    public function maxUpload(int $count): self
    {
        $this->maxUpload = $count;
        return $this;
    }

    // ==================== 键值对输入 ====================

    /**
     * 键值对输入（类似 Filament KeyValue）
     */
    public function keyValue(string $key, string $label): self
    {
        $field = new self($key, $label, 'keyValue');
        $this->key = $key;
        $this->title = $label;
        $this->type = 'keyValue';
        return $this;
    }

    public function keyValueLabels(string $keyLabel, string $valueLabel): self
    {
        $this->keyValueKeyLabel = ['label' => $keyLabel];
        $this->keyValueValueLabel = ['label' => $valueLabel];
        return $this;
    }

    public function keyValueReorderable(bool $value = true): self
    {
        $this->keyValueReorderable = $value;
        return $this;
    }

    // ==================== 可重复项构建器（类似 Filament Repeater） ====================

    /**
     * 可重复项构建器（嵌套字段数组）
     */
    public function repeater(string $key, string $label): self
    {
        $field = new self($key, $label, 'repeater');
        $this->key = $key;
        $this->title = $label;
        $this->type = 'repeater';
        return $this;
    }

    public function schema(array $fields): self
    {
        $this->repeaterSchema = $fields;
        return $this;
    }

    public function collapsible(string $label = '', bool $value = true): self
    {
        $this->repeaterCollapsible = $value;
        if ($label) {
            $this->repeaterCollapsibleLabel = $label;
        }
        return $this;
    }

    public function minItems(int $count): self
    {
        $this->repeaterMinItems = $count;
        return $this;
    }

    public function maxItems(int $count): self
    {
        $this->repeaterMaxItems = $count;
        return $this;
    }

    public function cloneable(bool $value = true): self
    {
        $this->repeaterCloneable = $value;
        return $this;
    }

    public function reorderable(bool $value = true): self
    {
        $this->repeaterReorderable = $value;
        return $this;
    }

    public function itemLabel(string $label): self
    {
        $this->repeaterItemLabel = $label;
        return $this;
    }

    public function addItemLabel(string $label): self
    {
        $this->extra['addItemLabel'] = $label;
        return $this;
    }

    // ==================== Markdown 编辑器 ====================

    /**
     * Markdown 编辑器
     */
    public function markdownEditor(string $key, string $label): self
    {
        $field = new self($key, $label, 'markdown');
        $this->key = $key;
        $this->title = $label;
        $this->type = 'markdown';
        return $this;
    }

    public function markdownHeight(string $height): self
    {
        $this->markdownEditorHeight = $height;
        return $this;
    }

    public function markdownUploadDisk(string $disk): self
    {
        $this->markdownUploadDisk = $disk;
        return $this;
    }

    public function markdownUploadPath(string $path): self
    {
        $this->markdownUploadPath = $path;
        return $this;
    }

    // ==================== 切换按钮组（类似 Filament ToggleButtons） ====================

    /**
     * 切换按钮组
     */
    public function toggleButtons(string $key, string $label): self
    {
        $field = new self($key, $label, 'toggleButtons');
        $this->key = $key;
        $this->title = $label;
        $this->type = 'toggleButtons';
        return $this;
    }

    public function toggleButtonsOptions(array $options): self
    {
        $this->toggleButtonsOptions = $options;
        return $this;
    }

    public function toggleButtonsMultiple(bool $value = true): self
    {
        $this->toggleButtonsMultiple = $value;
        return $this;
    }

    // ==================== 切换按钮组/Markdown 结束 ====================

    // ==================== 图标 ====================

    public function iconPrefix(string $prefix): self
    {
        $this->iconPrefix = $prefix;
        return $this;
    }

    // ==================== 获取验证规则 ====================

    public function getRules(?int $id = null): array
    {
        $rules = [];

        if ($this->isRequired) {
            $rules[] = 'required';
        }

        if (is_string($this->validationRules)) {
            $rulesArray = $this->validationRules ? explode('|', $this->validationRules) : [];
        } else {
            $rulesArray = $this->validationRules;
        }

        foreach ($rulesArray as $rule) {
            if ($id && str_starts_with($rule, 'unique:')) {
                $rule .= ',' . $this->key . ',' . $id;
            }
            $rules[] = $rule;
        }

        if ($this->maxValue !== null) {
            $rules[] = 'max:' . $this->maxValue;
        }

        if ($this->minValue !== null) {
            $rules[] = 'min:' . $this->minValue;
        }

        if ($this->maxLength !== null) {
            $rules[] = 'max:' . $this->maxLength;
        }

        if ($this->minLength !== null) {
            $rules[] = 'min:' . $this->minLength;
        }

        return $rules;
    }

    public function isRequired(): bool
    {
        return $this->isRequired;
    }

    public function isCreateOnly(): bool
    {
        return $this->creationOnlyFlag;
    }

    public function isCreationOnly(): bool
    {
        return $this->creationOnlyFlag;
    }

    public function isUpdateOnly(): bool
    {
        return $this->updateOnlyFlag;
    }

    public function isHidden(): bool
    {
        return $this->isHidden;
    }

    /**
     * 解析选项（支持动态回调）
     */
    public function resolveOptions(): array
    {
        if ($this->optionsCallback !== null) {
            return ($this->optionsCallback)();
        }
        return $this->options;
    }

    /**
     * 转换为数组（供前端渲染）
     */
    public function toArray(): array
    {
        return array_filter([
            'key' => $this->key,
            'title' => $this->title,
            'type' => $this->type,
            'required' => $this->isRequired ?: null,
            'options' => $this->resolveOptions() ?: null,
            'default' => $this->defaultValue,
            'max' => $this->maxValue,
            'min' => $this->minValue,
            'multiple' => $this->multipleFlag ?: null,
            'creationOnly' => $this->creationOnlyFlag ?: null,
            'updateOnly' => $this->updateOnlyFlag ?: null,
            'placeholder' => $this->placeholder,
            'help' => $this->help,
            'disabled' => $this->disabled ?: null,
            'readonly' => $this->readonly ?: null,
            'maxLength' => $this->maxLength,
            'minLength' => $this->minLength,
            'prefix' => $this->prefix,
            'suffix' => $this->suffix,
            'prepend' => $this->prepend,
            'append' => $this->append,
            'displayWhen' => !empty($this->displayWhen) ? $this->displayWhen : null,
            'depends' => !empty($this->depends) ? $this->depends : null,
            'searchable' => $this->searchable ?: null,
            'allowCreate' => $this->allowCreate ?: null,
            'clearable' => $this->clearable ?: null,
            'step' => $this->step,
            'precision' => $this->precision,
            'format' => $this->format,
            'colorMode' => $this->type === 'color' ? $this->colorMode : null,
            'rateCount' => $this->type === 'rate' ? $this->rateCount : null,
            'showScore' => $this->type === 'rate' ? $this->showScore : null,
            'rows' => $this->type === 'textarea' ? $this->rows : null,
            'editor' => $this->type === 'editor' ? $this->editor : null,
            'disk' => in_array($this->type, ['image', 'file', 'images']) ? $this->uploadDisk : null,
            'path' => in_array($this->type, ['image', 'file', 'images']) ? $this->uploadPath : null,
            'accept' => !empty($this->uploadAccept) ? $this->uploadAccept : null,
            'maxUpload' => $this->type === 'images' ? $this->maxUpload : null,
            'keyValueKeyLabel' => $this->type === 'keyValue' ? ($this->keyValueKeyLabel['label'] ?? '键') : null,
            'keyValueValueLabel' => $this->type === 'keyValue' ? ($this->keyValueValueLabel['label'] ?? '值') : null,
            'keyValueReorderable' => $this->type === 'keyValue' ? $this->keyValueReorderable : null,
            'repeaterSchema' => $this->type === 'repeater' && !empty($this->repeaterSchema) ? $this->repeaterSchema : null,
            'repeaterCollapsible' => $this->type === 'repeater' ? $this->repeaterCollapsible : null,
            'repeaterCollapsibleLabel' => $this->type === 'repeater' && $this->repeaterCollapsibleLabel ? $this->repeaterCollapsibleLabel : null,
            'repeaterMinItems' => $this->type === 'repeater' && $this->repeaterMinItems > 0 ? $this->repeaterMinItems : null,
            'repeaterMaxItems' => $this->type === 'repeater' && $this->repeaterMaxItems > 0 ? $this->repeaterMaxItems : null,
            'repeaterCloneable' => $this->type === 'repeater' ? $this->repeaterCloneable : null,
            'repeaterReorderable' => $this->type === 'repeater' ? $this->repeaterReorderable : null,
            'repeaterItemLabel' => $this->type === 'repeater' ? $this->repeaterItemLabel : null,
            'markdownHeight' => $this->type === 'markdown' ? ($this->markdownEditorHeight ?? '300px') : null,
            'markdownUploadDisk' => $this->type === 'markdown' ? $this->markdownUploadDisk : null,
            'markdownUploadPath' => $this->type === 'markdown' ? $this->markdownUploadPath : null,
            'toggleButtonsOptions' => $this->type === 'toggleButtons' && !empty($this->toggleButtonsOptions) ? $this->toggleButtonsOptions : null,
            'toggleButtonsMultiple' => $this->type === 'toggleButtons' ? $this->toggleButtonsMultiple : null,
        ], fn($v) => $v !== null);
    }
}
