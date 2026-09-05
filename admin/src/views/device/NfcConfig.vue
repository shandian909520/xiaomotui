<template>
  <div class="nfc-config-page">
    <!-- 顶部设备信息 -->
    <el-card class="device-summary" shadow="hover">
      <div class="summary-row" v-loading="loading">
        <div class="device-info">
          <div class="device-name">
            <span class="label">设备名称:</span>
            <strong>{{ device.device_name || '-' }}</strong>
            <el-tag
              :type="device.status === 1 ? 'success' : device.status === 0 ? 'danger' : 'warning'"
              size="small"
              style="margin-left: 12px"
            >{{ device.status_text || '未知' }}</el-tag>
          </div>
          <div class="device-meta">
            <span>SN: {{ device.device_code || '-' }}</span>
            <span class="separator">|</span>
            <span>类型: {{ device.type_text || device.type || '-' }}</span>
            <span class="separator">|</span>
            <span>触发模式: {{ device.trigger_mode_text || device.trigger_mode || '-' }}</span>
            <span class="separator">|</span>
            <span v-if="device.location">位置: {{ device.location }}</span>
          </div>
        </div>
        <div class="metric-block">
          <div class="metric-label">今日触发数</div>
          <div class="metric-value">{{ todayTriggerCount }}</div>
        </div>
      </div>
    </el-card>

    <!-- 3 Tab 配置区 -->
    <el-card class="config-card" v-loading="loading">
      <el-tabs v-model="activeTab" class="config-tabs">
        <!-- Tab 1: 任务配置 -->
        <el-tab-pane label="任务配置" name="task">
          <div class="task-tab">
            <div class="section-title">设备已配置的任务区块</div>
            <p class="section-desc">展示此设备在顾客端 H5 上展示的功能区块,用于快速核对配置完整性</p>
            <div class="block-grid">
              <div
                v-for="block in taskBlocks"
                :key="block.block"
                class="block-card"
                :class="{ active: block.enabled }"
              >
                <div class="block-icon">{{ getBlockIcon(block.block) }}</div>
                <div class="block-label">{{ block.label }}</div>
                <el-tag
                  :type="block.enabled ? 'success' : 'info'"
                  size="small"
                  effect="plain"
                >{{ block.enabled ? '已启用' : '未启用' }}</el-tag>
              </div>
            </div>

            <el-divider />

            <div class="section-title">AI 文案模板</div>
            <p class="section-desc">控制此设备是否启用 AI 文案模板(由 Agent C 文案池管理)</p>
            <el-form label-width="120px">
              <el-form-item label="启用 AI 文案">
                <el-switch
                  v-model="taskForm.ai_copy_enabled"
                  active-text="开启"
                  inactive-text="关闭"
                />
              </el-form-item>
              <el-form-item>
                <el-button type="primary" :loading="saving.task" @click="saveTaskTab">
                  保存任务配置
                </el-button>
              </el-form-item>
            </el-form>
          </div>
        </el-tab-pane>

        <!-- Tab 2: Wi-Fi / 二维码 -->
        <el-tab-pane label="Wi-Fi / 二维码" name="wifi">
          <el-form label-width="140px" :model="wifiForm">
            <div class="section-title">Wi-Fi 一键连配置</div>
            <p class="section-desc">顾客碰 NFC 后可直接连店内 Wi-Fi,密码加密保存,前台只下发一次性 token</p>
            <el-form-item label="Wi-Fi 名称 (SSID)">
              <el-input v-model="wifiForm.ssid" placeholder="如: ShopWiFi-5G" maxlength="50" show-word-limit />
            </el-form-item>
            <el-form-item label="Wi-Fi 密码">
              <el-input
                v-model="wifiForm.password"
                type="password"
                placeholder="不修改请留空"
                show-password
                maxlength="50"
              />
              <div class="form-tip">
                <span v-if="wifiForm.password_set" style="color: #67c23a">
                  <el-icon><CircleCheckFilled /></el-icon> 已设置密码
                </span>
                <span v-else style="color: #909399">未设置密码</span>
              </div>
            </el-form-item>

            <el-divider />

            <div class="section-title">店长二维码</div>
            <p class="section-desc">上传店长个人微信号二维码,顾客可长按识别加好友</p>
            <el-form-item label="二维码 URL">
              <el-input v-model="wifiForm.shop_owner_qr" placeholder="https://..." maxlength="500" show-word-limit />
            </el-form-item>
            <el-form-item v-if="wifiForm.shop_owner_qr">
              <el-image
                :src="wifiForm.shop_owner_qr"
                style="width: 120px; height: 120px; border: 1px solid #ebeef5"
                fit="contain"
                :preview-src-list="[wifiForm.shop_owner_qr]"
              />
            </el-form-item>

            <el-form-item>
              <el-button type="primary" :loading="saving.wifi" @click="saveWifiTab">
                保存 Wi-Fi 配置
              </el-button>
              <el-button @click="resetWifiForm">重置</el-button>
            </el-form-item>
          </el-form>
        </el-tab-pane>

        <!-- Tab 3: 私域配置 -->
        <el-tab-pane label="私域配置" name="private_domain">
          <el-form label-width="160px" :model="privateForm">
            <p class="section-desc">配置微信/QQ/企微三种加粉通道,顾客可选择任意渠道加好友</p>

            <div class="section-title">微信</div>
            <el-form-item label="微信加粉 URL">
              <el-input v-model="privateForm.wechat.url" placeholder="如 weixin://..." />
            </el-form-item>
            <el-form-item label="加微二维码 URL">
              <el-input v-model="privateForm.wechat.qr_url" placeholder="https://..." maxlength="500" />
            </el-form-item>
            <el-form-item label="微信号">
              <el-input v-model="privateForm.wechat.id" placeholder="可选,显示给顾客便于手动加好友" maxlength="50" />
            </el-form-item>

            <el-divider />

            <div class="section-title">企业微信</div>
            <el-form-item label="企微加粉 URL">
              <el-input v-model="privateForm.wework.url" placeholder="如 work.weixin.qq.com/..." />
            </el-form-item>
            <el-form-item label="企微二维码 URL">
              <el-input v-model="privateForm.wework.qr_url" placeholder="https://..." maxlength="500" />
            </el-form-item>
            <el-form-item label="客服微信号">
              <el-input v-model="privateForm.wework.kefu_wechat" placeholder="如 kefu_xxx" maxlength="50" />
            </el-form-item>

            <el-divider />

            <div class="section-title">QQ</div>
            <el-form-item label="QQ 号码">
              <el-input v-model="privateForm.qq.qq_number" placeholder="如 123456789" maxlength="20" />
            </el-form-item>
            <el-form-item label="QQ 二维码 URL">
              <el-input v-model="privateForm.qq.qq_qrcode" placeholder="https://..." maxlength="500" />
            </el-form-item>
            <el-form-item label="QQ 群链接">
              <el-input v-model="privateForm.qq.qq_group_url" placeholder="https://qm.qq.com/..." maxlength="500" />
            </el-form-item>
            <el-form-item label="客服 QQ 二维码">
              <el-input v-model="privateForm.qq.kefu_qrcode" placeholder="https://..." maxlength="500" />
            </el-form-item>

            <el-form-item>
              <el-button type="primary" :loading="saving.private_domain" @click="savePrivateTab">
                保存私域配置
              </el-button>
              <el-button @click="resetPrivateForm">重置</el-button>
            </el-form-item>
          </el-form>
        </el-tab-pane>
      </el-tabs>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { CircleCheckFilled } from '@element-plus/icons-vue'
