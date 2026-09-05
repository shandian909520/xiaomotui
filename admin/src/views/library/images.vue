<template>
  <div class="library-images">
    <PageHeader title="图文库" subtitle="查看和管理AI生成的图文内容" />

    <FilterToolbar showSearch placeholder="搜索图文标题..." @search="handleSearch" />

    <div class="image-grid">
      <div v-for="item in filteredImages" :key="item.id" class="image-card">
        <div class="image-cover" @click="handlePreview(item)">
          <img :src="item.cover" alt="封面" />
          <div class="image-type">{{ item.type }}</div>
        </div>
        <div class="image-info">
          <div class="image-title">{{ item.title }}</div>
          <div class="image-meta">
            <span>{{ item.platformName }}</span>
            <span>{{ item.views }}浏览</span>
          </div>
          <div class="image-actions">
            <el-button size="small" @click="handleCopy(item)">复制</el-button>
            <el-button size="small" type="primary" @click="handleDownload(item)">下载</el-button>
          </div>
        </div>
      </div>
    </div>

    <EmptyPanel v-if="filteredImages.length === 0" message="暂无图文数据" />

    <el-dialog v-model="previewVisible" title="图文预览" width="600px">
      <div v-if="currentItem" class="preview-content">
        <img :src="currentItem.cover" alt="封面" style="max-width: 100%;" />
        <div class="preview-text">{{ currentItem.content }}</div>
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { PageHeader, FilterToolbar, EmptyPanel } from '@/components/xmt'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getImages, deleteImage } from '@/api/library'
import { normalizeListPayload } from '@/utils/responseHelper'
import { downloadFile } from '@/utils/file'

const searchQuery = ref('')
const previewVisible = ref(false)
const currentItem = ref(null)
const loading = ref(false)


const images = ref([])

// 加载图文列表
const loadImageList = async () => {
  loading.value = true
  try {
    const params = {}
    if (searchQuery.value) params.keyword = searchQuery.value
    const res = await getImages(params)
    const rawList = normalizeListPayload(res)
    // 映射后端 ContentLibrary 字段到图文卡片期望的字段
    images.value = rawList.map(item => ({
      id: item.id,
      title: item.title || item.name || '未命名图文',
      cover: item.cover || item.thumbnail || '',
      content: item.content || item.description || '',
      type: item.type || item.libraryType || '图文',
      platformName: item.platformName || '',
      views: item.views || 0
    }))
  } catch (err) {
    console.error('获取图文列表失败:', err)
    images.value = []
    ElMessage.error('获取图文列表失败，请稍后重试')
  } finally {
    loading.value = false
  }
}

const filteredImages = computed(() => {
  return images.value.filter(item => !searchQuery.value || item.title.includes(searchQuery.value))
})

const handleSearch = (query) => {
  searchQuery.value = query
  loadImageList()
}
const handlePreview = (item) => { currentItem.value = item; previewVisible.value = true }
const handleCopy = (item) => { navigator.clipboard.writeText(item.content); ElMessage.success('已复制') }
const handleDownload = async (item) => {
  const url = item.cover || item.content || ''
  if (!url) {
    ElMessage.warning('暂无可下载的图片资源')
    return
  }
  // 如果是纯文本内容，下载为 txt 文件
  if (!item.cover && item.content && !item.content.startsWith('http')) {
    const blob = new Blob([item.content], { type: 'text/plain;charset=utf-8' })
    const blobUrl = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = blobUrl
    link.download = `${item.title || '图文内容'}.txt`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(blobUrl)
    ElMessage.success('下载已开始')
    return
  }
  ElMessage.info('正在开始下载...')
  const ok = await downloadFile(url, `${item.title || '图片'}.png`)
  if (ok) {
    ElMessage.success('下载已开始')
  }
}

onMounted(() => {
  loadImageList()
})
</script>

<style scoped lang="scss">
.library-images { padding: 20px; }
.image-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-top: 16px; }
.image-card { background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
.image-cover { position: relative; aspect-ratio: 1; background: #f0f0f0; cursor: pointer; img { width: 100%; height: 100%; object-fit: cover; } }
.image-type { position: absolute; top: 8px; left: 8px; background: #7b50ff; color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 12px; }
.image-info { padding: 12px; }
.image-title { font-size: 14px; font-weight: 500; color: #181224; margin-bottom: 8px; }
.image-meta { display: flex; gap: 12px; font-size: 12px; color: #746b80; margin-bottom: 12px; }
.image-actions { display: flex; gap: 8px; }
.preview-content { img { max-width: 100%; border-radius: 8px; } .preview-text { margin-top: 16px; line-height: 1.6; } }
</style>
