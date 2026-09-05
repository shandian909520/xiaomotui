<template>
  <div class="library-videos">
    <PageHeader title="视频库" subtitle="查看和管理AI生成的视频成品" />

    <FilterToolbar
      showSearch
      showExport
      placeholder="搜索视频标题..."
      @search="handleSearch"
      @export="handleExport"
    />

    <div class="filter-tabs">
      <el-radio-group v-model="platform" size="default">
        <el-radio-button label="全部平台" value="all" />
        <el-radio-button label="抖音" value="douyin" />
        <el-radio-button label="快手" value="kuaishou" />
        <el-radio-button label="小红书" value="xiaohongshu" />
        <el-radio-button label="视频号" value="video" />
      </el-radio-group>
    </div>

    <div class="store-filter">
      <el-select v-model="storeId" placeholder="选择门店" clearable>
        <el-option label="全部门店" :value="0" />
        <el-option v-for="store in stores" :key="store.id" :label="store.name" :value="store.id" />
      </el-select>
    </div>

    <div class="video-grid">
      <div v-for="video in filteredVideos" :key="video.id" class="video-card">
        <div class="video-cover" @click="handlePreview(video)">
          <img :src="video.cover" alt="封面" />
          <div class="video-duration">{{ video.duration }}s</div>
          <div class="video-play">
            <el-icon><VideoPlay /></el-icon>
          </div>
        </div>
        <div class="video-info">
          <div class="video-title">{{ video.title }}</div>
          <div class="video-meta">
            <span class="video-platform">
              <el-tag size="small" :type="getPlatformType(video.platform)">{{ video.platformName }}</el-tag>
            </span>
            <span class="video-views">{{ video.views }}播放</span>
          </div>
          <div class="video-actions">
            <el-button size="small" @click="handleCopy(video)">复制链接</el-button>
            <el-button size="small" type="primary" @click="handleDownload(video)">下载</el-button>
          </div>
        </div>
      </div>
    </div>

    <EmptyPanel v-if="filteredVideos.length === 0" message="暂无视频数据" />

    <el-dialog v-model="previewVisible" title="视频预览" width="800px">
      <video v-if="currentVideo" :src="currentVideo.url" controls autoplay style="width: 100%;" />
      <template #footer>
        <el-button @click="handleCopy(currentVideo)">复制链接</el-button>
        <el-button type="primary" @click="handleDownload(currentVideo)">下载</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { PageHeader, FilterToolbar, EmptyPanel } from '@/components/xmt'
import { ElMessage, ElMessageBox } from 'element-plus'
import { VideoPlay } from '@element-plus/icons-vue'
import { getVideos, deleteVideo, getStores, getPlatforms } from '@/api/library'
import { normalizeListPayload } from '@/utils/responseHelper'
import { downloadFile } from '@/utils/file'

const platform = ref('all')
const storeId = ref(0)
const searchQuery = ref('')
const previewVisible = ref(false)
const currentVideo = ref(null)
const loading = ref(false)


const stores = ref([])
const videos = ref([])

// 加载视频列表
const loadVideos = async () => {
  loading.value = true
  try {
    const params = {}
    if (platform.value !== 'all') params.platform = platform.value
    if (storeId.value !== 0) params.storeId = storeId.value
    if (searchQuery.value) params.keyword = searchQuery.value
    const res = await getVideos(params)
    const rawList = normalizeListPayload(res)
    // 映射后端 ContentLibrary 字段到视频卡片期望的字段
    videos.value = rawList.map(item => ({
      id: item.id,
      title: item.title || item.name || '未命名视频',
      cover: item.cover || item.thumbnail || '',
      url: item.url || item.videoUrl || '',
      duration: item.duration || 0,
      platform: item.platform || 'douyin',
      platformName: item.platformName || getPlatformLabel(item.platform),
      views: item.views || item.playCount || 0,
      storeId: item.storeId || 0
    }))
  } catch (err) {
    console.error('获取视频列表失败:', err)
    videos.value = []
    ElMessage.error('获取视频列表失败，请稍后重试')
  } finally {
    loading.value = false
  }
}

