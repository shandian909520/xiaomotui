<template>
  <el-drawer
    v-model="drawerVisible"
    title="任务中心"
    direction="rtl"
    size="400px"
    :append-to-body="true"
    class="task-drawer"
  >
    <template #header>
      <div class="drawer-header">
        <strong>任务中心</strong>
        <el-button text circle :icon="Refresh" @click="loadTasks" />
      </div>
    </template>

    <div class="task-summary">
      <div v-for="item in summaryList" :key="item.label" class="summary-item">
        <span class="summary-count" :style="{ color: item.color }">{{ item.count }}</span>
        <span class="summary-label">{{ item.label }}</span>
      </div>
    </div>

    <div v-loading="loading" class="task-body">
      <template v-if="taskGroups.length > 0">
        <div v-for="group in taskGroups" :key="group.type" class="task-group">
          <div class="group-title">
            <el-icon :style="{ color: group.color }"><component :is="group.icon" /></el-icon>
            <span>{{ group.name }}</span>
            <el-tag size="small" type="info">{{ group.tasks.length }}</el-tag>
          </div>

          <div v-for="task in group.tasks" :key="task.id" class="task-item">
            <div class="task-main">
              <div class="task-name">{{ task.name }}</div>
              <el-tag :type="statusTypeMap[task.status]" size="small">
                {{ statusLabelMap[task.status] || task.status }}
              </el-tag>
            </div>
            <el-progress
              v-if="task.progress != null && task.status === 'processing'"
              :percentage="task.progress"
              :stroke-width="6"
              :color="group.color"
              style="margin-top: 8px"
            />
            <div class="task-time">{{ task.created_at }}</div>
          </div>
        </div>
      </template>

      <div v-else-if="!loading" class="empty-state">
        <el-empty description="暂无进行中的任务" :image-size="80" />
      </div>
    </div>
  </el-drawer>

  <el-badge :value="processingCount || undefined" :hidden="!processingCount" :max="99">
    <el-button text circle class="task-btn" @click="drawerVisible = true">
      <el-icon><MagicStick /></el-icon>
    </el-button>
  </el-badge>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { MagicStick, Refresh, VideoCamera, Edit, Film } from '@element-plus/icons-vue'
import { getUserTaskList, getUserTaskSummary } from '@/api/index.js'

const drawerVisible = ref(false)
const loading = ref(false)
const taskGroups = ref([])
const summary = ref({})
let timer = null

const statusTypeMap = {
  pending: 'info',
  processing: 'warning',
  completed: 'success',
  failed: 'danger'
}

const statusLabelMap = {
  pending: '等待中',
  processing: '处理中',
  completed: '已完成',
  failed: '失败'
}

const processingCount = computed(() => {
  return taskGroups.value.reduce((sum, g) => {
    return sum + g.tasks.filter(t => t.status === 'processing').length
  }, 0)
})

const summaryList = computed(() => {
  const s = summary.value
  return [
    { label: '处理中', count: s.processing || 0, color: '#e6a23c' },
    { label: '等待中', count: s.pending || 0, color: '#909399' },
    { label: '已完成', count: s.completed || 0, color: '#67c23a' },
    { label: '失败', count: s.failed || 0, color: '#f56c6c' }
  ]
})

const fallbackTasks = [
  {
    type: 'video_export',
    name: '视频导出',
    icon: 'VideoCamera',
    color: '#a855f7',
    tasks: [
      { id: 1, name: '618大促视频导出', status: 'processing', progress: 65, created_at: '2026-05-26 10:30' },
      { id: 2, name: '门店宣传视频', status: 'pending', progress: null, created_at: '2026-05-26 09:15' }
    ]
  },
  {
    type: 'ai_generate',
    name: 'AI生成',
    icon: 'MagicStick',
    color: '#3b82f6',
    tasks: [
      { id: 3, name: '小红书笔记生成', status: 'completed', progress: 100, created_at: '2026-05-26 08:00' }
    ]
  },
  {
    type: 'clip_export',
    name: '剪辑导出',
    icon: 'Film',
    color: '#f59e0b',
    tasks: [
      { id: 4, name: '批量混剪导出(20个)', status: 'processing', progress: 30, created_at: '2026-05-26 11:00' }
    ]
  }
]

const emptySummary = { processing: 0, pending: 0, completed: 0, failed: 0 }
const fallbackSummary = { processing: 2, pending: 1, completed: 1, failed: 0 }

const unwrapSettledData = (result) => {
  if (result.status !== 'fulfilled' || !result.value) return null
  return result.value.data ?? result.value
}

const loadTasks = async () => {
  loading.value = true
  try {
    const [taskRes, summaryRes] = await Promise.allSettled([
      getUserTaskList({ page: 1, limit: 50 }),
      getUserTaskSummary()
    ])

    const taskData = unwrapSettledData(taskRes)
    if (taskData) {
      const data = taskData
      if (data.groups) {
        taskGroups.value = data.groups
      } else if (Array.isArray(data)) {
        taskGroups.value = data
      } else {
        taskGroups.value = import.meta.env.DEV ? fallbackTasks : []
      }
    } else {
      taskGroups.value = import.meta.env.DEV ? fallbackTasks : []
    }

    const summaryData = unwrapSettledData(summaryRes)
    if (summaryData) {
      summary.value = summaryData
    } else {
      summary.value = import.meta.env.DEV ? fallbackSummary : emptySummary
    }
  } catch {
    taskGroups.value = import.meta.env.DEV ? fallbackTasks : []
    summary.value = import.meta.env.DEV ? fallbackSummary : emptySummary
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadTasks()
  timer = setInterval(loadTasks, 30000)
})

onUnmounted(() => {
  if (timer) clearInterval(timer)
})
</script>

<style lang="scss" scoped>
.task-btn {
  font-size: 18px;
}

.drawer-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;

  strong {
    font-size: 18px;
    color: #303133;
  }
}

.task-summary {
  display: flex;
  gap: 0;
  padding: 0 0 16px;
  border-bottom: 1px solid #f0f0f0;
  margin-bottom: 16px;
}

.summary-item {
  flex: 1;
  text-align: center;
}

.summary-count {
  display: block;
  font-size: 22px;
  font-weight: 700;
}

.summary-label {
  display: block;
  margin-top: 4px;
  font-size: 12px;
  color: #909399;
}

.task-body {
  min-height: 200px;
}

.task-group {
  margin-bottom: 20px;
}

.group-title {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
  font-size: 15px;
  font-weight: 600;
  color: #303133;
}

.task-item {
  padding: 12px;
  background: #fafafa;
  border-radius: 8px;
  margin-bottom: 8px;
}

.task-main {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.task-name {
  font-size: 14px;
  color: #303133;
  font-weight: 500;
}

.task-time {
  margin-top: 6px;
  font-size: 12px;
  color: #c0c4cc;
}

.empty-state {
  padding: 40px 0;
}
</style>
