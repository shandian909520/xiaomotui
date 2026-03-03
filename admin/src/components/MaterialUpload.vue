<template>
  <div class="material-upload">
    <!-- 上传类型选择 -->
    <div class="type-selector">
      <span class="label">素材类型：</span>
      <el-radio-group v-model="uploadType">
        <el-radio-button label="image">图片</el-radio-button>
        <el-radio-button label="video">视频</el-radio-button>
        <el-radio-button label="audio">音频</el-radio-button>
      </el-radio-group>
    </div>

    <!-- 上传区域 -->
    <el-upload
      ref="uploadRef"
      class="upload-area"
      :action="uploadAction"
      :headers="uploadHeaders"
      :data="{ type: uploadType }"
      :multiple="true"
      :limit="10"
      :file-list="fileList"
      :auto-upload="false"
      :accept="acceptTypes"
      :before-upload="handleBeforeUpload"
      :on-change="handleFileChange"
      :on-remove="handleFileRemove"
      :on-success="handleUploadSuccess"
      :on-error="handleUploadError"
      :on-progress="handleUploadProgress"
      drag
    >
      <div class="upload-dragger">
        <el-icon class="upload-icon"><UploadFilled /></el-icon>
        <div class="upload-text">
          <p class="main-text">将文件拖到此处，或<em>点击上传</em></p>
          <p class="sub-text">{{ uploadTips }}</p>
        </div>
      </div>
    </el-upload>

    <!-- 文件列表 -->
    <div v-if="fileList.length > 0" class="file-list">
      <div class="list-header">
        <span>待上传文件 ({{ fileList.length }})</span>
        <el-button type="danger" size="small" text @click="handleClearFiles">
          清空列表
        </el-button>
      </div>
      <el-table :data="fileList" max-height="300">
        <el-table-column label="文件名" prop="name" min-width="200" show-overflow-tooltip />
        <el-table-column label="大小" width="120" align="center">
          <template #default="{ row }">
            {{ formatFileSize(row.size) }}
          </template>
        </el-table-column>
        <el-table-column label="状态" width="120" align="center">
          <template #default="{ row }">
            <el-tag v-if="row.status === 'ready'" type="info" size="small">待上传</el-tag>
            <el-tag v-else-if="row.status === 'uploading'" type="warning" size="small">
              <el-icon class="is-loading"><Loading /></el-icon>
              上传中
            </el-tag>
            <el-tag v-else-if="row.status === 'success'" type="success" size="small">成功</el-tag>
            <el-tag v-else-if="row.status === 'fail'" type="danger" size="small">失败</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="进度" width="150" align="center">
          <template #default="{ row }">
            <el-progress
              v-if="row.status === 'uploading' || row.status === 'success'"
              :percentage="row.percentage || 0"
              :status="row.status === 'success' ? 'success' : ''"
              :stroke-width="6"
            />
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="80" align="center">
          <template #default="{ row, $index }">
            <el-button
              type="danger"
              size="small"
              text
              @click="handleRemoveFile($index)"
            >
              移除
            </el-button>
          </template>
        </el-table-column>
      </el-table>
    </div>

    <!-- 操作按钮 -->
    <div class="upload-actions">
      <el-button @click="handleCancel">取消</el-button>
      <el-button
        type="primary"
        :loading="uploading"
        :disabled="fileList.length === 0"
        @click="handleSubmit"
      >
        {{ uploading ? '上传中...' : `开始上传 (${fileList.length})` }}
      </el-button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { ElMessage } from 'element-plus'
import { UploadFilled, Loading } from '@element-plus/icons-vue'
import { uploadMaterial } from '@/api/promo-material'
import { getToken } from '@/utils/request'

// Emits
const emit = defineEmits(['success', 'cancel'])

// 数据
const uploadRef = ref()
const uploadType = ref('image')
const fileList = ref([])
const uploading = ref(false)

// 上传配置
const uploadAction = computed(() => {
  const baseUrl = import.meta.env.VITE_API_BASE_URL || '/api'
  return `${baseUrl}/merchant/promo/materials`
})

const uploadHeaders = computed(() => ({
  Authorization: `Bearer ${getToken()}`
}))

// 接受的文件类型
const acceptTypes = computed(() => {
  const types = {
    image: 'image/jpeg,image/png,image/gif,image/webp,image/bmp',
    video: 'video/mp4,video/quicktime,video/x-msvideo,video/webm',
    audio: 'audio/mpeg,audio/wav,audio/ogg,audio/aac,audio/flac'
  }
  return types[uploadType.value] || ''
})

// 上传提示
const uploadTips = computed(() => {
  const tips = {
    image: '支持 JPG、PNG、GIF、WebP、BMP 格式，单文件不超过 10MB',
    video: '支持 MP4、MOV、AVI、WebM 格式，单文件不超过 500MB',
    audio: '支持 MP3、WAV、OGG、AAC、FLAC 格式，单文件不超过 100MB'
  }
  return tips[uploadType.value] || ''
})

