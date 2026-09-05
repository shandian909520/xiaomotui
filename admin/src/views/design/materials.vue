<template>
  <div class="design-materials-page">
    <template v-if="!currentScene">
      <div class="page-header">
        <div class="header-content">
          <h2>物料设计</h2>
          <p class="subtitle">选择场景，快速生成专属营销物料</p>
        </div>
        <div class="header-stats">
          <span class="stat-item"><strong>{{ scenes.length }}</strong> 个设计场景</span>
          <span class="stat-divider">·</span>
          <span class="stat-item"><strong>{{ totalTemplates }}</strong> 套模板</span>
        </div>
      </div>

      <div v-loading="loading" class="scene-grid">
        <div
          v-for="scene in scenes"
          :key="scene.sceneKey"
          class="scene-card"
          @click="enterScene(scene)"
        >
          <div class="scene-icon-wrap" :style="{ background: getSceneBg(scene.sceneKey) }">
            <span class="scene-icon">{{ getSceneIcon(scene.sceneKey) }}</span>
          </div>
          <div class="scene-info">
            <h3>{{ scene.sceneName }}</h3>
            <p>{{ scene.description }}</p>
            <span class="template-count">{{ scene.templateCount || 0 }} 套模板</span>
          </div>
          <el-icon class="scene-arrow"><ArrowRight /></el-icon>
        </div>

        <div v-if="!loading && scenes.length === 0" class="empty-state">
          <el-empty description="暂无设计场景" />
        </div>
      </div>
    </template>

    <template v-else>
      <div class="template-page">
        <div class="breadcrumb-bar">
          <el-breadcrumb separator="/">
            <el-breadcrumb-item>
              <a class="breadcrumb-link" @click="exitScene">物料设计</a>
            </el-breadcrumb-item>
            <el-breadcrumb-item>{{ currentScene.sceneName }}</el-breadcrumb-item>
          </el-breadcrumb>
        </div>

        <div class="template-header">
          <div class="template-header-left">
            <div class="scene-icon-sm" :style="{ background: getSceneBg(currentScene.sceneKey) }">
              {{ getSceneIcon(currentScene.sceneKey) }}
            </div>
            <div>
              <h3>{{ currentScene.sceneName }}</h3>
              <p>{{ currentScene.description }}</p>
            </div>
          </div>
          <el-button @click="exitScene">
            <el-icon><ArrowLeft /></el-icon>
            返回场景列表
          </el-button>
        </div>

        <div v-loading="templateLoading" class="template-grid">
          <div
            v-for="tpl in templates"
            :key="tpl.id"
            class="template-card"
            @click="openDesign(tpl)"
          >
            <div class="tpl-thumbnail">
              <el-image :src="tpl.thumbnail" fit="cover" class="tpl-img">
                <template #error>
                  <div class="img-error">
                    <el-icon><Picture /></el-icon>
                    <span>{{ tpl.name }}</span>
                  </div>
                </template>
              </el-image>
              <div class="tpl-size-tag" v-if="tpl.size">{{ tpl.size }}</div>
            </div>
            <div class="tpl-info">
              <h4>{{ tpl.name }}</h4>
              <div class="tpl-meta">
                <span v-if="tpl.dpi">{{ tpl.dpi }} DPI</span>
                <el-tag size="small" type="info">{{ tpl.format || 'PNG' }}</el-tag>
              </div>
            </div>
            <div class="tpl-hover-action">
              <el-button type="primary" size="small">选择模板</el-button>
            </div>
          </div>

          <div v-if="!templateLoading && templates.length === 0" class="empty-state">
            <el-empty description="暂无模板" />
          </div>
        </div>

        <div v-if="templateTotal > 0" class="pagination-wrap">
          <el-pagination
            v-model:current-page="templatePage"
            v-model:page-size="templatePageSize"
            :total="templateTotal"
            :page-sizes="[12, 24, 36]"
            layout="total, sizes, prev, pager, next"
            @size-change="loadTemplates"
            @current-change="loadTemplates"
          />
        </div>
      </div>
    </template>

    <el-dialog
      v-model="designDialogVisible"
      width="780px"
      class="design-dialog"
      :close-on-click-modal="false"
      destroy-on-close
      @close="closeDesignDialog"
    >
      <template #header>
        <div class="dialog-title-area">
          <h3>{{ selectedTemplate?.name || '设计物料' }}</h3>
          <el-steps :active="designStep" align-center class="dialog-steps">
            <el-step title="填写信息" />
            <el-step title="预览效果" />
            <el-step title="下载生成" />
          </el-steps>
        </div>
      </template>

      <div v-if="designStep === 0" class="step-content">
        <div class="form-scene-hint" v-if="currentScene">
          <el-icon><InfoFilled /></el-icon>
          <span>当前场景：{{ currentScene.sceneName }}，模板：{{ selectedTemplate?.name }}</span>
        </div>
        <el-form
          ref="designFormRef"
          :model="designForm"
          :rules="designRules"
          label-width="110px"
          class="design-form"
        >
          <el-form-item
            v-for="field in configFields"
            :key="field.key"
            :label="field.name"
            :prop="field.key"
            :rules="getFieldRules(field)"
          >
            <el-select
              v-if="field.type === 'select'"
              v-model="designForm[field.key]"
              :placeholder="'请选择' + field.name"
              style="width: 100%"
            >
              <el-option
                v-for="opt in field.options"
                :key="opt"
                :label="opt"
                :value="opt"
              />
            </el-select>
            <el-input
              v-else-if="field.type === 'textarea'"
              v-model="designForm[field.key]"
              type="textarea"
              :rows="3"
              :placeholder="'请输入' + field.name + (field.required ? '' : '（选填）')"
            />
            <el-input
              v-else
              v-model="designForm[field.key]"
              :placeholder="'请输入' + field.name + (field.required ? '' : '（选填）')"
            />
          </el-form-item>

          <el-form-item label="输出格式">
            <el-radio-group v-model="designForm.format">
              <el-radio-button label="PNG" />
              <el-radio-button label="JPG" />
              <el-radio-button label="PDF" />
            </el-radio-group>
          </el-form-item>
        </el-form>
      </div>

      <div v-if="designStep === 1" class="step-content preview-step">
        <div v-if="previewLoading" class="preview-loading">
          <el-icon class="is-loading" :size="36"><Loading /></el-icon>
          <span>正在生成预览，请稍候...</span>
        </div>
        <div v-else-if="previewData" class="preview-area">
          <img :src="previewData.previewUrl" alt="预览图" class="preview-img" />
          <div class="preview-info">
            <div class="preview-info-item" v-if="previewData.bindings">
              <span class="label">绑定信息：</span>
              <span class="value">
                <template v-if="previewData.bindings.storeName">{{ previewData.bindings.storeName }}</template>
                <template v-if="previewData.bindings.activityName"> · {{ previewData.bindings.activityName }}</template>
              </span>
            </div>
            <div class="preview-info-item" v-if="previewData.dimensions">
              <span class="label">尺寸：</span>
              <span class="value">{{ previewData.dimensions }}</span>
            </div>
          </div>
        </div>
        <div v-else class="preview-empty">
          <el-empty description="预览生成失败，请返回重试" />
        </div>
      </div>

      <div v-if="designStep === 2" class="step-content download-step">
        <div class="download-card">
          <el-icon class="download-icon"><CircleCheckFilled /></el-icon>
          <h3>设计完成</h3>
          <p>物料已生成，可以下载使用</p>
          <div class="download-meta" v-if="generateResult">
            <span>任务编号：{{ generateResult.taskId }}</span>
            <span>格式：{{ generateResult.format || 'PNG' }}</span>
          </div>
          <div class="download-actions">
            <el-button type="primary" size="large" @click="handleDownload('png')">
              <el-icon><Download /></el-icon>
              下载 PNG
            </el-button>
            <el-button size="large" @click="handleDownload('jpg')">
              <el-icon><Download /></el-icon>
              下载 JPG
            </el-button>
          </div>
        </div>
      </div>

      <template #footer>
        <div class="dialog-footer">
          <el-button v-if="designStep > 0" @click="designStep--">上一步</el-button>
          <el-button v-if="designStep === 0" type="primary" :loading="previewLoading" @click="nextDesignStep">
            生成预览
          </el-button>
          <el-button v-if="designStep === 1" type="primary" :loading="generateLoading" @click="nextDesignStep">
            确认生成
          </el-button>
          <el-button v-if="designStep === 2" @click="closeDesignDialog">完成</el-button>
        </div>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import {
  ArrowRight,
  ArrowLeft,
  Picture,
  Loading,
  CircleCheckFilled,
  Download,
  InfoFilled
} from '@element-plus/icons-vue'
import {
  getDesignSceneList,
  getDesignSceneDetail,
  getDesignSceneTemplates,
  previewDesignScene,
  generateDesignScene
} from '@/api/index.js'
import { normalizePagination, normalizeListPayload, snakeToCamel } from '@/utils/responseHelper'
import { downloadFile } from '@/utils/file'

