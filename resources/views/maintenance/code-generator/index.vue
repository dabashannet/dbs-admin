<template>
  <div class="code-generator-container">
    <!-- 主工作区：配置 + 数据表 + Tab 合并为一个白色卡片 -->
    <div class="main-card">
      <!-- 头部标题和操作按钮 -->
      <div class="card-header">
        <a-space>
          <icon-code />
          <span class="card-title">代码生成器</span>
        </a-space>
        <a-space>
          <a-button
            type="primary"
            :loading="previewLoading"
            @click="handlePreview"
          >
            <icon-eye /> 预览代码
          </a-button>
          <a-button
            type="outline"
            status="success"
            :loading="generateLoading"
            @click="handleGenerate"
          >
            <icon-download /> 生成代码
          </a-button>
        </a-space>
      </div>

      <!-- 基础配置表单 -->
      <a-form :model="form" layout="inline" class="config-form">
        <a-form-item label="资源名称">
          <a-input
            v-model="form.name"
            placeholder="如 User、Order"
            style="width: 180px"
            @change="onNameChange"
          />
        </a-form-item>
        <a-form-item label="生成类型">
          <a-select
            v-model="form.type"
            style="width: 140px"
            @change="onTypeChange"
          >
            <a-option value="core">核心模块</a-option>
            <a-option value="plugin">插件模块</a-option>
          </a-select>
        </a-form-item>
        <a-form-item v-if="form.type === 'plugin'" label="插件名称">
          <a-input
            v-model="form.plugin"
            placeholder="如 shop"
            style="width: 140px"
            @change="onNameChange"
          />
        </a-form-item>
        <a-form-item label="父级目录">
          <a-select
            v-model="form.parent"
            allow-create
            allow-search
            style="width: 160px"
            placeholder="选择或输入"
          >
            <a-option
              v-for="(label, key) in config.parents"
              :key="key"
              :value="key"
              >{{ label }}</a-option
            >
          </a-select>
        </a-form-item>
        <a-form-item label="菜单图标">
          <a-select v-model="form.icon" allow-search style="width: 150px">
            <a-option v-for="icon in config.icons" :key="icon" :value="icon">{{
              icon
            }}</a-option>
          </a-select>
        </a-form-item>
        <a-form-item label="排序">
          <a-input-number
            v-model="form.order"
            :min="0"
            :max="999"
            style="width: 100px"
          />
        </a-form-item>
      </a-form>

      <!-- 分隔线 -->
      <a-divider style="margin: 16px 0" />

      <!-- 数据表管理 -->
      <div class="table-section">
        <div class="table-header">
          <span class="table-title">数据表管理</span>
          <a-space>
            <a-button type="primary" size="small" @click="addTable">
              <icon-plus /> 添加数据表
            </a-button>
            <a-select
              v-if="form.tables.length > 0"
              v-model="activeTableIndex"
              size="small"
              style="width: 200px"
              placeholder="选择当前编辑的表"
            >
              <a-option v-for="(t, i) in form.tables" :key="i" :value="i">
                {{ t.table }} ({{ t.name }})
              </a-option>
            </a-select>
          </a-space>
        </div>
      </div>

      <!-- Tab 区域 -->
      <a-tabs v-model:active-key="activeTab" class="generator-tabs">
        <a-tab-pane key="fields" title="字段定义">
          <a-empty
            v-if="form.tables.length === 0"
            description="请先点击上方「添加数据表」按钮添加数据表"
            style="padding: 60px 0"
          >
            <template #icon><icon-database /></template>
            <template #extra>
              <a-button type="primary" @click="addTable">
                <icon-plus /> 添加数据表
              </a-button>
            </template>
          </a-empty>
          <FieldBuilder
            v-else
            v-model="currentTable.fields"
            :field-types="config.field_types"
            :db-types="config.db_types"
          />
        </a-tab-pane>

        <a-tab-pane key="grid" title="表格列">
          <a-empty
            v-if="form.tables.length === 0"
            description="请先添加数据表并定义字段"
            style="padding: 60px 0"
          />
          <GridBuilder
            v-else
            v-model="currentTable.grid_columns"
            :display-types="config.column_display_types"
            :fields="currentTable.fields"
          />
        </a-tab-pane>

        <a-tab-pane key="filters" title="筛选器">
          <a-empty
            v-if="form.tables.length === 0"
            description="请先添加数据表并定义字段"
            style="padding: 60px 0"
          />
          <FilterBuilder
            v-else
            v-model="currentTable.filters"
            :filter-types="config.filter_types"
            :fields="currentTable.fields"
          />
        </a-tab-pane>

        <a-tab-pane key="preview" title="代码预览">
          <template
            v-if="
              previewData &&
              previewData.preview &&
              Object.keys(previewData.preview).length > 0
            "
          >
            <a-radio-group
              v-model="previewFileKey"
              type="button"
              style="margin-bottom: 12px"
            >
              <a-radio
                v-for="(_, key) in previewData.preview"
                :key="key"
                :value="key"
                >{{ getFileName(String(key)) }}</a-radio
              >
            </a-radio-group>
            <CodeBlock
              v-if="previewData.preview[previewFileKey]"
              :code="previewData.preview[previewFileKey].content"
              :language="getLanguage(previewFileKey)"
            />
          </template>
          <a-empty v-else description="请先点击「预览代码」生成预览" />
        </a-tab-pane>

        <a-tab-pane key="files" title="生成文件">
          <div
            v-if="
              previewData && previewData.files && previewData.files.length > 0
            "
          >
            <a-list :data="previewData.files" :bordered="false">
              <template #item="{ item }">
                <a-list-item>
                  <a-space>
                    <icon-file />
                    <span class="file-path">{{ item }}</span>
                  </a-space>
                </a-list-item>
              </template>
            </a-list>
          </div>
          <a-empty v-else description="请先点击「预览代码」生成预览" />
        </a-tab-pane>
      </a-tabs>
    </div>

    <!-- 已生成历史列表：单独白色卡片 -->
    <div v-if="generatedHistory.length > 0" class="history-card">
      <div class="history-header">
        <a-space>
          <icon-history />
          <span class="history-title">已生成记录</span>
        </a-space>
      </div>
      <a-table
        :columns="historyColumns"
        :data="generatedHistory"
        :pagination="{ pageSize: 10 }"
        :bordered="false"
        row-key="id"
      >
        <template #type="{ record }">
          <a-tag :color="record.type === 'plugin' ? 'orangered' : 'blue'">
            {{ record.type === 'plugin' ? '插件' : '核心' }}
          </a-tag>
        </template>
        <template #tables="{ record }">
          <a-space v-if="record.tables && record.tables.length > 0" wrap>
            <a-tag v-for="(t, i) in record.tables" :key="i" size="small">{{
              t
            }}</a-tag>
          </a-space>
          <span v-else>-</span>
        </template>
        <template #operations="{ record }">
          <a-space>
            <a-button type="text" size="small" @click="loadHistoryItem(record)">
              <icon-eye /> 查看
            </a-button>
            <a-popconfirm
              content="确定删除此记录？"
              @ok="removeHistory(record.id)"
            >
              <a-button type="text" size="small" status="danger">
                <icon-delete /> 删除
              </a-button>
            </a-popconfirm>
          </a-space>
        </template>
      </a-table>
    </div>

    <!-- 添加数据表弹窗 -->
    <a-modal
      v-model:visible="tableModalVisible"
      title="添加数据表"
      @ok="handleTableSubmit"
    >
      <a-form :model="tableForm" layout="vertical">
        <a-form-item label="资源名称" required>
          <a-input v-model="tableForm.name" placeholder="如 User、Order" />
        </a-form-item>
        <a-form-item label="数据表名">
          <a-input
            v-model="tableForm.table"
            :placeholder="tableFormAutoTable"
          />
        </a-form-item>
      </a-form>
    </a-modal>
  </div>
