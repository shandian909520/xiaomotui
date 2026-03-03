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
        <el-card shadow="never">
          <el-descriptions :column="1" border>
            <el-descriptions-item label="AI服务商">{{ settings.ai?.provider || '-' }}</el-descriptions-item>
            <el-descriptions-item label="模型">{{ settings.ai?.model || '默认' }}</el-descriptions-item>
            <el-descriptions-item label="API Key状态">
              <el-tag :type="settings.ai?.status === 'configured' ? 'success' : 'danger'" size="small">
                {{ settings.ai?.status === 'configured' ? '已配置' : '未配置' }}
              </el-tag>
            </el-descriptions-item>
          </el-descriptions>
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
import { ElMessage } from 'element-plus'

const activeTab = ref('site')
const settings = ref({})

const siteForm = reactive({
  name: '',
  description: '',
  version: ''
})

const notificationForm = reactive({
  email_enabled: false,
  sms_enabled: false
})

const fetchSettings = async () => {
  try {
    const res = await systemApi.getSettings()
    if (res.code === 200) {
      settings.value = res.data || {}
      const site = res.data?.site || {}
      siteForm.name = site.name || ''
      siteForm.description = site.description || ''
      siteForm.version = site.version || ''
      const notif = res.data?.notification || {}
      notificationForm.email_enabled = notif.email_enabled || false
      notificationForm.sms_enabled = notif.sms_enabled || false
    }
  } catch (e) {
    ElMessage.error('获取系统设置失败')
  }
}

const handleSave = async () => {
  try {
    const res = await systemApi.updateSettings({
      site: { ...siteForm },
      notification: { ...notificationForm }
    })
    if (res.code === 200) {
      ElMessage.success('设置已保存')
    }
  } catch (e) {
    ElMessage.error('保存设置失败')
  }
}

onMounted(() => fetchSettings())
</script>

<style scoped>
.system-settings {
  padding: 20px;
}
</style>