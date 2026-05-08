<?php
/*
 * @Author:  lvtu@dabashan.cc
 * @Date: 2026-05-08 21:42:20
 * @LastEditTime: 2026-05-08 21:42:20
 * Copyright (c) 2025 by Dabashan.cc, All Rights Reserved.
 */


namespace Dabashan\DbsAdmin\Form;

use Illuminate\Http\Request;

/**
 * Form 表单基类
 *
 *  Form 的完整能力
 * 后端定义表单元数据，前端根据元数据动态渲染
 * 支持所有常见输入类型、布局嵌套、联动字段、动态选项
 */
class Form
{
    protected array $fields = [];
    protected ?Field $lastField = null;
    protected array $layout = [];           // 布局定义（tabs, columns, section）
    protected string $layoutMode = 'default'; // default, tabs, columns, section
    protected string $method = 'POST';      // 表单方法
    protected string $action = '';          // 表单提交地址
    protected bool $inline = false;         // 是否行内表单
    protected string $labelWidth = 'auto';  // 标签宽度
    protected string $labelAlign = 'left';  // 标签对齐
    protected bool $showReset = true;       // 显示重置按钮
    protected bool $showSubmit = true;      // 显示提交按钮
    protected string $submitText = '提交';  // 提交按钮文本
    protected string $resetText = '重置';   // 重置按钮文本
    protected bool $scrollToError = true;   // 自动滚动到错误字段

    public static function make(mixed $model = null): self
    {
        return new self();
    }

    // ==================== 基础字段 ====================

    public function field(string $key, string $label): self
    {
        $field = new Field($key, $label);
        $this->fields[] = $field;
        $this->lastField = $field;
        return $this;
    }

    public function text(string $key, string $label): self
    {
        return $this->field($key, $label)->type('text');
    }

    public function password(string $key, string $label): self
    {
        return $this->field($key, $label)->type('password');
    }

    public function textarea(string $key, string $label): self
    {
        return $this->field($key, $label)->type('textarea');
    }

    public function number(string $key, string $label): self
    {
        return $this->field($key, $label)->type('number');
    }

    public function email(string $key, string $label): self
    {
        return $this->field($key, $label)->type('email');
    }

    public function url(string $key, string $label): self
    {
        return $this->field($key, $label)->type('url');
    }

    public function hidden(string $key): self
    {
        $field = new Field($key, $key);
        $field->type('hidden');
        $this->fields[] = $field;
        $this->lastField = $field;
        return $this;
    }

    // ==================== 选择类字段 ====================

    public function select(string $key, string $label): self
    {
        return $this->field($key, $label)->type('select');
    }

    public function radio(string $key, string $label): self
    {
        return $this->field($key, $label)->type('radio');
    }

    public function checkbox(string $key, string $label): self
    {
        return $this->field($key, $label)->type('checkbox');
    }

    public function treeSelect(string $key, string $label): self
    {
        return $this->field($key, $label)->type('treeSelect');
    }

    public function autoComplete(string $key, string $label): self
    {
        return $this->field($key, $label)->type('autoComplete');
    }

    public function cascader(string $key, string $label): self
    {
        return $this->field($key, $label)->type('cascader');
    }

    // ==================== 日期时间字段 ====================

    public function date(string $key, string $label, string $format = 'YYYY-MM-DD'): self
    {
        return $this->field($key, $label)->type('date')->format($format);
    }

    public function dateTime(string $key, string $label, string $format = 'YYYY-MM-DD HH:mm:ss'): self
    {
        return $this->field($key, $label)->type('datetime')->format($format);
    }

    public function time(string $key, string $label): self
    {
        return $this->field($key, $label)->type('time');
    }

    public function dateRange(string $key, string $label, string $format = 'YYYY-MM-DD'): self
    {
        return $this->field($key, $label)->type('dateRange')->format($format);
    }

    public function year(string $key, string $label): self
    {
        return $this->field($key, $label)->type('year');
    }

    public function month(string $key, string $label): self
    {
        return $this->field($key, $label)->type('month');
    }

    // ==================== 上传类字段 ====================

    public function image(string $key, string $label): self
    {
        return $this->field($key, $label)->type('image');
    }

    public function images(string $key, string $label): self
    {
        return $this->field($key, $label)->type('images');
    }

    public function file(string $key, string $label): self
    {
        return $this->field($key, $label)->type('file');
    }

    public function files(string $key, string $label): self
    {
        return $this->field($key, $label)->type('files');
    }

    // ==================== 特殊字段 ====================

    public function switch(string $key, string $label): self
    {
        return $this->field($key, $label)->type('switch');
    }

    public function slider(string $key, string $label): self
    {
        return $this->field($key, $label)->type('slider');
    }

    public function rate(string $key, string $label): self
    {
        return $this->field($key, $label)->type('rate');
    }

    public function color(string $key, string $label): self
    {
        return $this->field($key, $label)->type('color');
    }

    public function tags(string $key, string $label): self
    {
        return $this->field($key, $label)->type('tags');
    }

