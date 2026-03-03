<template>
  <div class="video-library">
    <div class="header">
      <h2>视频模板库</h2>
      <p class="subtitle">选择视频模板,快速创建您的视频内容</p>
    </div>

    <!-- 筛选器 -->
    <div class="filters">
      <el-form :inline="true" :model="filters" class="filter-form">
        <el-form-item label="关键词">
          <el-input
            v-model="filters.keyword"
            placeholder="搜索模板名称"
            clearable
            @clear="handleSearch"
          >
            <template #suffix>
              <el-icon @click="handleSearch"><Search /></el-icon>
            </template>
          </el-input>
        </el-form-item>

        <el-form-item label="分类">
          <el-select
            v-model="filters.category"
            placeholder="选择分类"
            clearable
            @change="handleSearch"
          >
            <el-option
              v-for="cat in categories"
              :key="cat.category"
              :label="`${cat.category} (${cat.count})`"
              :value="cat.category"
            />
          </el-select>
        </el-form-item>

        <el-form-item label="行业">
          <el-select
            v-model="filters.industry"
            placeholder="选择行业"
            clearable
            @change="handleSearch"
          >
            <el-option
              v-for="industry in filterOptions.industries"
              :key="industry"
              :label="industry"
              :value="industry"
            />
          </el-select>
        </el-form-item>

        <el-form-item label="难度">
          <el-select
            v-model="filters.difficulty"
            placeholder="选择难度"
            clearable
            @change="handleSearch"
          >
            <el-option
              v-for="(label, value) in filterOptions.difficulties"
              :key="value"
              :label="label"
              :value="value"
            />
          </el-select>
        </el-form-item>

        <el-form-item label="宽高比">
          <el-select
            v-model="filters.aspect_ratio"
            placeholder="选择宽高比"
            clearable
            @change="handleSearch"
          >
            <el-option
              v-for="(label, value) in filterOptions.aspect_ratios"
              :key="value"
              :label="label"
              :value="value"
            />
          </el-select>
        </el-form-item>

        <el-form-item label="排序">
          <el-select
            v-model="filters.sort_by"
            @change="handleSearch"
          >
            <el-option
              v-for="(label, value) in filterOptions.sort_options"
              :key="value"
              :label="label"
              :value="value"
            />
          </el-select>
        </el-form-item>

        <el-form-item>
          <el-button type="primary" @click="handleSearch">搜索</el-button>
          <el-button @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>
    </div>

    <!-- 热门模板 -->
    <div v-if="!filters.keyword && currentPage === 1" class="hot-templates">
      <div class="section-header">
        <h3>热门模板</h3>
        <el-link type="primary" @click="showAllHot">查看全部</el-link>
      </div>
      <div class="hot-grid">
        <div
          v-for="template in hotTemplates"
          :key="template.id"
          class="hot-item"
          @click="viewTemplate(template)"
        >
          <div class="thumbnail">
            <img :src="template.preview_url || '/default-template.jpg'" :alt="template.name" />
            <div class="overlay">
              <el-button type="primary" size="small" @click.stop="useTemplate(template)">
                使用此模板
              </el-button>
            </div>
          </div>
          <div class="info">
            <h4>{{ template.name }}</h4>
            <div class="meta">
              <span class="duration">{{ formatDuration(template.video_duration) }}</span>
              <span class="usage">{{ template.usage_count }} 次使用</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 模板列表 -->
    <div class="template-list">
      <div class="list-header">
        <h3>全部模板</h3>
        <div class="stats">
          共 {{ total }} 个模板
        </div>
      </div>

      <div v-loading="loading" class="grid">
        <div
          v-for="template in templates"
          :key="template.id"
          class="template-card"
        >
          <div class="card-thumbnail" @click="viewTemplate(template)">
            <img :src="template.preview_url || '/default-template.jpg'" :alt="template.name" />
            <div class="card-overlay">
              <el-button
                type="primary"
                size="small"
                @click.stop="useTemplate(template)"
              >
                使用此模板
              </el-button>
              <el-button
                size="small"
                circle
                @click.stop="previewTemplate(template)"
              >
                <el-icon><View /></el-icon>
              </el-button>
            </div>
            <div v-if="template.aspect_ratio" class="aspect-ratio">
              {{ template.aspect_ratio }}
            </div>
          </div>

          <div class="card-content">
            <h4 class="card-title" :title="template.name">
              {{ template.name }}
            </h4>

            <div class="card-meta">
              <span class="category">{{ template.category }}</span>
              <span v-if="template.difficulty" class="difficulty">
                {{ getDifficultyLabel(template.difficulty) }}
              </span>
            </div>

            <div class="card-footer">
              <span class="duration">
                <el-icon><VideoCamera /></el-icon>
                {{ formatDuration(template.video_duration) }}
              </span>
              <span class="usage">
                <el-icon><View /></el-icon>
                {{ template.usage_count }}
              </span>
            </div>

            <div v-if="template.template_tags && template.template_tags.length" class="card-tags">
              <el-tag
                v-for="(tag, idx) in template.template_tags.slice(0, 3)"
                :key="idx"
                size="small"
                type="info"
              >
                {{ tag }}
              </el-tag>
            </div>
          </div>
        </div>

        <!-- 空状态 -->
        <div v-if="!loading && templates.length === 0" class="empty-state">
          <el-empty description="暂无视频模板" />
        </div>
      </div>

      <!-- 分页 -->
      <div v-if="total > 0" class="pagination">
        <el-pagination
          v-model:current-page="currentPage"
          v-model:page-size="pageSize"
          :total="total"
          :page-sizes="[12, 24, 48, 96]"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="handleSizeChange"
          @current-change="handlePageChange"
        />
      </div>
    </div>

    <!-- 模板详情对话框 -->
    <el-dialog
      v-model="detailVisible"
      :title="currentTemplate?.name"
      width="800px"
      @close="closeDetail"
    >
      <div v-if="currentTemplate" class="template-detail">
        <div class="detail-thumbnail">
          <img :src="currentTemplate.preview_url || '/default-template.jpg'" :alt="currentTemplate.name" />
          <div v-if="currentTemplate.video_url" class="video-url">
            <el-text size="small" type="info">视频URL: {{ currentTemplate.video_url }}</el-text>
          </div>
        </div>

        <el-descriptions :column="2" border>
          <el-descriptions-item label="模板名称">{{ currentTemplate.name }}</el-descriptions-item>
          <el-descriptions-item label="分类">{{ currentTemplate.category }}</el-descriptions-item>
          <el-descriptions-item label="风格">{{ currentTemplate.style }}</el-descriptions-item>
          <el-descriptions-item label="行业">{{ currentTemplate.industry || '-' }}</el-descriptions-item>
          <el-descriptions-item label="时长">{{ formatDuration(currentTemplate.video_duration) }}</el-descriptions-item>
          <el-descriptions-item label="分辨率">{{ currentTemplate.video_resolution || '-' }}</el-descriptions-item>
          <el-descriptions-item label="宽高比">{{ currentTemplate.aspect_ratio }}</el-descriptions-item>
          <el-descriptions-item label="难度">{{ getDifficultyLabel(currentTemplate.difficulty) }}</el-descriptions-item>
          <el-descriptions-item label="格式">{{ currentTemplate.video_format || '-' }}</el-descriptions-item>
          <el-descriptions-item label="使用次数">{{ currentTemplate.usage_count }}</el-descriptions-item>
          <el-descriptions-item label="来源">{{ currentTemplate.merchant_name }}</el-descriptions-item>
          <el-descriptions-item label="创建时间">{{ formatDate(currentTemplate.create_time) }}</el-descriptions-item>
        </el-descriptions>

        <div v-if="currentTemplate.template_tags && currentTemplate.template_tags.length" class="detail-tags">
          <h4>标签</h4>
          <el-space wrap>
            <el-tag
              v-for="(tag, idx) in currentTemplate.template_tags"
              :key="idx"
              type="info"
            >
              {{ tag }}
            </el-tag>
          </el-space>
        </div>
      </div>

      <template #footer>
        <el-button @click="closeDetail">关闭</el-button>
        <el-button type="primary" @click="useCurrentTemplate">使用此模板</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Search, View, VideoCamera, VideoPlay } from '@element-plus/icons-vue'