const SCENE_STYLES = {
  table_sticker: { icon: '🏷️', bg: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)' },
  badge: { icon: '🪪', bg: 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)' },
  poster: { icon: '🖼️', bg: 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)' },
  roll_up: { icon: '📜', bg: 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)' },
  display_stand: { icon: '🏪', bg: 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)' },
  member_card: { icon: '💳', bg: 'linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%)' },
}

const getSceneIcon = (key) => SCENE_STYLES[key]?.icon || '🎨'
const getSceneBg = (key) => SCENE_STYLES[key]?.bg || 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'

const loading = ref(false)
const scenes = ref([])
const currentScene = ref(null)
const sceneDetail = ref(null)
const configFields = ref([])

const templates = ref([])
const templateLoading = ref(false)
const templatePage = ref(1)
const templatePageSize = ref(12)
const templateTotal = ref(0)

const designDialogVisible = ref(false)
const designStep = ref(0)
const selectedTemplate = ref(null)

const previewLoading = ref(false)
const previewData = ref(null)

const generateLoading = ref(false)
const generateResult = ref(null)

const designFormRef = ref(null)
const designForm = reactive({})
const designRules = computed(() => {
  const rules = {}
  configFields.value.forEach(field => {
    if (field.required) {
      rules[field.key] = [{ required: true, message: `请输入${field.name}`, trigger: 'blur' }]
    }
  })
  return rules
})

