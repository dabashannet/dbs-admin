<?php

namespace Dabashan\DbsAdmin\Form;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Form 表单基类
 *
 * 后端定义表单元数据，前端根据元数据动态渲染
 * 支持字段定义、验证规则、上下文感知（create/update）
 */
class Form
{
    protected array $fields = [];
    protected ?Field $lastField = null;

    /**
     * 静态工厂方法
     *
     * @param mixed $model 可选的模型类名（保留用于兼容）
     */
    public static function make($model = null): self
    {
        return new self();
    }

    /**
     * 添加字段
     *
     * @param string $key   字段名
     * @param string $label 显示标签
     */
    public function field(string $key, string $label): self
    {
        $field = new Field($key, $label);
        $this->fields[] = $field;
        $this->lastField = $field;
        return $this;
    }

    /**
     * 添加文本输入字段
     */
    public function text(string $key, string $label): self
    {
        return $this->field($key, $label)->type('text');
    }

    /**
     * 添加密码输入字段
     */
    public function password(string $key, string $label): self
    {
        return $this->field($key, $label)->type('password');
    }

    /**
     * 添加下拉选择字段
     */
    public function select(string $key, string $label): self
    {
        return $this->field($key, $label)->type('select');
    }

    /**
     * 添加图片上传字段
     */
    public function image(string $key, string $label): self
    {
        return $this->field($key, $label)->type('image');
    }

    /**
     * 添加开关字段
     */
    public function switch(string $key, string $label): self
    {
        return $this->field($key, $label)->type('switch');
    }

    /**
     * 添加多行文本字段
     */
    public function textarea(string $key, string $label): self
    {
        return $this->field($key, $label)->type('textarea');
    }

    /**
     * 添加数字输入字段
     */
    public function number(string $key, string $label): self
    {
        return $this->field($key, $label)->type('number');
    }

    /**
     * 设置最后添加的字段类型
     */
    public function type(string $type): self
    {
        $this->lastField?->type($type);
        return $this;
    }

    /**
     * 设置最后添加的字段为必填
     */
    public function required(bool $v = true): self
    {
        $this->lastField?->required($v);
        return $this;
    }

    /**
     * 设置最后添加的字段最大值/最大长度
     */
    public function max(int $v): self
    {
        $this->lastField?->max($v);
        return $this;
    }

    /**
     * 设置最后添加的字段最小值/最小长度
     */
    public function min(int $v): self
    {
        $this->lastField?->min($v);
        return $this;
    }

    /**
     * 设置最后添加的字段选项
     *
     * @param array|Collection $options 选项数组或 Collection
     */
    public function options($options): self
    {
        $this->lastField?->options($options);
        return $this;
    }

    /**
     * 设置最后添加的字段为多选
     */
    public function multiple(bool $v = true): self
    {
        $this->lastField?->multiple($v);
        return $this;
    }

    /**
     * 设置最后添加的字段默认值
     */
    public function default($v): self
    {
        $this->lastField?->default($v);
        return $this;
    }

    /**
     * 设置最后添加的字段占位文本
     */
    public function placeholder(string $v): self
    {
        $this->lastField?->placeholder($v);
        return $this;
    }

    /**
     * 设置最后添加的字段帮助文本
     */
    public function help(string $v): self
    {
        $this->lastField?->help($v);
        return $this;
    }

    /**
     * 设置最后添加的字段仅创建时显示
     */
    public function createOnly(): self
    {
        $this->lastField?->createOnly();
        return $this;
    }

    /**
     * 设置最后添加的字段仅创建时显示（别名）
     */
    public function creationOnly(): self
    {
        return $this->createOnly();
    }

    /**
     * 设置最后添加的字段仅更新时显示
     */
    public function updateOnly(): self
    {
        $this->lastField?->updateOnly();
        return $this;
    }

    /**
     * 添加验证规则
     */
    public function rule(string $rule): self
    {
        $this->lastField?->rule($rule);
        return $this;
    }

    /**
     * 设置验证规则（别名，支持字符串）
     */
    public function rules(string $rules): self
    {
        $this->lastField?->rules($rules);
        return $this;
    }

    /**
     * 获取表单元数据
     *
     * @param string|null $context 上下文: create|update，null 返回全部字段
     */
    public function schema(?string $context = null): array
    {
        $fields = $this->fields;

        // 根据上下文过滤字段
        if ($context !== null) {
            $fields = array_filter($fields, function (Field $f) use ($context) {
                if ($context === 'create' && $f->isUpdateOnly()) {
                    return false;
                }
                if ($context === 'update' && $f->isCreateOnly()) {
                    return false;
                }
                return true;
            });
        }

        return [
            'fields' => array_values(array_map(fn(Field $f) => $f->toArray(), $fields)),
        ];
    }

    /**
     * getSchema 别名
     */
    public function getSchema(?string $context = null): array
    {
        return $this->schema($context);
    }

    /**
     * 验证请求数据
     *
     * @param Request $request HTTP 请求
     * @param string  $context 上下文: create|update
     * @return array 验证后的数据
     */
    public function validate(Request $request, string $context = 'create'): array
    {
        $rules = [];

        foreach ($this->fields as $field) {
            // 根据上下文跳过字段
            if ($context === 'create' && $field->isUpdateOnly()) {
                continue;
            }
            if ($context === 'update' && $field->isCreateOnly()) {
                continue;
            }

            $fieldRules = $field->getRules();
            if (!empty($fieldRules)) {
                // update 时将 required 改为 sometimes
                if ($context === 'update') {
                    $fieldRules = array_map(
                        fn($r) => $r === 'required' ? 'sometimes' : $r,
                        $fieldRules
                    );
                }
                $rules[$field->getKey()] = $fieldRules;
            }
        }

        return $request->validate($rules);
    }

    /**
     * 获取所有字段的 key 列表
     *
     * @param string $context 上下文: create|update
     */
    public function fieldKeys(string $context = 'create'): array
    {
        $filtered = array_filter($this->fields, function (Field $f) use ($context) {
            if ($context === 'create' && $f->isUpdateOnly()) {
                return false;
            }
            if ($context === 'update' && $f->isCreateOnly()) {
                return false;
            }
            return true;
        });

        return array_map(fn(Field $f) => $f->getKey(), $filtered);
    }

    /**
     * 从请求中提取指定字段的数据
     *
     * @param Request $request HTTP 请求
     * @param string  $context 上下文: create|update
     */
    public function getData(Request $request, string $context = 'create'): array
    {
        $keys = $this->fieldKeys($context);
        return $request->only($keys);
    }
}
