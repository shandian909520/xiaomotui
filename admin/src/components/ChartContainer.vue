<template>
  <el-card class="chart-container" shadow="hover" :body-style="{ padding: '20px' }">
    <!-- 卡片头部 -->
    <template #header>
      <div class="chart-header">
        <div class="chart-title-wrapper">
          <el-icon v-if="icon" :size="20" class="title-icon">
            <component :is="icon" />
          </el-icon>
          <span class="chart-title">{{ title }}</span>
        </div>
        <div class="chart-actions">
          <slot name="actions">
            <el-button
              v-if="refreshable"
              :icon="Refresh"
              circle
              size="small"
              :loading="loading"
              @click="handleRefresh"
            />
            <el-button
              v-if="downloadable"
              :icon="Download"
              circle
              size="small"
              @click="handleDownload"
            />
          </slot>
        </div>
      </div>
    </template>

    <!-- 卡片内容 -->
    <div class="chart-content" :style="{ height: height }">
      <!-- 加载状态 -->
      <div v-if="loading" class="chart-loading">
        <el-icon class="is-loading" :size="32">
          <Loading />
        </el-icon>
        <div class="loading-text">加载中...</div>
      </div>

      <!-- 空状态 -->
      <div v-else-if="empty" class="chart-empty">
        <el-icon :size="64" color="#C0C4CC">
          <DocumentDelete />
        </el-icon>
        <div class="empty-text">暂无数据</div>
      </div>

      <!-- 图表插槽 -->
      <div v-show="!loading && !empty" class="chart-wrapper">
        <slot></slot>
      </div>
    </div>

    <!-- 卡片底部 -->
    <template v-if="$slots.footer" #footer>
      <div class="chart-footer">
        <slot name="footer"></slot>
      </div>
    </template>
  </el-card>
</template>

<script setup>
import { Refresh, Download, Loading, DocumentDelete } from '@element-plus/icons-vue'

const props = defineProps({
  // 图表标题
  title: {
    type: String,
    required: true
  },
  // 标题图标
  icon: {
    type: [String, Object],
    default: ''
  },
  // 图表高度
  height: {
    type: String,
    default: '400px'
  },
  // 是否显示刷新按钮
  refreshable: {
    type: Boolean,
    default: true
  },
  // 是否显示下载按钮
  downloadable: {
    type: Boolean,
    default: true
  },
  // 加载状态
  loading: {
    type: Boolean,
    default: false
  },
  // 是否为空
  empty: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['refresh', 'download'])

// 处理刷新
const handleRefresh = () => {
  emit('refresh')
}

// 处理下载
const handleDownload = () => {
  emit('download')
}
</script>

<style lang="scss" scoped>
.chart-container {
  border-radius: 8px;
  height: 100%;

  :deep(.el-card__header) {
    padding: 16px 20px;
    border-bottom: 1px solid #EBEEF5;
  }

  .chart-header {
    display: flex;
    align-items: center;
    justify-content: space-between;

    .chart-title-wrapper {
      display: flex;
      align-items: center;
      gap: 8px;

      .title-icon {
        color: #409EFF;
      }

      .chart-title {
        font-size: 16px;
        font-weight: 600;
        color: #303133;
      }
    }

    .chart-actions {
      display: flex;
      gap: 8px;
    }
  }

  .chart-content {
    position: relative;
    width: 100%;

    .chart-loading {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      height: 100%;
      gap: 12px;

      .loading-text {
        font-size: 14px;
        color: #909399;
      }
    }

    .chart-empty {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      height: 100%;
      gap: 16px;

      .empty-text {
        font-size: 14px;
        color: #909399;
      }
    }

    .chart-wrapper {
      width: 100%;
      height: 100%;
    }
  }

  .chart-footer {
    padding-top: 12px;
    border-top: 1px solid #EBEEF5;
    font-size: 12px;
    color: #909399;
  }
}

// 响应式设计
@media (max-width: 768px) {
  .chart-container {
    .chart-header {
      .chart-title-wrapper {
        .chart-title {
          font-size: 14px;
        }
      }
    }
  }
}
</style>
