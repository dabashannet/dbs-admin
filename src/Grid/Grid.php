<?php

namespace Dabashan\DbsAdmin\Grid;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Grid 列表基类
 *
 * 后端定义元数据 + 数据查询，前端根据元数据动态渲染
 * 支持列定义、筛选器、排序、搜索、分页
 */
class Grid
{
    protected Builder $query;
    protected array $columns = [];
    protected array $filters = [];
    protected array $with = []; // eager loading
    protected int $perPage = 20;
    protected ?Column $lastColumn = null;
    protected ?Filter $lastFilter = null;

    /**
     * 创建 Grid 实例
     *
     * @param Builder $query Eloquent 查询构建器
     */
    public function __construct(Builder $query)
    {
        $this->query = $query;
    }

    /**
     * 静态工厂方法
     *
     * @param Builder|Model|string $query 查询构建器、模型实例或模型类名
     */
    public static function make($query): self
    {
        if (is_string($query)) {
            $query = (new $query)->newQuery();
        } elseif ($query instanceof Model) {
            $query = $query->newQuery();
        }
        return new self($query);
    }

    /**
     * 添加列
     *
     * @param string $key   字段名
     * @param string $title 显示标题
     */
    public function column(string $key, string $title): self
    {
        $column = new Column($key, $title);
        $this->columns[] = $column;
        $this->lastColumn = $column;
        return $this;
    }

    /**
     * 设置最后添加的列为可排序
     */
    public function sortable(bool $value = true): self
    {
        $this->lastColumn?->sortable($value);
        return $this;
    }

    /**
     * 设置最后添加的列为可搜索
     */
    public function searchable(bool $value = true): self
    {
        $this->lastColumn?->searchable($value);
        return $this;
    }

    /**
     * 设置最后添加的列为隐藏
     */
    public function hidden(bool $value = true): self
    {
        $this->lastColumn?->hidden($value);
        return $this;
    }

    /**
     * 设置最后添加的列宽度
     */
    public function width(string $width): self
    {
        $this->lastColumn?->width($width);
        return $this;
    }

    /**
     * 设置最后添加的列对齐方式
     */
    public function align(string $align): self
    {
        $this->lastColumn?->align($align);
        return $this;
    }

    /**
     * 添加筛选器
     *
     * @param string $key   字段名
     * @param string $title 显示标题
     * @param string $type  筛选类型: like, exact, between_date, select, in, gt, lt
     */
    public function filter(string $key, string $title, string $type = 'like'): self
    {
        $filter = new Filter($key, $title, $type);
        $this->filters[] = $filter;
        $this->lastFilter = $filter;
        return $this;
    }

    /**
     * 设置最后添加的筛选器选项（用于 select 类型）
     *
     * @param array $options 选项数组 [value => label]
     */
    public function options(array $options): self
    {
        $this->lastFilter?->options($options);
        return $this;
    }

    /**
     * 设置预加载关联
     *
     * @param array|string $relations 关联名称
     */
    public function with(array|string $relations): self
    {
        $this->with = array_merge($this->with, (array) $relations);
        return $this;
    }

    /**
     * 设置每页显示数量
     */
    public function perPage(int $size): self
    {
        $this->perPage = $size;
        return $this;
    }

    /**
     * 核心解析方法 - 处理请求并返回数据
     *
     * @param Request $request HTTP 请求
     * @return array 包含 columns, filters, data 的数组
     */
    public function resolve(Request $request): array
    {
        $query = $this->query;

        // 预加载关联
        if (!empty($this->with)) {
            $query->with($this->with);
        }

        // 应用筛选器
        foreach ($this->filters as $filter) {
            $value = $request->input($filter->getKey());
            $filter->apply($query, $value);
        }

        // 应用搜索（searchable 列的关键字搜索）
        $keyword = $request->input('keyword') ?? $request->input('search');
        if ($keyword) {
            $searchableColumns = array_filter($this->columns, fn(Column $c) => $c->isSearchable());
            if (!empty($searchableColumns)) {
                $query->where(function (Builder $q) use ($searchableColumns, $keyword) {
                    foreach ($searchableColumns as $col) {
                        $q->orWhere($col->getKey(), 'like', "%{$keyword}%");
                    }
                });
            }
        }

        // 应用排序
        $sortField = $request->input('sortField');
        $sortOrder = $request->input('sortOrder', 'desc');
        if ($sortField) {
            $sortableKeys = array_map(
                fn(Column $c) => $c->getKey(),
                array_filter($this->columns, fn(Column $c) => $c->isSortable())
            );
            if (in_array($sortField, $sortableKeys)) {
                $order = $sortOrder === 'ascend' ? 'asc' : 'desc';
                $query->orderBy($sortField, $order);
            }
        } else {
            // 默认按创建时间倒序
            $query->latest();
        }

        // 分页
        $pageSize = $request->input('pageSize', $this->perPage);
        $data = $query->paginate($pageSize);

        return [
            'columns' => array_map(fn(Column $c) => $c->toArray(), $this->columns),
            'filters' => array_map(fn(Filter $f) => $f->toArray(), $this->filters),
            'data' => $data,
        ];
    }

    /**
     * 仅获取列元数据（不执行查询）
     */
    public function getColumns(): array
    {
        return array_map(fn(Column $c) => $c->toArray(), $this->columns);
    }

    /**
     * 仅获取筛选器元数据（不执行查询）
     */
    public function getFilters(): array
    {
        return array_map(fn(Filter $f) => $f->toArray(), $this->filters);
    }
}
