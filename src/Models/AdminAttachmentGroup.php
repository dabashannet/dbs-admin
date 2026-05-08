<?php
/*
 * @Author:  lvtu@dabashan.cc
 * @Date: 2026-05-08 21:42:20
 * @LastEditTime: 2026-05-08 21:42:20
 * Copyright (c) 2025 by Dabashan.cc, All Rights Reserved.
 */


namespace Dabashan\DbsAdmin\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 后台附件分组模型
 *
 * @property int $id
 * @property string $name 分组名称
 * @property string $type 分组类型（image/video/audio/file）
 * @property int $admin_user_id 所属用户 ID
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @property-read \Dabashan\DbsAdmin\Models\AdminUser|null $user
 * @property-read \Illuminate\Database\Eloquent\Collection|\Dabashan\DbsAdmin\Models\AdminAttachment[] $attachments
 *
 * @method static Builder byType(string $type) 按分组类型筛选
 * @method static Builder byUser(int $userId) 按所属用户筛选
 */
class AdminAttachmentGroup extends BaseAdminModel
{

    protected $table = 'admin_attachment_groups';

    /**
     * 获取分组下的所有附件
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(AdminAttachment::class, 'group_id');
    }

    /**
     * 按分组类型筛选
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * 获取所属的管理员用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'admin_user_id');
    }

    /**
     * 按管理员用户 ID 筛选
     */
    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('admin_user_id', $userId);
    }
}