import { nfcConfigApi } from '@/api/nfc-config'

const route = useRoute()
const router = useRouter()

const loading = ref(false)
const activeTab = ref('task')
const device = ref({})
const taskBlocks = ref([])
const todayTriggerCount = ref(0)

const taskForm = reactive({ ai_copy_enabled: true })
const wifiForm = reactive({
  ssid: '',
  password: '',
  password_set: false,
  shop_owner_qr: ''
})
const privateForm = reactive({
  wechat: { url: '', qr_url: '', id: '' },
  wework: { url: '', qr_url: '', kefu_wechat: '' },
  qq: { qq_number: '', qq_qrcode: '', qq_group_url: '', kefu_qrcode: '' }
})
const saving = reactive({ task: false, wifi: false, private_domain: false })

const getDeviceId = () => {
  return parseInt(route.params.id || route.query.id || 0, 10)
}

const getBlockIcon = (key) => {
  const map = {
    wifi: '📶',
    publish: '🎬',
    groupbuy: '🛒',
    review: '⭐',
    contact: '💬',
    lottery: '🎁'
  }
  return map[key] || '📦'
}

const loadConfig = async () => {
  const id = getDeviceId()
  if (!id) {
    ElMessage.error('缺少设备 ID')
    return
  }
  loading.value = true
  try {
    const res = await nfcConfigApi.getConfig(id)
    const data = res?.data || res || {}
    device.value = data.device || {}
    todayTriggerCount.value = data.today_trigger_count || 0
    taskBlocks.value = data.tabs?.task || []
    // 任务配置
    taskForm.ai_copy_enabled = device.value.ai_copy_enabled === undefined
      ? true
      : !!device.value.ai_copy_enabled
    // Wi-Fi
    const wifi = data.tabs?.wifi || {}
    wifiForm.ssid = wifi.ssid || ''
    wifiForm.password_set = !!wifi.password_set
    wifiForm.password = ''
    wifiForm.shop_owner_qr = wifi.shop_owner_qr || ''
    // 私域
    const pd = data.tabs?.private_domain || {}
    Object.assign(privateForm.wechat, pd.wechat || {})
    Object.assign(privateForm.wework, pd.wework || {})
    Object.assign(privateForm.qq, pd.qq || {})
  } catch (e) {
    ElMessage.error('加载设备配置失败: ' + (e?.message || '网络异常'))
  } finally {
    loading.value = false
  }
}

