<template>
  <div class="filter-builder">
    <a-space style="margin-bottom: 16px">
      <a-button type="primary" size="small" @click="addFilter">
        <icon-plus /> 添加筛选器
      </a-button>
      <a-button size="small" @click="addAllFields">
        <icon-apps /> 从字段导入
      </a-button>
    </a-space>

    <a-table
      :columns="columns"
      :data="filters"
      :pagination="false"
      row-key="id"
      size="small"
      :bordered="{ cell: true }"
    >
      <template #key="{ record }">
        <a-input v-model="record.key" placeholder="字段名" size="mini" />
      </template>
      <template #label="{ record }">
        <a-input v-model="record.label" placeholder="显示名称" size="mini" />
      </template>
      <template #type="{ record }">
        <a-select v-model="record.type" size="mini" style="width: 130px">
          <a-option v-for="(label, key) in filterTypes" :key="key" :value="key">{{ label }}</a-option>
        </a-select>
      </template>
      <template #options="{ record }">
        <a-input v-model="record.options" placeholder="选项（JSON）" size="mini" style="width: 160px" />
      </template>
      <template #operation="{ record, rowIndex }">
        <a-button type="text" status="danger" size="mini" @click="removeFilter(rowIndex)">
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
  filterTypes: Record<string, string>;
  fields?: any[];
}>();

const emit = defineEmits(['update:modelValue']);

const filters = computed({
  get: () => props.modelValue || [],
  set: (val) => emit('update:modelValue', val),
});

const columns = [
  { title: '字段名', dataIndex: 'key', slotName: 'key', width: 150 },
  { title: '显示名称', dataIndex: 'label', slotName: 'label', width: 140 },
  { title: '筛选类型', dataIndex: 'type', slotName: 'type', width: 150 },
  { title: '选项数据', dataIndex: 'options', slotName: 'options', width: 180 },
  { title: '操作', slotName: 'operation', width: 80, align: 'center', fixed: 'right' },
];

let idCounter = 200;

function createFilter(overrides = {}) {
  return {
    id: `filter_${++idCounter}`,
    key: '',
    label: '',
    type: 'like',
    options: '',
    ...overrides,
  };
}

function addFilter() {
  filters.value = [...filters.value, createFilter()];
}

function addAllFields() {
  if (!props.fields || props.fields.length === 0) return;
  const newFilters = props.fields
    .filter((f) => ['select', 'radio', 'switch', 'boolean'].includes(f.type))
    .map((f) => createFilter({ key: f.key, label: f.label, type: 'select' }));
  filters.value = [...filters.value, ...newFilters];
}

function removeFilter(index: number) {
  filters.value = filters.value.filter((_, i) => i !== index);
}
</script>

<style scoped>
.filter-builder {
  padding: 8px 0;
}
</style>
