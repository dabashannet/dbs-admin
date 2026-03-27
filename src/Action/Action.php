<?php
/*
 * @Author: Author lvtu@dabashan.cc
 * @Date: 2026-03-27 00:57:34
 * @LastEditTime: 2026-03-27 17:08:47
 * @LastEditors: LastEditors
 * @Copyright: Copyright (c) 2025 by Dabashan.cc, All Rights Reserved.
 */

namespace Dabashan\DbsAdmin\Action;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Action 操作基类
 *
 * 提供批量操作和行操作的基础实现
 */
abstract class Action
{
    protected string $name;
    protected string $title;
    protected string $confirmMessage = '';
    protected bool $needConfirm = false;

    /**
     * 创建操作实例
     */
    public function __construct(string $name, string $title)
    {
        $this->name = $name;
        $this->title = $title;
    }

    /**
     * 获取操作名称
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * 获取操作标题
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * 设置需要确认
     */
    public function confirm(string $message = '确定执行此操作？'): self
    {
        $this->needConfirm = true;
        $this->confirmMessage = $message;
        return $this;
    }

    /**
     * 执行操作
     *
     * @param Builder $query 查询构建器
     * @param Request $request 请求对象
     * @return array 操作结果
     */
    abstract public function handle(Builder $query, Request $request): array;

    /**
     * 转换为数组（供前端渲染）
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'title' => $this->title,
            'needConfirm' => $this->needConfirm ?: null,
            'confirmMessage' => $this->confirmMessage ?: null,
        ], fn($v) => $v !== null);
    }
}
