<template>
  <div class="field-builder">
    <a-space style="margin-bottom: 16px">
      <a-button type="primary" size="small" @click="addField">
        <icon-plus /> 添加字段
      </a-button>
      <a-button size="small" @click="addTimestamps">
        <icon-clock-circle /> 添加时间戳
      </a-button>
    </a-space>

    <a-table
      :columns="columns"
      :data="fields"
      :pagination="false"
      :draggable="{ type: 'handle', width: 40 }"
      @change="onDragChange"
      row-key="id"
      size="small"
      :bordered="{ cell: true }"
    >
      <template #type="{ record }">
        <a-select v-model="record.type" size="mini" style="width: 140px">
          <a-option v-for="(info, key) in fieldTypes" :key="key" :value="key">
            {{ info.label }}
          </a-option>
        </a-select>
      </template>
      <template #key="{ record }">
        <a-input v-model="record.key" placeholder="字段名" size="mini" @change="onKeyChange(record)" />
      </template>
      <template #label="{ record }">
        <a-input v-model="record.label" placeholder="显示名称" size="mini" />
      </template>
      <template #db_type="{ record }">
        <a-select v-model="record.db_type" size="mini" style="width: 110px">
          <a-option v-for="(info, key) in dbTypes" :key="key" :value="key">{{ info.label }}</a-option>
        </a-select>
      </template>
      <template #nullable="{ record }">
        <a-switch v-model="record.nullable" size="small" />
      </template>
      <template #required="{ record }">
        <a-switch v-model="record.required" size="small" />
      </template>
      <template #default_value="{ record }">
        <a-input v-model="record.default" placeholder="默认值" size="mini" style="width: 100px" />
      </template>
      <template #comment="{ record }">
        <a-input v-model="record.comment" placeholder="注释" size="mini" style="width: 120px" />
      </template>
      <template #operation="{ record, rowIndex }">
        <a-space>
          <a-button type="text" size="mini" @click="duplicateField(record)">
            <icon-copy />
          </a-button>
          <a-button type="text" status="danger" size="mini" @click="removeField(rowIndex)">
            <icon-delete />
          </a-button>
        </a-space>
      </template>
    </a-table>
  </div>
</template>

<script lang="ts" setup>
import { computed } from 'vue';

const props = defineProps<{
  modelValue: any[];
  fieldTypes: Record<string, any>;
  dbTypes?: Record<string, any>;
}>();

const emit = defineEmits(['update:modelValue']);

const fields = computed({
  get: () => props.modelValue || [],
  set: (val) => emit('update:modelValue', val),
});

const defaultDbTypes = {
  string: { label: '字符串' },
  text: { label: '长文本' },
  integer: { label: '整数' },
  bigInteger: { label: '大整数' },
  decimal: { label: '小数' },
  boolean: { label: '布尔值' },
  date: { label: '日期' },
  dateTime: { label: '日期时间' },
  json: { label: 'JSON' },
};

const dbTypes = computed(() => props.dbTypes || defaultDbTypes);

const columns = [
  { title: '类型', dataIndex: 'type', slotName: 'type', width: 160 },
  { title: '字段名', dataIndex: 'key', slotName: 'key', width: 150 },
  { title: '显示名称', dataIndex: 'label', slotName: 'label', width: 140 },
  { title: '数据库类型', dataIndex: 'db_type', slotName: 'db_type', width: 130 },
  { title: '可空', dataIndex: 'nullable', slotName: 'nullable', width: 70, align: 'center' },
  { title: '必填', dataIndex: 'required', slotName: 'required', width: 70, align: 'center' },
  { title: '默认值', dataIndex: 'default', slotName: 'default_value', width: 120 },
  { title: '注释', dataIndex: 'comment', slotName: 'comment', width: 140 },
  { title: '操作', slotName: 'operation', width: 100, align: 'center', fixed: 'right' },
];

let idCounter = 0;

function createField(overrides = {}) {
  return {
    id: `field_${++idCounter}`,
    type: 'text',
    key: '',
    label: '',
    db_type: 'string',
    nullable: false,
    required: false,
    default: '',
    comment: '',
    ...overrides,
  };
}

function addField() {
  fields.value = [...fields.value, createField()];
}

function addTimestamps() {
  fields.value = [
    ...fields.value,
    createField({ type: 'dateTime', key: 'created_at', label: '创建时间', db_type: 'dateTime', nullable: true }),
    createField({ type: 'dateTime', key: 'updated_at', label: '更新时间', db_type: 'dateTime', nullable: true }),
  ];
}

function removeField(index: number) {
  fields.value = fields.value.filter((_, i) => i !== index);
}

function duplicateField(record: any) {
  const newField = { ...record, id: `field_${++idCounter}`, key: record.key + '_copy' };
  fields.value = [...fields.value, newField];
}

function onKeyChange(record: any) {
  // 字段名变化时自动更新显示名称
  if (record.key && !record.label) {
    record.label = record.key.replace(/_/g, ' ').replace(/\b\w/g, (c: string) => c.toUpperCase());
  }
}

function onDragChange({ newData }: any) {
  fields.value = newData;
}
</script>

<style scoped>
.field-builder {
  padding: 8px 0;
}
</style>