// 文件大小限制
const maxSize = computed(() => {
  const sizes = {
    image: 10 * 1024 * 1024, // 10MB
    video: 500 * 1024 * 1024, // 500MB
    audio: 100 * 1024 * 1024 // 100MB
  }
  return sizes[uploadType.value] || 10 * 1024 * 1024
})

// 上传前验证
const handleBeforeUpload = (file) => {
  // 验证文件大小
  if (file.size > maxSize.value) {
    ElMessage.error(`文件大小不能超过 ${formatFileSize(maxSize.value)}`)
    return false
  }

  // 验证文件类型
  const acceptList = acceptTypes.value.split(',')
  const isValidType = acceptList.some(type => {
    if (type.includes('/*')) {
      // 通配符匹配，如 image/*
      const category = type.split('/')[0]
      return file.type.startsWith(category)
    }
    return file.type === type
  })

  if (!isValidType) {
    ElMessage.error('文件格式不支持')
    return false
  }

  return true
}

// 文件变化
const handleFileChange = (file, files) => {
  fileList.value = files.map(f => ({
    ...f,
    percentage: 0,
    status: f.status || 'ready'
  }))
}

// 文件移除
const handleFileRemove = (file, files) => {
  fileList.value = files
}

// 移除单个文件
const handleRemoveFile = (index) => {
  fileList.value.splice(index, 1)
}

// 清空文件列表
const handleClearFiles = () => {
  fileList.value = []
}

// 上传成功
const handleUploadSuccess = (response, file, files) => {
  file.status = 'success'
  file.percentage = 100
}

// 上传失败
const handleUploadError = (error, file, files) => {
  file.status = 'fail'
  ElMessage.error(`文件 ${file.name} 上传失败`)
}

// 上传进度
const handleUploadProgress = (event, file, files) => {
  file.percentage = Math.floor(event.percent)
  file.status = 'uploading'
}

// 提交上传
const handleSubmit = async () => {
  if (fileList.value.length === 0) {
    ElMessage.warning('请先选择要上传的文件')
    return
  }

  uploading.value = true
  let successCount = 0
  let failCount = 0

  try {
    // 逐个上传文件
    for (const file of fileList.value) {
      if (file.status === 'success') {
        successCount++
        continue
      }

      file.status = 'uploading'
      file.percentage = 0

      try {
        await uploadMaterial(file.raw, uploadType.value, (progressEvent) => {
          file.percentage = Math.floor((progressEvent.loaded / progressEvent.total) * 100)
        })
        file.status = 'success'
        file.percentage = 100
        successCount++
      } catch (error) {
        file.status = 'fail'
        failCount++
        console.error(`上传文件 ${file.name} 失败:`, error)
      }
    }

    // 显示结果
    if (failCount === 0) {
      ElMessage.success(`全部上传成功 (${successCount} 个文件)`)
      emit('success')
    } else if (successCount > 0) {
      ElMessage.warning(`部分上传成功：成功 ${successCount} 个，失败 ${failCount} 个`)
    } else {
      ElMessage.error('上传失败，请重试')
    }
  } finally {
    uploading.value = false
  }
}

// 取消
const handleCancel = () => {
  emit('cancel')
}

// 格式化文件大小
const formatFileSize = (bytes) => {
  if (!bytes) return '0 B'
  const units = ['B', 'KB', 'MB', 'GB']
  let size = bytes
  let unitIndex = 0
  while (size >= 1024 && unitIndex < units.length - 1) {
    size /= 1024
    unitIndex++
  }
  return `${size.toFixed(1)} ${units[unitIndex]}`
}
</script>

<style scoped lang="scss">
.material-upload {
  .type-selector {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;

    .label {
      font-size: 14px;
      color: #606266;
      font-weight: 500;
    }
  }

  .upload-area {
    margin-bottom: 20px;

    :deep(.el-upload) {
      width: 100%;
    }

    :deep(.el-upload-dragger) {
      width: 100%;
      height: 180px;
      border: 2px dashed #d9d9d9;
      border-radius: 8px;
      background: #fafafa;
      transition: all 0.3s;

      &:hover {
        border-color: #409eff;
        background: #f5f7fa;
      }
    }

    .upload-dragger {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      height: 100%;
      padding: 20px;

      .upload-icon {
        font-size: 48px;
        color: #c0c4cc;
        margin-bottom: 16px;
      }

      .upload-text {
        text-align: center;

        .main-text {
          font-size: 16px;
          color: #606266;
          margin: 0 0 8px 0;

          em {
            color: #409eff;
            font-style: normal;
          }
        }

        .sub-text {
          font-size: 12px;
          color: #909399;
          margin: 0;
        }
      }
    }
  }

  .file-list {
    margin-bottom: 20px;

    .list-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 12px 0;
      font-size: 14px;
      color: #606266;
      border-bottom: 1px solid #ebeef5;
    }
  }

  .upload-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding-top: 16px;
    border-top: 1px solid #ebeef5;
  }
}

.is-loading {
  animation: rotating 2s linear infinite;
}

@keyframes rotating {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}
</style>