import { 
  getVideoLibraryList, 
  getVideoCategories, 
  getVideoFilters, 
  getHotVideos, 
  useVideoTemplate 
} from '@/api/video-library'

// 数据
const loading = ref(false)
const templates = ref([])
const hotTemplates = ref([])
const categories = ref([])
const filterOptions = ref({
  industries: [],
  difficulties: {},
  aspect_ratios: {},
  sort_options: {}
})

const total = ref(0)
const currentPage = ref(1)
const pageSize = ref(12)

// 筛选条件
const filters = reactive({
  keyword: '',
  category: '',
  industry: '',
  difficulty: '',
  aspect_ratio: '',
  sort_by: 'create_time'
})

// 详情对话框
const detailVisible = ref(false)
const currentTemplate = ref(null)

// 获取筛选选项
const getFilterOptions = async () => {
  try {
    const data = await getVideoFilters()
    if (data) {
      filterOptions.value = data
    }
  } catch (error) {
    console.error('获取筛选选项失败:', error)
  }
}

// 获取分类列表
const getCategories = async () => {
  try {
    const data = await getVideoCategories()
    if (data) {
      categories.value = data
    }
  } catch (error) {
    console.error('获取分类失败:', error)
  }
}

// 获取热门模板
const getHotTemplates = async () => {
  try {
    const data = await getHotVideos({ limit: 6 })
    if (data) {
      hotTemplates.value = Array.isArray(data) ? data : []
    }
  } catch (error) {
    console.error('获取热门模板失败:', error)
  }
}

