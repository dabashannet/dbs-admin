<?php

namespace Dabashan\DbsAdmin\Grid;

/**
 * Grid 列定义类
 *
 * 用于定义表格列的属性，支持链式调用
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

    /**
     * 创建列实例
     *
     * @param string $key   字段名
     * @param string $title 显示标题
     */
    public function __construct(string $key, string $title)
    {
        $this->key = $key;
        $this->title = $title;
    }

    /**
     * 设置列是否可排序
     */
    public function sortable(bool $value = true): self
    {
        $this->sortable = $value;
        return $this;
    }

    /**
     * 设置列是否可搜索
     */
    public function searchable(bool $value = true): self
    {
        $this->searchable = $value;
        return $this;
    }

    /**
     * 设置列是否隐藏
     */
    public function hidden(bool $value = true): self
    {
        $this->hidden = $value;
        return $this;
    }

    /**
     * 设置列宽度
     */
    public function width(string $width): self
    {
        $this->width = $width;
        return $this;
    }

    /**
     * 设置列对齐方式
     *
     * @param string $align left|center|right
     */
    public function align(string $align): self
    {
        $this->align = $align;
        return $this;
    }

    /**
     * 获取字段名
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * 判断是否可排序
     */
    public function isSortable(): bool
    {
        return $this->sortable;
    }

    /**
     * 判断是否可搜索
     */
    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    /**
     * 转换为数组（前端 JSON）
     */
    public function toArray(): array
    {
        return array_filter([
            'key' => $this->key,
            'title' => $this->title,
            'sortable' => $this->sortable ?: null,
            'searchable' => $this->searchable ?: null,
            'hidden' => $this->hidden ?: null,
            'width' => $this->width,
            'align' => $this->align,
        ], fn($v) => $v !== null);
    }
}