const totalTemplates = computed(() => {
  return scenes.value.reduce((sum, s) => sum + (s.templateCount || 0), 0)
})

const getFieldRules = (field) => {
  if (!field.required) return []
  return [{ required: true, message: `请输入${field.name}`, trigger: 'blur' }]
}

const enterScene = async (scene) => {
  currentScene.value = scene
  templatePage.value = 1
  loadTemplates()
  loadSceneDetail(scene.sceneKey)
}

const exitScene = () => {
  currentScene.value = null
  sceneDetail.value = null
  configFields.value = []
  templates.value = []
}

const loadSceneDetail = async (sceneKey) => {
  try {
    const res = await getDesignSceneDetail(sceneKey)
    const detail = snakeToCamel(res) || {}
    sceneDetail.value = detail
    if (Array.isArray(detail.configFields)) {
      configFields.value = detail.configFields.map(f => snakeToCamel(f))
    }
  } catch {
    configFields.value = getDefaultFields()
  }
}

const getDefaultFields = () => [
  { key: 'qrCodeUrl', name: '二维码链接', type: 'text', required: true },
  { key: 'storeName', name: '门店名称', type: 'text', required: true },
  { key: 'activityName', name: '活动名称', type: 'text', required: false },
]

const loadTemplates = async () => {
  if (!currentScene.value) return
  templateLoading.value = true
  try {
    const res = await getDesignSceneTemplates({
      scene_key: currentScene.value.sceneKey,
      page: templatePage.value,
      limit: templatePageSize.value
    })
    const { list, total } = normalizePagination(res)
    templates.value = list
    templateTotal.value = total
  } catch {
    templates.value = []
    templateTotal.value = 0
    ElMessage.error('获取模板列表失败，请稍后重试')
  } finally {
    templateLoading.value = false
  }
}

const openDesign = (tpl) => {
  selectedTemplate.value = tpl
  designStep.value = 0
  previewData.value = null
  generateResult.value = null

  Object.keys(designForm).forEach(k => delete designForm[k])
  configFields.value.forEach(field => {
    designForm[field.key] = field.type === 'select' && field.options?.length
      ? field.options[0] : ''
  })
  designForm.format = 'PNG'
  designDialogVisible.value = true
}

