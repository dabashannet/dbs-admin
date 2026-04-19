<?php

namespace Dabashan\DbsAdmin\Grid;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Grid 列表基类
 *
 * 后端定义元数据 + 数据查询，前端根据元数据动态渲染
 *  Table 组件的完整能力
 * 支持列定义、筛选器、排序、搜索、分页、导出、行操作、批量操作
 */
class Grid
{
    use HasImportExport;

    protected Builder $query;
    protected array $columns = [];
    protected array $filters = [];
    protected array $with = [];
    protected array $select = [];            // 仅查询指定字段（性能优化）
    protected int $perPage = 20;
    protected array $perPageOptions = [10, 20, 50, 100];
    protected ?Column $lastColumn = null;
    protected ?Filter $lastFilter = null;
    protected ?string $defaultSortField = 'created_at';
    protected string $defaultSortOrder = 'desc';
    protected array $actions = [];           // 操作列表（行操作/头部操作/批量操作）
    protected bool $showSelectAll = true;    // 显示全选复选框
    protected bool $showPagination = true;   // 显示分页
    protected bool $showBorder = true;       // 显示边框
    protected ?string $emptyText = null;     // 空数据提示文本
    protected array $rowAttributes = [];     // 行属性
    protected ?\Closure $queryCallback = null; // 自定义查询回调
    protected array $whenConditions = [];     // 条件筛选
    protected bool $cacheEnabled = false;    // 是否启用查询缓存
    protected int $cacheTtl = 60;            // 缓存时间（秒）

    public function __construct(Builder $query)
    {
        $this->query = $query;
    }

    public static function make($query): self
    {
        if (is_string($query)) {
            $query = (new $query)->newQuery();
        } elseif ($query instanceof Model) {
            $query = $query->newQuery();
        }
        return new self($query);
    }

    // ==================== 列定义 ====================

    public function column(string $key, string $title): self
    {
        $column = new Column($key, $title);
        $this->columns[] = $column;
        $this->lastColumn = $column;
        return $this;
    }

    public function sortable(bool $value = true): self
    {
        $this->lastColumn?->sortable($value);
        return $this;
    }

    public function searchable(bool $value = true): self
    {
        $this->lastColumn?->searchable($value);
        return $this;
    }

    public function hidden(bool $value = true): self
    {
        $this->lastColumn?->hidden($value);
        return $this;
    }

    public function width(string $width): self
    {
        $this->lastColumn?->width($width);
        return $this;
    }

    public function align(string $align): self
    {
        $this->lastColumn?->align($align);
        return $this;
    }

    /**
     * 魔法方法：将未定义的方法转发到最后创建的 Column
     * 支持 limit()、display()、badge()、image()、progress() 等 Column 方法链式调用
     */
    public function __call(string $name, array $arguments): self
    {
        $this->lastColumn?->$name(...$arguments);
        return $this;
    }

    // ==================== 筛选器 ====================

    public function filter(string $key, string $title, string $type = 'like'): self
    {
        $filter = new Filter($key, $title, $type);
        $this->filters[] = $filter;
        $this->lastFilter = $filter;
        return $this;
    }

    public function options(array $options): self
    {
        $this->lastFilter?->options($options);
        return $this;
    }

    /**
     * 复杂筛选器（多字段组合， Filter 的 query 回调）
     */
    public function filterQuery(string $key, string $title, \Closure $callback, string $type = 'custom'): self
    {
        $filter = new Filter($key, $title, $type);
        $filter->setQueryCallback($callback);
        $this->filters[] = $filter;
        $this->lastFilter = $filter;
        return $this;
    }

    // ==================== 性能优化 ====================

    /**
     * 仅查询指定字段（大幅减少内存和网络开销）
     *
     * 示例: ->select(['id', 'name', 'email'])
     */
    public function select(array $columns): self
    {
        $this->select = $columns;
        return $this;
    }

    /**
     * 预加载关联
     */
    public function with(array|string $relations): self
    {
        $this->with = array_merge($this->with, (array) $relations);
        return $this;
    }

    /**
     * 条件筛选（ 的 when）
     *
     * 示例: ->when($request->has('vip'), fn($q) => $q->where('is_vip', true))
     */
    public function when(mixed $condition, \Closure $callback): self
    {
        if ($condition) {
            $this->whenConditions[] = $callback;
        }
        return $this;
    }

