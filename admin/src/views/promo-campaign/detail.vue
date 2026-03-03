<template>
  <div class="campaign-detail">
    <!-- 页面头部 -->
    <div class="page-header">
      <div class="header-left">
        <el-button text @click="router.back()">
          <el-icon><ArrowLeft /></el-icon>
          返回
        </el-button>
        <h2>{{ campaignInfo?.name || '活动详情' }}</h2>
        <el-tag :type="getStatusType(campaignInfo?.status)" size="large">
          {{ getStatusLabel(campaignInfo?.status) }}
        </el-tag>
      </div>
      <div class="header-right">
        <el-button @click="handleEdit">
          <el-icon><Edit /></el-icon>
          编辑活动
        </el-button>
        <el-button @click="handleBindDevice">
          <el-icon><Monitor /></el-icon>
          绑定设备
        </el-button>
      </div>
    </div>

    <!-- 加载状态 -->
    <div v-if="loading" class="loading-container">
      <el-skeleton :rows="10" animated />
    </div>

    <template v-else-if="campaignInfo">
      <!-- 统计数据卡片 -->
      <div class="stats-section">
        <div class="section-title">活动数据</div>
        <div class="stats-grid">
          <div class="stat-card">
            <div class="stat-icon trigger">
              <el-icon><TrendCharts /></el-icon>
            </div>
            <div class="stat-content">
              <div class="stat-value">{{ stats.trigger_count || 0 }}</div>
              <div class="stat-label">触发次数</div>
            </div>
          </div>
          <div class="stat-card">
            <div class="stat-icon download">
              <el-icon><Download /></el-icon>
            </div>
            <div class="stat-content">
              <div class="stat-value">{{ stats.download_count || 0 }}</div>
              <div class="stat-label">下载次数</div>
            </div>
          </div>
          <div class="stat-card">
            <div class="stat-icon publish">
              <el-icon><Upload /></el-icon>
            </div>
            <div class="stat-content">
              <div class="stat-value">{{ stats.publish_count || 0 }}</div>
              <div class="stat-label">发布次数</div>
            </div>
          </div>
          <div class="stat-card">
            <div class="stat-icon reward">
              <el-icon><Present /></el-icon>
            </div>
            <div class="stat-content">
              <div class="stat-value">{{ stats.reward_count || 0 }}</div>
              <div class="stat-label">奖励发放</div>
            </div>
          </div>
        </div>
      </div>

      <!-- 基本信息 -->
      <div class="info-section">
        <div class="section-title">基本信息</div>
        <div class="info-card">
          <el-descriptions :column="2" border>
            <el-descriptions-item label="活动名称">
              {{ campaignInfo.name }}
            </el-descriptions-item>
            <el-descriptions-item label="活动状态">
              <el-tag :type="getStatusType(campaignInfo.status)" size="small">
                {{ getStatusLabel(campaignInfo.status) }}
              </el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="活动时间" :span="2">
              {{ formatDate(campaignInfo.start_time) }} 至 {{ formatDate(campaignInfo.end_time) }}
            </el-descriptions-item>
            <el-descriptions-item label="目标平台" :span="2">
              <el-tag
                v-for="platform in campaignInfo.platforms"
                :key="platform"
                :type="platform === 'douyin' ? 'danger' : 'warning'"
                size="small"
                style="margin-right: 8px"
              >
                {{ platform === 'douyin' ? '抖音' : '快手' }}
              </el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="活动描述" :span="2">
              {{ campaignInfo.description || '暂无描述' }}
            </el-descriptions-item>
            <el-descriptions-item label="推广文案" :span="2">
              <div class="promo-text">{{ campaignInfo.promo_text || '暂无推广文案' }}</div>
            </el-descriptions-item>
            <el-descriptions-item label="话题标签" :span="2">
              <el-tag
                v-for="tag in campaignInfo.tags"
                :key="tag"
                type="primary"
                size="small"
                style="margin-right: 8px"
              >
                {{ tag }}
              </el-tag>
              <span v-if="!campaignInfo.tags?.length">暂无标签</span>
            </el-descriptions-item>
            <el-descriptions-item label="关联优惠券">
              {{ campaignInfo.coupon_name || '无' }}
            </el-descriptions-item>
            <el-descriptions-item label="创建时间">
              {{ formatDate(campaignInfo.create_time) }}
            </el-descriptions-item>
          </el-descriptions>
        </div>
      </div>

      <!-- 关联变体 -->
      <div class="variants-section">
        <div class="section-header">
          <span class="section-title">关联变体 ({{ variants.length }})</span>
        </div>
        <div class="variants-grid">
          <div
            v-for="variant in variants"
            :key="variant.id"
            class="variant-item"
          >
            <div class="variant-preview">
              <el-image
                v-if="variant.thumbnail_url"
                :src="variant.thumbnail_url"
                fit="cover"
                class="preview-thumb"
              >
                <template #error>
                  <div class="thumb-error">
                    <el-icon><VideoPlay /></el-icon>
                  </div>
                </template>
              </el-image>
              <div v-else class="thumb-placeholder">
                <el-icon><VideoPlay /></el-icon>
              </div>
              <div class="variant-duration">{{ formatDuration(variant.duration) }}</div>
            </div>
            <div class="variant-info">
              <div class="variant-name">{{ variant.template_name || '变体' }}</div>
              <div class="variant-meta">
                <span>{{ formatFileSize(variant.file_size) }}</span>
                <span>使用 {{ variant.use_count || 0 }} 次</span>
              </div>
            </div>
          </div>

          <el-empty v-if="variants.length === 0" description="暂无关联变体" :image-size="60" />
        </div>
      </div>

      <!-- 绑定设备 -->
      <div class="devices-section">
        <div class="section-header">
          <span class="section-title">绑定设备 ({{ devices.length }})</span>
          <el-button type="primary" size="small" @click="handleBindDevice">
            添加设备
          </el-button>
        </div>
        <div class="devices-table">
          <el-table :data="devices" border stripe max-height="300">
            <el-table-column label="设备名称" prop="name" min-width="120" show-overflow-tooltip>
              <template #default="{ row }">
                {{ row.name || row.sn }}
              </template>
            </el-table-column>
            <el-table-column label="SN码" prop="sn" width="140" />
            <el-table-column label="状态" width="80" align="center">
              <template #default="{ row }">
                <el-tag :type="row.status === 'online' ? 'success' : 'info'" size="small">
                  {{ row.status === 'online' ? '在线' : '离线' }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="位置" prop="location" min-width="120" show-overflow-tooltip>
              <template #default="{ row }">
                {{ row.location || '-' }}
              </template>
            </el-table-column>
            <el-table-column label="触发次数" width="100" align="center">
              <template #default="{ row }">
                {{ row.trigger_count || 0 }}
              </template>
            </el-table-column>
          </el-table>

          <el-empty v-if="devices.length === 0" description="暂无绑定设备" :image-size="60" />
        </div>
      </div>

      <!-- 分发记录 -->
      <div class="distributions-section">
        <div class="section-header">
          <span class="section-title">分发记录</span>
          <el-button size="small" @click="loadDistributions">
            <el-icon><Refresh /></el-icon>
            刷新
          </el-button>
        </div>
        <div class="distributions-table">
          <el-table v-loading="distributionLoading" :data="distributions" border stripe max-height="300">
            <el-table-column label="设备" prop="device_name" min-width="120" show-overflow-tooltip />
            <el-table-column label="变体" prop="variant_name" min-width="120" show-overflow-tooltip />
            <el-table-column label="分发时间" width="180" align="center">
              <template #default="{ row }">
                {{ formatDate(row.create_time) }}
              </template>
            </el-table-column>
            <el-table-column label="状态" width="100" align="center">
              <template #default="{ row }">
                <el-tag :type="row.status === 'success' ? 'success' : 'danger'" size="small">
                  {{ row.status === 'success' ? '成功' : '失败' }}
                </el-tag>
              </template>
            </el-table-column>
          </el-table>

          <el-empty v-if="!distributionLoading && distributions.length === 0" description="暂无分发记录" :image-size="60" />
        </div>
      </div>
    </template>

    <!-- 编辑活动对话框 -->
    <el-dialog
      v-model="editDialogVisible"
      title="编辑活动"
      width="700px"
      destroy-on-close
      :close-on-click-modal="false"
    >
      <CampaignEditor
        ref="editorRef"
        :is-edit="true"
        :campaign-data="campaignInfo"
        @success="handleEditorSuccess"
        @cancel="editDialogVisible = false"
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
        v-if="campaignInfo"
        :campaign-id="campaignInfo.id"
        :initial-devices="devices"
        @update="handleDeviceUpdate"
      />
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import {
  ArrowLeft,
  Edit,
  Monitor,
  TrendCharts,
  Download,
  Upload,
  Present,
  VideoPlay,
  Refresh
} from '@element-plus/icons-vue'
import {
  getCampaignDetail,
  getCampaignStats,
  getCampaignDistributions,
  updateCampaign
} from '@/api/promo-campaign'
import CampaignEditor from '@/components/CampaignEditor.vue'
import DeviceBinder from '@/components/DeviceBinder.vue'

const route = useRoute()
const router = useRouter()

// 状态
const loading = ref(true)
const campaignInfo = ref(null)
const stats = ref({})
const variants = ref([])
const devices = ref([])
const distributions = ref([])
const distributionLoading = ref(false)

// 对话框
const editDialogVisible = ref(false)
const deviceDialogVisible = ref(false)
const editorRef = ref(null)

// 获取活动详情
const loadCampaignDetail = async () => {
  loading.value = true
  try {
    const id = route.params.id
    const response = await getCampaignDetail(id)
    if (response) {
      campaignInfo.value = response
      variants.value = response.variants || []
      devices.value = response.devices || []
    }
  } catch (error) {
    console.error('获取活动详情失败:', error)
    ElMessage.error('获取活动详情失败')
  } finally {
    loading.value = false
  }
}

// 获取统计数据
const loadStats = async () => {
  try {
    const id = route.params.id
    const response = await getCampaignStats(id)
    if (response) {
      stats.value = response
    }
  } catch (error) {
    console.error('获取统计数据失败:', error)
  }
}

// 获取分发记录
const loadDistributions = async () => {
  distributionLoading.value = true
  try {
    const id = route.params.id
    const response = await getCampaignDistributions(id, { limit: 50 })
    if (response) {
      if (response.list) {
        distributions.value = response.list
      } else if (response.data) {
        distributions.value = Array.isArray(response.data) ? response.data : []
      } else if (Array.isArray(response)) {
        distributions.value = response
      }
    }
  } catch (error) {
    console.error('获取分发记录失败:', error)
  } finally {
    distributionLoading.value = false
  }
}

// 编辑活动
const handleEdit = () => {
  editDialogVisible.value = true
}

// 绑定设备
const handleBindDevice = () => {
  deviceDialogVisible.value = true
}

// 编辑器提交成功
const handleEditorSuccess = async (data) => {
  try {
    await updateCampaign(campaignInfo.value.id, data)
    ElMessage.success('活动更新成功')
    editDialogVisible.value = false
    loadCampaignDetail()
  } catch (error) {
    console.error('更新活动失败:', error)
    ElMessage.error('更新活动失败')
  }
}

// 设备更新
const handleDeviceUpdate = (updatedDevices) => {
  devices.value = updatedDevices
  loadCampaignDetail()
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

// 格式化时长
const formatDuration = (seconds) => {
  if (!seconds) return '-'
  const mins = Math.floor(seconds / 60)
  const secs = Math.floor(seconds % 60)
  return mins > 0 ? `${mins}:${secs.toString().padStart(2, '0')}` : `0:${secs.toString().padStart(2, '0')}`
}

// 格式化文件大小
const formatFileSize = (bytes) => {
  if (!bytes) return '-'
  const units = ['B', 'KB', 'MB', 'GB']
  let size = bytes
  let unitIndex = 0
  while (size >= 1024 && unitIndex < units.length - 1) {
    size /= 1024
    unitIndex++
  }
  return `${size.toFixed(1)} ${units[unitIndex]}`
}

// 初始化
onMounted(() => {
  loadCampaignDetail()
  loadStats()
  loadDistributions()
})
</script>

<style scoped lang="scss">
.campaign-detail {
  padding: 20px;

  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;

    .header-left {
      display: flex;
      align-items: center;
      gap: 16px;

      h2 {
        font-size: 24px;
        font-weight: 600;
        margin: 0;
        color: #303133;
      }
    }

    .header-right {
      display: flex;
      gap: 12px;
    }
  }

  .loading-container {
    padding: 40px;
    background: #fff;
    border-radius: 8px;
  }

  .section-title {
    font-size: 16px;
    font-weight: 600;
    color: #303133;
    margin-bottom: 16px;
  }

  .section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
  }

  // 统计数据
  .stats-section {
    margin-bottom: 24px;

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 16px;

      .stat-card {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 20px;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);

        .stat-icon {
          width: 48px;
          height: 48px;
          border-radius: 12px;
          display: flex;
          align-items: center;
          justify-content: center;
          font-size: 24px;

          &.trigger {
            background: rgba(64, 158, 255, 0.1);
            color: #409eff;
          }

          &.download {
            background: rgba(103, 194, 58, 0.1);
            color: #67c23a;
          }

          &.publish {
            background: rgba(230, 162, 60, 0.1);
            color: #e6a23c;
          }

          &.reward {
            background: rgba(245, 108, 108, 0.1);
            color: #f56c6c;
          }
        }

        .stat-content {
          flex: 1;

          .stat-value {
            font-size: 28px;
            font-weight: 600;
            color: #303133;
            line-height: 1.2;
          }

          .stat-label {
            font-size: 13px;
            color: #909399;
            margin-top: 4px;
          }
        }
      }
    }
  }

  // 基本信息
  .info-section {
    margin-bottom: 24px;

    .info-card {
      background: #fff;
      border-radius: 8px;
      padding: 20px;

      .promo-text {
        white-space: pre-wrap;
        word-break: break-all;
      }
    }
  }

  // 关联变体
  .variants-section {
    margin-bottom: 24px;

    .variants-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
      gap: 16px;
      background: #fff;
      border-radius: 8px;
      padding: 20px;

      .variant-item {
        border: 1px solid #e8e8e8;
        border-radius: 8px;
        overflow: hidden;
        transition: all 0.3s;

        &:hover {
          box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .variant-preview {
          position: relative;
          aspect-ratio: 16/9;
          background: #f5f7fa;

          .preview-thumb {
            width: 100%;
            height: 100%;
          }

          .thumb-placeholder,
          .thumb-error {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            font-size: 32px;
          }

          .thumb-error {
            background: #e9ecef;
            color: #adb5bd;
          }

          .variant-duration {
            position: absolute;
            bottom: 8px;
            right: 8px;
            padding: 2px 8px;
            background: rgba(0, 0, 0, 0.6);
            color: #fff;
            font-size: 12px;
            border-radius: 4px;
          }
        }

        .variant-info {
          padding: 12px;

          .variant-name {
            font-size: 14px;
            font-weight: 500;
            color: #303133;
            margin-bottom: 4px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
          }

          .variant-meta {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #909399;
          }
        }
      }
    }
  }

  // 绑定设备
  .devices-section {
    margin-bottom: 24px;

    .devices-table {
      background: #fff;
      border-radius: 8px;
      padding: 20px;
    }
  }

  // 分发记录
  .distributions-section {
    .distributions-table {
      background: #fff;
      border-radius: 8px;
      padding: 20px;
    }
  }
}

@media (max-width: 1200px) {
  .campaign-detail .stats-section .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 768px) {
  .campaign-detail .stats-section .stats-grid {
    grid-template-columns: 1fr;
  }
}
</style>