// 获取模板列表
const getTemplates = async () => {
  loading.value = true
  try {
    const params = {
      page: currentPage.value,
      limit: pageSize.value,
      ...filters
    }

    // 移除空值
    Object.keys(params).forEach(key => {
      if (params[key] === '' || params[key] === null || params[key] === undefined) {
        delete params[key]
      }
    })

    const response = await getVideoLibraryList(params)
    if (response) {
      templates.value = response.list || []
      total.value = response.pagination?.total || 0
    }
  } catch (error) {
    ElMessage.error('获取模板列表失败')
    console.error(error)
  } finally {
    loading.value = false
  }
}

// 搜索
const handleSearch = () => {
  currentPage.value = 1
  getTemplates()
}

// 重置
const handleReset = () => {
  Object.keys(filters).forEach(key => {
    if (key === 'sort_by') {
      filters[key] = 'create_time'
    } else {
      filters[key] = ''
    }
  })
  handleSearch()
}

// 页码变化
const handlePageChange = (page) => {
  currentPage.value = page
  getTemplates()
  // 滚动到顶部
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

// 每页数量变化
const handleSizeChange = (size) => {
  pageSize.value = size
  currentPage.value = 1
  getTemplates()
}

// 查看模板详情
const viewTemplate = (template) => {
  currentTemplate.value = template
  detailVisible.value = true
}

// 预览模板
const previewTemplate = (template) => {
  // TODO: 实现视频预览功能
  ElMessage.info('预览功能开发中')
}

// 使用模板
const useTemplate = async (template) => {
  try {
    await ElMessageBox.confirm(
      `确定要使用模板"${template.name}"吗?使用后会创建一个副本。`,
      '确认使用',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'info'
      }
    )

    const response = await useVideoTemplate(template.id)

    if (response) {
      ElMessage.success('模板使用成功!')
    }
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error('使用模板失败')
      console.error(error)
    }
  }
}

// 使用当前模板
const useCurrentTemplate = () => {
  if (currentTemplate.value) {
    useTemplate(currentTemplate.value)
    closeDetail()
  }
}

// 关闭详情
const closeDetail = () => {
  detailVisible.value = false
  currentTemplate.value = null
}

// 显示全部热门
const showAllHot = () => {
  filters.keyword = ''
  filters.sort_by = 'usage_count'
  handleSearch()
}

// 格式化时长
const formatDuration = (seconds) => {
  if (!seconds) return '-'
  const mins = Math.floor(seconds / 60)
  const secs = seconds % 60
  return mins > 0 ? `${mins}分${secs}秒` : `${secs}秒`
}

