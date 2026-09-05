<template>
  <div class="xmt-empty-panel">
    <div class="empty-icon">
      <el-icon :size="48" color="#c9bfdb"><component :is="icon" /></el-icon>
    </div>
    <p class="empty-message">{{ message || '暂无数据' }}</p>
    <el-button v-if="actionText" type="primary" @click="$emit('action')">
      {{ actionText }}
    </el-button>
  </div>
</template>

<script setup>
import { FolderOpened } from '@element-plus/icons-vue'

defineProps({
  message: String,
  icon: {
    type: String,
    default: 'FolderOpened'
  },
  actionText: String
})

defineEmits(['action'])
</script>

<style lang="scss" scoped>
.xmt-empty-panel {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  background: #fff;
  border-radius: 16px;

  .empty-icon {
    width: 80px;
    height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 16px;
    border-radius: 50%;
    background: #f8f3ff;
  }

  .empty-message {
    margin: 0 0 20px;
    color: #746b80;
    font-size: 15px;
  }
}
</style>