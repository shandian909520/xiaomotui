<template>
  <div class="review-config-list">
    <el-card class="filter-card">
      <div class="filter-bar">
        <el-select v-model="deviceId" placeholder="选择设备" filterable style="width: 260px" @change="loadAll">
          <el-option v-for="d in deviceOptions" :key="d.id" :label="`${d.deviceCode || d.device_code || d.id}${d.deviceName || d.device_name ? '（' + (d.deviceName || d.device_name) + '）' : ''}`" :value="d.id" />
        </el-select>
        <el-button type="primary" :loading="loading" @click="loadAll">刷新</el-button>
        <div class="spacer"></div>
        <el-button type="success" plain @click="openTemplateDialog">
          <el-icon><Plus /></el-icon>
          新增模板
        </el-button>
      </div>
    </el-card>

    <el-row :gutter="16" v-if="deviceId">
      <el-col :span="14">
        <el-card class="config-card">
          <template #header>
            <div class="card-header">
              <span>点评配置</span>
              <el-button type="primary" size="small" :loading="configSaving" @click="saveConfig">保存配置</el-button>
            </div>
          </template>
          <el-form :model="config" label-width="120px" v-loading="loading">
            <el-form-item label="启用点评">
              <el-switch v-model="config.enabled" />
              <span class="hint">关闭后,顾客端不再展示点评入口</span>
            </el-form-item>
            <el-form-item label="AI 草稿开关">
              <el-switch v-model="config.ai_draft_enabled" />
              <span class="hint">关闭后,顾客点击"看草稿"将返回本地兜底</span>
            </el-form-item>
            <el-form-item label="默认草稿数">
              <el-input-number v-model="config.default_count" :min="1" :max="5" />
              <span class="hint">1-5 条/次</span>
            </el-form-item>
            <el-divider content-position="left">平台跳转链接</el-divider>
            <el-table :data="config.platforms" border>
              <el-table-column label="平台" width="110" align="center">
                <template #default="{ row }">{{ platformName(row.key) }}</template>
              </el-table-column>
              <el-table-column label="跳转 URL" min-width="240">
                <template #default="{ row }">
                  <el-input v-model="row.jump_url" placeholder="https://..." size="small" />
                </template>
              </el-table-column>
              <el-table-column label="显示名" width="140">
                <template #default="{ row }">
                  <el-input v-model="row.name" placeholder="显示名" size="small" />
                </template>
              </el-table-column>
              <el-table-column label="操作" width="80" align="center">
                <template #default="{ $index }">
                  <el-button type="danger" size="small" link @click="removePlatform($index)">删除</el-button>
                </template>
              </el-table-column>
            </el-table>
            <div style="margin-top: 8px">
              <el-select v-model="newPlatformKey" placeholder="添加平台" style="width: 140px" size="small">
                <el-option v-for="p in availablePlatforms" :key="p.key" :label="p.name" :value="p.key" />
              </el-select>
              <el-button size="small" type="primary" plain @click="addPlatform" :disabled="!newPlatformKey">添加</el-button>
            </div>
          </el-form>
        </el-card>
      </el-col>

      <el-col :span="10">
        <el-card class="template-card">
          <template #header>
            <div class="card-header">
              <span>AI 草稿模板</span>
              <el-select v-model="filterPlatform" placeholder="平台筛选" clearable style="width: 140px" size="small" @change="loadTemplates">
                <el-option v-for="p in platformOptions" :key="p.key" :label="p.name" :value="p.key" />
              </el-select>
            </div>
          </template>
          <el-table :data="templates" v-loading="templateLoading" size="small" border>
            <el-table-column label="标题" min-width="140" show-overflow-tooltip prop="title" />
            <el-table-column label="平台" width="90" align="center">
              <template #default="{ row }">
                <el-tag size="small">{{ platformName(row.platform) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="样式" width="90" align="center" prop="style" />
            <el-table-column label="权重" width="70" align="center" prop="weight" />
            <el-table-column label="归属" width="80" align="center">
              <template #default="{ row }">
                <el-tag size="small" :type="Number(row.merchant_id) === 0 ? 'info' : 'success'">
                  {{ Number(row.merchant_id) === 0 ? '平台' : '商家' }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="80" align="center" fixed="right">
              <template #default="{ row }">
                <el-button v-if="Number(row.merchant_id) !== 0" size="small" type="danger" link @click="removeTemplate(row)">删除</el-button>
                <span v-else class="muted">-</span>
              </template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-col>
    </el-row>

    <el-empty v-else description="请先选择设备" />

    <!-- 新增模板对话框 -->
    <el-dialog v-model="tplDialogVisible" title="新增 AI 草稿模板" width="640px" @close="resetTplForm">
      <el-form :model="tplForm" :rules="tplRules" ref="tplFormRef" label-width="100px">
        <el-form-item label="平台" prop="platform">
          <el-select v-model="tplForm.platform" placeholder="平台" style="width: 100%">
            <el-option v-for="p in platformOptions" :key="p.key" :label="p.name" :value="p.key" />
          </el-select>
        </el-form-item>
        <el-form-item label="标题" prop="title">
          <el-input v-model="tplForm.title" maxlength="100" show-word-limit />
        </el-form-item>
        <el-form-item label="AI 风格">
          <el-input v-model="tplForm.style" placeholder="亲切自然" />
        </el-form-item>
        <el-form-item label="提示词" prop="prompt">
          <el-input v-model="tplForm.prompt" type="textarea" :rows="6" maxlength="1000" show-word-limit placeholder="支持占位符 {merchant_name}、{count}" />
        </el-form-item>
        <el-form-item label="权重">
          <el-input-number v-model="tplForm.weight" :min="1" :max="9999" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="tplDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="tplSubmitting" @click="submitTemplate">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { reviewApi, nfcApi, reviewAdminApi } from '@/api/index'
import { normalizeListPayload } from '@/utils/responseHelper'

const loading = ref(false)
const configSaving = ref(false)
const templateLoading = ref(false)
const tplSubmitting = ref(false)
const tplDialogVisible = ref(false)
const tplFormRef = ref(null)

const deviceId = ref(null)
const deviceOptions = ref([])

const config = reactive({
  enabled: true,
  ai_draft_enabled: true,
  default_count: 3,
  platforms: [],
  merchant_name: ''
})

const platformOptions = [
  { key: 'DIANPING', name: '大众点评' },
  { key: 'MEITUAN',  name: '美团' },
  { key: 'GAODE',    name: '高德地图' },
  { key: 'BAIDU',    name: '百度地图' },
  { key: 'DOUYIN',   name: '抖音' }
]
const platformName = (k) => (platformOptions.find(p => p.key === k)?.name) || k
const availablePlatforms = computed(() => {
  const used = new Set(config.platforms.map(p => p.key))
  return platformOptions.filter(p => !used.has(p.key))
})
const newPlatformKey = ref('')

const templates = ref([])
const filterPlatform = ref('')

const blankTplForm = () => ({
  platform: 'DIANPING',
  title: '',
  style: '亲切自然',
  prompt: '',
  weight: 10
})
const tplForm = reactive(blankTplForm())
const tplRules = {
  platform: [{ required: true, message: '请选择平台', trigger: 'change' }],
  title:    [{ required: true, message: '请输入标题', trigger: 'blur' }],
  prompt:   [{ required: true, message: '请输入提示词', trigger: 'blur' }]
}

const loadDevices = async () => {
  try {
    const res = await nfcApi.getDevices({ page: 1, limit: 200 })
    deviceOptions.value = normalizeListPayload(res)
  } catch (_) {
    deviceOptions.value = []
  }
}

const loadConfig = async () => {
  if (!deviceId.value) return
  loading.value = true
  try {
    // 用公开 GET 读,不再用 saveConfig 写一次来反推
    const res = await reviewApi.getConfig(deviceId.value)
    const data = (res && (res.data || res)) || {}
    config.enabled           = !!data.enabled
    config.ai_draft_enabled  = data.ai_draft_enabled !== false
    config.default_count     = Number(data.default_count || 3)
    config.platforms         = Array.isArray(data.platforms) ? data.platforms.map(p => ({ ...p })) : []
    config.merchant_name     = data.merchant_name || ''
  } catch (err) {
    console.error('加载点评配置失败:', err)
    ElMessage.error(err?.message || '加载点评配置失败')
  } finally {
    loading.value = false
  }
}

const loadTemplates = async () => {
  if (!deviceId.value) return
  templateLoading.value = true
  try {
    const res = await reviewAdminApi.draftTemplates({
      device_id: deviceId.value,
      platform:  filterPlatform.value || ''
    })
    templates.value = normalizeListPayload(res)
  } catch (err) {
    console.error('加载模板失败:', err)
    templates.value = []
  } finally {
    templateLoading.value = false
  }
}

const loadAll = async () => {
  await loadConfig()
  await loadTemplates()
}

const addPlatform = () => {
  const key = newPlatformKey.value
  if (!key) return
  const opt = platformOptions.find(p => p.key === key)
  if (!opt) return
  config.platforms.push({ key, name: opt.name, jump_url: '', icon: '' })
  newPlatformKey.value = ''
}

const removePlatform = (idx) => {
  config.platforms.splice(idx, 1)
}

const saveConfig = async () => {
  if (!deviceId.value) {
    ElMessage.warning('请先选择设备')
    return
  }
  configSaving.value = true
  try {
    const payload = {
      device_id:          deviceId.value,
      enabled:            !!config.enabled,
      ai_draft_enabled:   !!config.ai_draft_enabled,
      default_count:      Number(config.default_count) || 3,
      platforms:          config.platforms.map(p => ({
        key: p.key,
        name: p.name || platformName(p.key),
        jump_url: p.jump_url || '',
        icon: p.icon || ''
      }))
    }
    const res = await reviewAdminApi.saveConfig(payload)
    const data = res?.data || res
    if (data) {
      config.enabled           = !!data.enabled
      config.ai_draft_enabled  = !!data.ai_draft_enabled
      config.default_count     = Number(data.default_count || 3)
      config.platforms         = Array.isArray(data.platforms) ? data.platforms : []
      config.merchant_name     = data.merchant_name || ''
    }
    ElMessage.success('保存成功')
  } catch (err) {
    console.error('保存失败:', err)
    ElMessage.error(err?.message || '保存失败')
  } finally {
    configSaving.value = false
  }
}

const openTemplateDialog = () => {
  if (!deviceId.value) {
    ElMessage.warning('请先选择设备')
    return
  }
  resetTplForm()
  tplDialogVisible.value = true
}

const resetTplForm = () => {
  Object.assign(tplForm, blankTplForm())
  tplFormRef.value?.clearValidate?.()
}

const submitTemplate = async () => {
  await tplFormRef.value.validate().catch(() => {})
  tplSubmitting.value = true
  try {
    const payload = {
      device_id: deviceId.value,
      platform:  tplForm.platform,
      title:     tplForm.title,
      style:     tplForm.style,
      prompt:    tplForm.prompt,
      weight:    Number(tplForm.weight) || 10
    }
    await reviewAdminApi.addTemplate(payload)
    ElMessage.success('新增成功')
    tplDialogVisible.value = false
    loadTemplates()
  } catch (err) {
    console.error('新增模板失败:', err)
    ElMessage.error(err?.message || '新增模板失败')
  } finally {
    tplSubmitting.value = false
  }
}

const removeTemplate = async (row) => {
  try {
    await ElMessageBox.confirm(`确定删除模板 "${row.title}" 吗？`, '警告', {
      type: 'error', confirmButtonText: '删除', confirmButtonClass: 'el-button--danger'
    })
    await reviewAdminApi.removeTemplate(row.id, { device_id: deviceId.value })
    ElMessage.success('删除成功')
    loadTemplates()
  } catch (err) {
    if (err !== 'cancel') {
      console.error('删除模板失败:', err)
      ElMessage.error(err?.message || '删除模板失败')
    }
  }
}

onMounted(async () => {
  await loadDevices()
})
</script>

<style lang="scss" scoped>
.review-config-list {
  padding: 20px;

  .filter-card { margin-bottom: 16px; }
  .filter-bar  { display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
    .spacer { flex: 1; }
  }
  .config-card, .template-card { margin-bottom: 16px; }

  .card-header { display: flex; align-items: center; justify-content: space-between; }
  .hint { margin-left: 12px; font-size: 12px; color: #909399; }
  .muted { color: #c0c4cc; }
}
</style>