const saveTaskTab = async () => {
  saving.task = true
  try {
    await nfcConfigApi.saveConfig(getDeviceId(), {
      task: { ai_copy_enabled: taskForm.ai_copy_enabled ? 1 : 0 }
    })
    ElMessage.success('任务配置保存成功')
  } catch (e) {
    ElMessage.error('保存失败: ' + (e?.message || '网络异常'))
  } finally {
    saving.task = false
  }
}

const saveWifiTab = async () => {
  saving.wifi = true
  try {
    const payload = { wifi: { ssid: wifiForm.ssid } }
    if (wifiForm.password) {
      payload.wifi.password = wifiForm.password
    }
    if (wifiForm.shop_owner_qr) {
      payload.wifi.shop_owner_qr = wifiForm.shop_owner_qr
    }
    await nfcConfigApi.saveConfig(getDeviceId(), payload)
    ElMessage.success('Wi-Fi 配置保存成功')
    wifiForm.password = ''
    wifiForm.password_set = true
  } catch (e) {
    ElMessage.error('保存失败: ' + (e?.message || '网络异常'))
  } finally {
    saving.wifi = false
  }
}

const resetWifiForm = () => {
  wifiForm.password = ''
}

const savePrivateTab = async () => {
  saving.private_domain = true
  try {
    await nfcConfigApi.saveConfig(getDeviceId(), {
      private_domain: {
        wechat: { ...privateForm.wechat },
        wework: { ...privateForm.wework },
        qq: { ...privateForm.qq }
      }
    })
    ElMessage.success('私域配置保存成功')
  } catch (e) {
    ElMessage.error('保存失败: ' + (e?.message || '网络异常'))
  } finally {
    saving.private_domain = false
  }
}

const resetPrivateForm = () => {
  loadConfig()
}

onMounted(() => {
  loadConfig()
})

watch(() => route.params.id, () => {
  loadConfig()
})
</script>

<style lang="scss" scoped>
.nfc-config-page {
  padding: 16px;
}

.device-summary {
  margin-bottom: 16px;

  .summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .device-info {
    flex: 1;
    min-width: 0;
  }

  .device-name {
    font-size: 18px;
    color: #1f2937;
    margin-bottom: 6px;

    .label {
      color: #6b7280;
      font-size: 14px;
      margin-right: 6px;
    }
  }

  .device-meta {
    font-size: 13px;
    color: #6b7280;

    .separator {
      margin: 0 10px;
      color: #d1d5db;
    }
  }

  .metric-block {
    padding: 12px 24px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-radius: 8px;
    text-align: center;
    color: #fff;
    min-width: 140px;
  }

  .metric-label {
    font-size: 12px;
    opacity: 0.9;
  }

  .metric-value {
    font-size: 28px;
    font-weight: 700;
    line-height: 1.4;
  }
}

.config-card {
  min-height: 600px;
}

.config-tabs {
  :deep(.el-tabs__nav-wrap::after) {
    background: #e5e7eb;
  }
}

.task-tab {
  .section-title {
    font-size: 15px;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 6px;
  }

  .section-desc {
    font-size: 13px;
    color: #6b7280;
    margin-bottom: 16px;
  }
}

.block-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
  margin-bottom: 16px;

  .block-card {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 16px;
    border-radius: 10px;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    transition: all 0.2s;

    &.active {
      background: linear-gradient(135deg, #eef2ff, #f3e8ff);
      border-color: #c7d2fe;
    }

    .block-icon {
      font-size: 22px;
    }

    .block-label {
      flex: 1;
      font-size: 14px;
      font-weight: 500;
      color: #1f2937;
    }
  }
}

.form-tip {
  margin-top: 4px;
  font-size: 12px;
  display: flex;
  align-items: center;
  gap: 4px;
}
</style>