</template>

<script lang="ts" setup>
  import { ref, computed, onMounted, watch } from 'vue';
  import { Message } from '@arco-design/web-vue';
  import {
    getCodeGeneratorConfig,
    previewCode,
    generateCode,
  } from '@/api/code-generator';
  import { syncRoutes } from '@/api/maintenance';
  import FieldBuilder from './components/FieldBuilder.vue';
  import GridBuilder from './components/GridBuilder.vue';
  import FilterBuilder from './components/FilterBuilder.vue';
  import CodeBlock from './components/CodeBlock.vue';

  interface TableConfig {
    name: string;
    table: string;
    fields: any[];
    grid_columns: any[];
    filters: any[];
    fillable: string[];
  }

  const form = ref({
    name: '',
    type: 'core',
    plugin: '',
    parent: 'system',
    icon: 'icon-file',
    order: 90,
    tables: [] as TableConfig[],
  });

  const activeTableIndex = ref(0);
  const activeTab = ref('fields');

  const config = ref<{
    field_types: Record<string, any>;
    column_display_types: Record<string, any>;
    filter_types: Record<string, any>;
    parents: Record<string, any>;
    icons: string[];
    db_types: Record<string, any>;
  }>({
    field_types: {},
    column_display_types: {},
    filter_types: {},
    parents: {},
    icons: [],
    db_types: {},
  });

  const previewData = ref<any>(null);
  const previewFileKey = ref('controller');
  const previewLoading = ref(false);
  const generateLoading = ref(false);

  const currentTable = computed(() => {
    if (form.value.tables.length === 0) {
      return {
        name: '',
        table: '',
        fields: [],
        grid_columns: [],
        filters: [],
        fillable: [],
      };
    }
    const idx = Math.min(activeTableIndex.value, form.value.tables.length - 1);
    return form.value.tables[idx];
  });

  const tableModalVisible = ref(false);
  const tableForm = ref({ name: '', table: '' });

  const tableFormAutoTable = computed(() => {
    const { name } = tableForm.value;
    if (!name) return '';
    const plural = name.endsWith('s') ? name : `${name}s`;
    const snake = plural
      .replace(/([A-Z])/g, '_$1')
      .toLowerCase()
      .replace(/^_/, '');
    const prefix =
      form.value.type === 'plugin' && form.value.plugin
        ? `plugin_${form.value.plugin}_`
        : 'admin_';
    return `${prefix}${snake}`;
  });

  function getDefaultConfig() {
    return {
      field_types: {
        text: { label: '文本输入' },
        password: { label: '密码输入' },
        textarea: { label: '多行文本' },
        number: { label: '数字输入' },
        email: { label: '邮箱输入' },
        select: { label: '下拉选择' },
        switch: { label: '开关' },
        date: { label: '日期选择' },
        dateTime: { label: '日期时间' },
        image: { label: '单图上传' },
        images: { label: '多图上传' },
        file: { label: '文件上传' },
        editor: { label: '富文本编辑器' },
        tags: { label: '标签输入' },
        color: { label: '颜色选择' },
        rate: { label: '评分' },
        slider: { label: '滑块' },
        code: { label: '代码编辑器' },
        keyValue: { label: '键值对输入' },
        repeater: { label: '可重复项' },
        markdown: { label: 'Markdown 编辑器' },
        toggleButtons: { label: '切换按钮组' },
      },
      column_display_types: {
        badge: '徽章',
        dot: '圆点',
        image: '图片',
        money: '金额',
        date: '日期',
        datetime: '日期时间',
        toggle: '开关',
        copyable: '可复制',
      },
      filter_types: {
        like: '模糊搜索',
        select: '下拉选择',
        equal: '精确匹配',
        between_date: '日期区间',
        between: '区间',
      },
      parents: {
        system: '系统管理',
        user: '用户管理',
        order: '订单管理',
        setting: '系统设置',
      },
      icons: [
        'icon-user',
        'icon-settings',
        'icon-shopping',
        'icon-file',
        'icon-image',
      ],
      db_types: {
        string: { label: '字符串' },
        text: { label: '长文本' },
        integer: { label: '整数' },
        decimal: { label: '小数' },
        boolean: { label: '布尔值' },
        dateTime: { label: '日期时间' },
      },
    };
  }

  function autoTableName(tableName: string, name: string) {
    if (tableName) return tableName;
    if (!name) return '';
    const plural = name.endsWith('s') ? name : `${name}s`;
    const snake = plural
      .replace(/([A-Z])/g, '_$1')
      .toLowerCase()
      .replace(/^_/, '');
    const prefix =
      form.value.type === 'plugin' && form.value.plugin
        ? `plugin_${form.value.plugin}_`
        : 'admin_';
    return `${prefix}${snake}`;
  }

  function createTableConfig(name: string, tableName: string): TableConfig {
    return {
      name,
      table: tableName,
      fields: [],
      grid_columns: [],
      filters: [],
      fillable: [],
    };
  }

  function onNameChange() {
    if (form.value.name && form.value.tables.length === 0) {
      const tableName = autoTableName('', form.value.name);
      form.value.tables.push(createTableConfig(form.value.name, tableName));
      activeTableIndex.value = 0;
    }
  }

  function onTypeChange() {
    form.value.plugin = '';
    if (form.value.type === 'plugin') {
      form.value.icon = 'p_file';
    } else {
      form.value.icon = 'icon-file';
    }
    form.value.tables.forEach((t) => {
      if (!t.table) {
        t.table = autoTableName('', t.name);
      }
    });
  }

  function addTable() {
    tableForm.value = { name: '', table: '' };
    tableModalVisible.value = true;
  }

  function handleTableSubmit() {
    if (!tableForm.value.name) {
      Message.warning('请输入资源名称');
      return;
    }
    const tableName = tableForm.value.table || tableFormAutoTable.value;
    form.value.tables.push(createTableConfig(tableForm.value.name, tableName));
    activeTableIndex.value = form.value.tables.length - 1;
    tableModalVisible.value = false;
    Message.success('数据表已添加');
  }

  function onTableIndexChange() {
    previewData.value = null;
    previewFileKey.value = 'controller';
  }

  watch(activeTableIndex, onTableIndexChange);

  // ==================== 历史记录 ====================
  const generatedHistory = ref<any[]>([]);

  const historyColumns = [
    { title: '资源名称', dataIndex: 'name', width: 120 },
    { title: '类型', slotName: 'type', width: 80 },
    { title: '数据表', slotName: 'tables', ellipsis: true },
    { title: '生成时间', dataIndex: 'created_at', width: 180 },
    { title: '操作', slotName: 'operations', width: 120 },
  ];

  function resetForm() {
    form.value = {
      name: '',
      type: 'core',
      plugin: '',
      parent: 'system',
      icon: 'icon-file',
      order: 90,
      tables: [],
    };
    activeTableIndex.value = 0;
    previewData.value = null;
    previewFileKey.value = 'controller';
  }

  function saveToHistory(item: any) {
    generatedHistory.value.unshift(item);
    if (generatedHistory.value.length > 20) {
      generatedHistory.value = generatedHistory.value.slice(0, 20);
    }
    try {
      localStorage.setItem(
        'code_generator_history',
        JSON.stringify(generatedHistory.value)
      );
    } catch (e) {
      // eslint-disable-next-line no-console
      console.warn('保存生成历史失败:', e);
    }
  }

  async function handlePreview() {
    if (form.value.tables.length === 0) {
      Message.warning('请先添加至少一个数据表');
      return;
    }

    previewLoading.value = true;
    try {
      const current = currentTable.value;
      const res = await previewCode({
        name: current.name,
        type: form.value.type,
        plugin: form.value.plugin,
        parent: form.value.parent,
        table: current.table || autoTableName('', current.name),
        icon: form.value.icon,
        order: form.value.order,
        fields: current.fields,
        grid_columns: current.grid_columns,
        fillable: current.fields.map((f: any) => f.key),
      });
      previewData.value = res.data;
      activeTab.value = 'preview';
      Message.success('代码预览已生成');
    } catch (e: any) {
      Message.error(e.response?.data?.msg || '预览失败');
    } finally {
      previewLoading.value = false;
    }
  }

  async function handleGenerate() {
    if (form.value.tables.length === 0) {
      Message.warning('请先添加至少一个数据表');
      return;
    }

    generateLoading.value = true;
    const generatedFiles: string[] = [];
    const tableNames: string[] = [];

    try {
      const promises = form.value.tables
        .filter((t) => {
          if (t.fields.length === 0) {
            Message.warning(`表 "${t.name}" 至少需要一个字段，跳过`);
            return false;
          }
          return true;
        })
        .map((t, idx) =>
          generateCode({
            name: t.name,
            type: form.value.type,
            plugin: form.value.plugin,
            parent: form.value.parent,
            table: t.table || autoTableName('', t.name),
            icon: form.value.icon,
            order: form.value.order + idx,
            fields: t.fields,
            grid_columns: t.grid_columns,
            fillable: t.fields.map((f: any) => f.key),
            force: true,
          }).then((res) => ({
            files: res.data?.files || [],
            tableName: t.table || t.name,
          }))
        );

      const results = await Promise.all(promises);
      results.forEach((result) => {
        generatedFiles.push(...result.files);
        tableNames.push(result.tableName);
      });

      if (generatedFiles.length === 0) {
        Message.warning('没有生成任何文件，请检查表定义');
        return;
      }

      Message.success(`代码生成成功，共生成 ${generatedFiles.length} 个文件`);

      if (form.value.type === 'plugin') {
        try {
          await syncRoutes();
          Message.success('API 接口已自动注册到 API Manager');
        } catch (e) {
          // eslint-disable-next-line no-console
          console.warn('API Manager 同步失败，请手动同步:', e);
        }
      }

      saveToHistory({
        id: Date.now().toString(),
        name: form.value.name || form.value.tables[0]?.name,
        type: form.value.type,
        plugin: form.value.plugin,
        tables: tableNames,
        parent: form.value.parent,
        files: generatedFiles.length,
        created_at: new Date().toLocaleString('zh-CN'),
      });

      resetForm();
    } catch (e: any) {
      Message.error(e.response?.data?.msg || '生成失败');
    } finally {
      generateLoading.value = false;
    }
  }

  function getFileName(key: string) {
    const map: Record<string, string> = {
      controller: 'Controller.php',
      model: 'Model.php',
      migration: 'Migration.php',
      vue: 'index.vue',
      router: 'router.ts',
      plugin_json: 'plugin.json',
      service_provider: 'ServiceProvider.php',
      business_vue: 'BusinessPage.vue',
      business_router: 'business-router.ts',
    };
    return map[key] || key;
  }

  function getLanguage(key: string) {
    const map: Record<string, string> = {
      controller: 'php',
      model: 'php',
      migration: 'php',
      vue: 'vue',
      router: 'typescript',
      plugin_json: 'json',
      service_provider: 'php',
      business_vue: 'vue',
      business_router: 'typescript',
    };
    return map[key] || 'text';
  }

  function loadHistory() {
    try {
      const stored = localStorage.getItem('code_generator_history');
      if (stored) {
        generatedHistory.value = JSON.parse(stored);
      }
    } catch (e) {
      // eslint-disable-next-line no-console
      console.warn('加载生成历史失败:', e);
    }
  }

  function removeHistory(id: string) {
    generatedHistory.value = generatedHistory.value.filter(
      (item) => item.id !== id
    );
    try {
      localStorage.setItem(
        'code_generator_history',
        JSON.stringify(generatedHistory.value)
      );
    } catch (e) {
      // eslint-disable-next-line no-console
      console.warn('删除生成历史失败:', e);
    }
  }

  function loadHistoryItem(item: any) {
    form.value.name = item.name;
    form.value.type = item.type;
    form.value.plugin = item.plugin || '';
    form.value.parent = item.parent;
    form.value.icon = 'icon-file';
    Message.info('已加载历史记录，请添加数据表后补充字段定义');
  }

  onMounted(async () => {
    try {
      const res = await getCodeGeneratorConfig();
      config.value = res.data;
    } catch (e) {
      config.value = getDefaultConfig();
    }
    loadHistory();
  });
