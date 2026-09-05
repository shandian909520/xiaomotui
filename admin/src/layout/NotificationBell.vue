<template>
  <el-popover
    placement="bottom-end"
    :width="360"
    trigger="click"
    popper-class="notification-popover"
  >
    <template #reference>
      <el-badge :value="unreadCount || undefined" :hidden="!unreadCount" :max="99">
        <el-button text circle :icon="Bell" class="bell-btn" />
      </el-badge>
    </template>

    <div class="notification-panel">
      <div class="panel-header">
        <strong>通知</strong>
        <el-button text size="small" @click="markAllRead" :disabled="!unreadCount">
          全部已读
        </el-button>
      </div>

      <div v-loading="loading" class="notification-list">
        <div
          v-for="item in notifications"
          :key="item.id"
          class="notification-item"
          :class="{ unread: !item.is_read }"
          @click="handleClick(item)"
        >
          <div class="noti-icon" :class="item.type">
            <el-icon><component :is="getIcon(item.type)" /></el-icon>
          </div>
          <div class="noti-content">
            <div class="noti-title">{{ item.title }}</div>
            <div class="noti-time">{{ item.created_at }}</div>
          </div>
          <span v-if="!item.is_read" class="unread-dot"></span>
        </div>

        <div v-if="!loading && notifications.length === 0" class="empty-state">
          <el-empty description="暂无通知" :image-size="60" />
        </div>
      </div>

      <div class="panel-footer">
        <a class="view-all-link" @click="goToNotifications">查看更多</a>
      </div>
    </div>
  </el-popover>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { Bell, InfoFilled, Promotion, BellFilled } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import {
  getNotificationList,
  markNotificationRead,
  markAllNotificationRead,
  getUnreadNotificationCount
} from '@/api/index.js'

const router = useRouter()

const loading = ref(false)
const unreadCount = ref(0)
const notifications = ref([])
let timer = null

const fallbackNotifications = [
  { id: 1, title: '系统已升级至V3.2版本，新增批量剪辑功能', type: 'system', is_read: false, created_at: '2026-05-26 10:30' },
  { id: 2, title: '618活动即将开始，快来配置活动场景', type: 'activity', is_read: false, created_at: '2026-05-25 14:00' },
  { id: 3, title: '智能员工新增"文案改写"功能', type: 'feature', is_read: true, created_at: '2026-05-24 09:15' },
  { id: 4, title: '您的剪辑魔力余额不足，请及时充值', type: 'system', is_read: true, created_at: '2026-05-23 16:40' },
  { id: 5, title: '五一活动数据报告已生成', type: 'feature', is_read: true, created_at: '2026-05-22 11:20' }
]

const getIcon = (type) => {
  const map = {
    system: InfoFilled,
    activity: Promotion,
    feature: BellFilled
  }
  return map[type] || Bell
}

const unwrapData = (res) => res?.data ?? res

const loadUnreadCount = async () => {
  try {
    const res = await getUnreadNotificationCount()
    const data = unwrapData(res)
    unreadCount.value = data?.count ?? 0
  } catch {
    unreadCount.value = import.meta.env.DEV ? 2 : 0
  }
}

const loadNotifications = async () => {
  loading.value = true
  try {
    const res = await getNotificationList({ page: 1, limit: 5 })
    const data = unwrapData(res)
    if (data.list && data.list.length) {
      notifications.value = data.list
    } else if (Array.isArray(data) && data.length) {
      notifications.value = data
    } else {
      notifications.value = []
    }
  } catch {
    notifications.value = import.meta.env.DEV ? fallbackNotifications : []
  } finally {
    loading.value = false
  }
}

const handleClick = async (item) => {
  if (!item.is_read) {
    try {
      await markNotificationRead({ id: item.id })
      item.is_read = true
      unreadCount.value = Math.max(0, unreadCount.value - 1)
    } catch {
      // ignore
    }
  }
}

const markAllRead = async () => {
  try {
    await markAllNotificationRead()
    notifications.value.forEach(n => { n.is_read = true })
    unreadCount.value = 0
    ElMessage.success('已全部标记为已读')
  } catch {
    ElMessage.error('操作失败')
  }
}

const goToNotifications = () => {
  router.push('/notifications')
}

onMounted(() => {
  loadNotifications()
  loadUnreadCount()
  timer = setInterval(loadUnreadCount, 60000)
})

onUnmounted(() => {
  if (timer) clearInterval(timer)
})
</script>

<style lang="scss" scoped>
.bell-btn {
  font-size: 18px;
}

.notification-panel {
  margin: -12px;
}

.panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  border-bottom: 1px solid #f0f0f0;

  strong {
    font-size: 16px;
    color: #303133;
  }
}

.notification-list {
  max-height: 360px;
  overflow-y: auto;
}

.notification-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px 16px;
  cursor: pointer;
  transition: background 0.2s;

  &:hover {
    background: #f9f7ff;
  }
}

.noti-icon {
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  flex-shrink: 0;
  font-size: 16px;

  &.system {
    color: #409eff;
    background: #ecf5ff;
  }
  &.activity {
    color: #e6a23c;
    background: #fdf6ec;
  }
  &.feature {
    color: #a855f7;
    background: #f3e8ff;
  }
}

.noti-content {
  flex: 1;
  min-width: 0;
}

.noti-title {
  font-size: 13px;
  color: #303133;
  line-height: 1.5;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.noti-time {
  margin-top: 4px;
  font-size: 12px;
  color: #c0c4cc;
}

.unread-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #f56c6c;
  flex-shrink: 0;
  margin-top: 6px;
}

.notification-item.unread .noti-title {
  font-weight: 600;
}

.empty-state {
  padding: 30px 0;
}

.panel-footer {
  border-top: 1px solid #f0f0f0;
  text-align: center;
  padding: 10px;
}

.view-all-link {
  color: #a855f7;
  font-size: 13px;
  cursor: pointer;
  text-decoration: none;

  &:hover {
    color: #9333ea;
  }
}
</style>