const nextDesignStep = async () => {
  if (designStep.value === 0) {
    try {
      if (designFormRef.value) await designFormRef.value.validate()
    } catch {
      return
    }
    designStep.value = 1
    previewLoading.value = true
    try {
      const params = {
        template_id: selectedTemplate.value.id,
        scene_key: currentScene.value.sceneKey,
        qr_code_url: designForm.qrCodeUrl || '',
        store_name: designForm.storeName || '',
        activity_name: designForm.activityName || '',
        dimensions: selectedTemplate.value.size || '',
        format: designForm.format || 'PNG',
      }
      configFields.value.forEach(field => {
        if (!['qrCodeUrl', 'storeName', 'activityName'].includes(field.key)) {
          const snakeKey = field.key.replace(/([A-Z])/g, '_$1').toLowerCase()
          params[snakeKey] = designForm[field.key] || ''
        }
      })

      const res = await previewDesignScene(params)
      const data = snakeToCamel(res) || {}
      previewData.value = data
    } catch {
      previewData.value = null
      ElMessage.error('预览生成失败，请重试')
    } finally {
      previewLoading.value = false
    }
  } else if (designStep.value === 1) {
    generateLoading.value = true
    try {
      const params = {
        template_id: selectedTemplate.value.id,
        scene_key: currentScene.value.sceneKey,
        qr_code_url: designForm.qrCodeUrl || '',
        store_name: designForm.storeName || '',
        activity_name: designForm.activityName || '',
        dimensions: selectedTemplate.value.size || '',
        format: designForm.format || 'PNG',
      }
      configFields.value.forEach(field => {
        if (!['qrCodeUrl', 'storeName', 'activityName'].includes(field.key)) {
          const snakeKey = field.key.replace(/([A-Z])/g, '_$1').toLowerCase()
          params[snakeKey] = designForm[field.key] || ''
        }
      })

      const res = await generateDesignScene(params)
      const data = snakeToCamel(res) || {}
      generateResult.value = data
      designStep.value = 2
      ElMessage.success('物料生成成功')
    } catch {
      ElMessage.error('生成失败，请重试')
    } finally {
      generateLoading.value = false
    }
  }
}

const handleDownload = (format) => {
  const url = generateResult.value?.downloadUrl || previewData.value?.previewUrl
  if (!url) {
    ElMessage.warning('暂无可下载的文件，请返回上一步重新生成')
    return
  }
  const name = `${selectedTemplate.value?.name || '物料设计'}.${format}`
  downloadFile(url, name)
  ElMessage.success('下载已开始')
}

const closeDesignDialog = () => {
  designDialogVisible.value = false
  selectedTemplate.value = null
  designStep.value = 0
  previewData.value = null
  generateResult.value = null
}

onMounted(async () => {
  loading.value = true
  try {
    const res = await getDesignSceneList()
    scenes.value = normalizeListPayload(res)
  } catch {
    scenes.value = []
    ElMessage.error('获取场景列表失败，请稍后重试')
  } finally {
    loading.value = false
  }
})
</script>

<style scoped lang="scss">
.design-materials-page {
  padding: 20px;
  min-height: calc(100vh - 120px);
}

.page-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 12px;
  padding: 28px 32px;
  margin-bottom: 24px;
  color: #fff;

  .header-content {
    h2 {
      font-size: 28px;
      font-weight: 700;
      margin: 0 0 6px;
    }
    .subtitle {
      font-size: 14px;
      opacity: 0.85;
      margin: 0;
    }
  }

  .header-stats {
    margin-top: 16px;
    font-size: 13px;
    opacity: 0.9;
    strong {
      font-size: 16px;
    }
    .stat-divider {
      margin: 0 8px;
      opacity: 0.5;
    }
  }
}

.scene-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 16px;
  min-height: 200px;
}

.scene-card {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 20px;
  background: #fff;
  border: 1px solid #ebeef5;
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.25s;

  &:hover {
    box-shadow: 0 6px 20px rgba(100, 60, 200, 0.1);
    transform: translateY(-2px);
    border-color: #c084fc;
  }
}

.scene-icon-wrap {
  width: 56px;
  height: 56px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 12px;
  flex-shrink: 0;
}

.scene-icon {
  font-size: 26px;
}

.scene-info {
  flex: 1;
  min-width: 0;

  h3 {
    font-size: 16px;
    font-weight: 600;
    color: #303133;
    margin: 0 0 4px;
  }

  p {
    font-size: 12px;
    color: #909399;
    margin: 0 0 6px;
    line-height: 1.4;
  }

  .template-count {
    font-size: 12px;
    color: #a855f7;
    font-weight: 600;
  }
}

.scene-arrow {
  color: #c0c4cc;
  font-size: 16px;
  transition: color 0.2s;
}

.scene-card:hover .scene-arrow {
  color: #a855f7;
}

.empty-state {
  grid-column: 1 / -1;
  padding: 60px 0;
}

.template-page {
  min-height: calc(100vh - 120px);
}

