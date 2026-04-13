<?php

namespace Dabashan\DbsAdmin\Grid;

/**
 * 导入导出操作 Trait
 *
 * 提供类似 Filament 的导入导出功能
 */
trait HasImportExport
{
    /**
     * 快捷方法：头部导入操作
     */
    public function importAction(string $label = '导入', string $route = ''): self
    {
        $action = Action::make('import', $label)
            ->header()
            ->type('primary')
            ->icon('icon-upload')
            ->modal(['width' => 600, 'title' => $label]);

        if ($route) {
            $action->apiRoute($route);
        }

        $this->actions[] = $action;
        return $this;
    }

    /**
     * 快捷方法：头部导出操作
     */
    public function exportAction(string $label = '导出'): self
    {
        $this->actions[] = Action::make('export', $label)
            ->header()
            ->type('success')
            ->icon('icon-download');
        return $this;
    }

    /**
     * 快捷方法：行复制操作
     */
    public function replicateAction(string $label = '复制'): self
    {
        $this->actions[] = Action::make('replicate', $label)
            ->row()
            ->type('warning')
            ->icon('icon-copy')
            ->confirm(true, '确定要复制并创建新记录吗？');
        return $this;
    }

    /**
     * 快捷方法：行强制删除操作（软删除模式下永久删除）
     */
    public function forceDeleteAction(string $label = '永久删除'): self
    {
        $this->actions[] = Action::make('force-delete', $label)
            ->row()
            ->type('danger')
            ->icon('icon-delete')
            ->confirm(true, '确定要永久删除吗？此操作不可恢复。');
        return $this;
    }

    /**
     * 快捷方法：行恢复操作（软删除模式下恢复）
     */
    public function restoreAction(string $label = '恢复'): self
    {
        $this->actions[] = Action::make('restore', $label)
            ->row()
            ->type('success')
            ->icon('icon-undo')
            ->confirm(true, '确定要恢复此记录吗？');
        return $this;
    }

    /**
     * 快捷方法：批量恢复操作
     */
    public function batchRestoreAction(string $label = '批量恢复'): self
    {
        $this->actions[] = Action::make('batch-restore', $label)
            ->bulk()
            ->type('success')
            ->icon('icon-undo')
            ->confirm(true, '确定要批量恢复选中的记录吗？');
        return $this;
    }

    /**
     * 快捷方法：批量强制删除操作
     */
    public function batchForceDeleteAction(string $label = '批量永久删除'): self
    {
        $this->actions[] = Action::make('batch-force-delete', $label)
            ->bulk()
            ->type('danger')
            ->icon('icon-delete')
            ->confirm(true, '确定要永久批量删除选中的记录吗？此操作不可恢复。');
        return $this;
    }
}
