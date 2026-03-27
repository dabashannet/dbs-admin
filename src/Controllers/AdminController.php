<?php

namespace Dabashan\DbsAdmin\Controllers;

use Dabashan\DbsAdmin\Grid\Grid;
use Dabashan\DbsAdmin\Form\Form;
use Dabashan\DbsAdmin\Show\Show;
use Dabashan\DbsAdmin\Traits\HasApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * 后台管理 CRUD 控制器基类
 *
 * 提供通用的 CRUD 方法，子类只需覆写 grid() 和 form() 方法
 */
abstract class AdminController extends Controller
{
    use HasApiResponse;

    /**
     * 模型类名
     *
     * @var string
     */
    protected string $model;

    /**
     * 定义列表页 Grid
     *
     * @return Grid
     */
    abstract protected function grid(): Grid;

    /**
     * 定义表单
     *
     * @return Form
     */
    abstract protected function form(): Form;

    /**
     * 定义详情展示
     *
     * @param mixed $id 记录 ID
     * @return Show
     */
    protected function detail($id): Show
    {
        return Show::make($this->model::findOrFail($id));
    }

    /**
     * 列表页
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        return $this->success($this->grid()->resolve($request));
    }

    /**
     * 新建
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
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

    /**
     * 详情
     *
     * @param mixed $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        return $this->success($this->detail($id)->toArray());
    }

    /**
     * 更新
     *
     * @param Request $request
     * @param mixed $id
     * @return \Illuminate\Http\JsonResponse
     */
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

    /**
     * 删除
     *
     * @param mixed $id 单个 ID 或逗号分隔的多个 ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $ids = is_array($id) ? $id : explode(',', (string) $id);
        // 过滤无效 ID
        $ids = array_filter($ids, fn($v) => is_numeric($v) && $v > 0);
        if (empty($ids)) {
            return $this->fail('无效的 ID 参数', 422);
        }
        $this->model::destroy($ids);
        return $this->success([], '删除成功');
    }

    /**
     * 保存后钩子（创建和更新共用）
     *
     * @param Request $request
     * @param mixed $model
     * @return void
     */
    protected function afterSave(Request $request, $model): void {}

    /**
     * 格式化数据库错误信息（生产环境不暴露敏感信息）
     *
     * @param \Illuminate\Database\QueryException $e
     * @return string
     */
    protected function formatDbError(\Illuminate\Database\QueryException $e): string
    {
        if (app()->environment('production')) {
            // Duplicate entry
            if ($e->getCode() == 23000) {
                return '数据已存在（唯一约束冲突）';
            }
            return '数据库操作失败';
        }
        return $e->getMessage();
    }

    /**
     * 获取表单 schema
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function formSchema()
    {
        return $this->success(['schema' => $this->form()->schema()]);
    }
}
