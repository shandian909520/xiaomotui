<template>
  <div class="library-topics">
    <PageHeader title="话题库" subtitle="热门话题和趋势话题管理" />

    <FilterToolbar showSearch showExport placeholder="搜索话题..." @search="handleSearch" @export="handleExport" />

    <div class="topic-grid">
      <div v-for="topic in filteredTopics" :key="topic.id" class="topic-card">
        <div class="topic-header">
          <span class="topic-name">#{{ topic.name }}#</span>
          <el-tag :type="topic.hot >= 10000 ? 'danger' : 'warning'" size="small">{{ topic.hot >= 10000 ? '热搜' : '热门' }}</el-tag>
        </div>
        <div class="topic-desc">{{ topic.description }}</div>
        <div class="topic-stats">
          <span>浏览 {{ topic.views }}</span>
          <span>参与 {{ topic.participate }}</span>
        </div>
        <div class="topic-actions">
          <el-button size="small" @click="handleCopy(topic)">复制话题</el-button>
          <el-button size="small" type="primary" @click="handleUse(topic)">立即使用</el-button>
        </div>
      </div>
    </div>

    <EmptyPanel v-if="filteredTopics.length === 0" message="暂无话题数据" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { PageHeader, FilterToolbar, EmptyPanel } from '@/components/xmt'
import { ElMessage } from 'element-plus'
import { getTopics, deleteTopic } from '@/api/library'
import { normalizeListPayload } from '@/utils/responseHelper'

const searchQuery = ref('')
const loading = ref(false)


const topics = ref([])

// 加载话题列表
const loadTopics = async () => {
  loading.value = true
  try {
    const params = {}
    if (searchQuery.value) params.keyword = searchQuery.value
    const res = await getTopics(params)
    const rawList = normalizeListPayload(res)
    // 映射后端 ContentLibrary 字段到话题卡片期望的字段
    topics.value = rawList.map(item => ({
      id: item.id,
      name: item.name || item.title || '',
      description: item.description || '',
      hot: item.hot || item.totalCount || 0,
      views: item.views || item.totalCount || 0,
      participate: item.participate || item.usedCount || 0
    }))
  } catch (err) {
    console.error('获取话题列表失败:', err)
    topics.value = []
    ElMessage.error('获取话题列表失败，请稍后重试')
  } finally {
    loading.value = false
  }
}

const filteredTopics = computed(() => {
  return topics.value.filter(t => !searchQuery.value || t.name.includes(searchQuery.value))
})

const handleSearch = (query) => {
  searchQuery.value = query
  loadTopics()
}
const handleExport = () => {
  if (!topics.value.length) {
    ElMessage.warning('暂无数据可导出')
    return
  }
  try {
    const columns = [
      { label: '话题名称', key: 'name' },
      { label: '描述', key: 'description' },
      { label: '热度', key: 'hot' },
      { label: '浏览量', key: 'views' },
      { label: '参与数', key: 'participate' }
    ]
    const header = columns.map(c => c.label).join(',')
    const rows = topics.value.map(row =>
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
    link.download = `话题库_${new Date().toISOString().slice(0, 10)}.csv`
    link.click()
    URL.revokeObjectURL(url)
    ElMessage.success('导出成功')
  } catch (e) {
    console.error('导出失败:', e)
    ElMessage.error('导出失败')
  }
}
const handleCopy = (topic) => { navigator.clipboard.writeText(`#${topic.name}#`); ElMessage.success('话题已复制') }
const handleUse = (topic) => {
  navigator.clipboard.writeText(`#${topic.name}#`).then(() => {
    ElMessage.success('话题已复制到剪贴板')
  }).catch(() => {
    ElMessage.error('复制失败，请手动复制')
  })
}

onMounted(() => {
  loadTopics()
})
</script>

<style scoped lang="scss">
.library-topics { padding: 20px; }
.topic-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-top: 16px; }
.topic-card { background: #fff; border-radius: 12px; padding: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
.topic-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.topic-name { font-size: 16px; font-weight: 600; color: #7b50ff; }
.topic-desc { font-size: 14px; color: #746b80; margin-bottom: 12px; line-height: 1.5; }
.topic-stats { display: flex; gap: 16px; font-size: 12px; color: #746b80; margin-bottom: 12px; }
.topic-actions { display: flex; gap: 8px; }
</style>
