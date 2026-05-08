<?php
/*
 * @Author:  lvtu@dabashan.cc
 * @Date: 2026-05-08 21:42:20
 * @LastEditTime: 2026-05-08 21:42:20
 * Copyright (c) 2025 by Dabashan.cc, All Rights Reserved.
 */


namespace Dabashan\DbsAdmin\Grid;

/**
 * 操作分组类（类似 Filament Action Group）
 *
 * 将多个操作组合为一个分组，显示为下拉菜单或按钮组
 */
class ActionGroup
{
    protected string $label = '更多操作';
    protected string $icon = 'icon-more';
    protected string $type = 'default';
    protected array $actions = [];
    protected string $position = Action::POSITION_ROW;
    protected string $mode = 'dropdown';  // dropdown, modal

    public function __construct(array $actions = [])
    {
        $this->actions = $actions;
    }

    public static function make(array $actions = []): self
    {
        return new self($actions);
    }

    public function label(string $label): self
    {
        $this->label = $label;
        return $this;
    }

    public function icon(string $icon): self
    {
        $this->icon = $icon;
        return $this;
    }

    public function type(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function position(string $position): self
    {
        $this->position = $position;
        return $this;
    }

    public function row(): self
    {
        $this->position = Action::POSITION_ROW;
        return $this;
    }

    public function header(): self
    {
        $this->position = Action::POSITION_HEADER;
        return $this;
    }

    public function dropdown(): self
    {
        $this->mode = 'dropdown';
        return $this;
    }

    public function modal(): self
    {
        $this->mode = 'modal';
        return $this;
    }

    public function actions(array $actions): self
    {
        $this->actions = $actions;
        return $this;
    }

    public function addAction(Action $action): self
    {
        $this->actions[] = $action;
        return $this;
    }

    public function getActions(): array
    {
        return $this->actions;
    }

    public function getPosition(): string
    {
        return $this->position;
    }

    public function toArray(): array
    {
        return [
            'key' => 'group_' . spl_object_id($this),
            'label' => $this->label,
            'icon' => $this->icon,
            'type' => $this->type,
            'position' => $this->position,
            'mode' => $this->mode,
            'isGroup' => true,
            'actions' => array_map(fn(Action $a) => $a->toArray(), $this->actions),
        ];
    }
}
