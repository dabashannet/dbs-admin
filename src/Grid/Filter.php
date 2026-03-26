<?php

namespace Dabashan\DbsAdmin\Grid;

use Illuminate\Database\Eloquent\Builder;

/**
 * Grid 过滤器类
 *
 * 支持多种过滤类型：like, equal, between_date, in, select, gt, lt
 */
class Filter
{
    protected string $key;
    protected string $title;
    protected string $type;
    protected array $options = [];

    /**
     * 创建过滤器实例
     *
     * @param string $key   字段名
     * @param string $title 显示标题
     * @param string $type  过滤类型: like|equal|between_date|in|select|gt|lt
     */
    public function __construct(string $key, string $title, string $type = 'like')
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
     * 获取过滤类型
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * 设置选项（用于 select 类型）
     *
     * @param array $options 选项数组 [value => label]
     */
    public function options(array $options): self
    {
        $this->options = $options;
        return $this;
    }

    /**
     * 应用过滤条件到查询
     *
     * @param Builder $query 查询构建器
     * @param mixed $value 过滤值
     */
    public function apply(Builder $query, mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        match ($this->type) {
            'like' => $this->applyLike($query, $value),
            'equal', 'select' => $this->applyEqual($query, $value),
            'between_date' => $this->applyBetweenDate($query, $value),
            'in' => $this->applyIn($query, $value),
            'gt' => $this->applyGreaterThan($query, $value),
            'lt' => $this->applyLessThan($query, $value),
            default => $this->applyLike($query, $value),
        };
    }

    /**
     * LIKE 模糊匹配
     */
    protected function applyLike(Builder $query, mixed $value): void
    {
        $query->where($this->key, 'like', "%{$value}%");
    }

    /**
     * 精确匹配
     */
    protected function applyEqual(Builder $query, mixed $value): void
    {
        $query->where($this->key, $value);
    }

    /**
     * 日期范围查询
     *
     * 期望 value 是数组 [start, end]
     */
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

    /**
     * IN 查询
     */
    protected function applyIn(Builder $query, mixed $value): void
    {
        $values = is_array($value) ? $value : explode(',', $value);
        $query->whereIn($this->key, $values);
    }

    /**
     * 大于查询
     */
    protected function applyGreaterThan(Builder $query, mixed $value): void
    {
        $query->where($this->key, '>', $value);
    }

    /**
     * 小于查询
     */
    protected function applyLessThan(Builder $query, mixed $value): void
    {
        $query->where($this->key, '<', $value);
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
        ], fn($v) => $v !== null);
    }
}
