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
use Illuminate\Support\Facades\Storage;

/**
 * 后台附件模型
 *
 * @property int $id
 * @property int $admin_user_id 上传用户 ID
 * @property string $name 文件名
 * @property string $path 存储路径
 * @property string $url 访问地址
 * @property string|null $mime_type MIME 类型
 * @property string|null $extension 文件扩展名
 * @property int $size 文件大小（字节）
 * @property string $driver 存储驱动（local/oss/cos/qiniu 等）
 * @property string $type 文件类型（image/video/file）
 * @property string|null $group_id 分组 ID
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @property-read \Dabashan\DbsAdmin\Models\AdminUser|null $user
 * @property-read \Dabashan\DbsAdmin\Models\AdminAttachmentGroup|null $group
 *
 * @method static Builder byType(string $type) 按文件类型筛选
 * @method static Builder byDriver(string $driver) 按存储驱动筛选
 * @method static Builder byUser(int $userId) 按上传用户筛选
 */
class AdminAttachment extends BaseAdminModel
{

    protected $table = 'admin_attachments';

    /**
     * 获取所属分组
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(AdminAttachmentGroup::class, 'group_id');
    }

    /**
     * 获取文件的完整访问 URL
     *
     * 根据存储驱动动态生成完整的可访问 URL：
     * - 远程存储（OSS/COS/七牛等）：通过对应 filesystem 驱动生成
     * - 本地存储：映射到 public 磁盘并拼接应用域名
     * - 已是完整 URL 的直接返回
     *
     * @param string|null $value 数据库中的 url 字段值
     */
    public function getUrlAttribute($value): string
    {
        if ($value && filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        if ($this->path && filter_var($this->path, FILTER_VALIDATE_URL)) {
            return $this->path;
        }

        try {
            $disk = $this->driver === 'local' ? 'public' : $this->driver;
            /** @var \Illuminate\Filesystem\FilesystemAdapter $filesystem */
            $filesystem = Storage::disk($disk);
            $url = $filesystem->url($this->path);

            if (!preg_match('/^https?:\/\//', $url)) {
                $url = rtrim(config('app.url', ''), '/') . $url;
            }

            return $url;
        } catch (\Exception $e) {
            return $this->path ?? '';
        }
    }

    /**
     * 按文件类型筛选（image/video/file）
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * 按存储驱动筛选
     */
    public function scopeByDriver(Builder $query, string $driver): Builder
    {
        return $query->where('driver', $driver);
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
