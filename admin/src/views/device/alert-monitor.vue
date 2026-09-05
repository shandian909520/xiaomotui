<template>
  <div class="alert-monitor-container">
    <div class="page-header">
      <div class="header-left">
        <h1 class="page-title">告警监控</h1>
        <el-badge :value="unresolvedCount" :hidden="unresolvedCount === 0" type="danger">
          <el-tag type="warning" effect="plain">未处理</el-tag>
        </el-badge>
      </div>
      <div class="header-actions">
        <el-button :icon="Refresh" @click="loadData">刷新</el-button>
        <el-button :icon="Check" @click="showRuleDialog = true">告警规则</el-button>
        <el-button type="primary" @click="handleCheckAlerts">手动检测</el-button>
      </div>
    </div>

    <!-- 筛选栏 -->
    <el-card shadow="never" style="margin-bottom: 20px;">
      <el-form :inline="true" :model="filterForm">
        <el-form-item label="告警类型">
          <el-select v-model="filterForm.alert_type" placeholder="全部" clearable>
            <el-option label="设备离线" value="offline" />
            <el-option label="低电量" value="low_battery" />
            <el-option label="响应超时" value="response_timeout" />
            <el-option label="设备错误" value="device_error" />
            <el-option label="信号弱" value="signal_weak" />
            <el-option label="温度异常" value="temperature" />
            <el-option label="心跳异常" value="heartbeat" />
            <el-option label="触发失败" value="trigger_failed" />
          </el-select>
        </el-form-item>
        <el-form-item label="告警级别">
          <el-select v-model="filterForm.alert_level" placeholder="全部" clearable>
            <el-option label="低" value="low" />
            <el-option label="中" value="medium" />
            <el-option label="高" value="high" />
            <el-option label="严重" value="critical" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="filterForm.status" placeholder="全部" clearable>
            <el-option label="待处理" value="pending" />
            <el-option label="已确认" value="acknowledged" />
            <el-option label="已解决" value="resolved" />
            <el-option label="已忽略" value="ignored" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :icon="Search" @click="loadAlerts">查询</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 实时告警列表 -->
    <el-card shadow="hover">
      <template #header>
        <div class="card-header">
          <span>告警列表</span>
          <div class="batch-actions">
            <el-button
              type="primary"
              size="small"
              :disabled="selectedAlerts.length === 0"
              @click="handleBatchAction('acknowledge')"
            >
              批量确认 ({{ selectedAlerts.length }})
            </el-button>
            <el-button
              type="success"
              size="small"
              :disabled="selectedAlerts.length === 0"
              @click="handleBatchAction('resolve')"
            >
              批量解决
            </el-button>
            <el-button
              type="info"
              size="small"
              :disabled="selectedAlerts.length === 0"
              @click="handleBatchAction('ignore')"
            >
              批量忽略
            </el-button>
          </div>
        </div>
      </template>

      <el-table :data="alerts" v-loading="loading" stripe @selection-change="handleSelectionChange">
        <el-table-column type="selection" width="55" />
        <el-table-column prop="alert_type_text" label="告警类型" width="120" />
        <el-table-column prop="alert_level_text" label="级别" width="80">
          <template #default="{ row }">
            <el-tag :color="row.level_color" effect="dark" size="small" style="border: none;">
              {{ row.alert_level_text }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="message" label="告警信息" min-width="200" show-overflow-tooltip />
        <el-table-column prop="device_name" label="设备" width="120" />
        <el-table-column prop="status_text" label="状态" width="80">
          <template #default="{ row }">
            <el-tag
              :type="row.status === 'pending' ? 'danger' : row.status === 'acknowledged' ? 'warning' : row.status === 'resolved' ? 'success' : 'info'"
              size="small"
            >
              {{ row.status_text }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="create_time" label="触发时间" width="170" />
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" v-if="row.status === 'pending'" @click="handleAction(row, 'acknowledge')">确认</el-button>
            <el-button link type="success" v-if="row.status !== 'resolved'" @click="handleAction(row, 'resolve')">解决</el-button>
            <el-button link type="info" v-if="row.status === 'pending'" @click="handleAction(row, 'ignore')">忽略</el-button>
            <el-button link @click="viewDetail(row)">详情</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-wrapper">
        <el-pagination
          v-model:current-page="pagination.page"
          v-model:page-size="pagination.limit"
          :total="pagination.total"
          :page-sizes="[20, 50, 100]"
          layout="total, sizes, prev, pager, next"
          @size-change="loadAlerts"
          @current-change="loadAlerts"
        />
      </div>
    </el-card>

    <!-- 告警详情对话框 -->
    <el-dialog v-model="detailDialogVisible" title="告警详情" width="600px">
      <el-descriptions :column="2" border v-if="currentAlert">
        <el-descriptions-item label="告警ID">{{ currentAlert.id }}</el-descriptions-item>
        <el-descriptions-item label="告警类型">{{ currentAlert.alert_type_text }}</el-descriptions-item>
        <el-descriptions-item label="告警级别">
          <el-tag :color="currentAlert.level_color" effect="dark" size="small" style="border: none;">
            {{ currentAlert.alert_level_text }}
          </el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="状态">{{ currentAlert.status_text }}</el-descriptions-item>
        <el-descriptions-item label="设备">{{ currentAlert.device_name || '-' }}</el-descriptions-item>
        <el-descriptions-item label="商户">{{ currentAlert.merchant_name || '-' }}</el-descriptions-item>
        <el-descriptions-item label="告警信息" :span="2">{{ currentAlert.message }}</el-descriptions-item>
        <el-descriptions-item label="触发时间">{{ currentAlert.create_time }}</el-descriptions-item>
        <el-descriptions-item label="处理时间">{{ currentAlert.resolve_time || '-' }}</el-descriptions-item>
      </el-descriptions>
      <div v-if="currentAlert?.related_alerts?.length" style="margin-top: 16px;">
        <h4>相关告警</h4>
        <el-table :data="currentAlert.related_alerts" size="small" stripe>
          <el-table-column prop="alert_type_text" label="类型" width="100" />
          <el-table-column prop="message" label="信息" show-overflow-tooltip />
          <el-table-column prop="create_time" label="时间" width="160" />
        </el-table>
      </div>
    </el-dialog>

    <!-- 告警规则对话框 -->
    <el-dialog v-model="showRuleDialog" title="告警规则配置" width="700px">
      <el-table :data="alertRules" v-loading="rulesLoading" stripe>
        <el-table-column prop="alert_type" label="告警类型" width="120" />
        <el-table-column prop="enabled" label="启用" width="80">
          <template #default="{ row }">
            <el-switch v-model="row.enabled" @change="handleRuleChange(row)" />
          </template>
        </el-table-column>
        <el-table-column prop="threshold" label="阈值" width="100">
          <template #default="{ row }">
            <el-input-number v-model="row.threshold" size="small" :min="1" @change="handleRuleChange(row)" />
          </template>
        </el-table-column>
        <el-table-column prop="interval_minutes" label="检测间隔(分)" width="120">
          <template #default="{ row }">
            <el-input-number v-model="row.interval_minutes" size="small" :min="1" @change="handleRuleChange(row)" />
          </template>
        </el-table-column>
        <el-table-column prop="alert_level" label="级别" width="100">
          <template #default="{ row }">
            <el-select v-model="row.alert_level" size="small">
              <el-option label="低" value="low" />
              <el-option label="中" value="medium" />
              <el-option label="高" value="high" />
              <el-option label="严重" value="critical" />
            </el-select>
          </template>
        </el-table-column>
        <el-table-column prop="notify_channels" label="通知渠道" min-width="150">
          <template #default="{ row }">
            <el-checkbox-group v-model="row.notify_channels" size="small">
              <el-checkbox-button label="sms">短信</el-checkbox-button>
              <el-checkbox-button label="email">邮件</el-checkbox-button>
              <el-checkbox-button label="wechat">微信</el-checkbox-button>
            </el-checkbox-group>
          </template>
        </el-table-column>
      </el-table>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Refresh, Check, Search } from '@element-plus/icons-vue'
import {
  getAlertList,
  getAlertDetail,
  acknowledgeAlert,
  resolveAlert,
  ignoreAlert,
  batchActionAlerts,
  getAlertStats,
  checkDeviceAlerts,
  getAlertRules,
  updateAlertRule
} from '@/api/alert'

const loading = ref(false)
const rulesLoading = ref(false)
const alerts = ref([])
const alertRules = ref([])
const unresolvedCount = ref(0)
const selectedAlerts = ref([])
const detailDialogVisible = ref(false)
const showRuleDialog = ref(false)
const currentAlert = ref(null)

const filterForm = reactive({
  alert_type: '',
  alert_level: '',
  status: 'pending'
})

const pagination = reactive({
  page: 1,
  limit: 20,
  total: 0
})

const loadAlerts = async () => {
  loading.value = true
  try {
    const res = await getAlertList({
      ...filterForm,
      page: pagination.page,
      limit: pagination.limit
    })
    const data = res || {}
    alerts.value = data.list || data.data || []
    pagination.total = data.total || 0
  } catch (e) {
    alerts.value = []
  } finally {
    loading.value = false
  }
}

const loadStats = async () => {
  try {
    const res = await getAlertStats({})
    unresolvedCount.value = res?.unresolved_count || 0
  } catch (e) {
    console.error('加载告警统计失败:', e)
  }
}

const loadRules = async () => {
  rulesLoading.value = true
  try {
    const res = await getAlertRules({})
    alertRules.value = res?.rules || []
  } catch (e) {
    alertRules.value = []
  } finally {
    rulesLoading.value = false
  }
}

const loadData = () => {
  loadAlerts()
  loadStats()
}

const handleSelectionChange = (rows) => {
  selectedAlerts.value = rows
}

const handleAction = async (row, action) => {
  const actions = {
    acknowledge: acknowledgeAlert,
    resolve: resolveAlert,
    ignore: ignoreAlert
  }
  try {
    await actions[action](row.id, {})
    ElMessage.success('操作成功')
    loadAlerts()
    loadStats()
  } catch (e) {
    ElMessage.error('操作失败')
  }
}

const handleBatchAction = async (action) => {
  const ids = selectedAlerts.value.map(a => a.id)
  if (ids.length === 0) return
  try {
    await batchActionAlerts({ alert_ids: ids, action })
    ElMessage.success('批量操作成功')
    loadAlerts()
    loadStats()
  } catch (e) {
    ElMessage.error('批量操作失败')
  }
}

const viewDetail = async (row) => {
  try {
    const res = await getAlertDetail(row.id)
    currentAlert.value = res
    detailDialogVisible.value = true
  } catch (e) {
    ElMessage.error('获取详情失败')
  }
}

const handleCheckAlerts = async () => {
  try {
    await checkDeviceAlerts({})
    ElMessage.success('告警检测已执行')
    loadData()
  } catch (e) {
    ElMessage.error('告警检测失败')
  }
}

const handleRuleChange = (() => {
  const timers = {}
  return (rule) => {
    const key = rule.alert_type
    if (timers[key]) clearTimeout(timers[key])
    timers[key] = setTimeout(async () => {
      try {
        await updateAlertRule({
          alert_type: rule.alert_type,
          rule: {
            enabled: rule.enabled,
            threshold: rule.threshold,
            interval_minutes: rule.interval_minutes,
            alert_level: rule.alert_level,
            notify_channels: rule.notify_channels
          }
        })
        ElMessage.success('规则更新成功')
      } catch (e) {
        ElMessage.error('规则更新失败')
      }
    }, 300)
  }
})()

onMounted(() => {
  loadData()
  loadRules()
})
</script>

<style lang="scss" scoped>
.alert-monitor-container {
  padding: 20px;
  background: #f5f7fa;
  min-height: 100vh;

  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;

    .header-left {
      display: flex;
      align-items: center;
      gap: 12px;

      .page-title {
        margin: 0;
        font-size: 24px;
        font-weight: 600;
        color: #303133;
      }
    }

    .header-actions {
      display: flex;
      gap: 12px;
    }
  }

  .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 16px;
    font-weight: 600;

    .batch-actions {
      display: flex;
      gap: 8px;
    }
  }

  .pagination-wrapper {
    display: flex;
    justify-content: flex-end;
    margin-top: 20px;
  }
}

@media (max-width: 768px) {
  .alert-monitor-container {
    padding: 12px;

    .page-header {
      flex-direction: column;
      align-items: flex-start;
      gap: 12px;
    }

    .card-header {
      flex-direction: column;
      align-items: flex-start;
      gap: 8px;
    }
  }
}
</style>
