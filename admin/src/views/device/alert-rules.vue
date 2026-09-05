<template>
  <div class="alert-rules-container">
    <div class="page-header">
      <div class="header-left">
        <h1 class="page-title">告警规则配置</h1>
      </div>
      <div class="header-actions">
        <el-button :icon="Refresh" @click="loadRules">刷新</el-button>
        <el-button type="primary" :icon="Setting" @click="handleResetAll">
          重置全部
        </el-button>
      </div>
    </div>

    <el-card class="template-card" shadow="never" style="margin-bottom: 20px;">
      <div class="template-header">
        <span class="template-title">快速应用模板</span>
        <div class="template-actions">
          <el-button size="small" @click="handleApplyTemplate('basic')">基础模板</el-button>
          <el-button size="small" type="warning" @click="handleApplyTemplate('strict')">严格模板</el-button>
          <el-button size="small" type="success" @click="handleApplyTemplate('relaxed')">宽松模板</el-button>
        </div>
      </div>
    </el-card>

    <el-card class="table-card" shadow="never">
      <el-table
        v-loading="loading"
        :data="rules"
        stripe
      >
        <el-table-column prop="alert_type" label="规则类型" width="160">
          <template #default="{ row }">
            <el-tag type="info" size="small" effect="plain">
              {{ getTypeText(row.alert_type) }}
            </el-tag>
          </template>
        </el-table-column>

        <el-table-column label="告警级别" width="120">
          <template #default="{ row }">
            <el-tag :type="getLevelType(row.level)" size="small">
              {{ getLevelText(row.level) }}
            </el-tag>
          </template>
        </el-table-column>

        <el-table-column label="触发条件" min-width="300">
          <template #default="{ row }">
            <div class="condition-text">
              {{ formatCondition(row) }}
            </div>
          </template>
        </el-table-column>

        <el-table-column label="通知方式" width="160">
          <template #default="{ row }">
            <div class="notify-tags">
              <el-tag v-if="row.notify_in_app" size="small" type="success" style="margin-right: 4px;">站内</el-tag>
              <el-tag v-if="row.notify_email" size="small" type="warning" style="margin-right: 4px;">邮件</el-tag>
              <el-tag v-if="row.notify_sms" size="small" type="danger">短信</el-tag>
              <el-tag v-if="!row.notify_in_app && !row.notify_email && !row.notify_sms" size="small" type="info">无</el-tag>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="启用状态" width="100">
          <template #default="{ row }">
            <el-switch
              v-model="row.enabled"
              @change="handleToggleRule(row)"
            />
          </template>
        </el-table-column>

        <el-table-column label="操作" width="120" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" size="small" :icon="Edit" @click="handleEdit(row)">
              编辑
            </el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog
      v-model="editDialogVisible"
      :title="isEdit ? '编辑规则' : '新建规则'"
      width="600px"
      destroy-on-close
    >
      <el-form :model="ruleForm" label-width="100px">
        <el-form-item label="规则类型">
          <el-select v-model="ruleForm.alert_type" :disabled="isEdit" placeholder="选择告警类型">
            <el-option label="设备离线" value="offline" />
            <el-option label="电池电量低" value="low_battery" />
            <el-option label="响应超时" value="response_timeout" />
            <el-option label="设备故障" value="device_error" />
            <el-option label="信号弱" value="signal_weak" />
            <el-option label="温度异常" value="temperature" />
            <el-option label="心跳异常" value="heartbeat" />
            <el-option label="触发失败" value="trigger_failed" />
          </el-select>
        </el-form-item>

        <el-form-item label="告警级别">
          <el-select v-model="ruleForm.level">
            <el-option label="严重" value="critical" />
            <el-option label="高级" value="high" />
            <el-option label="中级" value="medium" />
            <el-option label="低级" value="low" />
          </el-select>
        </el-form-item>

        <el-form-item label="触发阈值">
          <el-input-number v-model="ruleForm.threshold" :min="1" />
          <span style="margin-left: 8px; color: #909399;">次</span>
        </el-form-item>

        <el-form-item label="时间窗口">
          <el-input-number v-model="ruleForm.window" :min="1" />
          <el-select v-model="ruleForm.window_unit" style="width: 100px; margin-left: 8px;">
            <el-option label="分钟" value="minute" />
            <el-option label="小时" value="hour" />
            <el-option label="天" value="day" />
          </el-select>
        </el-form-item>

        <el-form-item label="冷却时间">
          <el-input-number v-model="ruleForm.cooldown" :min="0" />
          <span style="margin-left: 8px; color: #909399;">分钟（0为无冷却）</span>
        </el-form-item>

        <el-form-item label="通知方式">
          <el-checkbox v-model="ruleForm.notify_in_app">站内通知</el-checkbox>
          <el-checkbox v-model="ruleForm.notify_email">邮件通知</el-checkbox>
          <el-checkbox v-model="ruleForm.notify_sms">短信通知</el-checkbox>
        </el-form-item>

        <el-form-item label="启用规则">
          <el-switch v-model="ruleForm.enabled" />
        </el-form-item>
      </el-form>

      <template #footer>
        <span class="dialog-footer">
          <el-button @click="editDialogVisible = false">取消</el-button>
          <el-button type="primary" @click="handleSaveRule">保存</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Refresh, Setting, Edit } from '@element-plus/icons-vue'