    public function editor(string $key, string $label, string $driver = 'wangEditor'): self
    {
        $this->field($key, $label)->type('editor');
        $this->lastField?->editor($driver);
        return $this;
    }

    public function code(string $key, string $label, string $language = 'php'): self
    {
        return $this->field($key, $label)->type('code');
    }

    public function html(string $content): self
    {
        $field = new Field('_html_' . md5($content), '');
        $field->type('html');
        $this->fields[] = $field;
        $this->lastField = $field;
        return $this;
    }

    public function divider(string $text = ''): self
    {
        $field = new Field('_divider_' . uniqid(), $text);
        $field->type('divider');
        $this->fields[] = $field;
        $this->lastField = $field;
        return $this;
    }

    public function icon(string $key, string $label): self
    {
        return $this->field($key, $label)->type('icon');
    }

    // ==================== 高级字段类型 ====================

    /**
     * 键值对输入（类似 Filament KeyValue）
     */
    public function keyValue(string $key, string $label = '键值对'): self
    {
        return $this->field($key, $label)->type('keyValue');
    }

    /**
     * 可重复项构建器（类似 Filament Repeater）
     */
    public function repeater(string $key, string $label = '重复项'): self
    {
        return $this->field($key, $label)->type('repeater');
    }

    /**
     * Markdown 编辑器
     */
    public function markdownEditor(string $key, string $label): self
    {
        return $this->field($key, $label)->type('markdown');
    }

    /**
     * 切换按钮组（类似 Filament ToggleButtons）
     */
    public function toggleButtons(string $key, string $label): self
    {
        return $this->field($key, $label)->type('toggleButtons');
    }

    // ==================== 链式方法快捷 ====================

    public function type(string $type): self
    {
        $this->lastField?->type($type);
        return $this;
    }

    public function required(bool $v = true): self
    {
        $this->lastField?->required($v);
        return $this;
    }

    public function max(int $v): self
    {
        $this->lastField?->max($v);
        return $this;
    }

    public function min(int $v): self
    {
        $this->lastField?->min($v);
        return $this;
    }

    public function options($options): self
    {
        $this->lastField?->options($options);
        return $this;
    }

    public function multiple(bool $v = true): self
    {
        $this->lastField?->multiple($v);
        return $this;
    }

    public function default($v): self
    {
        $this->lastField?->default($v);
        return $this;
    }

    public function placeholder(string $v): self
    {
        $this->lastField?->placeholder($v);
        return $this;
    }

    public function help(string $v): self
    {
        $this->lastField?->help($v);
        return $this;
    }

    public function createOnly(): self
    {
        $this->lastField?->createOnly();
        return $this;
    }

    public function creationOnly(): self
    {
        return $this->createOnly();
    }

    public function updateOnly(): self
    {
        $this->lastField?->updateOnly();
        return $this;
    }

    public function rule(string $rule): self
    {
        $this->lastField?->rule($rule);
        return $this;
    }

    public function rules(string $rules): self
    {
        $this->lastField?->rules($rules);
        return $this;
    }

    // ==================== 新增链式方法 ====================

    public function disabled(bool $v = true): self
    {
        $this->lastField?->disabled($v);
        return $this;
    }

    public function readonly(bool $v = true): self
    {
        $this->lastField?->readonly($v);
        return $this;
    }

    public function clearable(bool $v = true): self
    {
        $this->lastField?->clearable($v);
        return $this;
    }

    public function searchableOptions(bool $v = true): self
    {
        $this->lastField?->searchableOptions($v);
        return $this;
    }

    public function allowCreate(bool $v = true): self
    {
        $this->lastField?->allowCreate($v);
        return $this;
    }

    public function prefix(string $v): self
    {
        $this->lastField?->prefix($v);
        return $this;
    }

    public function suffix(string $v): self
    {
        $this->lastField?->suffix($v);
        return $this;
    }

    public function prepend(string $v): self
    {
        $this->lastField?->prepend($v);
        return $this;
    }

    public function append(string $v): self
    {
        $this->lastField?->append($v);
        return $this;
    }

    public function displayWhen(string $field, string $operator, mixed $value): self
    {
        $this->lastField?->displayWhen($field, $operator, $value);
        return $this;
    }

    public function depends(array $fields): self
    {
        $this->lastField?->depends($fields);
        return $this;
    }

    public function format(string $v): self
    {
        $this->lastField?->format($v);
        return $this;
    }

    public function rows(int $v): self
    {
        $this->lastField?->rows($v);
        return $this;
    }

    public function disk(string $v): self
    {
        $this->lastField?->disk($v);
        return $this;
    }

    public function path(string $v): self
    {
        $this->lastField?->path($v);
        return $this;
    }

    public function maxLength(int $v): self
    {
        $this->lastField?->maxLength($v);
        return $this;
    }

    public function step(int $v): self
    {
        $this->lastField?->step($v);
        return $this;
    }

    public function precision(int $v): self
    {
        $this->lastField?->precision($v);
        return $this;
    }

    // ==================== 布局方法 ====================