.breadcrumb-bar {
  margin-bottom: 20px;
}

.breadcrumb-link {
  cursor: pointer;
}

.template-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 24px;
}

.template-header-left {
  display: flex;
  align-items: center;
  gap: 16px;

  .scene-icon-sm {
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    font-size: 20px;
    flex-shrink: 0;
  }

  h3 {
    font-size: 20px;
    font-weight: 600;
    color: #303133;
    margin: 0;
  }

  p {
    font-size: 13px;
    color: #909399;
    margin: 4px 0 0;
  }
}

.template-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 20px;
  min-height: 300px;
}

.template-card {
  background: #fff;
  border: 1px solid #ebeef5;
  border-radius: 10px;
  overflow: hidden;
  cursor: pointer;
  transition: all 0.25s;
  position: relative;

  &:hover {
    box-shadow: 0 6px 20px rgba(100, 60, 200, 0.1);
    transform: translateY(-2px);
    border-color: #c084fc;

    .tpl-hover-action {
      opacity: 1;
      transform: translateY(0);
    }
  }
}

.tpl-thumbnail {
  width: 100%;
  height: 200px;
  background: #f5f7fa;
  overflow: hidden;
  position: relative;
}

.tpl-img {
  width: 100%;
  height: 100%;
}

.tpl-size-tag {
  position: absolute;
  bottom: 8px;
  right: 8px;
  background: rgba(0, 0, 0, 0.55);
  color: #fff;
  font-size: 11px;
  padding: 2px 8px;
  border-radius: 4px;
  backdrop-filter: blur(4px);
}

.img-error {
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: #909399;

  .el-icon {
    font-size: 32px;
    margin-bottom: 8px;
  }

  span {
    font-size: 12px;
  }
}

.tpl-info {
  padding: 12px;

  h4 {
    margin: 0 0 8px;
    font-size: 14px;
    font-weight: 500;
    color: #303133;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}

.tpl-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;

  span {
    font-size: 12px;
    color: #909399;
  }
}

.tpl-hover-action {
  position: absolute;
  bottom: 12px;
  left: 50%;
  transform: translateX(-50%) translateY(8px);
  opacity: 0;
  transition: all 0.25s;
}

.pagination-wrap {
  margin-top: 24px;
  display: flex;
  justify-content: flex-end;
}

.dialog-title-area {
  width: 100%;

  h3 {
    font-size: 18px;
    font-weight: 600;
    margin: 0 0 16px;
    color: #303133;
  }
}

.dialog-steps {
  :deep(.el-step__title) {
    font-size: 13px;
  }
}

.step-content {
  min-height: 260px;
  padding: 20px 0;
}

.form-scene-hint {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  background: #f0f5ff;
  border-radius: 8px;
  margin-bottom: 20px;
  color: #667eea;
  font-size: 13px;
}

.design-form {
  max-width: 520px;
}

.preview-step {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.preview-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  color: #909399;
  padding: 40px 0;

  .el-icon {
    color: #a855f7;
  }
}

.preview-area {
  text-align: center;
  width: 100%;
}

.preview-img {
  max-width: 100%;
  max-height: 380px;
  border-radius: 8px;
  border: 1px solid #ebeef5;
}

.preview-info {
  margin-top: 16px;
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  justify-content: center;
}

.preview-info-item {
  font-size: 13px;
  color: #606266;

  .label {
    color: #909399;
  }

  .value {
    color: #303133;
  }
}

.preview-empty {
  width: 100%;
}

.download-step {
  display: flex;
  align-items: center;
  justify-content: center;
}

.download-card {
  text-align: center;
  padding: 30px;

  .download-icon {
    font-size: 48px;
    color: #22c55e;
    margin-bottom: 16px;
  }

  h3 {
    font-size: 20px;
    font-weight: 600;
    color: #303133;
    margin: 0 0 8px;
  }

  p {
    color: #909399;
    font-size: 14px;
    margin: 0 0 16px;
  }
}

.download-meta {
  display: flex;
  gap: 24px;
  justify-content: center;
  margin-bottom: 24px;
  font-size: 12px;
  color: #909399;
}

.download-actions {
  display: flex;
  gap: 12px;
  justify-content: center;
}

.dialog-footer {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

@media (max-width: 768px) {
  .scene-grid {
    grid-template-columns: 1fr;
  }

  .template-grid {
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 10px;
  }

  .template-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
  }
}
</style>
