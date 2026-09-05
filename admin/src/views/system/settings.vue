<template>
  <div class="system-settings">
    <el-tabs v-model="activeTab">
      <!-- 基本设置 -->
      <el-tab-pane label="基本设置" name="site">
        <el-card shadow="never">
          <el-form :model="siteForm" label-width="120px" style="max-width: 500px">
            <el-form-item label="站点名称">
              <el-input v-model="siteForm.name" />
            </el-form-item>
            <el-form-item label="站点描述">
              <el-input v-model="siteForm.description" type="textarea" :rows="3" />
            </el-form-item>
            <el-form-item label="版本号">
              <el-input v-model="siteForm.version" disabled />
            </el-form-item>
            <el-form-item>
              <el-button type="primary" @click="handleSave">保存设置</el-button>
            </el-form-item>
          </el-form>
        </el-card>
      </el-tab-pane>

      <!-- AI服务配置 -->
      <el-tab-pane label="AI服务" name="ai">
        <!-- 文本模型配置 -->
        <el-card shadow="never" style="margin-bottom: 16px">
          <template #header>
            <div style="display: flex; justify-content: space-between; align-items: center">
              <span>文本生成模型</span>
              <el-button size="small" @click="handleTestProvider(aiForm.default_provider)" :loading="testing">
                测试连接
              </el-button>
            </div>
          </template>
          <el-form :model="aiForm" label-width="140px" style="max-width: 600px">
            <el-form-item label="默认文本模型">
              <el-select v-model="aiForm.default_provider" style="width: 100%">
                <el-option label="MiniMax 大模型" value="minimax" />
                <el-option label="百度文心一言" value="wenxin" />
              </el-select>
            </el-form-item>

            <el-divider content-position="left">MiniMax 配置</el-divider>
            <el-form-item label="Auth Token">
              <el-input v-model="aiForm.minimax_auth_token" placeholder="MiniMax Auth Token" show-password />
            </el-form-item>
            <el-form-item label="模型">
              <el-select v-model="aiForm.minimax_model" style="width: 100%">
                <el-option label="MiniMax-M2.7-highspeed (高速)" value="MiniMax-M2.7-highspeed" />
                <el-option label="MiniMax-M2.7 (标准)" value="MiniMax-M2.7" />
              </el-select>
            </el-form-item>

            <el-divider content-position="left">百度文心一言 配置</el-divider>
            <el-form-item label="API Key">
              <el-input v-model="aiForm.wenxin_api_key" placeholder="百度 API Key" show-password />
            </el-form-item>
            <el-form-item label="Secret Key">
              <el-input v-model="aiForm.wenxin_secret_key" placeholder="百度 Secret Key" show-password />
            </el-form-item>
            <el-form-item label="模型">
              <el-select v-model="aiForm.wenxin_model" style="width: 100%">
                <el-option label="ERNIE-Bot-turbo (推荐)" value="ernie-bot-turbo" />
                <el-option label="ERNIE-Bot" value="ernie-bot" />
                <el-option label="ERNIE-Bot 4.0" value="ernie-bot-4" />
                <el-option label="ERNIE Speed" value="ernie-speed" />
              </el-select>
            </el-form-item>
          </el-form>
        </el-card>

        <!-- 图像/视频模型配置 -->
        <el-card shadow="never">
          <template #header>
            <div style="display: flex; justify-content: space-between; align-items: center">
              <span>图像/视频生成模型（智谱AI）</span>
              <el-button size="small" @click="handleTestProvider('zhipu')" :loading="testing">
                测试连接
              </el-button>
            </div>
          </template>
          <el-form :model="aiForm" label-width="140px" style="max-width: 600px">
            <el-form-item label="API Key">
              <el-input v-model="aiForm.zhipu_api_key" placeholder="智谱AI API Key" show-password />
            </el-form-item>
            <el-form-item label="图像生成模型">
              <el-select v-model="aiForm.zhipu_image_model" style="width: 100%">
                <el-option label="CogView-3-Flash (图像生成-快速)" value="CogView-3-Flash" />
                <el-option label="CogView-3-Plus (图像生成-增强)" value="CogView-3-Plus" />
              </el-select>
            </el-form-item>
            <el-form-item label="视频生成模型">
              <el-select v-model="aiForm.zhipu_video_model" style="width: 100%">
                <el-option label="CogVideoX-Flash (视频生成-快速)" value="CogVideoX-Flash" />
                <el-option label="CogVideoX-2 (视频生成-标准)" value="CogVideoX-2" />
              </el-select>
            </el-form-item>
            <el-form-item>
              <el-button type="primary" @click="handleSaveAi" :loading="saving">保存AI配置</el-button>
            </el-form-item>
          </el-form>
        </el-card>
      </el-tab-pane>

      <!-- 通知设置 -->
      <el-tab-pane label="通知设置" name="notification">
        <el-card shadow="never">
          <el-form label-width="120px" style="max-width: 500px">
            <el-form-item label="邮件通知">
              <el-switch v-model="notificationForm.email_enabled" />
            </el-form-item>
            <el-form-item label="短信通知">
              <el-switch v-model="notificationForm.sms_enabled" />
            </el-form-item>
            <el-form-item>
              <el-button type="primary" @click="handleSave">保存设置</el-button>
            </el-form-item>
          </el-form>
        </el-card>
      </el-tab-pane>

      <!-- 系统信息 -->
      <el-tab-pane label="系统信息" name="system">
        <el-card shadow="never">
          <el-descriptions :column="1" border>
            <el-descriptions-item label="PHP版本">{{ settings.system?.php_version || '-' }}</el-descriptions-item>
            <el-descriptions-item label="框架">{{ settings.system?.framework || '-' }}</el-descriptions-item>
            <el-descriptions-item label="运行环境">
              <el-tag :type="settings.system?.environment === 'production' ? 'success' : 'warning'" size="small">
                {{ settings.system?.environment || '-' }}
              </el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="时区">{{ settings.system?.timezone || '-' }}</el-descriptions-item>
          </el-descriptions>
        </el-card>
      </el-tab-pane>
    </el-tabs>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { systemApi } from '@/api/system'