    /**
     * Tab 分组
     *
     * @param array $tabs [['label' => '基础信息', 'fields' => ['name', 'email']], ...]
     */
    public function tabs(array $tabs): self
    {
        $this->layoutMode = 'tabs';
        $this->layout = $tabs;
        return $this;
    }

    /**
     * 列布局（多列并排）
     *
     * @param int $span 列数
     */
    public function columns(int $span = 2): self
    {
        $this->layoutMode = 'columns';
        $this->layout = ['span' => $span];
        return $this;
    }

    /**
     * 分区块（fieldset/section）
     */
    public function section(string $title, array $fields = []): self
    {
        $this->layoutMode = 'section';
        $this->layout[] = ['title' => $title, 'fields' => $fields];
        return $this;
    }

    // ==================== 表单配置 ====================

    public function method(string $method): self
    {
        $this->method = $method;
        return $this;
    }

    public function action(string $action): self
    {
        $this->action = $action;
        return $this;
    }

    public function inline(bool $value = true): self
    {
        $this->inline = $value;
        return $this;
    }

    public function labelWidth(string $width): self
    {
        $this->labelWidth = $width;
        return $this;
    }

    public function showReset(bool $value = true): self
    {
        $this->showReset = $value;
        return $this;
    }

    public function showSubmit(bool $value = true): self
    {
        $this->showSubmit = $value;
        return $this;
    }

    public function submitText(string $text): self
    {
        $this->submitText = $text;
        return $this;
    }

    public function resetText(string $text): self
    {
        $this->resetText = $text;
        return $this;
    }

    // ==================== 核心方法 ====================

    protected function isSystemTimestampKey(string $key): bool
    {
        return in_array($key, ['created_at', 'updated_at', 'deleted_at'], true);
    }

    public function schema(?string $context = null): array
    {
        $fields = $this->fields;

        if ($context !== null) {
            $fields = array_filter($fields, function (Field $f) use ($context) {
                if ($context === 'create' && $f->isUpdateOnly()) {
                    return false;
                }
                if ($context === 'update' && $f->isCreateOnly()) {
                    return false;
                }
                if ($context === 'create' && $this->isSystemTimestampKey($f->getKey())) {
                    return false;
                }
                return true;
            });
        }

        if ($context === null || $context === 'update') {
            foreach ($fields as $f) {
                if ($this->isSystemTimestampKey($f->getKey())) {
                    if ($f->getKey() === 'updated_at' || $f->getKey() === 'deleted_at') {
                        $f->type('hidden');
                        continue;
                    }
                    $f->readonly(true)->disabled(true)->updateOnly(true);
                }
            }
        }

        return [
            'fields' => array_values(array_map(fn(Field $f) => $f->toArray(), $fields)),
            'layout' => [
                'mode' => $this->layoutMode,
                'config' => $this->layout,
            ],
            'config' => [
                'method' => $this->method,
                'action' => $this->action,
                'inline' => $this->inline,
                'labelWidth' => $this->labelWidth,
                'labelAlign' => $this->labelAlign,
                'showReset' => $this->showReset,
                'showSubmit' => $this->showSubmit,
                'submitText' => $this->submitText,
                'resetText' => $this->resetText,
                'scrollToError' => $this->scrollToError,
            ],
        ];
    }

    public function getSchema(?string $context = null): array
    {
        return $this->schema($context);
    }

    public function validate(Request $request, string $context = 'create'): array
    {
        $rules = [];
        $keys = $this->fieldKeys($context);

        foreach ($this->fields as $field) {
            if ($context === 'create' && $field->isUpdateOnly()) {
                continue;
            }
            if ($context === 'update' && $field->isCreateOnly()) {
                continue;
            }
            if ($this->isSystemTimestampKey($field->getKey())) {
                continue;
            }

            $fieldRules = $field->getRules();
            if (!empty($fieldRules)) {
                if ($context === 'update') {
                    $fieldRules = array_map(
                        fn($r) => $r === 'required' ? 'sometimes' : $r,
                        $fieldRules
                    );
                }
                $rules[$field->getKey()] = $fieldRules;
            }
        }

        $data = $request->only($keys);
        if (empty($rules)) {
            return $data;
        }

        $validated = $request->validate($rules);
        return array_merge($data, $validated);
    }

    public function fieldKeys(string $context = 'create'): array
    {
        $filtered = array_filter($this->fields, function (Field $f) use ($context) {
            if ($context === 'create' && $f->isUpdateOnly()) {
                return false;
            }
            if ($context === 'update' && $f->isCreateOnly()) {
                return false;
            }
            if ($this->isSystemTimestampKey($f->getKey())) {
                return false;
            }
            return true;
        });

        return array_map(fn(Field $f) => $f->getKey(), $filtered);
    }

    public function getData(Request $request, string $context = 'create'): array
    {
        $keys = $this->fieldKeys($context);
        return $request->only($keys);
    }

    public function getLayout(): array
    {
        return [
            'mode' => $this->layoutMode,
            'config' => $this->layout,
        ];
    }
}
