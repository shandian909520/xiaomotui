<template>
  <div class="promo-campaign">
    <!-- 页面头部 -->
    <div class="page-header">
      <div class="header-left">
        <h2>推广活动</h2>
        <p class="subtitle">创建和管理推广活动，绑定设备进行内容分发</p>
      </div>
      <div class="header-right">
        <el-button type="primary" icon="Plus" @click="handleCreate">
          创建活动
        </el-button>
      </div>
    </div>

    <!-- 筛选栏 -->
    <div class="filter-bar">
      <div class="filter-tabs">
        <el-radio-group v-model="listQuery.status" @change="handleFilterChange">
          <el-radio-button label="">全部</el-radio-button>
          <el-radio-button label="active">进行中</el-radio-button>
          <el-radio-button label="ended">已结束</el-radio-button>
        </el-radio-group>
      </div>
      <div class="filter-right">
        <el-button @click="getList">
          <el-icon><Refresh /></el-icon>
          刷新
        </el-button>
      </div>
    </div>

    <!-- 活动列表 - 卡片视图 -->
    <div v-loading="loading" class="campaign-grid">
      <div
        v-for="item in campaignList"
        :key="item.id"
        class="campaign-card"
      >
        <div class="card-header">
          <div class="card-title" :title="item.name">{{ item.name }}</div>
          <el-tag :type="getStatusType(item.status)" size="small">
            {{ getStatusLabel(item.status) }}
          </el-tag>
        </div>

        <div class="card-body">
          <div class="card-desc" :title="item.description">
            {{ item.description || '暂无描述' }}
          </div>

          <div class="card-stats">
            <div class="stat-item">
              <el-icon><VideoPlay /></el-icon>
              <span class="stat-value">{{ item.variant_count || 0 }}</span>
              <span class="stat-label">变体</span>
            </div>
            <div class="stat-item">
              <el-icon><Monitor /></el-icon>
              <span class="stat-value">{{ item.device_count || 0 }}</span>
              <span class="stat-label">设备</span>
            </div>
            <div class="stat-item">
              <el-icon><TrendCharts /></el-icon>
              <span class="stat-value">{{ item.trigger_count || 0 }}</span>
              <span class="stat-label">触发</span>
            </div>
          </div>

          <div class="card-time">
            <div class="time-item">
              <el-icon><Clock /></el-icon>
              <span>{{ formatDate(item.start_time) }}</span>
            </div>
            <div class="time-separator">至</div>
            <div class="time-item">
              <span>{{ formatDate(item.end_time) }}</span>
            </div>
          </div>

          <div class="card-platforms">
            <el-tag
              v-for="platform in item.platforms"
              :key="platform"
              :type="platform === 'douyin' ? 'danger' : 'warning'"
              size="small"
            >
              {{ platform === 'douyin' ? '抖音' : '快手' }}
            </el-tag>
          </div>
        </div>

        <div class="card-actions">
          <el-button type="primary" size="small" @click="handleViewDetail(item)">
            <el-icon><View /></el-icon>
            详情
          </el-button>
          <el-button size="small" @click="handleEdit(item)">
            <el-icon><Edit /></el-icon>
            编辑
          </el-button>
          <el-button size="small" @click="handleBindDevice(item)">
            <el-icon><Monitor /></el-icon>
            设备
          </el-button>
          <el-button type="danger" size="small" @click="handleDelete(item)">
            <el-icon><Delete /></el-icon>
          </el-button>
        </div>
      </div>

      <!-- 空状态 -->
      <div v-if="!loading && campaignList.length === 0" class="empty-state">
        <el-empty description="暂无推广活动">
          <el-button type="primary" @click="handleCreate">创建活动</el-button>
        </el-empty>
      </div>
    </div>

    <!-- 分页 -->
    <div v-if="total > 0" class="pagination">
      <el-pagination
        v-model:current-page="listQuery.page"
        v-model:page-size="listQuery.limit"
        :total="total"
        :page-sizes="[12, 24, 48]"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="handleSizeChange"
        @current-change="handlePageChange"
      />
    </div>

    <!-- 创建/编辑活动对话框 -->
    <el-dialog
      v-model="dialogVisible"
      :title="dialogType === 'create' ? '创建活动' : '编辑活动'"
      width="700px"
      destroy-on-close
      :close-on-click-modal="false"
    >
      <CampaignEditor
        ref="editorRef"
        :is-edit="dialogType === 'edit'"
        :campaign-data="currentCampaign"
        @success="handleEditorSuccess"
        @cancel="dialogVisible = false"
      />
    </el-dialog>

    <!-- 设备绑定对话框 -->
    <el-dialog
      v-model="deviceDialogVisible"
      title="设备绑定"
      width="600px"
      destroy-on-close
    >
      <DeviceBinder
        v-if="currentCampaign"
        :campaign-id="currentCampaign.id"
        :initial-devices="currentCampaign.devices || []"
        @update="handleDeviceUpdate"
        @error="handleDeviceError"
      />
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  Plus,
  Refresh,
  View,
  Edit,
  Delete,
  Monitor,
  VideoPlay,
  TrendCharts,
  Clock
} from '@element-plus/icons-vue'
import {
  getCampaignList,
  getCampaignDetail,
  createCampaign,
  updateCampaign,
  deleteCampaign
} from '@/api/promo-campaign'
import CampaignEditor from '@/components/CampaignEditor.vue'
import DeviceBinder from '@/components/DeviceBinder.vue'

