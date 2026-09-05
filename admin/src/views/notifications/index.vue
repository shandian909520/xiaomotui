<template>
  <div class="notifications-page">
    <div class="page-header">
      <h2>功能通知</h2>
    </div>

    <div class="filter-bar">
      <el-tabs v-model="activeType" @tab-change="handleSearch">
        <el-tab-pane label="全部" name="all" />
        <el-tab-pane label="功能更新" name="feature" />
        <el-tab-pane label="系统通知" name="system" />
        <el-tab-pane label="活动通知" name="activity" />
      </el-tabs>

      <div class="read-filter">
        <el-select v-model="readFilter" size="small" style="width: 120px" @change="handleSearch">
          <el-option label="全部" value="all" />
          <el-option label="未读" value="unread" />
          <el-option label="已读" value="read" />
        </el-select>
      </div>
    </div>

    <div v-loading="loading" class="notification-list">
      <div
        v-for="item in notifications"
        :key="item.id"
        class="notification-item"
        :class="{ unread: !item.is_read }"
        @click="viewDetail(item)"
      >
        <div class="noti-icon" :class="item.type">
          <el-icon><component :is="getIcon(item.type)" /></el-icon>
        </div>
        <div class="noti-body">
          <div class="noti-header">
            <strong class="noti-title">{{ item.title }}</strong>
            <el-tag :type="typeTagMap[item.type]" size="small">{{ typeLabelMap[item.type] }}</el-tag>
          </div>
          <p class="noti-summary">{{ item.summary }}</p>
          <div class="noti-footer">
            <span class="noti-time">{{ item.created_at }}</span>
            <span v-if="!item.is_read" class="unread-badge">未读</span>
          </div>
        </div>
      </div>

      <div v-if="!loading && notifications.length === 0" class="empty-state">
        <el-empty description="暂无通知" />
      </div>
    </div>

    <div v-if="total > 0" class="pagination-wrap">
      <el-pagination
        v-model:current-page="page"
        v-model:page-size="pageSize"
        :total="total"
        :page-sizes="[10, 20, 30]"
        layout="total, sizes, prev, pager, next"
        @size-change="handleSearch"
        @current-change="handleSearch"
      />
    </div>

    <!-- 详情弹窗 -->
    <el-dialog v-model="detailVisible" :title="currentNoti?.title" width="600px">
      <div class="detail-content">
        <div class="detail-meta">
          <el-tag :type="typeTagMap[currentNoti?.type]" size="small">
            {{ typeLabelMap[currentNoti?.type] }}
          </el-tag>
          <span class="detail-time">{{ currentNoti?.created_at }}</span>
        </div>
        <div class="detail-body" v-html="currentNoti?.content || currentNoti?.summary"></div>
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { InfoFilled, Promotion, BellFilled } from '@element-plus/icons-vue'
import { getNotificationList, getNotificationDetail, markNotificationRead } from '@/api/index.js'
import { normalizePagination } from '@/utils/responseHelper'

const loading = ref(false)
const activeType = ref('all')
const readFilter = ref('all')
const notifications = ref([])
const page = ref(1)
const pageSize = ref(10)
const total = ref(0)

const detailVisible = ref(false)
const currentNoti = ref(null)

const typeLabelMap = { system: '系统通知', feature: '功能更新', activity: '活动通知' }
const typeTagMap = { system: 'info', feature: 'success', activity: 'warning' }

const getIcon = (type) => {
  const map = { system: InfoFilled, activity: Promotion, feature: BellFilled }
  return map[type] || BellFilled
}


const handleSearch = () => {
  page.value = 1
  loadList()
}

const loadList = async () => {
  loading.value = true
  try {
    const params = {
      page: page.value,
      limit: pageSize.value,
      type: activeType.value === 'all' ? undefined : activeType.value,
      read_status: readFilter.value === 'all' ? undefined : readFilter.value
    }
    const res = await getNotificationList(params)
    const { list, total: totalCount } = normalizePagination(res)
    notifications.value = list
    total.value = totalCount
  } catch (err) {
    console.error('[通知列表] 加载失败:', err)
    notifications.value = []
    total.value = 0
    ElMessage.error('获取通知列表失败，请稍后重试')
  } finally {
    loading.value = false
  }
}


const viewDetail = async (item) => {
  currentNoti.value = item
  detailVisible.value = true
  if (!item.is_read) {
    try {
      await markNotificationRead({ id: item.id })
      item.is_read = true
    } catch {
      // ignore
    }
  }
}

onMounted(() => {
  loadList()
})
</script>

<style scoped lang="scss">
.notifications-page {
  padding: 20px;
  min-height: calc(100vh - 120px);
}

.page-header {
  margin-bottom: 20px;

  h2 {
    font-size: 24px;
    font-weight: 600;
    color: #303133;
    margin: 0;
  }
}

.filter-bar {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 20px;
  background: #fff;
  border-radius: 12px;
  padding: 0 20px;

  :deep(.el-tabs__header) {
    margin-bottom: 0;
  }
}

.read-filter {
  padding-top: 12px;
}

.notification-list {
  min-height: 300px;
}

.notification-item {
  display: flex;
  gap: 16px;
  padding: 20px;
  background: #fff;
  border-radius: 12px;
  margin-bottom: 12px;
  cursor: pointer;
  transition: all 0.2s;
  border: 1px solid transparent;

  &:hover {
    box-shadow: 0 4px 16px rgba(100, 60, 200, 0.08);
    border-color: #e8e0f0;
  }

  &.unread {
    border-left: 3px solid #a855f7;
  }
}

.noti-icon {
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 10px;
  flex-shrink: 0;
  font-size: 18px;

  &.system {
    color: #409eff;
    background: #ecf5ff;
  }
  &.activity {
    color: #e6a23c;
    background: #fdf6ec;
  }
  &.feature {
    color: #67c23a;
    background: #f0f9eb;
  }
}

.noti-body {
  flex: 1;
  min-width: 0;
}

.noti-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
}

.noti-title {
  font-size: 15px;
  color: #303133;
  font-weight: 500;
}

.notification-item.unread .noti-title {
  font-weight: 600;
}

.noti-summary {
  margin: 8px 0 0;
  font-size: 13px;
  color: #909399;
  line-height: 1.6;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.noti-footer {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-top: 10px;
}

.noti-time {
  font-size: 12px;
  color: #c0c4cc;
}

.unread-badge {
  font-size: 11px;
  color: #a855f7;
  background: #f3e8ff;
  padding: 1px 8px;
  border-radius: 10px;
}

.empty-state {
  padding: 60px 0;
}

.pagination-wrap {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}

.detail-meta {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 20px;
  padding-bottom: 16px;
  border-bottom: 1px solid #f0f0f0;
}

.detail-time {
  font-size: 13px;
  color: #909399;
}

.detail-body {
  font-size: 14px;
  line-height: 1.8;
  color: #303133;

  :deep(ul) {
    padding-left: 20px;
  }

  :deep(li) {
    margin-bottom: 6px;
  }
}
</style>
