<template>
  <div class="promo-material">
    <!-- 页面头部 -->
    <div class="page-header">
      <div class="header-left">
        <h2>素材库</h2>
        <p class="subtitle">管理您的推广素材，支持图片、视频、音频上传</p>
      </div>
      <div class="header-right">
        <el-button type="primary" icon="Upload" @click="handleUpload">
          上传素材
        </el-button>
      </div>
    </div>

    <!-- 筛选和视图切换 -->
    <div class="filter-bar">
      <div class="filter-tabs">
        <el-radio-group v-model="listQuery.type" @change="handleFilterChange">
          <el-radio-button label="">全部</el-radio-button>
          <el-radio-button label="image">图片</el-radio-button>
          <el-radio-button label="video">视频</el-radio-button>
          <el-radio-button label="audio">音频</el-radio-button>
        </el-radio-group>
      </div>
      <div class="view-toggle">
        <el-segmented v-model="viewMode" :options="viewOptions" size="default">
          <template #default="{ item }">
            <el-icon :size="18">
              <component :is="item.icon" />
            </el-icon>
          </template>
        </el-segmented>
      </div>
    </div>

    <!-- 素材列表 - 卡片视图 -->
    <div v-if="viewMode === 'card'" v-loading="loading" class="material-grid">
      <div
        v-for="item in materialList"
        :key="item.id"
        class="material-card"
      >
        <div class="card-preview" @click="handlePreview(item)">
          <!-- 图片预览 -->
          <template v-if="item.type === 'image'">
            <el-image
              :src="item.url"
              :alt="item.name"
              fit="cover"
              class="preview-image"
            >
              <template #error>
                <div class="image-error">
                  <el-icon><Picture /></el-icon>
                  <span>加载失败</span>
                </div>
              </template>
            </el-image>
          </template>

          <!-- 视频预览 -->
          <template v-else-if="item.type === 'video'">
            <div class="preview-video">
              <el-icon class="play-icon"><VideoPlay /></el-icon>
              <span class="duration">{{ formatDuration(item.duration) }}</span>
            </div>
          </template>

          <!-- 音频预览 -->
          <template v-else-if="item.type === 'audio'">
            <div class="preview-audio">
              <el-icon class="audio-icon"><Headset /></el-icon>
              <span class="duration">{{ formatDuration(item.duration) }}</span>
            </div>
          </template>

          <!-- 悬浮操作层 -->
          <div class="card-overlay">
            <el-button type="primary" size="small" @click.stop="handlePreview(item)">
              <el-icon><View /></el-icon>
              预览
            </el-button>
            <el-button type="danger" size="small" @click.stop="handleDelete(item)">
              <el-icon><Delete /></el-icon>
              删除
            </el-button>
          </div>
        </div>

        <div class="card-info">
          <div class="card-name" :title="item.name">{{ item.name }}</div>
          <div class="card-meta">
            <el-tag :type="getTypeTagType(item.type)" size="small">
              {{ getTypeLabel(item.type) }}
            </el-tag>
            <span class="file-size">{{ formatFileSize(item.size) }}</span>
          </div>
          <div class="card-time">{{ formatDate(item.create_time) }}</div>
        </div>
      </div>

      <!-- 空状态 -->
      <div v-if="!loading && materialList.length === 0" class="empty-state">
        <el-empty description="暂无素材">
          <el-button type="primary" @click="handleUpload">上传素材</el-button>
        </el-empty>
      </div>
    </div>

    <!-- 素材列表 - 列表视图 -->
    <div v-if="viewMode === 'list'" v-loading="loading" class="material-list">
      <el-table :data="materialList" border stripe>
        <el-table-column label="预览" width="100" align="center">
          <template #default="{ row }">
            <div class="list-preview" @click="handlePreview(row)">
              <el-image
                v-if="row.type === 'image'"
                :src="row.url"
                :alt="row.name"
                fit="cover"
                class="list-thumb"
              >
                <template #error>
                  <div class="thumb-error">
                    <el-icon><Picture /></el-icon>
                  </div>
                </template>
              </el-image>
              <div v-else class="list-icon">
                <el-icon v-if="row.type === 'video'"><VideoPlay /></el-icon>
                <el-icon v-else-if="row.type === 'audio'"><Headset /></el-icon>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="文件名" prop="name" min-width="200" show-overflow-tooltip />
        <el-table-column label="类型" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="getTypeTagType(row.type)" size="small">
              {{ getTypeLabel(row.type) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="大小" width="120" align="center">
          <template #default="{ row }">
            {{ formatFileSize(row.size) }}
          </template>
        </el-table-column>
        <el-table-column label="时长" width="100" align="center">
          <template #default="{ row }">
            {{ row.duration ? formatDuration(row.duration) : '-' }}
          </template>
        </el-table-column>
        <el-table-column label="上传时间" width="180" align="center">
          <template #default="{ row }">
            {{ formatDate(row.create_time) }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="160" align="center" fixed="right">
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
      <div v-if="!loading && materialList.length === 0" class="empty-state">
        <el-empty description="暂无素材">
          <el-button type="primary" @click="handleUpload">上传素材</el-button>
        </el-empty>
      </div>
    </div>

    <!-- 分页 -->
    <div v-if="total > 0" class="pagination">
      <el-pagination
        v-model:current-page="listQuery.page"
        v-model:page-size="listQuery.limit"
        :total="total"
        :page-sizes="[12, 24, 48, 96]"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="handleSizeChange"
        @current-change="handlePageChange"
      />
    </div>

    <!-- 上传对话框 -->
    <el-dialog
      v-model="uploadVisible"
      title="上传素材"
      width="600px"
      destroy-on-close
    >
      <MaterialUpload
        @success="handleUploadSuccess"
        @cancel="uploadVisible = false"
      />
    </el-dialog>

    <!-- 预览对话框 -->
    <el-dialog
      v-model="previewVisible"
      :title="previewItem?.name || '预览'"
      width="800px"
      destroy-on-close
    >
      <div class="preview-container">
        <!-- 图片预览 -->
        <div v-if="previewItem?.type === 'image'" class="preview-image-wrapper">
          <el-image
            :src="previewItem?.url"
            :alt="previewItem?.name"
            fit="contain"
            class="preview-full-image"
          >
            <template #error>
              <div class="image-error-large">
                <el-icon><Picture /></el-icon>
                <span>图片加载失败</span>
              </div>
            </template>
          </el-image>
        </div>

        <!-- 视频预览 -->
        <div v-else-if="previewItem?.type === 'video'" class="preview-video-wrapper">
          <video
            :src="previewItem?.url"
            controls
            class="preview-full-video"
          >
            您的浏览器不支持视频播放
          </video>
        </div>

        <!-- 音频预览 -->
        <div v-else-if="previewItem?.type === 'audio'" class="preview-audio-wrapper">
          <div class="audio-cover">
            <el-icon class="audio-cover-icon"><Headset /></el-icon>
          </div>
          <audio
            :src="previewItem?.url"
            controls
            class="preview-full-audio"
          >
            您的浏览器不支持音频播放
          </audio>
        </div>
      </div>

      <template #footer>
        <el-button @click="previewVisible = false">关闭</el-button>
        <el-button type="danger" @click="handleDelete(previewItem); previewVisible = false">
          删除素材
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  View,
  Delete,
  Upload,
  Picture,
  VideoPlay,
  Headset,
  Grid,
  List
} from '@element-plus/icons-vue'
import { getMaterialList, deleteMaterial } from '@/api/promo-material'
import MaterialUpload from '@/components/MaterialUpload.vue'

// 数据状态
const loading = ref(false)
const materialList = ref([])
const total = ref(0)

// 查询参数
const listQuery = reactive({
  page: 1,
  limit: 24,
  type: '',
  keyword: ''
})

// 视图模式
const viewMode = ref('card')
const viewOptions = [
  { label: 'card', icon: Grid },
  { label: 'list', icon: List }
]

// 上传对话框
const uploadVisible = ref(false)

// 预览
const previewVisible = ref(false)
const previewItem = ref(null)

// 获取素材列表
const getList = async () => {
  loading.value = true
  try {
    const params = { ...listQuery }
    // 移除空值
    Object.keys(params).forEach(key => {
      if (params[key] === '' || params[key] === null || params[key] === undefined) {
        delete params[key]
      }
    })

    const response = await getMaterialList(params)
    if (response) {
      // 处理不同的响应格式
      if (response.list) {
        materialList.value = response.list
        total.value = response.pagination?.total || 0
      } else if (response.data) {
        materialList.value = Array.isArray(response.data) ? response.data : []
        total.value = response.total || materialList.value.length
      } else if (Array.isArray(response)) {
        materialList.value = response
        total.value = response.length
      }
    }
  } catch (error) {
    console.error('获取素材列表失败:', error)
    ElMessage.error('获取素材列表失败')
  } finally {
    loading.value = false
  }
}

// 筛选变化
const handleFilterChange = () => {
  listQuery.page = 1
  getList()
}

// 分页大小变化
const handleSizeChange = (size) => {
  listQuery.limit = size
  listQuery.page = 1
  getList()
}

// 页码变化
const handlePageChange = (page) => {
  listQuery.page = page
  getList()
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

// 上传素材
const handleUpload = () => {
  uploadVisible.value = true
}

// 上传成功回调
const handleUploadSuccess = () => {
  uploadVisible.value = false
  getList()
  ElMessage.success('素材上传成功')
}

// 预览
const handlePreview = (item) => {
  previewItem.value = item
  previewVisible.value = true
}

// 删除
const handleDelete = async (item) => {
  if (!item) return

  try {
    await ElMessageBox.confirm(
      `确定要删除素材"${item.name}"吗？删除后无法恢复。`,
      '删除确认',
      {
        confirmButtonText: '确定删除',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )

    await deleteMaterial(item.id)
    ElMessage.success('删除成功')
    getList()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除素材失败:', error)
      ElMessage.error('删除失败')
    }
  }
}

// 获取类型标签样式
const getTypeTagType = (type) => {
  const types = {
    image: 'success',
    video: 'primary',
    audio: 'warning'
  }
  return types[type] || 'info'
}

// 获取类型标签文本
const getTypeLabel = (type) => {
  const labels = {
    image: '图片',
    video: '视频',
    audio: '音频'
  }
  return labels[type] || type
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
  getList()
})
</script>

<style scoped lang="scss">
.promo-material {
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

    .view-toggle {
      display: flex;
      align-items: center;
      gap: 8px;
    }
  }

  .material-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 20px;
    min-height: 300px;

    .material-card {
      background: #fff;
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
      transition: all 0.3s;

      &:hover {
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
        transform: translateY(-2px);

        .card-overlay {
          opacity: 1;
        }
      }

      .card-preview {
        position: relative;
        aspect-ratio: 1;
        background: #f5f7fa;
        cursor: pointer;
        overflow: hidden;

        .preview-image {
          width: 100%;
          height: 100%;
        }

        .preview-video,
        .preview-audio {
          width: 100%;
          height: 100%;
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          gap: 12px;
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
          color: #fff;

          .play-icon,
          .audio-icon {
            font-size: 48px;
            opacity: 0.9;
          }

          .duration {
            font-size: 14px;
            opacity: 0.8;
          }
        }

        .preview-audio {
          background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .image-error {
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          height: 100%;
          color: #c0c4cc;

          .el-icon {
            font-size: 32px;
            margin-bottom: 8px;
          }
        }

        .card-overlay {
          position: absolute;
          inset: 0;
          background: rgba(0, 0, 0, 0.6);
          display: flex;
          align-items: center;
          justify-content: center;
          gap: 12px;
          opacity: 0;
          transition: opacity 0.3s;
        }
      }

      .card-info {
        padding: 12px;

        .card-name {
          font-size: 14px;
          font-weight: 500;
          color: #303133;
          overflow: hidden;
          text-overflow: ellipsis;
          white-space: nowrap;
          margin-bottom: 8px;
        }

        .card-meta {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 6px;

          .file-size {
            font-size: 12px;
            color: #909399;
          }
        }

        .card-time {
          font-size: 12px;
          color: #c0c4cc;
        }
      }
    }

    .empty-state {
      grid-column: 1 / -1;
      padding: 60px 0;
    }
  }

  .material-list {
    background: #fff;
    border-radius: 8px;
    padding: 20px;
    min-height: 300px;

    .list-preview {
      width: 60px;
      height: 60px;
      border-radius: 4px;
      overflow: hidden;
      cursor: pointer;

      .list-thumb {
        width: 100%;
        height: 100%;
      }

      .list-icon,
      .thumb-error {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f5f7fa;
        color: #909399;
        font-size: 24px;
      }
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

  .preview-container {
    .preview-image-wrapper {
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 300px;

      .preview-full-image {
        max-width: 100%;
        max-height: 60vh;
      }

      .image-error-large {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: #c0c4cc;

        .el-icon {
          font-size: 64px;
          margin-bottom: 16px;
        }
      }
    }

    .preview-video-wrapper {
      display: flex;
      justify-content: center;

      .preview-full-video {
        max-width: 100%;
        max-height: 60vh;
        border-radius: 8px;
      }
    }

    .preview-audio-wrapper {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 24px;
      padding: 40px 0;

      .audio-cover {
        width: 200px;
        height: 200px;
        border-radius: 50%;
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 8px 32px rgba(240, 147, 251, 0.3);

        .audio-cover-icon {
          font-size: 80px;
          color: #fff;
        }
      }

      .preview-full-audio {
        width: 100%;
        max-width: 500px;
      }
    }
  }
}
</style>