const router = useRouter()

// 数据状态
const loading = ref(false)
const campaignList = ref([])
const total = ref(0)

// 查询参数
const listQuery = reactive({
  page: 1,
  limit: 12,
  status: ''
})

// 对话框
const dialogVisible = ref(false)
const dialogType = ref('create') // create | edit
const editorRef = ref(null)
const currentCampaign = ref(null)

// 设备绑定对话框
const deviceDialogVisible = ref(false)

// 获取活动列表
const getList = async () => {
  loading.value = true
  try {
    const params = { ...listQuery }
    Object.keys(params).forEach(key => {
      if (params[key] === '' || params[key] === null || params[key] === undefined) {
        delete params[key]
      }
    })

    const response = await getCampaignList(params)
    if (response) {
      if (response.list) {
        campaignList.value = response.list
        total.value = response.pagination?.total || 0
      } else if (response.data) {
        campaignList.value = Array.isArray(response.data) ? response.data : []
        total.value = response.total || campaignList.value.length
      } else if (Array.isArray(response)) {
        campaignList.value = response
        total.value = response.length
      }
    }
  } catch (error) {
    console.error('获取活动列表失败:', error)
    ElMessage.error('获取活动列表失败')
  } finally {
    loading.value = false
  }
}

// 筛选变化
const handleFilterChange = () => {
  listQuery.page = 1
  getList()
}

// 分页
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

// 创建活动
const handleCreate = () => {
  dialogType.value = 'create'
  currentCampaign.value = null
  dialogVisible.value = true
}

// 编辑活动
const handleEdit = async (item) => {
  dialogType.value = 'edit'

  try {
    const detail = await getCampaignDetail(item.id)
    if (detail) {
      currentCampaign.value = {
        ...detail,
        date_range: [detail.start_time, detail.end_time]
      }
      dialogVisible.value = true
    }
  } catch (error) {
    console.error('获取活动详情失败:', error)
    ElMessage.error('获取活动详情失败')
  }
}

// 查看详情
const handleViewDetail = (item) => {
  router.push(`/promo/campaign/detail/${item.id}`)
}

// 绑定设备
const handleBindDevice = async (item) => {
  try {
    const detail = await getCampaignDetail(item.id)
    if (detail) {
      currentCampaign.value = detail
      deviceDialogVisible.value = true
    }
  } catch (error) {
    console.error('获取活动详情失败:', error)
    ElMessage.error('获取活动详情失败')
  }
}

// 删除活动
const handleDelete = async (item) => {
  try {
    await ElMessageBox.confirm(
      `确定要删除活动"${item.name}"吗？删除后无法恢复。`,
      '删除确认',
      {
        confirmButtonText: '确定删除',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )

    await deleteCampaign(item.id)
    ElMessage.success('删除成功')
    getList()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除活动失败:', error)
      ElMessage.error('删除失败')
    }
  }
}

