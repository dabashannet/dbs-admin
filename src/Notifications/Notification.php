<?php
/*
 * @Author:  lvtu@dabashan.cc
 * @Date: 2026-05-08 21:42:20
 * @LastEditTime: 2026-05-08 21:42:20
 * Copyright (c) 2025 by Dabashan.cc, All Rights Reserved.
 */


namespace Dabashan\DbsAdmin\Notifications;

/**
 * 通知系统
 *
 * 类似 Filament Notifications 的流畅 API
 * 后端推送通知到前端，Arco Vue 自动渲染
 *
 * 用法:
 * Notification::make()
 *     ->title('操作成功')
 *     ->body('数据已保存')
 *     ->success()
 *     ->send();
 *
 * 在控制器中:
 * return $this->success($data)->withNotification(
 *     Notification::make()->title('保存成功')->success()
 * );
 */
class Notification
{
    public const TYPE_SUCCESS = 'success';
    public const TYPE_WARNING = 'warning';
    public const TYPE_ERROR = 'error';
    public const TYPE_INFO = 'info';

    protected string $type = self::TYPE_INFO;
    protected string $title = '';
    protected string $body = '';
    protected int $duration = 3000;
    protected bool $closable = true;
    protected ?string $icon = null;
    protected array $actions = [];

    public static function make(?string $title = null): self
    {
        $instance = new self();
        if ($title) {
            $instance->title = $title;
        }
        return $instance;
    }

    public function title(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function body(string $body): self
    {
        $this->body = $body;
        return $this;
    }

    public function success(): self
    {
        $this->type = self::TYPE_SUCCESS;
        $this->icon = 'icon-check-circle-fill';
        return $this;
    }

    public function warning(): self
    {
        $this->type = self::TYPE_WARNING;
        $this->icon = 'icon-exclamation-circle-fill';
        return $this;
    }

    public function error(): self
    {
        $this->type = self::TYPE_ERROR;
        $this->icon = 'icon-close-circle-fill';
        return $this;
    }

    public function info(): self
    {
        $this->type = self::TYPE_INFO;
        $this->icon = 'icon-info-circle-fill';
        return $this;
    }

    public function type(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function duration(int $ms): self
    {
        $this->duration = $ms;
        return $this;
    }

    public function closable(bool $value = true): self
    {
        $this->closable = $value;
        return $this;
    }

    public function icon(string $icon): self
    {
        $this->icon = $icon;
        return $this;
    }

    public function action(string $label, string $event): self
    {
        $this->actions[] = ['label' => $label, 'event' => $event];
        return $this;
    }

    /**
     * 发送通知到 Session（下次请求时前端获取）
     */
    public function send(): void
    {
        session()->push('dbs_notifications', $this->toArray());
    }

    /**
     * 快捷方法：发送成功通知
     */
    public static function sendSuccess(string $title, string $body = ''): self
    {
        return self::make($title)->body($body)->success()->send();
    }

    /**
     * 快捷方法：发送错误通知
     */
    public static function sendError(string $title, string $body = ''): self
    {
        return self::make($title)->body($body)->error()->send();
    }

    /**
     * 快捷方法：发送警告通知
     */
    public static function sendWarning(string $title, string $body = ''): self
    {
        return self::make($title)->body($body)->warning()->send();
    }

    /**
     * 快捷方法：发送信息通知
     */
    public static function sendInfo(string $title, string $body = ''): self
    {
        return self::make($title)->body($body)->info()->send();
    }

    /**
     * 获取并清除 Session 中的通知
     */
    public static function pull(): array
    {
        return session()->pull('dbs_notifications', []);
    }

    public function toArray(): array
    {
        return array_filter([
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body ?: null,
            'duration' => $this->duration,
            'closable' => $this->closable,
            'icon' => $this->icon,
            'actions' => !empty($this->actions) ? $this->actions : null,
        ], fn($v) => $v !== null);
    }
}
