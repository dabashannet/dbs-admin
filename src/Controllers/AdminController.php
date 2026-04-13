<?php

namespace Dabashan\DbsAdmin\Controllers;

use Dabashan\DbsAdmin\Grid\Grid;
use Dabashan\DbsAdmin\Grid\Action;
use Dabashan\DbsAdmin\Form\Form;
use Dabashan\DbsAdmin\Show\Show;
use Dabashan\DbsAdmin\Traits\HasApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * 后台管理 CRUD 控制器基类
 *
 * 提供通用的 CRUD 方法，子类只需覆写 grid() 和 form() 方法
 *  Resource 的完整能力
 */
abstract class AdminController extends Controller
{
    use HasApiResponse;

    protected string $model;

    protected function grid(): Grid
    {
        $grid = Grid::make($this->model)
            ->createAction()
            ->editAction()
            ->deleteAction()
            ->batchDeleteAction();
        $this->configureActions($grid);
        return $grid;
    }

    /**
     * 配置操作（子类可覆写自定义操作）
     *
     * 示例:
     * protected function configureActions(Grid $grid): void
     * {
     *     $grid->action(Action::make('export', '导出')->header()->type('success'));
     *     $grid->action(Action::make('view', '查看')->row()->modal(['width' => 700]));
     * }
     */
    protected function configureActions(Grid $grid): void {}

    abstract protected function form(): Form;

    protected function detail($id): Show
    {
        return Show::make($this->model::findOrFail($id));
    }

    // ==================== CRUD 接口 ====================

    public function index(Request $request)
    {
        return $this->success($this->grid()->resolve($request));
    }

    public function store(Request $request)
    {
        $form = $this->form();
        $data = $form->validate($request, 'create');
        try {
            $model = $this->model::create($data);
            $this->afterSave($request, $model);
            return $this->success($model);
        } catch (\Illuminate\Database\QueryException $e) {
            return $this->fail('数据保存失败: ' . $this->formatDbError($e), 422);
        }
    }

    public function show($id)
    {
        return $this->success([
            'data' => $this->detail($id)->toArray(),
            'schema' => $this->detail($id)->schema(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $instance = $this->model::findOrFail($id);
        $form = $this->form();
        $data = $form->validate($request, 'update');
        try {
            $instance->update($data);
            $this->afterSave($request, $instance);
            return $this->success($instance);
        } catch (\Illuminate\Database\QueryException $e) {
            return $this->fail('数据更新失败: ' . $this->formatDbError($e), 422);
        }
    }

    public function destroy($id)
    {
        $ids = is_array($id) ? $id : explode(',', (string) $id);
        $ids = array_filter($ids, fn($v) => is_numeric($v) && $v > 0);
        if (empty($ids)) {
            return $this->fail('无效的 ID 参数', 422);
        }
        $count = count($ids);
        $this->model::destroy($ids);
        return $this->success([], "已删除 {$count} 条记录");
    }

    // ==================== Schema 接口 ====================

    /**
     * 获取表单 Schema（用于动态渲染表单）
     */
    public function formSchema(?string $context = null)
    {
        return $this->success($this->form()->schema($context));
    }

    /**
     * 获取详情 Schema（用于动态渲染详情页）
     */
    public function showSchema($id)
    {
        return $this->success($this->detail($id)->schema());
    }

    /**
     * 获取 Grid 元数据（不含数据，用于动态渲染表格结构）
     */
    public function gridMeta()
    {
        return $this->success([
            'columns' => $this->grid()->getColumns(),
            'filters' => $this->grid()->getFilters(),
            ...$this->grid()->resolveActionsByPosition(),
        ]);
    }

    // ==================== 批量操作 ====================

    /**
     * 批量更新
     */
    public function batchUpdate(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|integer',
            'field' => 'required|string',
            'value' => 'required',
        ]);

        $ids = $request->input('ids');
        $field = $request->input('field');
        $value = $request->input('value');

        $count = $this->model::whereIn('id', $ids)->update([$field => $value]);

        return $this->success(['count' => $count], "已更新 {$count} 条记录");
    }

    /**
     * 批量删除
     */
    public function batchDestroy(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|integer',
        ]);

        $ids = $request->input('ids');
        $count = count($ids);
        $this->model::whereIn('id', $ids)->delete();

        return $this->success(['count' => $count], "已删除 {$count} 条记录");
    }

    // ==================== 状态切换 ====================

    /**
     * 切换字段状态（用于表格中 switch 组件的 AJAX 切换）
     */
    public function toggle(Request $request, $id)
    {
        $request->validate([
            'field' => 'required|string',
            'value' => 'required',
        ]);

        $instance = $this->model::findOrFail($id);
        $field = $request->input('field');
        $value = $request->input('value');

        $instance->{$field} = $value;
        $instance->save();

        return $this->success(['id' => $id, 'field' => $field, 'value' => $value], '状态已更新');
    }

    // ==================== 钩子 ====================

    protected function afterSave(Request $_request, mixed $_model): void {}

    protected function formatDbError(\Illuminate\Database\QueryException $e): string
    {
        if (app()->environment('production')) {
            if ($e->getCode() == 23000) {
                return '数据已存在（唯一约束冲突）';
            }
            return '数据库操作失败';
        }
        return $e->getMessage();
    }
}
