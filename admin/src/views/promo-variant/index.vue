<template>
  <div class="promo-variant">
    <!-- 页面头部 -->
    <div class="page-header">
      <div class="header-left">
        <h2>视频变体</h2>
        <p class="subtitle">管理基于模板生成的视频变体</p>
      </div>
      <div class="header-right">
        <el-button
          v-if="selectedIds.length > 0"
          type="danger"
          icon="Delete"
          @click="handleBatchDelete"
        >
          批量删除 ({{ selectedIds.length }})
        </el-button>
      </div>
    </div>

    <!-- 筛选栏 -->
    <div class="filter-bar">
      <div class="filter-left">
        <el-select
          v-model="listQuery.template_id"
          placeholder="按模板筛选"
          clearable
          @change="handleFilterChange"
        >
          <el-option
            v-for="item in templateOptions"
            :key="item.id"
            :label="item.name"
            :value="item.id"
          />
        </el-select>
      </div>
      <div class="filter-right">
        <el-button @click="getList">
          <el-icon><Refresh /></el-icon>
          刷新
        </el-button>
      </div>
    </div>

    <!-- 变体列表 -->
    <div v-loading="loading" class="variant-list">
      <el-table
        ref="tableRef"
        :data="variantList"
        border
        stripe
        @selection-change="handleSelectionChange"
      >
        <el-table-column type="selection" width="55" align="center" />
        <el-table-column label="预览" width="120" align="center">
          <template #default="{ row }">
            <div class="variant-preview" @click="handlePreview(row)">
              <el-image
                v-if="row.thumbnail_url"
                :src="row.thumbnail_url"
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
              <div class="play-overlay">
                <el-icon class="play-icon"><VideoPlay /></el-icon>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="所属模板" prop="template_name" min-width="150" show-overflow-tooltip>
          <template #default="{ row }">
            <span>{{ row.template_name || '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column label="时长" width="100" align="center">
          <template #default="{ row }">
            {{ formatDuration(row.duration) }}
          </template>
        </el-table-column>
        <el-table-column label="文件大小" width="120" align="center">
          <template #default="{ row }">
            {{ formatFileSize(row.file_size) }}
          </template>
        </el-table-column>
        <el-table-column label="MD5" width="200" show-overflow-tooltip>
          <template #default="{ row }">
            <el-tooltip :content="row.md5" placement="top">
              <span class="md5-text">{{ row.md5 || '-' }}</span>
            </el-tooltip>
          </template>
        </el-table-column>
        <el-table-column label="使用次数" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="row.use_count > 0 ? 'success' : 'info'" size="small">
              {{ row.use_count || 0 }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="getStatusType(row.status)" size="small">
              {{ getStatusLabel(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="创建时间" width="180" align="center">
          <template #default="{ row }">
            {{ formatDate(row.create_time) }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="140" align="center" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" size="small" @click="handlePreview(row)">
              预览
            </el-button>
            <el-button type="danger" size="small" @click="handleDelete(row)">
              删除
            </el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- 空状态 -->
      <div v-if="!loading && variantList.length === 0" class="empty-state">
        <el-empty description="暂无变体">
          <el-button type="primary" @click="router.push('/promo/template')">
            去创建模板
          </el-button>
        </el-empty>
      </div>
    </div>

    <!-- 分页 -->
    <div v-if="total > 0" class="pagination">
      <el-pagination
        v-model:current-page="listQuery.page"
        v-model:page-size="listQuery.limit"
        :total="total"
        :page-sizes="[10, 20, 50, 100]"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="handleSizeChange"
        @current-change="handlePageChange"
      />
    </div>

    <!-- 视频预览对话框 -->
    <el-dialog
      v-model="previewVisible"
      :title="previewItem?.template_name ? `${previewItem.template_name} - 变体预览` : '变体预览'"
      width="800px"
      destroy-on-close
      class="preview-dialog"
    >
      <div class="preview-container">
        <video
          v-if="previewItem?.video_url"
          ref="videoRef"
          :src="previewItem.video_url"
          controls
          class="preview-video"
        >
          您的浏览器不支持视频播放
        </video>
        <div v-else class="no-video">
          <el-icon><VideoPlay /></el-icon>
          <span>视频暂不可用</span>
        </div>
      </div>

      <div class="preview-info">
        <el-descriptions :column="2" border>
          <el-descriptions-item label="时长">
            {{ formatDuration(previewItem?.duration) }}
          </el-descriptions-item>
          <el-descriptions-item label="文件大小">
            {{ formatFileSize(previewItem?.file_size) }}
          </el-descriptions-item>
          <el-descriptions-item label="MD5" :span="2">
            {{ previewItem?.md5 || '-' }}
          </el-descriptions-item>
          <el-descriptions-item label="使用次数">
            {{ previewItem?.use_count || 0 }} 次
          </el-descriptions-item>
          <el-descriptions-item label="创建时间">
            {{ formatDate(previewItem?.create_time) }}
          </el-descriptions-item>
        </el-descriptions>
      </div>

      <template #footer>
        <el-button @click="previewVisible = false">关闭</el-button>
        <el-button type="primary" @click="handleDownload(previewItem)">
          <el-icon><Download /></el-icon>
          下载
        </el-button>
        <el-button type="danger" @click="handleDelete(previewItem); previewVisible = false">
          删除
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  Refresh,
  Delete,
  Download,
  VideoPlay
} from '@element-plus/icons-vue'
import {
  getVariantList,
  deleteVariant,
  batchDeleteVariants
} from '@/api/promo-template'
import { getTemplateList } from '@/api/promo-template'

const router = useRouter()
const route = useRoute()

// 数据状态
const loading = ref(false)
const variantList = ref([])
const total = ref(0)
const tableRef = ref(null)

// 查询参数
const listQuery = reactive({
  page: 1,
  limit: 20,
  template_id: ''
})

// 模板选项
const templateOptions = ref([])

// 选中项
const selectedIds = ref([])

// 预览
const previewVisible = ref(false)
const previewItem = ref(null)
const videoRef = ref(null)

// 获取变体列表
const getList = async () => {
  loading.value = true
  try {
    const params = { ...listQuery }
    Object.keys(params).forEach(key => {
      if (params[key] === '' || params[key] === null || params[key] === undefined) {
        delete params[key]
      }
    })

    const response = await getVariantList(params)
    if (response) {
      if (response.list) {
        variantList.value = response.list
        total.value = response.pagination?.total || 0
      } else if (response.data) {
        variantList.value = Array.isArray(response.data) ? response.data : []
        total.value = response.total || variantList.value.length
      } else if (Array.isArray(response)) {
        variantList.value = response
        total.value = response.length
      }
    }
  } catch (error) {
    console.error('获取变体列表失败:', error)
    ElMessage.error('获取变体列表失败')
  } finally {
    loading.value = false
  }
}

// 获取模板选项
const getTemplateOptions = async () => {
  try {
    const response = await getTemplateList({ limit: 100 })
    if (response) {
      if (response.list) {
        templateOptions.value = response.list
      } else if (response.data) {
        templateOptions.value = Array.isArray(response.data) ? response.data : []
      } else if (Array.isArray(response)) {
        templateOptions.value = response
      }
    }
  } catch (error) {
    console.error('获取模板列表失败:', error)
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

// 选择变化
const handleSelectionChange = (selection) => {
  selectedIds.value = selection.map(item => item.id)
}

// 预览
const handlePreview = (item) => {
  previewItem.value = item
  previewVisible.value = true
}

// 下载
const handleDownload = (item) => {
  if (!item?.video_url) {
    ElMessage.warning('视频地址不可用')
    return
  }
  window.open(item.video_url, '_blank')
}

// 删除
const handleDelete = async (item) => {
  if (!item) return

  try {
    await ElMessageBox.confirm(
      '确定要删除该变体吗？删除后无法恢复。',
      '删除确认',
      {
        confirmButtonText: '确定删除',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )

    await deleteVariant(item.id)
    ElMessage.success('删除成功')
    getList()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除变体失败:', error)
      ElMessage.error('删除失败')
    }
  }
}

// 批量删除
const handleBatchDelete = async () => {
  if (selectedIds.value.length === 0) {
    ElMessage.warning('请选择要删除的变体')
    return
  }

  try {
    await ElMessageBox.confirm(
      `确定要删除选中的 ${selectedIds.value.length} 个变体吗？删除后无法恢复。`,
      '批量删除确认',
      {
        confirmButtonText: '确定删除',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )

    await batchDeleteVariants(selectedIds.value)
    ElMessage.success('批量删除成功')
    selectedIds.value = []
    getList()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('批量删除失败:', error)
      ElMessage.error('批量删除失败')
    }
  }
}

// 获取状态类型
const getStatusType = (status) => {
  const types = {
    pending: 'warning',
    processing: 'primary',
    completed: 'success',
    failed: 'danger'
  }
  return types[status] || 'info'
}

// 获取状态标签
const getStatusLabel = (status) => {
  const labels = {
    pending: '等待中',
    processing: '生成中',
    completed: '已完成',
    failed: '失败'
  }
  return labels[status] || status
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

// 格式化时长
const formatDuration = (seconds) => {
  if (!seconds) return '-'
  const mins = Math.floor(seconds / 60)
  const secs = Math.floor(seconds % 60)
  return mins > 0 ? `${mins}:${secs.toString().padStart(2, '0')}` : `0:${secs.toString().padStart(2, '0')}`
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
  // 从路由参数获取模板ID
  if (route.query.template_id) {
    listQuery.template_id = Number(route.query.template_id)
  }
  getTemplateOptions()
  getList()
})
</script>

<style scoped lang="scss">
.promo-variant {
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

  .variant-list {
    background: #fff;
    border-radius: 8px;
    padding: 20px;
    min-height: 300px;

    .variant-preview {
      width: 80px;
      height: 60px;
      border-radius: 4px;
      overflow: hidden;
      cursor: pointer;
      position: relative;

      &:hover {
        .play-overlay {
          opacity: 1;
        }
      }

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
        font-size: 20px;
      }

      .thumb-error {
        background: #e9ecef;
        color: #adb5bd;
      }

      .play-overlay {
        position: absolute;
        inset: 0;
        background: rgba(0, 0, 0, 0.4);
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.3s;

        .play-icon {
          font-size: 24px;
          color: #fff;
        }
      }
    }

    .md5-text {
      font-family: monospace;
      font-size: 12px;
      color: #606266;
    }

    .empty-state {
      padding: 60px 0;
    }
  }

  .pagination {
    margin-top: 24px;
    display: flex;
    justify-content: center;
  }

  .preview-dialog {
    .preview-container {
      display: flex;
      justify-content: center;
      margin-bottom: 20px;

      .preview-video {
        max-width: 100%;
        max-height: 60vh;
        border-radius: 8px;
        background: #000;
      }

      .no-video {
        width: 100%;
        height: 300px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 12px;
        background: #f5f7fa;
        border-radius: 8px;
        color: #909399;

        .el-icon {
          font-size: 48px;
        }
      }
    }

    .preview-info {
      margin-bottom: 16px;
    }
  }
}
</style>