import { getAiConfig, updateAiConfig, testAiConnection } from '@/api/ai'
import { ElMessage } from 'element-plus'

const activeTab = ref('site')
const settings = ref({})
const saving = ref(false)
const testing = ref(false)

const siteForm = reactive({
  name: '',
  description: '',
  version: ''
})

const notificationForm = reactive({
  email_enabled: false,
  sms_enabled: false
})

const aiForm = reactive({
  default_provider: 'minimax',
  minimax_auth_token: '',
  minimax_model: 'MiniMax-M2.7-highspeed',
  wenxin_api_key: '',
  wenxin_secret_key: '',
  wenxin_model: 'ernie-bot-turbo',
  zhipu_api_key: '',
  zhipu_image_model: 'CogView-3-Flash',
  zhipu_video_model: 'CogVideoX-Flash',
})

const fetchSettings = async () => {
  try {
    const res = await systemApi.getSettings()
    const data = res || {}
    settings.value = data
    const site = data.site || {}
    siteForm.name = site.name || ''
    siteForm.description = site.description || ''
    siteForm.version = site.version || ''
    const notif = data.notification || {}
    notificationForm.email_enabled = notif.email_enabled || false
    notificationForm.sms_enabled = notif.sms_enabled || false
  } catch (e) {
    ElMessage.error('获取系统设置失败')
  }
}

const fetchAiConfig = async () => {
  try {
    const res = await getAiConfig()
    const data = res || {}
    aiForm.default_provider = data.default_provider || 'minimax'

    const providers = data.providers || {}
    if (providers.minimax) {
      aiForm.minimax_auth_token = providers.minimax.auth_token || ''
      aiForm.minimax_model = providers.minimax.model || 'MiniMax-M2.7-highspeed'
    }
    if (providers.wenxin) {
      aiForm.wenxin_api_key = providers.wenxin.api_key || ''
      aiForm.wenxin_secret_key = providers.wenxin.secret_key || ''
      aiForm.wenxin_model = providers.wenxin.model || 'ernie-bot-turbo'
    }
    if (providers.zhipu) {
      aiForm.zhipu_api_key = providers.zhipu.api_key || ''
      aiForm.zhipu_image_model = providers.zhipu.image_model || 'CogView-3-Flash'
      aiForm.zhipu_video_model = providers.zhipu.video_model || 'CogVideoX-Flash'
    }
  } catch (e) {
    console.error('获取AI配置失败', e)
  }
}

const handleSave = async () => {
  try {
    await systemApi.updateSettings({
      site: { ...siteForm },
      notification: { ...notificationForm }
    })
    ElMessage.success('设置已保存')
  } catch (e) {
    ElMessage.error('保存设置失败')
  }
}

const handleSaveAi = async () => {
  saving.value = true
  try {
    await updateAiConfig({ ...aiForm })
    ElMessage.success('AI配置已保存')
  } catch (e) {
    ElMessage.error('保存AI配置失败')
  } finally {
    saving.value = false
  }
}

const handleTestProvider = async (provider) => {
  testing.value = true
  try {
    const res = await testAiConnection(provider)
    ElMessage.success(`连接测试成功 (${res.duration || ''})`)
  } catch (e) {
    ElMessage.error('连接测试失败：' + (e.message || '未知错误'))
  } finally {
    testing.value = false
  }
}

onMounted(() => {
  fetchSettings()
  fetchAiConfig()
})
</script>

<style scoped>
.system-settings {
  padding: 20px;
}
</style>