    /**
     * 自定义查询回调（完全控制查询逻辑）
     */
    public function query(\Closure $callback): self
    {
        $this->queryCallback = $callback;
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
     * 设置每页选项
     */
    public function perPageOptions(array $options): self
    {
        $this->perPageOptions = $options;
        return $this;
    }

    // ==================== 行操作 ====================

    // ==================== 操作 ====================

    /**
     * 添加操作（行操作/头部操作/批量操作）
     *
     * 示例:
     * ->action(Action::make('edit', '编辑')->drawer())        // 行操作，抽屉编辑
     * ->action(Action::make('view', '查看')->modal())         // 行操作，弹窗查看
     * ->action(Action::make('delete', '删除')->confirm())     // 行操作，确认删除
     * ->action(Action::make('create', '新增')->header())      // 头部操作，新增按钮
     * ->action(Action::make('export', '导出')->header()->type('success'))  // 头部操作，导出
     * ->action(Action::make('batch-delete', '批量删除')->bulk()->confirm()) // 批量操作
     */
    public function action(Action $action): self
    {
        $this->actions[] = $action;
        return $this;
    }

    /**
     * 快捷方法：行编辑操作（弹窗模式）
     */
    public function editAction(string $label = '编辑', string $mode = Action::MODE_DRAWER): self
    {
        $action = Action::make('edit', $label)->row();
        if ($mode === Action::MODE_MODAL) {
            $action->modal();
        } elseif ($mode === Action::MODE_DRAWER) {
            $action->drawer();
        }
        $this->actions[] = $action;
        return $this;
    }

    /**
     * 快捷方法：行删除操作（带确认）
     */
    public function deleteAction(string $label = '删除'): self
    {
        $this->actions[] = Action::make('delete', $label)
            ->row()
            ->type('danger')
            ->confirm(true, '确定要删除吗？此操作不可撤销。');
        return $this;
    }

    /**
     * 快捷方法：行查看操作（弹窗模式）
     */
    public function viewAction(string $label = '查看'): self
    {
        $this->actions[] = Action::make('view', $label)
            ->row()
            ->modal(['width' => 700]);
        return $this;
    }

    /**
     * 快捷方法：头部新增操作
     */
    public function createAction(string $label = '新增'): self
    {
        $this->actions[] = Action::make('create', $label)
            ->header()
            ->type('primary');
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
     * 快捷方法：批量删除操作
     */
    public function batchDeleteAction(string $label = '批量删除'): self
    {
        $this->actions[] = Action::make('batch-delete', $label)
            ->bulk()
            ->type('danger')
            ->confirm(true, '确定要批量删除选中的 {count} 条记录吗？');
        return $this;
    }

    // ==================== 表格外观 ====================

    /**
     * 显示/隐藏全选复选框
     */
    public function showSelectAll(bool $value = true): self
    {
        $this->showSelectAll = $value;
        return $this;
    }

    /**
     * 显示/隐藏分页
     */
    public function showPagination(bool $value = true): self
    {
        $this->showPagination = $value;
        return $this;
    }

    /**
     * 显示/隐藏边框
     */
    public function showBorder(bool $value = true): self
    {
        $this->showBorder = $value;
        return $this;
    }

    /**
     * 空数据提示文本
     */
    public function emptyText(string $text): self
    {
        $this->emptyText = $text;
        return $this;
    }

    /**
     * 默认排序
     */
    public function defaultSort(string $field, string $order = 'desc'): self
    {
        $this->defaultSortField = $field;
        $this->defaultSortOrder = $order;
        return $this;
    }

    // ==================== 导出支持 ====================

    /**
     * 标记此 Grid 支持导出（前端根据此元数据渲染导出按钮）
     */
    public function exportable(array $columns = []): self
    {
        $this->actions[] = [
            'key' => 'export',
            'label' => '导出',
            'type' => 'primary',
            'columns' => $columns,
        ];
        return $this;
    }

    /**
     * 标记此 Grid 支持刷新按钮
     */
    public function refreshable(): self
    {
        $this->actions[] = ['key' => 'refresh', 'label' => '刷新'];
        return $this;
    }

    // ==================== 核心解析方法 ====================

    public function resolve(Request $request): array
    {
        $query = $this->query;

        // 自定义查询回调优先
        if ($this->queryCallback !== null) {
            $query = ($this->queryCallback)($query, $request);
        }

        // 预加载关联
        if (!empty($this->with)) {
            $query->with($this->with);
        }

        // 仅查询指定字段（性能优化）
        if (!empty($this->select)) {
            $query->select($this->select);
        }

        // 应用筛选器
        foreach ($this->filters as $filter) {
            $value = $request->input($filter->getKey());
            $filter->apply($query, $value);
        }

        // 应用条件筛选（when）
        foreach ($this->whenConditions as $condition) {
            $condition($query);
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
        $sortOrder = $request->input('sortOrder', $this->defaultSortOrder);
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
            $query->orderBy($this->defaultSortField, $this->defaultSortOrder === 'asc' ? 'asc' : 'desc');
        }

        // 分页
        if ($this->showPagination) {
            $pageSize = (int) $request->input('pageSize', $this->perPage);
            $paginator = $query->paginate($pageSize);

            $items = [];
            foreach ($paginator->items() as $item) {
                $items[] = $this->formatRow($item);
            }

            return [
                'columns' => array_map(fn(Column $c) => $c->toArray(), $this->columns),
                'filters' => array_map(fn(Filter $f) => $f->toArray(), $this->filters),
                'items' => $items,
                'current_page' => $paginator->currentPage(),
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
                'per_page_options' => $this->perPageOptions,
                'actions' => $this->resolveActions(),
                'headerActions' => $this->resolveActions(Action::POSITION_HEADER),
                'rowActions' => $this->resolveActions(Action::POSITION_ROW),
                'bulkActions' => $this->resolveActions(Action::POSITION_BULK),
                'showSelectAll' => $this->showSelectAll,
                'showPagination' => $this->showPagination,
                'showBorder' => $this->showBorder,
                'emptyText' => $this->emptyText ?? '暂无数据',
            ];
        }

        // 不分页模式
        $items = [];
        foreach ($query->get() as $item) {
            $items[] = $this->formatRow($item);
        }

        return [
            'columns' => array_map(fn(Column $c) => $c->toArray(), $this->columns),
            'filters' => array_map(fn(Filter $f) => $f->toArray(), $this->filters),
            'items' => $items,
            'actions' => $this->resolveActions(),
            'headerActions' => $this->resolveActions(Action::POSITION_HEADER),
            'rowActions' => $this->resolveActions(Action::POSITION_ROW),
            'bulkActions' => $this->resolveActions(Action::POSITION_BULK),
            'showSelectAll' => $this->showSelectAll,
            'showPagination' => false,
            'showBorder' => $this->showBorder,
            'emptyText' => $this->emptyText ?? '暂无数据',
        ];
    }

    /**
     * 格式化单行数据（后端预格式化 display 列，大幅减轻前端负担）
     */
    protected function formatRow(object $item): array
    {
        $data = $item instanceof \Illuminate\Database\Eloquent\Model
            ? $item->toArray()
            : (array) $item;

        foreach ($this->columns as $column) {
            $key = $column->getKey();
            if (array_key_exists($key, $data)) {
                $data[$key] = $column->formatValue($data[$key], $item);
            }
        }

        return $data;
    }

    // ==================== 元数据获取 ====================

    public function getColumns(): array
    {
        return array_map(fn(Column $c) => $c->toArray(), $this->columns);
    }

    public function getFilters(): array
    {
        return array_map(fn(Filter $f) => $f->toArray(), $this->filters);
    }

    public function getActions(): array
    {
        return $this->resolveActions();
    }

    public function getBatchActions(): array
    {
        return $this->resolveActions(Action::POSITION_BULK);
    }

    /**
     * 按位置解析操作
     */
    protected function resolveActions(?string $position = null): array
    {
        $actions = $position
            ? array_filter($this->actions, fn(Action $a) => $a->getPosition() === $position)
            : $this->actions;

        return array_map(fn(Action $a) => $a->toArray(), $actions);
    }

    /**
     * 按位置解析操作（供外部调用）
     */
    public function resolveActionsByPosition(): array
    {
        return [
            'headerActions' => $this->resolveActions(Action::POSITION_HEADER),
            'rowActions' => $this->resolveActions(Action::POSITION_ROW),
            'bulkActions' => $this->resolveActions(Action::POSITION_BULK),
        ];
    }
}