// 获取难度标签
const getDifficultyLabel = (difficulty) => {
  const labels = {
    easy: '简单',
    medium: '中等',
    hard: '困难'
  }
  return labels[difficulty] || '-'
}

// 格式化日期
const formatDate = (dateStr) => {
  if (!dateStr) return '-'
  const date = new Date(dateStr)
  return date.toLocaleString('zh-CN')
}

// 初始化
onMounted(() => {
  getFilterOptions()
  getCategories()
  getHotTemplates()
  getTemplates()
})
</script>

<style scoped lang="scss">
.video-library {
  padding: 20px;

  .header {
    margin-bottom: 30px;

    h2 {
      font-size: 28px;
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

  .filters {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 30px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);

    .filter-form {
      :deep(.el-form-item) {
        margin-bottom: 0;
      }
    }
  }

  .hot-templates {
    margin-bottom: 40px;

    .section-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;

      h3 {
        font-size: 20px;
        font-weight: 600;
        margin: 0;
      }
    }

    .hot-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 20px;

      .hot-item {
        cursor: pointer;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.1);
        transition: all 0.3s;

        &:hover {
          transform: translateY(-4px);
          box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        }

        .thumbnail {
          position: relative;
          aspect-ratio: 16/9;
          overflow: hidden;
          background: #f5f5f5;

          img {
            width: 100%;
            height: 100%;
            object-fit: cover;
          }

          .overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;

            &:hover {
              opacity: 1;
            }
          }
        }

        .info {
          padding: 12px;
          background: #fff;

          h4 {
            margin: 0 0 8px 0;
            font-size: 14px;
            font-weight: 500;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
          }

          .meta {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #909399;

            .duration::before {
              content: '⏱ ';
            }
          }
        }
      }
    }
  }

  .template-list {
    .list-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;

      h3 {
        font-size: 20px;
        font-weight: 600;
        margin: 0;
      }

      .stats {
        font-size: 14px;
        color: #909399;
      }
    }

    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 20px;
      min-height: 300px;

      .template-card {
        background: #fff;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        transition: all 0.3s;

        &:hover {
          box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
        }

        .card-thumbnail {
          position: relative;
          aspect-ratio: 16/9;
          overflow: hidden;
          background: #f5f5f5;
          cursor: pointer;

          img {
            width: 100%;
            height: 100%;
            object-fit: cover;
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

            &:hover {
              opacity: 1;
            }
          }

          .aspect-ratio {
            position: absolute;
            top: 8px;
            right: 8px;
            background: rgba(0, 0, 0, 0.6);
            color: #fff;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
          }
        }

        .card-content {
          padding: 12px;

          .card-title {
            margin: 0 0 8px 0;
            font-size: 14px;
            font-weight: 500;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
          }

          .card-meta {
            display: flex;
            gap: 8px;
            margin-bottom: 8px;

            .category, .difficulty {
              font-size: 12px;
              padding: 2px 8px;
              border-radius: 4px;
            }

            .category {
              background: #ecf5ff;
              color: #409eff;
            }

            .difficulty {
              background: #f0f9ff;
              color: #67c23a;
            }
          }

          .card-footer {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #909399;
            margin-bottom: 8px;

            .duration, .usage {
              display: flex;
              align-items: center;
              gap: 4px;
            }
          }

          .card-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
          }
        }
      }

      .empty-state {
        grid-column: 1 / -1;
        padding: 60px 0;
      }
    }

    .pagination {
      margin-top: 30px;
      display: flex;
      justify-content: center;
    }
  }

  .template-detail {
    .detail-thumbnail {
      margin-bottom: 20px;
      border-radius: 8px;
      overflow: hidden;

      img {
        width: 100%;
        display: block;
      }

      .video-url {
        padding: 12px;
        background: #f5f5f5;
      }
    }

    .detail-tags {
      margin-top: 20px;

      h4 {
        margin: 0 0 12px 0;
        font-size: 14px;
        font-weight: 500;
      }
    }
  }
}
</style>
