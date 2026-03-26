<?php

namespace Dabashan\DbsAdmin\Show;

/**
 * Show 详情展示组件
 *
 * 用于定义详情页的展示字段
 */
class Show
{
    protected $model;
    protected array $fields = [];
    protected array $with = [];

    /**
     * 静态工厂方法
     *
     * @param mixed $model 模型实例
     */
    public static function make($model): self
    {
        $instance = new self();
        $instance->model = $model;
        return $instance;
    }

    /**
     * 设置预加载关联
     *
     * @param array|string $relations 关联名称
     */
    public function with(array|string $relations): self
    {
        $this->with = array_merge($this->with, (array)$relations);
        if ($this->model && !empty($this->with)) {
            $this->model->load($this->with);
        }
        return $this;
    }

    /**
     * 添加展示字段
     *
     * @param string $key   字段名
     * @param string $label 显示标签
     */
    public function field(string $key, string $label): self
    {
        $this->fields[] = ['key' => $key, 'label' => $label];
        return $this;
    }

    /**
     * 转换为数组
     *
     * 如果未指定字段，返回模型的全部数据
     * 如果指定了字段，只返回指定字段的数据
     */
    public function toArray(): array
    {
        $data = $this->model->toArray();

        if (empty($this->fields)) {
            return $data;
        }

        // 只返回指定字段
        $result = [];
        foreach ($this->fields as $field) {
            $result[$field['key']] = data_get($data, $field['key']);
        }
        return $result;
    }

    /**
     * 获取字段定义（供前端渲染）
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * 获取模型实例
     */
    public function getModel()
    {
        return $this->model;
    }
}