import {
  getAlertRules,
  updateAlertRule,
  resetAlertRule,
  applyAlertRuleTemplate
} from '@/api/alert'

const loading = ref(false)
const rules = ref([])

const editDialogVisible = ref(false)
const isEdit = ref(false)

const ruleForm = reactive({
  alert_type: '',
  level: 'medium',
  threshold: 3,
  window: 5,
  window_unit: 'minute',
  cooldown: 30,
  notify_in_app: true,
  notify_email: false,
  notify_sms: false,
  enabled: true
})

const loadRules = async () => {
  loading.value = true
  try {
    const res = await getAlertRules()
    const data = res || {}
    rules.value = data.rules || []
  } catch (error) {
    console.error('加载规则失败:', error)
    ElMessage.error('加载失败')
  } finally {
    loading.value = false
  }
}

const handleEdit = (row) => {
  isEdit.value = true
  Object.assign(ruleForm, {
    alert_type: row.alert_type,
    level: row.level || 'medium',
    threshold: row.threshold || 3,
    window: row.window || 5,
    window_unit: row.window_unit || 'minute',
    cooldown: row.cooldown || 30,
    notify_in_app: row.notify_in_app !== false,
    notify_email: row.notify_email === true,
    notify_sms: row.notify_sms === true,
    enabled: row.enabled !== false
  })
  editDialogVisible.value = true
}

const handleSaveRule = async () => {
  if (!ruleForm.alert_type) {
    ElMessage.warning('请选择规则类型')
    return
  }
  try {
    await updateAlertRule({
      alert_type: ruleForm.alert_type,
      rule: {
        level: ruleForm.level,
        threshold: ruleForm.threshold,
        window: ruleForm.window,
        window_unit: ruleForm.window_unit,
        cooldown: ruleForm.cooldown,
        notify_in_app: ruleForm.notify_in_app,
        notify_email: ruleForm.notify_email,
        notify_sms: ruleForm.notify_sms,
        enabled: ruleForm.enabled
      }
    })
    ElMessage.success('保存成功')
    editDialogVisible.value = false
    loadRules()
  } catch (error) {
    console.error('保存失败:', error)
    ElMessage.error('保存失败')
  }
}

const handleToggleRule = async (row) => {
  try {
    await updateAlertRule({
      alert_type: row.alert_type,
      rule: { ...row }
    })
    ElMessage.success(row.enabled ? '已启用' : '已禁用')
  } catch (error) {
    row.enabled = !row.enabled
    console.error('切换失败:', error)
    ElMessage.error('操作失败')
  }
}

const handleResetAll = async () => {
  try {
    await ElMessageBox.confirm('确定重置全部告警规则为默认值吗？', '重置确认', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
    await resetAlertRule({})
    ElMessage.success('已重置全部规则')
    loadRules()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('重置失败:', error)
      ElMessage.error('重置失败')
    }
  }
}

const handleApplyTemplate = async (template) => {
  try {
    await ElMessageBox.confirm(`确定应用"${getTemplateName(template)}"吗？将覆盖当前所有规则。`, '应用模板', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
    await applyAlertRuleTemplate({ template })
    ElMessage.success('模板应用成功')
    loadRules()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('应用模板失败:', error)
      ElMessage.error('应用模板失败')
    }
  }
}

const getTemplateName = (template) => {
  const map = { basic: '基础模板', strict: '严格模板', relaxed: '宽松模板' }
  return map[template] || template
}

const getTypeText = (type) => {
  const map = {
    offline: '设备离线',
    low_battery: '电池电量低',
    response_timeout: '响应超时',
    device_error: '设备故障',
    signal_weak: '信号弱',
    temperature: '温度异常',
    heartbeat: '心跳异常',
    trigger_failed: '触发失败'
  }
  return map[type] || type
}

const getLevelType = (level) => {
  const map = { critical: 'danger', high: 'warning', medium: '', low: 'info' }
  return map[level] || ''
}

const getLevelText = (level) => {
  const map = { critical: '严重', high: '高级', medium: '中级', low: '低级' }
  return map[level] || level
}

const formatCondition = (row) => {
  const threshold = row.threshold || 3
  const window = row.window || 5
  const unit = { minute: '分钟', hour: '小时', day: '天' }[row.window_unit] || '分钟'
  return `${window}${unit}内触发${threshold}次`
}

onMounted(() => {
  loadRules()
})
</script>

<style lang="scss" scoped>
.alert-rules-container {
  padding: 20px;
  background: #f5f7fa;
  min-height: 100vh;

  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;

    .header-left {
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

  .template-card {
    .template-header {
      display: flex;
      justify-content: space-between;
      align-items: center;

      .template-title {
        font-size: 14px;
        font-weight: 500;
        color: #606266;
      }

      .template-actions {
        display: flex;
        gap: 8px;
      }
    }
  }

  .table-card {
    .condition-text {
      font-size: 13px;
      color: #606266;
    }

    .notify-tags {
      display: flex;
      flex-wrap: wrap;
      gap: 2px;
    }
  }

  .dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
  }
}

@media (max-width: 768px) {
  .alert-rules-container {
    padding: 12px;

    .page-header {
      flex-direction: column;
      align-items: flex-start;
      gap: 12px;

      .header-actions {
        width: 100%;

        :deep(.el-button) {
          flex: 1;
        }
      }
    }

    .template-card {
      .template-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
      }
    }
  }
}
</style>
