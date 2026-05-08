<?php
/*
 * @Author:  lvtu@dabashan.cc
 * @Date: 2026-05-08 21:42:20
 * @LastEditTime: 2026-05-08 21:42:20
 * Copyright (c) 2025 by Dabashan.cc, All Rights Reserved.
 */


namespace Dabashan\DbsAdmin\Grid;

use Illuminate\Database\Eloquent\Builder;

/**
 * Grid 过滤器类
 *
 * 支持多种过滤类型， Filter 的能力
 * 支持自定义查询回调（Filter::query 回调模式）
 */
class Filter
{
    protected string $key;
    protected string $title;
    protected string $type;
    protected array $options = [];
    protected ?\Closure $queryCallback = null;
    protected mixed $defaultValue = null;
    protected ?string $placeholder = null;
    protected bool $multiple = false;
    protected array $extra = [];

    public function __construct(string $key, string $title, string $type = 'like')
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

    /**
     * 设置选项（用于 select 类型）
     */
    public function options(array $options): self
    {
        $this->options = $options;
        return $this;
    }

    /**
     * 设置自定义查询回调（ Filter::query）
     *
     * 示例: fn($query, $value) => $query->where('status', $value)
     */
    public function setQueryCallback(\Closure $callback): self
    {
        $this->queryCallback = $callback;
        return $this;
    }

    /**
     * 默认值
     */
    public function default(mixed $value): self
    {
        $this->defaultValue = $value;
        return $this;
    }

    /**
     * 占位文本
     */
    public function placeholder(string $text): self
    {
        $this->placeholder = $text;
        return $this;
    }

    /**
     * 多选模式
     */
    public function multiple(bool $value = true): self
    {
        $this->multiple = $value;
        return $this;
    }

    /**
     * 额外属性（传递给前端的自定义配置）
     */
    public function extra(array $attrs): self
    {
        $this->extra = $attrs;
        return $this;
    }

    /**
     * 应用过滤条件到查询
     */
    public function apply(Builder $query, mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        // 自定义回调优先
        if ($this->queryCallback !== null) {
            ($this->queryCallback)($query, $value);
            return;
        }

        match ($this->type) {
            'like' => $this->applyLike($query, $value),
            'equal', 'select' => $this->applyEqual($query, $value),
            'between_date' => $this->applyBetweenDate($query, $value),
            'in' => $this->applyIn($query, $value),
            'gt' => $this->applyGreaterThan($query, $value),
            'lt' => $this->applyLessThan($query, $value),
            'between' => $this->applyBetween($query, $value),
            default => $this->applyLike($query, $value),
        };
    }

    protected function applyLike(Builder $query, mixed $value): void
    {
        $query->where($this->key, 'like', "%{$value}%");
    }

    protected function applyEqual(Builder $query, mixed $value): void
    {
        if (is_array($value) && $this->multiple) {
            $query->whereIn($this->key, $value);
        } else {
            $query->where($this->key, $value);
        }
    }

    protected function applyBetweenDate(Builder $query, mixed $value): void
    {
        if (is_array($value)) {
            $start = $value[0] ?? null;
            $end = $value[1] ?? null;
        } else {
            return;
        }

        if ($start) {
            $query->where($this->key, '>=', $start);
        }

        if ($end) {
            $query->where($this->key, '<=', $end);
        }
    }

    protected function applyIn(Builder $query, mixed $value): void
    {
        $values = is_array($value) ? $value : explode(',', (string) $value);
        $query->whereIn($this->key, $values);
    }

    protected function applyGreaterThan(Builder $query, mixed $value): void
    {
        $query->where($this->key, '>', $value);
    }

    protected function applyLessThan(Builder $query, mixed $value): void
    {
        $query->where($this->key, '<', $value);
    }

    protected function applyBetween(Builder $query, mixed $value): void
    {
        if (is_array($value) && count($value) === 2) {
            $query->whereBetween($this->key, [$value[0], $value[1]]);
        }
    }

    /**
     * 转换为数组（供前端使用）
     */
    public function toArray(): array
    {
        return array_filter([
            'key' => $this->key,
            'title' => $this->title,
            'type' => $this->type,
            'options' => !empty($this->options) ? $this->options : null,
            'defaultValue' => $this->defaultValue,
            'placeholder' => $this->placeholder,
            'multiple' => $this->multiple ?: null,
            'hasCustomQuery' => $this->queryCallback !== null,
            'extra' => !empty($this->extra) ? $this->extra : null,
        ], fn($v) => $v !== null);
    }
}
