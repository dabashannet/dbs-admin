<?php

namespace Dabashan\DbsAdmin\Form;

/**
 * Form 字段定义类
 *
 * 用于定义表单字段的属性，支持链式调用
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

    /**
     * 创建字段实例
     *
     * @param string $key   字段名
     * @param string $title 显示标题
     * @param string $type  字段类型: text|password|select|multiSelect|switch|image|textarea|number
     */
    public function __construct(string $key, string $title, string $type = 'text')
    {
        $this->key = $key;
        $this->title = $title;
        $this->type = $type;
    }

    /**
     * 获取字段名
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * 获取字段类型
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * 设置字段类型
     */
    public function type(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    /**
     * 设置必填
     */
    public function required(bool $value = true): self
    {
        $this->isRequired = $value;
        return $this;
    }

    /**
     * 设置验证规则
     */
    public function rules(string|array $rules): self
    {
        $this->validationRules = $rules;
        return $this;
    }

    /**
     * 添加单个验证规则
     */
    public function rule(string $rule): self
    {
        if (is_string($this->validationRules)) {
            $this->validationRules = $this->validationRules ? explode('|', $this->validationRules) : [];
        }
        $this->validationRules[] = $rule;
        return $this;
    }

    /**
     * 设置选项（用于 select/multiSelect）
     */
    public function options($options): self
    {
        if ($options instanceof \Illuminate\Support\Collection) {
            $options = $options->toArray();
        }
        $this->options = $options;
        return $this;
    }

    /**
     * 设置默认值
     */
    public function default(mixed $value): self
    {
        $this->defaultValue = $value;
        return $this;
    }

    /**
     * 设置最大值/最大长度
     */
    public function max(int $value): self
    {
        $this->maxValue = $value;
        return $this;
    }

    /**
     * 设置最小值/最小长度
     */
    public function min(int $value): self
    {
        $this->minValue = $value;
        return $this;
    }

    /**
     * 设置为多选
     */
    public function multiple(bool $value = true): self
    {
        $this->multipleFlag = $value;
        return $this;
    }

    /**
     * 设置仅创建时显示
     */
    public function createOnly(bool $value = true): self
    {
        $this->creationOnlyFlag = $value;
        return $this;
    }

    /**
     * 设置仅创建时显示（别名）
     */
    public function creationOnly(bool $value = true): self
    {
        return $this->createOnly($value);
    }

    /**
     * 设置仅更新时显示
     */
    public function updateOnly(bool $value = true): self
    {
        $this->updateOnlyFlag = $value;
        return $this;
    }

    /**
     * 设置占位符
     */
    public function placeholder(string $placeholder): self
    {
        $this->placeholder = $placeholder;
        return $this;
    }

    /**
     * 设置帮助文本
     */
    public function help(string $help): self
    {
        $this->help = $help;
        return $this;
    }

    /**
     * 获取验证规则
     *
     * @param int|null $id 编辑时的记录 ID（用于 unique 规则排除）
     */
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
            // 处理 unique 规则，添加 ID 排除
            if ($id && str_starts_with($rule, 'unique:')) {
                $rule .= ',' . $this->key . ',' . $id;
            }
            $rules[] = $rule;
        }

        // 添加 max 规则
        if ($this->maxValue !== null) {
            $rules[] = 'max:' . $this->maxValue;
        }

        // 添加 min 规则
        if ($this->minValue !== null) {
            $rules[] = 'min:' . $this->minValue;
        }

        return $rules;
    }

    /**
     * 判断是否必填
     */
    public function isRequired(): bool
    {
        return $this->isRequired;
    }

    /**
     * 判断是否仅创建时显示
     */
    public function isCreateOnly(): bool
    {
        return $this->creationOnlyFlag;
    }

    /**
     * 判断是否仅创建时显示（别名）
     */
    public function isCreationOnly(): bool
    {
        return $this->creationOnlyFlag;
    }

    /**
     * 判断是否仅更新时显示
     */
    public function isUpdateOnly(): bool
    {
        return $this->updateOnlyFlag;
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
            'options' => !empty($this->options) ? $this->options : null,
            'default' => $this->defaultValue,
            'max' => $this->maxValue,
            'min' => $this->minValue,
            'multiple' => $this->multipleFlag ?: null,
            'creationOnly' => $this->creationOnlyFlag ?: null,
            'updateOnly' => $this->updateOnlyFlag ?: null,
            'placeholder' => $this->placeholder,
            'help' => $this->help,
        ], fn($v) => $v !== null);
    }
}
