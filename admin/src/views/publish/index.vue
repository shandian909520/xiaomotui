<template>
  <div class="publish-manage">
    <!-- 页面头部 -->
    <div class="page-header">
      <div class="header-left">
        <h2>发布管理</h2>
        <p class="subtitle">管理内容发布任务，追踪发布状态</p>
      </div>
      <div class="header-right">
        <el-button type="primary" @click="handleCreate">
          <el-icon><Plus /></el-icon>
          创建发布
        </el-button>
      </div>
    </div>

    <!-- 筛选条 -->
    <div class="filter-bar">
      <div class="filter-left">
        <el-radio-group v-model="listQuery.status" @change="handleFilterChange">
          <el-radio-button label="">全部</el-radio-button>
          <el-radio-button label="PENDING">待发布</el-radio-button>
          <el-radio-button label="PROCESSING">发布中</el-radio-button>
          <el-radio-button label="SUCCESS">已发布</el-radio-button>
          <el-radio-button label="FAILED">失败</el-radio-button>
          <el-radio-button label="PARTIAL_SUCCESS">部分成功</el-radio-button>
        </el-radio-group>
      </div>
      <div class="filter-right">
        <el-select
          v-model="listQuery.platform"
          placeholder="平台筛选"
          clearable
          style="width: 140px; margin-right: 12px"
          @change="handleFilterChange"
        >
          <el-option label="抖音" value="DOUYIN" />
          <el-option label="快手" value="KUAISHOU" />
          <el-option label="小红书" value="XIAOHONGSHU" />
          <el-option label="微博" value="WEIBO" />
          <el-option label="B站" value="BILIBILI" />
        </el-select>
        <el-button @click="getList">
          <el-icon><Refresh /></el-icon>
          刷新
        </el-button>
      </div>
    </div>

    <!-- 卡片列表 -->
    <div v-loading="loading" class="card-grid">
      <div
        v-for="row in taskList"
        :key="row.id"
        class="pub-card"
        :class="['status-' + (row.status || '').toLowerCase()]"
      >
        <div class="card-head">
          <div class="task-id">#{{ row.id }} · 内容 {{ row.content_task_id }}</div>
          <el-tag :type="getStatusType(row.status)" size="small" effect="dark">
            {{ getStatusLabel(row.status) }}
          </el-tag>
        </div>

        <div class="platform-tags">
          <span
            v-for="(p, idx) in parsePlatforms(row.platforms)"
            :key="idx"
            class="pf-chip"
            :class="['pf-' + (p || '').toLowerCase()]"
          >
            {{ getPlatformName(p) }}
          </span>
        </div>

        <div class="meta-grid">
          <div class="meta-cell">
            <span class="cell-label">发布进度</span>
            <span class="cell-value">
              <span class="ok">{{ row.success_count || 0 }}</span>
              /
              <span class="err">{{ row.failed_count || 0 }}</span>
              / {{ row.total_count || 0 }}
            </span>
          </div>
          <div class="meta-cell">
            <span class="cell-label">定时发布</span>
            <span class="cell-value">{{ row.scheduled_time ? formatTime(row.scheduled_time) : '立即发布' }}</span>
          </div>
          <div class="meta-cell full">
            <span class="cell-label">创建时间</span>
            <span class="cell-value">{{ formatTime(row.create_time) }}</span>
          </div>
        </div>

        <div class="card-actions">
          <el-button type="primary" link size="small" @click="handleViewDetail(row)">
            详情
          </el-button>
          <el-button
            v-if="canRetry(row.status)"
            type="warning"
            link
            size="small"
            @click="handleRetry(row)"
          >
            重试
          </el-button>
          <el-button
            v-if="canCancel(row.status)"
            type="danger"
            link
            size="small"
            @click="handleCancel(row)"
          >
            取消
          </el-button>
          <el-button
            v-if="row.status === 'PENDING' && row.scheduled_time"
            type="info"
            link
            size="small"
            @click="handleEditSchedule(row)"
          >
            改时
          </el-button>
        </div>
      </div>

      <el-empty v-if="!loading && taskList.length === 0" description="暂无发布任务" class="empty" />
    </div>

    <!-- 分页 -->
    <div v-if="total > 0" class="pagination">
      <el-pagination
        v-model:current-page="listQuery.page"
        v-model:page-size="listQuery.limit"
        :total="total"
        :page-sizes="[10, 20, 50]"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="handleSizeChange"
        @current-change="handlePageChange"
      />
    </div>

    <!-- 创建发布 -->
    <el-dialog
      v-model="createDialogVisible"
      title="创建发布任务"
      width="680px"
      destroy-on-close
      :close-on-click-modal="false"
    >
      <el-form
        ref="createFormRef"
        :model="createForm"
        :rules="createRules"
        label-width="100px"
      >
        <el-form-item label="内容任务ID" prop="content_task_id">
          <el-input
            v-model.number="createForm.content_task_id"
            placeholder="请输入内容任务ID"
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item label="发布平台" prop="platforms">
          <div class="platform-config-list">
            <div
              v-for="(p, index) in createForm.platforms"
              :key="index"
              class="platform-config-item"
            >
              <el-row :gutter="12">
                <el-col :span="8">
                  <el-select v-model="p.platform" placeholder="选择平台" style="width: 100%">
                    <el-option label="抖音" value="DOUYIN" />
                    <el-option label="快手" value="KUAISHOU" />
                    <el-option label="小红书" value="XIAOHONGSHU" />
                    <el-option label="微博" value="WEIBO" />
                    <el-option label="B站" value="BILIBILI" />
                  </el-select>
                </el-col>
                <el-col :span="8">
                  <el-input v-model="p.account_id" placeholder="账号ID（选填）" />
                </el-col>
                <el-col :span="6">
                  <el-input v-model="p.config.title" placeholder="标题（选填）" />
                </el-col>
                <el-col :span="2">
                  <el-button
                    type="danger"
                    link
                    :icon="Delete"
                    @click="removePlatformConfig(index)"
                  />
                </el-col>
              </el-row>
            </div>
            <el-button type="primary" link @click="addPlatformConfig">
              + 添加平台
            </el-button>
          </div>
        </el-form-item>
        <el-form-item label="定时发布">
          <el-date-picker
            v-model="createForm.scheduled_time"
            type="datetime"
            placeholder="不选则立即发布"
            format="YYYY-MM-DD HH:mm:ss"
            value-format="YYYY-MM-DD HH:mm:ss"
            :disabled-date="disableDate"
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="createDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="submitCreate">
          确认发布
        </el-button>
      </template>
    </el-dialog>

    <!-- 任务详情 -->
    <el-dialog
      v-model="detailDialogVisible"
      title="发布任务详情"
      width="700px"
      destroy-on-close
    >
      <div v-if="currentTask" class="task-detail">
        <el-descriptions :column="2" border>
          <el-descriptions-item label="任务ID">{{ currentTask.task_id }}</el-descriptions-item>
          <el-descriptions-item label="内容ID">{{ currentTask.content_task_id }}</el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag :type="getStatusType(currentTask.status)" size="small">
              {{ getStatusLabel(currentTask.status) }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="发布进度">
            成功 {{ currentTask.success_count || 0 }} / 失败 {{ currentTask.failed_count || 0 }} / 共 {{ currentTask.total_count || 0 }}
          </el-descriptions-item>
          <el-descriptions-item label="定时发布">
            {{ currentTask.scheduled_time ? formatTime(currentTask.scheduled_time) : '立即发布' }}
          </el-descriptions-item>
          <el-descriptions-item label="创建时间">{{ formatTime(currentTask.created_at) }}</el-descriptions-item>
        </el-descriptions>

        <div v-if="currentTask.results && currentTask.results.length" class="results-section">
          <h4>发布结果</h4>
          <el-table :data="currentTask.results" stripe size="small">
            <el-table-column prop="platform" label="平台" width="100">
              <template #default="{ row }">
                {{ getPlatformName(row.platform) }}
              </template>
            </el-table-column>
            <el-table-column label="状态" width="100">
              <template #default="{ row }">
                <el-tag :type="row.success ? 'success' : 'danger'" size="small">
                  {{ row.success ? '成功' : '失败' }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="error" label="错误信息" min-width="200">
              <template #default="{ row }">
                {{ row.error || '-' }}
              </template>
            </el-table-column>
            <el-table-column prop="published_at" label="发布时间" width="170">
              <template #default="{ row }">
                {{ row.published_at ? formatTime(row.published_at) : '-' }}
              </template>
            </el-table-column>
          </el-table>
        </div>
      </div>
    </el-dialog>

    <!-- 改时间 -->
    <el-dialog
      v-model="scheduleDialogVisible"
      title="修改定时发布时间"
      width="450px"
      destroy-on-close
    >
      <el-form label-width="100px">
        <el-form-item label="新的时间">
          <el-date-picker
            v-model="newScheduleTime"
            type="datetime"
            placeholder="选择新的发布时间"
            format="YYYY-MM-DD HH:mm:ss"
            value-format="YYYY-MM-DD HH:mm:ss"
            :disabled-date="disableDate"
            style="width: 100%"
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="scheduleDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="submitScheduleUpdate">
          确认修改
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Delete, Refresh } from '@element-plus/icons-vue'
import {
  getPublishTasks,
  createPublishTask,
  getPublishTaskDetail,
  retryPublishTask,
  updateScheduledTask,
  cancelPublishTask
} from '@/api/publish'

const loading = ref(false)
const submitting = ref(false)
const taskList = ref([])
const total = ref(0)

const listQuery = reactive({
  page: 1,
  limit: 20,
  status: '',
  platform: ''
})

const createDialogVisible = ref(false)
const detailDialogVisible = ref(false)
const scheduleDialogVisible = ref(false)
const createFormRef = ref(null)
const currentTask = ref(null)
const editingTaskId = ref(null)
const newScheduleTime = ref('')

const createForm = reactive({
  content_task_id: '',
  platforms: [],
  scheduled_time: ''
})

// PlatformGrid 选中同步到 createForm.platforms（保留用户已填的 account_id / title）
watch(selectedPlatformKeys, (keys) => {
  const prev = new Map(createForm.platforms.map(p => [p.platform, p]))
  createForm.platforms = keys.map(k => {
    if (prev.has(k)) return prev.get(k)
    return { platform: k, account_id: '', config: { title: '' } }
  })
}, { deep: true })

// 前端可选的 7 通道 → 后端只接受 5 种 key(bug 兜底):
// douyin / kuaishou / xiaohongshu / weibo / bilibili
// 视频号 / 朋友圈 当前后端不支持,这里把 XIAOHONGSHU_IMG/VID 折叠到 XIAOHONGSHU;
// WECHAT_* 折叠到 XIAOHONGSHU(占位,后端实装 pyq 后改为 pyq);WEIBO/BILIBILI/WEIBO 等原样保留。
const SERVER_PLATFORM_WHITELIST = new Set([
  'DOUYIN', 'KUAISHOU', 'XIAOHONGSHU', 'WEIBO', 'BILIBILI'
])

// 兼容老数据 / 后端只接受 5 种 key：把小红书图文/视频归一为 XIAOHONGSHU；微信/朋友圈后端暂未支持,归一为 XIAOHONGSHU 占位
const normalizePlatformKeys = (keys) => {
  const set = new Set()
  const dropped = []
  keys.forEach(raw => {
    if (!raw) return
    const k = String(raw).toUpperCase()
    let mapped = k
    if (k === 'XIAOHONGSHU_IMG' || k === 'XIAOHONGSHU_VID') {
      mapped = 'XIAOHONGSHU'
    } else if (k === 'WECHAT_SHIPINHAO' || k === 'WECHAT_FRIEND' || k === 'PYQ') {
      // 后端暂未实装视频号/朋友圈,折叠到 XIAOHONGSHU 防 422
      mapped = 'XIAOHONGSHU'
    }
    if (SERVER_PLATFORM_WHITELIST.has(mapped)) {
      set.add(mapped)
    } else {
      dropped.push(k)
    }
  })
  if (dropped.length) {
    console.warn('[publish] 后端不支持的平台 key 已丢弃:', dropped)
  }
  return Array.from(set)
}

const createRules = {
  content_task_id: [
    { required: true, message: '请输入内容任务ID', trigger: 'blur' }
  ],
  platforms: [
    {
      validator: (rule, value, callback) => {
        if (!value || value.length === 0) {
          callback(new Error('请至少添加一个发布平台'))
        } else if (!value.some(p => p.platform)) {
          callback(new Error('请选择发布平台'))
        } else {
          callback()
        }
      },
      trigger: 'change'
    }
  ]
}

const parsePlatforms = (platforms) => {
  if (!platforms) return []
  if (typeof platforms === 'string') {
    try {
      platforms = JSON.parse(platforms)
    } catch {
      return []
    }
  }
  return platforms.map(p => typeof p === 'object' ? p.platform : p)
}

const getPlatformName = (key) => {
  const map = {
    DOUYIN: '抖音',
    KUAISHOU: '快手',
    XIAOHONGSHU: '小红书',
    WEIBO: '微博',
    BILIBILI: 'B站',
    douyin: '抖音',
    kuaishou: '快手',
    xiaohongshu: '小红书',
    weibo: '微博',
    bilibili: 'B站'
  }
  return map[key] || key
}

const getPlatformTagType = (key) => {
  const map = {
    DOUYIN: 'danger',
    KUAISHOU: 'warning',
    XIAOHONGSHU: '',
    WEIBO: 'info',
    BILIBILI: 'success',
    douyin: 'danger',
    kuaishou: 'warning',
    xiaohongshu: '',
    weibo: 'info',
    bilibili: 'success'
  }
  return map[key] || 'info'
}

const getStatusType = (status) => {
  const map = {
    PENDING: 'warning',
    PROCESSING: '',
    SUCCESS: 'success',
    FAILED: 'danger',
    PARTIAL_SUCCESS: 'warning',
    CANCELLED: 'info'
  }
  return map[status] || 'info'
}

const getStatusLabel = (status) => {
  const map = {
    PENDING: '待发布',
    PROCESSING: '发布中',
    SUCCESS: '已发布',
    FAILED: '失败',
    PARTIAL_SUCCESS: '部分成功',
    CANCELLED: '已取消'
  }
  return map[status] || status
}

const canRetry = (status) => ['FAILED', 'PARTIAL_SUCCESS'].includes(status)
const canCancel = (status) => ['PENDING', 'PROCESSING'].includes(status)

const formatTime = (time) => {
  if (!time) return '-'
  const d = new Date(time)
  if (isNaN(d.getTime())) return time
  return d.toLocaleString('zh-CN', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  })
}