</script>

<style scoped lang="less">
  .code-generator-container {
    padding: 20px;
    min-height: calc(100vh - 100px);
  }

  // 主工作区卡片 - 白色背景
  .main-card {
    background: white;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 16px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
  }

  // 卡片头部
  .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;

    .card-title {
      font-size: 16px;
      font-weight: 500;
      color: var(--color-text-1);
    }
  }

  // 配置表单
  .config-form {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;

    :deep(.arco-form-item) {
      margin-bottom: 0;
    }
  }

  // 数据表管理区域
  .table-section {
    margin-bottom: 8px;
  }

  .table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;

    .table-title {
      font-size: 14px;
      font-weight: 500;
      color: var(--color-text-1);
    }
  }

  // Tab 区域
  .generator-tabs {
    min-height: 400px;
  }

  // 历史记录卡片 - 独立白色背景
  .history-card {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
  }

  .history-header {
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--color-border-2);

    .history-title {
      font-size: 16px;
      font-weight: 500;
      color: var(--color-text-1);
    }
  }

  // 文件路径样式
  .file-path {
    font-family: 'SF Mono', 'Fira Code', monospace;
    font-size: 13px;
    color: var(--color-text-2);
  }

  // 表格样式优化
  :deep(.arco-table-th) {
    background-color: var(--color-fill-2);
    font-weight: 500;
  }

  :deep(.arco-table-td) {
    vertical-align: middle;
  }

  // 响应式优化
  @media (max-width: 768px) {
    .code-generator-container {
      padding: 12px;
    }

    .main-card,
    .history-card {
      padding: 16px;
    }

    .card-header {
      flex-direction: column;
      align-items: flex-start;
      gap: 12px;
    }

    .config-form {
      flex-direction: column;

      :deep(.arco-form-item) {
        width: 100%;
      }
    }

    .table-header {
      flex-direction: column;
      align-items: flex-start;
      gap: 12px;
    }
  }
</style>
