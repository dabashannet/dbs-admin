<template>
  <div class="code-block">
    <div class="code-header">
      <a-space>
        <span class="lang-badge">{{ language.toUpperCase() }}</span>
        <a-button type="text" size="mini" @click="copyCode">
          <icon-copy /> 复制
        </a-button>
      </a-space>
    </div>
    <pre class="code-content"><code>{{ code }}</code></pre>
  </div>
</template>

<script lang="ts" setup>
import { Message } from '@arco-design/web-vue';

defineProps<{
  code: string;
  language: string;
}>();

function copyCode() {
  const code = (document.querySelector('.code-block code') as HTMLElement)?.textContent || '';
  navigator.clipboard.writeText(code).then(() => {
    Message.success('已复制到剪贴板');
  });
}
</script>

<style scoped>
.code-block {
  border: 1px solid var(--color-border-2);
  border-radius: 4px;
  overflow: hidden;
}
.code-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 12px;
  background: var(--color-fill-2);
  border-bottom: 1px solid var(--color-border-2);
}
.lang-badge {
  font-size: 11px;
  padding: 2px 6px;
  border-radius: 3px;
  background: rgb(var(--primary-6));
  color: #fff;
  font-weight: 600;
}
.code-content {
  margin: 0;
  padding: 16px;
  overflow-x: auto;
  font-family: 'SF Mono', 'Fira Code', 'Consolas', monospace;
  font-size: 13px;
  line-height: 1.6;
  background: var(--color-bg-1);
  max-height: 600px;
  overflow-y: auto;
}
</style>
