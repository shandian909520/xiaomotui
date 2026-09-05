<template>
  <div class="video-project-container">
    <div class="page-header">
      <h2>剪辑工程</h2>
      <el-button type="primary" @click="goToEdit">新建剪辑</el-button>
    </div>

    <el-card class="filter-card">
      <el-row :gutter="20">
        <el-col :span="6">
          <el-input v-model="searchKey" placeholder="搜索标题" clearable @clear="loadTasks" @keyup.enter="loadTasks">
            <template #append>
              <el-button :icon="Search" @click="loadTasks" />
            </template>
          </el-input>
        </el-col>
        <el-col :span="12">
          <el-radio-group v-model="statusFilter" @change="loadTasks">
            <el-radio-button label="">全部</el-radio-button>
            <el-radio-button label="pending">排队中</el-radio-button>
            <el-radio-button label="processing">处理中</el-radio-button>
            <el-radio-button label="success">成功</el-radio-button>
            <el-radio-button label="failed">失败</el-radio-button>
          </el-radio-group>
        </el-col>
      </el-row>
    </el-card>

    <el-card class="table-card">
      <el-table :data="taskList" v-loading="loading" stripe>
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column label="封面" width="120">
          <template #default="{ row }">
            <el-image
              v-if="row.cover"
              :src="row.cover"
              fit="cover"
              class="cover-image"
              :preview-src-list="[row.cover]"
            />
            <div v-else class="no-cover">无封面</div>
          </template>
        </el-table-column>
        <el-table-column prop="title" label="标题" min-width="180" show-overflow-tooltip />
        <el-table-column prop="storeName" label="门店" width="120" show-overflow-tooltip />
        <el-table-column label="平台" width="100">
          <template #default="{ row }">
            <el-tag size="small">{{ getPlatformName(row.platform) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="getStatusType(row.status)" size="small">
              {{ getStatusName(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="进度" width="150">
          <template #default="{ row }">
            <el-progress
              v-if="row.status === 'processing' || row.status === 'pending'"
              :percentage="row.progress"
              :status="row.progress === 100 ? 'success' : ''"
            />
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column prop="duration" label="时长(s)" width="80" />
        <el-table-column prop="createdAt" label="创建时间" width="120" />
        <el-table-column label="操作" width="220" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="handlePreview(row)">预览</el-button>
            <el-button
              v-if="row.status === 'failed'"
              link
              type="warning"
              size="small"
              @click="handleRetry(row)"
            >
              重新生成
            </el-button>
            <el-button
              v-if="row.status === 'success'"
              link
              type="success"
              size="small"
              @click="handleDownload(row)"
            >
              下载
            </el-button>
            <el-button link type="info" size="small" @click="handleRecord(row)">发布记录</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination">
        <el-pagination
          v-model:current-page="pagination.page"
          v-model:page-size="pagination.pageSize"
          :total="pagination.total"
          :page-sizes="[10, 20, 50, 100]"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="loadTasks"
          @current-change="loadTasks"
        />
      </div>
    </el-card>

    <!-- 预览弹窗 -->
    <el-dialog v-model="previewVisible" title="视频预览" width="600px" destroy-on-close>
      <div class="preview-content">
        <video
          v-if="previewTask?.videoUrl"
          :src="previewTask.videoUrl"
          controls
          class="preview-video"
        />
        <el-empty v-else description="暂无视频" />
      </div>
    </el-dialog>

    <!-- 发布记录弹窗 -->
    <el-dialog v-model="recordVisible" title="发布记录" width="600px" destroy-on-close>
      <el-empty v-if="recordList.length === 0" description="暂无发布记录" :image-size="80" />
      <el-table v-else :data="recordList" stripe>
        <el-table-column prop="platform" label="平台" width="100">
          <template #default="{ row }">
            <el-tag size="small">{{ getPlatformName(row.platform) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="row.status === 'success' ? 'success' : 'danger'" size="small">
              {{ row.status === 'success' ? '已发布' : '失败' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="publishTime" label="发布时间" width="160" />
        <el-table-column prop="errorMsg" label="备注" show-overflow-tooltip />
      </el-table>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Search } from '@element-plus/icons-vue'
import { retryVideoTask, getVideoTasks } from '@/api/video'
import { getPublishTasks } from '@/api/publish'
import { useRouter } from 'vue-router'
import { normalizePagination } from '@/utils/responseHelper'
import { downloadFile } from '@/utils/file'

const router = useRouter()

const loading = ref(false)
const searchKey = ref('')
const statusFilter = ref('')
const taskList = ref([])
const pagination = reactive({
  page: 1,
  pageSize: 10,
  total: 0
})

const previewVisible = ref(false)
const previewTask = ref(null)
const recordVisible = ref(false)
const recordList = ref([])

const getPlatformName = (platform) => {
  const map = {
    douyin: '抖音',
    kuaishou: '快手',
    xiaohongshu: '小红书',
    weixin: '视频号'
  }
  return map[platform] || platform
}

const getStatusName = (status) => {
  const map = {
    pending: '排队中',
    processing: '处理中',
    success: '成功',
    failed: '失败'
  }
  return map[status] || status
}

const getStatusType = (status) => {
  const map = {
    pending: 'info',
    processing: 'primary',
    success: 'success',
    failed: 'danger'
  }
  return map[status] || 'info'
}

const loadTasks = async () => {
  loading.value = true
  try {
    const params = {
      page: pagination.page,
      page_size: pagination.pageSize,
      status: statusFilter.value || undefined,
      keyword: searchKey.value || undefined
    }
    const res = await getVideoTasks(params)
    const { list, total: totalCount } = normalizePagination(res)
    // 映射后端 ContentTask 字段到前端期望的字段
    taskList.value = list.map(item => ({
      id: item.id,
      title: item.title || item.type || '视频任务',
      storeName: item.storeName || '',
      platform: item.platform || 'douyin',
      status: item.status || 'pending',
      progress: item.progress || 0,
      duration: item.duration || 0,
      cover: item.cover || item.videoUrl ? '' : '',
      videoUrl: item.videoUrl || item.contentUrl || '',
      createdAt: item.createTime || item.createdAt || ''
    }))
    pagination.total = totalCount
  } catch (error) {
    console.error('获取任务列表失败:', error)
    taskList.value = []
    pagination.total = 0
    ElMessage.error('获取任务列表失败，请稍后重试')
  } finally {
    loading.value = false
  }
}

const goToEdit = () => {
  router.push('/video/edit')
}

const handlePreview = (row) => {
  previewTask.value = row
  previewVisible.value = true
}

const handleRetry = async (row) => {
  try {
    await ElMessageBox.confirm('确定要重新生成该视频吗？', '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
    await retryVideoTask(row.id)
    ElMessage.success('已重新生成')
    loadTasks()
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error('操作失败')
    }
  }
}

const handleDownload = async (row) => {
  const url = row.videoUrl || row.outputUrl || ''
  if (!url) {
    ElMessage.warning('暂无可下载的视频文件')
    return
  }
  ElMessage.info('正在开始下载...')
  const ok = await downloadFile(url, `${row.title || '视频'}.mp4`)
  if (ok) {
    ElMessage.success('下载已开始')
  }
}

const handleRecord = async (row) => {
  recordVisible.value = true
  recordList.value = []
  try {
    const res = await getPublishTasks({ taskId: row.id })
    const raw = res?.list || res?.data || res || []
    recordList.value = Array.isArray(raw) ? raw.map(item => ({
      platform: item.platform || '',
      status: item.status || 'pending',
      publishTime: item.publishTime || item.createTime || '-',
      errorMsg: item.errorMsg || item.message || ''
    })) : []
  } catch (err) {
    console.error('获取发布记录失败:', err)
    recordList.value = []
  }
}

onMounted(() => {
  loadTasks()
})
</script>

<style scoped lang="scss">
.video-project-container {
  padding: 20px;

  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;

    h2 {
      margin: 0;
      font-weight: 500;
    }
  }

  .filter-card {
    margin-bottom: 20px;
  }

  .cover-image {
    width: 80px;
    height: 50px;
    border-radius: 4px;
  }

  .no-cover {
    width: 80px;
    height: 50px;
    background-color: #f5f5f5;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    color: #999;
  }

  .pagination {
    margin-top: 20px;
    display: flex;
    justify-content: flex-end;
  }

  .preview-content {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 300px;

    .preview-video {
      max-width: 100%;
      max-height: 400px;
    }
  }
}
</style>