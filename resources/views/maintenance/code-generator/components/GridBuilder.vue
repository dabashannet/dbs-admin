<template>
  <div class="grid-builder">
    <a-space style="margin-bottom: 16px">
      <a-button type="primary" size="small" @click="addColumn">
        <icon-plus /> 添加列
      </a-button>
      <a-button size="small" @click="addDefaultColumns">
        <icon-list /> 默认列（ID + 创建时间）
      </a-button>
      <a-button size="small" @click="addAllFields">
        <icon-apps /> 从字段导入
      </a-button>
    </a-space>

    <a-table
      :columns="columns"
      :data="gridColumns"
      :pagination="false"
      :draggable="{ type: 'handle', width: 40 }"
      @change="onDragChange"
      row-key="id"
      size="small"
      :bordered="{ cell: true }"
    >
      <template #key="{ record }">
        <a-input v-model="record.key" placeholder="字段名" size="mini" />
      </template>
      <template #label="{ record }">
        <a-input v-model="record.label" placeholder="列标题" size="mini" />
      </template>
      <template #display_type="{ record }">
        <a-select v-model="record.display_type" size="mini" style="width: 120px" allow-clear>
          <a-option value="">文本</a-option>
          <a-option v-for="(label, key) in displayTypes" :key="key" :value="key">{{ label }}</a-option>
        </a-select>
      </template>
      <template #sortable="{ record }">
        <a-switch v-model="record.sortable" size="small" />
      </template>
      <template #searchable="{ record }">
        <a-switch v-model="record.searchable" size="small" />
      </template>
      <template #width="{ record }">
        <a-input v-model="record.width" placeholder="如 100px" size="mini" style="width: 90px" />
      </template>
      <template #operation="{ record, rowIndex }">
        <a-button type="text" status="danger" size="mini" @click="removeColumn(rowIndex)">
          <icon-delete />
        </a-button>
      </template>
    </a-table>
  </div>
</template>

<script lang="ts" setup>
import { computed } from 'vue';

const props = defineProps<{
  modelValue: any[];
  displayTypes: Record<string, string>;
  fields?: any[];
}>();

const emit = defineEmits(['update:modelValue']);

const gridColumns = computed({
  get: () => props.modelValue || [],
  set: (val) => emit('update:modelValue', val),
});

const columns = [
  { title: '字段名', dataIndex: 'key', slotName: 'key', width: 150 },
  { title: '列标题', dataIndex: 'label', slotName: 'label', width: 140 },
  { title: '显示类型', dataIndex: 'display_type', slotName: 'display_type', width: 140 },
  { title: '可排序', dataIndex: 'sortable', slotName: 'sortable', width: 80, align: 'center' },
  { title: '可搜索', dataIndex: 'searchable', slotName: 'searchable', width: 80, align: 'center' },
  { title: '宽度', dataIndex: 'width', slotName: 'width', width: 110 },
  { title: '操作', slotName: 'operation', width: 80, align: 'center', fixed: 'right' },
];

let idCounter = 100;

function createColumn(overrides = {}) {
  return {
    id: `col_${++idCounter}`,
    key: '',
    label: '',
    display_type: '',
    sortable: false,
    searchable: false,
    width: '',
    ...overrides,
  };
}

function addColumn() {
  gridColumns.value = [...gridColumns.value, createColumn()];
}

function addDefaultColumns() {
  gridColumns.value = [
    ...gridColumns.value,
    createColumn({ key: 'id', label: 'ID', sortable: true, width: '60px' }),
    createColumn({ key: 'created_at', label: '创建时间', sortable: true }),
  ];
}

function addAllFields() {
  if (!props.fields || props.fields.length === 0) return;
  const newCols = props.fields.map((f) =>
    createColumn({ key: f.key, label: f.label })
  );
  gridColumns.value = [...gridColumns.value, ...newCols];
}

function removeColumn(index: number) {
  gridColumns.value = gridColumns.value.filter((_, i) => i !== index);
}

function onDragChange({ newData }: any) {
  gridColumns.value = newData;
}
</script>

<style scoped>
.grid-builder {
  padding: 8px 0;
}
</style>
