<template>
  <div class="xmt-filter-toolbar">
    <div class="filter-left">
      <el-date-picker
        v-if="showDateRange"
        v-model="dateRange"
        type="daterange"
        range-separator="-"
        start-placeholder="开始日期"
        end-placeholder="结束日期"
        size="default"
        class="date-range-picker"
      />
      <slot />
    </div>
    <div class="filter-right">
      <el-input
        v-if="showSearch"
        v-model="keyword"
        :placeholder="searchPlaceholder"
        prefix-icon="Search"
        clearable
        class="search-input"
        @keyup.enter="$emit('search', keyword)"
      />
      <slot name="actions" />
      <el-button v-if="showExport" @click="$emit('export')">导出</el-button>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const props = defineProps({
  showSearch: Boolean,
  showDateRange: Boolean,
  showExport: Boolean,
  searchPlaceholder: {
    type: String,
    default: '搜索'
  },
  modelValue: Object
})

const emit = defineEmits(['update:modelValue', 'search', 'export'])

const keyword = ref('')
const dateRange = ref([])
</script>

<style lang="scss" scoped>
.xmt-filter-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 24px;
  background: #fff;
  border-radius: 12px;
  margin-bottom: 16px;

  .filter-left,
  .filter-right {
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .search-input {
    width: 220px;
  }

  .date-range-picker {
    width: 280px;
  }
}
</style>