// 编辑器提交成功
const handleEditorSuccess = async (data) => {
  try {
    if (dialogType.value === 'create') {
      await createCampaign(data)
      ElMessage.success('活动创建成功')
    } else {
      await updateCampaign(currentCampaign.value.id, data)
      ElMessage.success('活动更新成功')
    }
    dialogVisible.value = false
    getList()
  } catch (error) {
    console.error('操作失败:', error)
    ElMessage.error('操作失败')
  }
}

// 设备更新
const handleDeviceUpdate = (devices) => {
  if (currentCampaign.value) {
    currentCampaign.value.devices = devices
    currentCampaign.value.device_count = devices.length
  }
  getList()
}

// 设备错误
const handleDeviceError = (error) => {
  console.error('设备操作错误:', error)
}

// 获取状态类型
const getStatusType = (status) => {
  const types = {
    active: 'success',
    pending: 'warning',
    ended: 'info',
    paused: 'danger'
  }
  return types[status] || 'info'
}

// 获取状态标签
const getStatusLabel = (status) => {
  const labels = {
    active: '进行中',
    pending: '待开始',
    ended: '已结束',
    paused: '已暂停'
  }
  return labels[status] || status
}

// 格式化日期
const formatDate = (dateStr) => {
  if (!dateStr) return '-'
  const date = new Date(dateStr)
  return date.toLocaleString('zh-CN', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit'
  })
}

// 初始化
onMounted(() => {
  getList()
})
</script>

<style scoped lang="scss">
.promo-campaign {
  padding: 20px;

  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 24px;

    .header-left {
      h2 {
        font-size: 24px;
        font-weight: 600;
        margin: 0 0 8px 0;
        color: #303133;
      }

      .subtitle {
        font-size: 14px;
        color: #909399;
        margin: 0;
      }
    }
  }

  .filter-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding: 16px 20px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  }

  .campaign-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
    min-height: 300px;

    .campaign-card {
      background: #fff;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
      transition: all 0.3s;

      &:hover {
        box-shadow: 0 4px 20px rgba(99, 102, 241, 0.15);
        transform: translateY(-4px);
      }

      .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px;
        border-bottom: 1px solid #f0f0f0;

        .card-title {
          font-size: 16px;
          font-weight: 600;
          color: #303133;
          overflow: hidden;
          text-overflow: ellipsis;
          white-space: nowrap;
          flex: 1;
          margin-right: 12px;
        }
      }

      .card-body {
        padding: 16px;

        .card-desc {
          font-size: 13px;
          color: #606266;
          line-height: 1.6;
          margin-bottom: 16px;
          overflow: hidden;
          text-overflow: ellipsis;
          display: -webkit-box;
          -webkit-line-clamp: 2;
          -webkit-box-orient: vertical;
          min-height: 42px;
        }

        .card-stats {
          display: flex;
          justify-content: space-around;
          padding: 12px 0;
          margin-bottom: 16px;
          background: #f5f7fa;
          border-radius: 8px;

          .stat-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;

            .el-icon {
              font-size: 20px;
              color: #409eff;
            }

            .stat-value {
              font-size: 18px;
              font-weight: 600;
              color: #303133;
            }

            .stat-label {
              font-size: 12px;
              color: #909399;
            }
          }
        }

        .card-time {
          display: flex;
          align-items: center;
          gap: 8px;
          margin-bottom: 12px;
          font-size: 12px;
          color: #909399;

          .time-item {
            display: flex;
            align-items: center;
            gap: 4px;
          }

          .time-separator {
            color: #c0c4cc;
          }
        }

        .card-platforms {
          display: flex;
          gap: 8px;
        }
      }

      .card-actions {
        display: flex;
        gap: 8px;
        padding: 0 16px 16px;
      }
    }

    .empty-state {
      grid-column: 1 / -1;
      padding: 60px 0;
    }
  }

  .pagination {
    margin-top: 24px;
    display: flex;
    justify-content: center;
  }
}
</style>