const getPlatformLabel = (p) => {
  const map = { douyin: '抖音', kuaishou: '快手', xiaohongshu: '小红书', video: '视频号' }
  return map[p] || p || ''
}

// 加载门店列表
const loadStores = async () => {
  try {
    const res = await getStores()
    stores.value = normalizeListPayload(res)
  } catch (err) {
    console.error('获取门店列表失败:', err)
    stores.value = []
    ElMessage.error('获取门店列表失败，请稍后重试')
  }
}

// 平台和门店筛选变化时重新请求
watch([platform, storeId], () => {
  loadVideos()
})

const filteredVideos = computed(() => {
  return videos.value.filter(video => {
    const matchPlatform = platform.value === 'all' || video.platform === platform.value
    const matchStore = storeId.value === 0 || video.storeId === storeId.value
    const matchSearch = !searchQuery.value || video.title.includes(searchQuery.value)
    return matchPlatform && matchStore && matchSearch
  })
})

const getPlatformType = (platform) => {
  const types = { douyin: 'danger', kuaishou: 'warning', xiaohongshu: 'success', video: 'info' }
  return types[platform] || 'info'
}

const handleSearch = (query) => {
  searchQuery.value = query
  loadVideos()
}
const handleExport = () => {
  if (!videos.value.length) {
    ElMessage.warning('暂无数据可导出')
    return
  }
  try {
    const columns = [
      { label: '视频标题', key: 'title' },
      { label: '时长(秒)', key: 'duration' },
      { label: '平台', key: 'platformName' },
      { label: '播放量', key: 'views' },
      { label: '视频链接', key: 'url' }
    ]
    const header = columns.map(c => c.label).join(',')
    const rows = videos.value.map(row =>
      columns.map(c => {
        let val = String(row[c.key] ?? '')
        if (val.includes(',') || val.includes('"') || val.includes('\n')) {
          val = '"' + val.replace(/"/g, '""') + '"'
        }
        return val
      }).join(',')
    )
    const csv = '\uFEFF' + header + '\n' + rows.join('\n')
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `视频库_${new Date().toISOString().slice(0, 10)}.csv`
    link.click()
    URL.revokeObjectURL(url)
    ElMessage.success('导出成功')
  } catch (e) {
    console.error('导出失败:', e)
    ElMessage.error('导出失败')
  }
}
const handlePreview = (video) => { currentVideo.value = video; previewVisible.value = true }
const handleCopy = (video) => { navigator.clipboard.writeText(video.url); ElMessage.success('链接已复制') }
const handleDownload = async (video) => {
  const url = video.url || ''
  if (!url) {
    ElMessage.warning('暂无可下载的视频资源')
    return
  }
  ElMessage.info('正在开始下载...')
  const ok = await downloadFile(url, `${video.title || '视频'}.mp4`)
  if (ok) {
    ElMessage.success('下载已开始')
  }
}

onMounted(() => {
  loadVideos()
  loadStores()
})
</script>

<style scoped lang="scss">
.library-videos {
  padding: 20px;
}
.filter-tabs {
  margin: 16px 0;
}
.store-filter {
  margin-bottom: 16px;
  .el-select { width: 200px; }
}
.video-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}
.video-card {
  background: #fff;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}
.video-cover {
  position: relative;
  aspect-ratio: 16/9;
  background: #f0f0f0;
  cursor: pointer;
  img { width: 100%; height: 100%; object-fit: cover; }
  .video-duration {
    position: absolute; bottom: 8px; right: 8px;
    background: rgba(0,0,0,0.7); color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 12px;
  }
  .video-play {
    position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
    width: 48px; height: 48px; background: rgba(123,80,255,0.9); border-radius: 50%;
    display: flex; align-items: center; justify-content: center; color: #fff; font-size: 24px;
  }
}
.video-info { padding: 12px; }
.video-title { font-size: 14px; font-weight: 500; color: #181224; margin-bottom: 8px; }
.video-meta { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; font-size: 12px; color: #746b80; }
.video-actions { display: flex; gap: 8px; }
</style>