const disableDate = (date) => date.getTime() < Date.now() - 86400000

const getList = async () => {
  loading.value = true
  try {
    const params = { ...listQuery }
    Object.keys(params).forEach(key => {
      if (params[key] === '' || params[key] === null || params[key] === undefined) {
        delete params[key]
      }
    })
    const res = await getPublishTasks(params)
    if (res) {
      if (res.list) {
        taskList.value = res.list
        total.value = res.total || 0
      } else if (Array.isArray(res)) {
        taskList.value = res
        total.value = res.length
      }
    }
  } catch {
    ElMessage.error('获取发布任务列表失败')
  } finally {
    loading.value = false
  }
}

const handleFilterChange = () => {
  listQuery.page = 1
  getList()
}

const handleSizeChange = (size) => {
  listQuery.limit = size
  listQuery.page = 1
  getList()
}

const handlePageChange = (page) => {
  listQuery.page = page
  getList()
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

const handleCreate = () => {
  createForm.content_task_id = ''
  createForm.platforms = []
  selectedPlatformKeys.value = []
  createForm.scheduled_time = ''
  createDialogVisible.value = true
}

const addPlatformConfig = () => {
  // 保留方法作为向后兼容；现在主要通过 PlatformGrid 选
  createForm.platforms.push({ platform: '', account_id: '', config: { title: '' } })
}

const removePlatformConfig = (index) => {
  // 同时同步 PlatformGrid 的选中态
  const removed = createForm.platforms[index]
  if (removed?.platform) {
    selectedPlatformKeys.value = selectedPlatformKeys.value.filter(k => k !== removed.platform)
  }
  createForm.platforms.splice(index, 1)
}

const submitCreate = async () => {
  // bug B9 兜底: 防止连点重复提交
  if (submitting.value) return
  try {
    await createFormRef.value.validate()
  } catch {
    return
  }
  // 归一化：小红书图文/视频 → XIAOHONGSHU；视频号暂归一为 XIAOHONGSHU 防后端 422
  const keys = normalizePlatformKeys(selectedPlatformKeys.value)
  if (keys.length === 0) {
    ElMessage.warning('请至少选择一个发布平台')
    return
  }
  submitting.value = true
  try {
    // 保留用户为每个通道填的 account_id / title
    const lookup = new Map(createForm.platforms.map(p => [p.platform, p]))
    const data = {
      content_task_id: createForm.content_task_id,
      platforms: keys.map(k => {
        const p = lookup.get(k) || {}
        return {
          platform: k,
          ...(p.account_id ? { account_id: Number(p.account_id) } : {}),
          ...(p.config && p.config.title ? { config: { title: p.config.title } } : {})
        }
      })
    }
    if (createForm.scheduled_time) {
      data.scheduled_time = createForm.scheduled_time
    }
    await createPublishTask(data)
    ElMessage.success('发布任务创建成功')
    createDialogVisible.value = false
    getList()
  } catch (e) {
    ElMessage.error(e.message || '创建发布任务失败')
  } finally {
    submitting.value = false
  }
}

const handleViewDetail = async (row) => {
  try {
    const res = await getPublishTaskDetail(row.id)
    if (res) {
      currentTask.value = res
      detailDialogVisible.value = true
    }
  } catch {
    ElMessage.error('获取任务详情失败')
  }
}

const handleRetry = async (row) => {
  try {
    await ElMessageBox.confirm('确定要重试此发布任务吗？', '重试确认', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
    await retryPublishTask(row.id)
    ElMessage.success('任务已重新提交')
    getList()
  } catch (e) {
    if (e !== 'cancel') {
      ElMessage.error(e.message || '重试失败')
    }
  }
}

const handleCancel = async (row) => {
  try {
    await ElMessageBox.confirm('确定要取消此发布任务吗？', '取消确认', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
    await cancelPublishTask(row.id)
    ElMessage.success('任务已取消')
    getList()
  } catch (e) {
    if (e !== 'cancel') {
      ElMessage.error(e.message || '取消失败')
    }
  }
}

const handleEditSchedule = (row) => {
  editingTaskId.value = row.id
  newScheduleTime.value = row.scheduled_time || ''
  scheduleDialogVisible.value = true
}

const submitScheduleUpdate = async () => {
  if (!newScheduleTime.value) {
    ElMessage.warning('请选择新的发布时间')
    return
  }
  submitting.value = true
  try {
    await updateScheduledTask(editingTaskId.value, { scheduled_time: newScheduleTime.value })
    ElMessage.success('定时发布时间已更新')
    scheduleDialogVisible.value = false
    getList()
  } catch (e) {
    ElMessage.error(e.message || '修改失败')
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  getList()
})
</script>

<style lang="scss" scoped>
.publish-manage {
  padding: 20px;

  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 24px;

    .header-left {
      h2 {
        font-size: 24px;
        font-weight: 700;
        margin: 0 0 8px 0;
        background: linear-gradient(135deg, #FF6B35 0%, #FF8E53 100%);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
      }

      .subtitle {
        font-size: 14px;
        color: #909399;
        margin: 0;
      }
    }

    :deep(.el-button--primary) {
      background: linear-gradient(135deg, #FF6B35, #FF8E53);
      border-color: #FF6B35;
    }
  }

  .filter-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding: 16px 20px;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
  }

  .card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 16px;
    min-height: 200px;

    .empty { grid-column: 1 / -1; }
  }

  .pub-card {
    background: #fff;
    border-radius: 16px;
    padding: 16px 18px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    border-left: 4px solid #FF6B35;
    transition: all 0.2s;

    &:hover {
      box-shadow: 0 4px 16px rgba(255, 107, 53, 0.15);
      transform: translateY(-2px);
    }

    &.status-failed { border-left-color: #f56c6c; }
    &.status-success { border-left-color: #67c23a; }
    &.status-pending { border-left-color: #e6a23c; }
    &.status-processing { border-left-color: #409eff; }

    .card-head {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 12px;

      .task-id {
        font-size: 13px;
        color: #909399;
        font-family: 'JetBrains Mono', monospace;
      }
    }

    .platform-tags {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      margin-bottom: 14px;
    }

    .pf-chip {
      padding: 3px 10px;
      border-radius: 10px;
      font-size: 12px;
      background: rgba(255, 107, 53, 0.1);
      color: #FF6B35;
      font-weight: 500;

      &.pf-douyin { background: rgba(245, 108, 108, 0.1); color: #f56c6c; }
      &.pf-kuaishou { background: rgba(230, 162, 60, 0.1); color: #e6a23c; }
      &.pf-xiaohongshu { background: rgba(255, 107, 53, 0.1); color: #FF6B35; }
      &.pf-weibo { background: rgba(64, 158, 255, 0.1); color: #409eff; }
      &.pf-bilibili { background: rgba(103, 194, 58, 0.1); color: #67c23a; }
    }

    .meta-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 8px 16px;
      padding: 12px;
      background: #fafafa;
      border-radius: 8px;
      margin-bottom: 12px;

      .meta-cell {
        .cell-label {
          font-size: 11px;
          color: #c0c4cc;
          display: block;
          margin-bottom: 2px;
        }
        .cell-value {
          font-size: 13px;
          color: #303133;
          font-weight: 500;
          .ok { color: #67c23a; font-weight: 600; }
          .err { color: #f56c6c; font-weight: 600; }
        }
        &.full { grid-column: span 2; }
      }
    }

    .card-actions {
      display: flex;
      gap: 4px;
      border-top: 1px dashed #f0f0f0;
      padding-top: 8px;
    }
  }

  .pagination {
    margin-top: 24px;
    display: flex;
    justify-content: center;
  }

  .platform-config-list {
    width: 100%;

    .platform-config-item {
      margin-bottom: 8px;
    }
  }

  .task-detail {
    .results-section {
      margin-top: 20px;

      h4 {
        font-size: 14px;
        font-weight: 600;
        margin: 0 0 12px 0;
        color: #303133;
      }
    }
  }
}
</style>
