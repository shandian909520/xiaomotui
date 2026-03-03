<template>
  <div class="promo-template">
    <!-- 页面头部 -->
    <div class="page-header">
      <div class="header-left">
        <h2>视频模板</h2>
        <p class="subtitle">配置视频合成规则，生成推广视频变体</p>
      </div>
      <div class="header-right">
        <el-button type="primary" icon="Plus" @click="handleCreate">
          创建模板
        </el-button>
      </div>
    </div>

    <!-- 视图切换 -->
    <div class="filter-bar">
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

    <!-- 模板列表 - 卡片视图 -->
    <div v-if="viewMode === 'card'" v-loading="loading" class="template-grid">
      <div
        v-for="item in templateList"
        :key="item.id"
        class="template-card"
      >
        <div class="card-preview">
          <div class="preview-images">
            <div
              v-for="(material, index) in item.preview_materials?.slice(0, 4)"
              :key="index"
              class="preview-item"
            >
              <el-image
                v-if="material.type === 'image'"
                :src="material.url"
                fit="cover"
                class="preview-thumb"
              >
                <template #error>
                  <div class="thumb-error">
                    <el-icon><Picture /></el-icon>
                  </div>
                </template>
              </el-image>
              <div v-else class="video-thumb">
                <el-icon><VideoPlay /></el-icon>
              </div>
            </div>
          </div>
          <div class="card-overlay">
            <el-button type="primary" size="small" @click="handleViewVariants(item)">
              <el-icon><View /></el-icon>
              查看变体
            </el-button>
          </div>
        </div>

        <div class="card-info">
          <div class="card-name" :title="item.name">{{ item.name }}</div>
          <div class="card-meta">
            <el-tag type="info" size="small">
              {{ item.material_count || 0 }} 个素材
            </el-tag>
            <el-tag type="success" size="small">
              {{ item.variant_count || 0 }} 个变体
            </el-tag>
          </div>
          <div class="card-config">
            <span><el-icon><Timer /></el-icon> {{ item.duration_per_image }}秒/张</span>
            <span><el-icon><MagicStick /></el-icon> {{ getTransitionLabel(item.transition_effect) }}</span>
          </div>
          <div class="card-time">{{ formatDate(item.create_time) }}</div>
        </div>

        <div class="card-actions">
          <el-button type="primary" size="small" @click="handleGenerate(item)">
            <el-icon><MagicStick /></el-icon>
            生成变体
          </el-button>
          <el-button size="small" @click="handleEdit(item)">
            <el-icon><Edit /></el-icon>
            编辑
          </el-button>
          <el-button type="danger" size="small" @click="handleDelete(item)">
            <el-icon><Delete /></el-icon>
          </el-button>
        </div>
      </div>

      <!-- 空状态 -->
      <div v-if="!loading && templateList.length === 0" class="empty-state">
        <el-empty description="暂无模板">
          <el-button type="primary" @click="handleCreate">创建模板</el-button>
        </el-empty>
      </div>
    </div>

    <!-- 模板列表 - 表格视图 -->
    <div v-if="viewMode === 'list'" v-loading="loading" class="template-list">
      <el-table :data="templateList" border stripe>
        <el-table-column label="模板名称" prop="name" min-width="180" show-overflow-tooltip />
        <el-table-column label="素材数量" width="100" align="center">
          <template #default="{ row }">
            <el-tag type="info" size="small">{{ row.material_count || 0 }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="变体数量" width="100" align="center">
          <template #default="{ row }">
            <el-tag type="success" size="small">{{ row.variant_count || 0 }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="每张时长" width="100" align="center">
          <template #default="{ row }">
            {{ row.duration_per_image }}秒
          </template>
        </el-table-column>
        <el-table-column label="转场效果" width="120" align="center">
          <template #default="{ row }">
            {{ getTransitionLabel(row.transition_effect) }}
          </template>
        </el-table-column>
        <el-table-column label="创建时间" width="180" align="center">
          <template #default="{ row }">
            {{ formatDate(row.create_time) }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="240" align="center" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" size="small" @click="handleGenerate(row)">
              生成变体
            </el-button>
            <el-button size="small" @click="handleEdit(row)">
              编辑
            </el-button>
            <el-button type="danger" size="small" @click="handleDelete(row)">
              删除
            </el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- 空状态 -->
      <div v-if="!loading && templateList.length === 0" class="empty-state">
        <el-empty description="暂无模板">
          <el-button type="primary" @click="handleCreate">创建模板</el-button>
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

    <!-- 创建/编辑模板对话框 -->
    <el-dialog
      v-model="dialogVisible"
      :title="dialogType === 'create' ? '创建模板' : '编辑模板'"
      width="800px"
      destroy-on-close
      :close-on-click-modal="false"
    >
      <el-form
        ref="formRef"
        :model="formData"
        :rules="formRules"
        label-width="120px"
        class="template-form"
      >
        <el-form-item label="模板名称" prop="name">
          <el-input
            v-model="formData.name"
            placeholder="请输入模板名称"
            maxlength="50"
            show-word-limit
          />
        </el-form-item>

        <el-form-item label="选择素材" prop="materials">
          <MaterialSelector
            v-model="formData.materials"
            :max-select="20"
            :allowed-types="['image']"
            @change="handleMaterialChange"
          />
          <div class="form-tip">支持拖拽排序，最多选择20个图片素材</div>
        </el-form-item>

        <el-form-item label="每张时长" prop="duration_per_image">
          <el-input-number
            v-model="formData.duration_per_image"
            :min="1"
            :max="10"
            :step="0.5"
            :precision="1"
          />
          <span class="unit">秒</span>
        </el-form-item>

        <el-form-item label="转场效果" prop="transition_effect">
          <el-select v-model="formData.transition_effect" placeholder="请选择转场效果">
            <el-option
              v-for="item in transitionOptions"
              :key="item.value"
              :label="item.label"
              :value="item.value"
            />
          </el-select>
        </el-form-item>

        <el-form-item label="背景音乐" prop="background_music_id">
          <el-select
            v-model="formData.background_music_id"
            placeholder="请选择背景音乐"
            clearable
            :loading="musicLoading"
          >
            <el-option
              v-for="item in musicList"
              :key="item.id"
              :label="item.name"
              :value="item.id"
            >
              <span>{{ item.name }}</span>
              <span style="color: #909399; font-size: 12px; margin-left: 8px;">
                {{ formatDuration(item.duration) }}
              </span>
            </el-option>
          </el-select>
          <div class="form-tip">可选项，从素材库音频中选择</div>
        </el-form-item>

        <el-form-item label="变体数量" prop="variant_count">
          <el-input-number
            v-model="formData.variant_count"
            :min="1"
            :max="100"
          />
          <span class="unit">个</span>
          <div class="form-tip">基于模板生成的视频变体数量，每个变体会随机调整素材顺序</div>
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button
          v-if="dialogType === 'create'"
          type="primary"
          :loading="submitLoading"
          @click="handleSubmit"
        >
          创建并生成
        </el-button>
        <el-button
          v-else
          type="primary"
          :loading="submitLoading"
          @click="handleSubmit"
        >
          保存
        </el-button>
      </template>
    </el-dialog>

    <!-- 生成变体对话框 -->
    <el-dialog
      v-model="generateVisible"
      title="生成变体"
      width="400px"
      destroy-on-close
    >
      <el-form :model="generateForm" label-width="100px">
        <el-form-item label="模板名称">
          <span>{{ currentTemplate?.name }}</span>
        </el-form-item>
        <el-form-item label="生成数量">
          <el-input-number
            v-model="generateForm.count"
            :min="1"
            :max="50"
          />
          <span class="unit">个</span>
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="generateVisible = false">取消</el-button>
        <el-button
          type="primary"
          :loading="generateLoading"
          @click="confirmGenerate"
        >
          开始生成
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  Plus,
  View,
  Edit,
  Delete,
  Timer,
  MagicStick,
  Picture,
  VideoPlay,
  Grid,
  List
} from '@element-plus/icons-vue'
import {
  createTemplate,
  getTemplateList,
  getTemplateDetail,
  updateTemplate,
  deleteTemplate,
  generateVariants
} from '@/api/promo-template'
import { getMaterialList } from '@/api/promo-material'
import MaterialSelector from '@/components/MaterialSelector.vue'

const router = useRouter()

// 数据状态
const loading = ref(false)
const templateList = ref([])
const total = ref(0)

// 查询参数
const listQuery = reactive({
  page: 1,
  limit: 12,
  keyword: ''
})

// 视图模式
const viewMode = ref('card')
const viewOptions = [
  { label: 'card', icon: Grid },
  { label: 'list', icon: List }
]

// 对话框
const dialogVisible = ref(false)
const dialogType = ref('create') // create | edit
const formRef = ref(null)
const submitLoading = ref(false)

// 表单数据
const formData = reactive({
  name: '',
  materials: [],
  duration_per_image: 3,
  transition_effect: 'fade',
  background_music_id: null,
  variant_count: 10
})

// 表单验证规则
const formRules = {
  name: [
    { required: true, message: '请输入模板名称', trigger: 'blur' },
    { min: 2, max: 50, message: '长度在 2 到 50 个字符', trigger: 'blur' }
  ],
  materials: [
    { required: true, validator: validateMaterials, trigger: 'change' }
  ],
  duration_per_image: [
    { required: true, message: '请设置每张图片时长', trigger: 'blur' }
  ],
  transition_effect: [
    { required: true, message: '请选择转场效果', trigger: 'change' }
  ]
}

// 自定义验证素材
function validateMaterials(rule, value, callback) {
  if (!value || value.length < 2) {
    callback(new Error('请至少选择2个素材'))
  } else {
    callback()
  }
}

// 转场效果选项
const transitionOptions = [
  { label: '淡入淡出', value: 'fade' },
  { label: '滑动', value: 'slide' },
  { label: '缩放', value: 'zoom' },
  { label: '旋转', value: 'rotate' },
  { label: '翻转', value: 'flip' },
  { label: '模糊', value: 'blur' }
]

// 音乐列表
const musicList = ref([])
const musicLoading = ref(false)

// 生成变体
const generateVisible = ref(false)
const generateForm = reactive({
  count: 10
})
const currentTemplate = ref(null)
const generateLoading = ref(false)

// 获取模板列表
const getList = async () => {
  loading.value = true
  try {
    const params = { ...listQuery }
    Object.keys(params).forEach(key => {
      if (params[key] === '' || params[key] === null || params[key] === undefined) {
        delete params[key]
      }
    })

    const response = await getTemplateList(params)
    if (response) {
      if (response.list) {
        templateList.value = response.list
        total.value = response.pagination?.total || 0
      } else if (response.data) {
        templateList.value = Array.isArray(response.data) ? response.data : []
        total.value = response.total || templateList.value.length
      } else if (Array.isArray(response)) {
        templateList.value = response
        total.value = response.length
      }
    }
  } catch (error) {
    console.error('获取模板列表失败:', error)
    ElMessage.error('获取模板列表失败')
  } finally {
    loading.value = false
  }
}

// 获取音乐列表
const getMusicList = async () => {
  musicLoading.value = true
  try {
    const response = await getMaterialList({ type: 'audio', limit: 100 })
    if (response) {
      if (response.list) {
        musicList.value = response.list
      } else if (response.data) {
        musicList.value = Array.isArray(response.data) ? response.data : []
      } else if (Array.isArray(response)) {
        musicList.value = response
      }
    }
  } catch (error) {
    console.error('获取音乐列表失败:', error)
  } finally {
    musicLoading.value = false
  }
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

// 创建模板
const handleCreate = () => {
  dialogType.value = 'create'
  resetForm()
  getMusicList()
  dialogVisible.value = true
}

// 编辑模板
const handleEdit = async (item) => {
  dialogType.value = 'edit'
  resetForm()
  getMusicList()

  try {
    const detail = await getTemplateDetail(item.id)
    if (detail) {
      formData.name = detail.name
      formData.materials = detail.materials || []
      formData.duration_per_image = detail.duration_per_image
      formData.transition_effect = detail.transition_effect
      formData.background_music_id = detail.background_music_id
      formData.variant_count = detail.variant_count || 10
      currentTemplate.value = detail
    }
  } catch (error) {
    console.error('获取模板详情失败:', error)
    ElMessage.error('获取模板详情失败')
  }

  dialogVisible.value = true
}

// 重置表单
const resetForm = () => {
  formData.name = ''
  formData.materials = []
  formData.duration_per_image = 3
  formData.transition_effect = 'fade'
  formData.background_music_id = null
  formData.variant_count = 10
  currentTemplate.value = null
  formRef.value?.resetFields()
}

// 素材变化
const handleMaterialChange = (materials) => {
  formData.materials = materials
}

// 提交表单
const handleSubmit = async () => {
  if (!formRef.value) return

  try {
    await formRef.value.validate()
  } catch {
    return
  }

  submitLoading.value = true
  try {
    const data = {
      name: formData.name,
      materials: formData.materials.map(m => m.id),
      duration_per_image: formData.duration_per_image,
      transition_effect: formData.transition_effect,
      background_music_id: formData.background_music_id,
      variant_count: formData.variant_count
    }

    if (dialogType.value === 'create') {
      const result = await createTemplate(data)
      ElMessage.success('模板创建成功，正在生成变体...')
      dialogVisible.value = false
      getList()
      // 创建后自动生成变体
      if (result && result.id) {
        await generateVariants(result.id, formData.variant_count)
        ElMessage.success(`已生成 ${formData.variant_count} 个变体`)
      }
    } else {
      await updateTemplate(currentTemplate.value.id, data)
      ElMessage.success('模板更新成功')
      dialogVisible.value = false
      getList()
    }
  } catch (error) {
    console.error('操作失败:', error)
    ElMessage.error('操作失败')
  } finally {
    submitLoading.value = false
  }
}

// 删除模板
const handleDelete = async (item) => {
  try {
    await ElMessageBox.confirm(
      `确定要删除模板"${item.name}"吗？删除后相关的变体也会被删除。`,
      '删除确认',
      {
        confirmButtonText: '确定删除',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )

    await deleteTemplate(item.id)
    ElMessage.success('删除成功')
    getList()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除模板失败:', error)
      ElMessage.error('删除失败')
    }
  }
}

// 生成变体
const handleGenerate = (item) => {
  currentTemplate.value = item
  generateForm.count = 10
  generateVisible.value = true
}

// 确认生成
const confirmGenerate = async () => {
  if (!currentTemplate.value) return

  generateLoading.value = true
  try {
    await generateVariants(currentTemplate.value.id, generateForm.count)
    ElMessage.success(`已提交生成 ${generateForm.count} 个变体的任务`)
    generateVisible.value = false
    getList()
  } catch (error) {
    console.error('生成变体失败:', error)
    ElMessage.error('生成变体失败')
  } finally {
    generateLoading.value = false
  }
}

// 查看变体
const handleViewVariants = (item) => {
  router.push({
    path: '/promo/variant',
    query: { template_id: item.id }
  })
}

// 获取转场效果标签
const getTransitionLabel = (value) => {
  const item = transitionOptions.find(o => o.value === value)
  return item ? item.label : value
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
.promo-template {
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
    justify-content: flex-end;
    align-items: center;
    margin-bottom: 20px;
    padding: 16px 20px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  }

  .template-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
    min-height: 300px;

    .template-card {
      background: #fff;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
      transition: all 0.3s;

      &:hover {
        box-shadow: 0 4px 20px rgba(99, 102, 241, 0.15);
        transform: translateY(-4px);

        .card-overlay {
          opacity: 1;
        }
      }

      .card-preview {
        position: relative;
        height: 160px;
        background: #f5f7fa;
        overflow: hidden;

        .preview-images {
          display: grid;
          grid-template-columns: repeat(2, 1fr);
          gap: 2px;
          height: 100%;
          padding: 2px;

          .preview-item {
            .preview-thumb {
              width: 100%;
              height: 100%;
            }

            .video-thumb,
            .thumb-error {
              width: 100%;
              height: 100%;
              display: flex;
              align-items: center;
              justify-content: center;
              background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
              color: #fff;
              font-size: 24px;
            }

            .thumb-error {
              background: #e9ecef;
              color: #adb5bd;
            }
          }
        }

        .card-overlay {
          position: absolute;
          inset: 0;
          background: rgba(0, 0, 0, 0.5);
          display: flex;
          align-items: center;
          justify-content: center;
          opacity: 0;
          transition: opacity 0.3s;
        }
      }

      .card-info {
        padding: 16px;

        .card-name {
          font-size: 16px;
          font-weight: 600;
          color: #303133;
          margin-bottom: 12px;
          overflow: hidden;
          text-overflow: ellipsis;
          white-space: nowrap;
        }

        .card-meta {
          display: flex;
          gap: 8px;
          margin-bottom: 8px;
        }

        .card-config {
          display: flex;
          gap: 16px;
          font-size: 12px;
          color: #909399;
          margin-bottom: 8px;

          span {
            display: flex;
            align-items: center;
            gap: 4px;
          }
        }

        .card-time {
          font-size: 12px;
          color: #c0c4cc;
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

  .template-list {
    background: #fff;
    border-radius: 8px;
    padding: 20px;
    min-height: 300px;

    .empty-state {
      padding: 60px 0;
    }
  }

  .pagination {
    margin-top: 24px;
    display: flex;
    justify-content: center;
  }

  .template-form {
    .form-tip {
      font-size: 12px;
      color: #909399;
      margin-top: 4px;
    }

    .unit {
      margin-left: 8px;
      color: #606266;
    }
  }
}
</style>
