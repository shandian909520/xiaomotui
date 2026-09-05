<template>
  <div class="activity-redpackets">
    <!-- 账户说明卡片 -->
    <el-card class="account-card">
      <div class="account-info">
        <div class="account-text">
          <h3>红包营销活动</h3>
          <p>通过红包激励员工和客户，提升门店活跃度与内容发布量。每次发放红包将收取 1% 手续费。</p>
        </div>
        <el-tooltip content="充值功能即将开放，敬请期待" placement="bottom">
          <span>
            <el-button type="primary" disabled>充值</el-button>
          </span>
        </el-tooltip>
      </div>
    </el-card>

    <!-- 余额统计行 -->
    <div class="balance-cards">
      <div class="balance-card">
        <div class="balance-label">账户余额</div>
        <div class="balance-value">¥{{ formatMoney(balanceOverview.accountBalance) }}</div>
      </div>
      <div class="balance-card">
        <div class="balance-label">预算总余额</div>
        <div class="balance-value primary">¥{{ formatMoney(balanceOverview.totalBudget) }}</div>
      </div>
      <div class="balance-card">
        <div class="balance-label">剩余预算金额</div>
        <div class="balance-value success">¥{{ formatMoney(balanceOverview.remainBudget) }}</div>
      </div>
      <div class="balance-card">
        <div class="balance-label">实际消耗金额</div>
        <div class="balance-value danger">¥{{ formatMoney(balanceOverview.actualCost) }}</div>
      </div>
    </div>

    <!-- 操作栏 -->
    <el-card class="search-card">
      <div class="search-actions">
        <el-button type="primary" @click="handleAddActivity">
          <el-icon><Plus /></el-icon>
          添加活动
        </el-button>
      </div>
    </el-card>

    <!-- 活动列表 -->
    <el-card class="table-card">
      <el-table :data="activityList" v-loading="loading" stripe>
        <el-table-column prop="name" label="活动名称" min-width="160" />
        <el-table-column prop="budget" label="预算金额" width="120" align="center">
          <template #default="{ row }">¥{{ formatMoney(row.budget) }}</template>
        </el-table-column>
        <el-table-column prop="actualCost" label="实际消耗" width="120" align="center">
          <template #default="{ row }">¥{{ formatMoney(row.actualCost) }}</template>
        </el-table-column>
        <el-table-column prop="storeCount" label="参与门店数" width="110" align="center" />
        <el-table-column label="活动时间" width="200">
          <template #default="{ row }">{{ row.startTime }} ~ {{ row.endTime }}</template>
        </el-table-column>
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="getStatusType(row.status)">{{ getStatusText(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200" fixed="right" align="center">
          <template #default="{ row }">
            <el-button size="small" type="primary" link @click="handleEditActivity(row)">编辑</el-button>
            <el-button size="small" :type="row.status === 'active' ? 'warning' : 'success'" link @click="handleToggleStatus(row)">
              {{ row.status === 'active' ? '停用' : '启用' }}
            </el-button>
            <el-button size="small" link @click="handleViewStats(row)">统计</el-button>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        v-model:current-page="pagination.page"
        v-model:page-size="pagination.limit"
        :total="pagination.total"
        :page-sizes="[10, 20, 50]"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="loadActivityList"
        @current-change="loadActivityList"
      />
    </el-card>

    <!-- 添加/编辑活动弹窗 -->
    <el-dialog v-model="dialogVisible" :title="dialogTitle" width="640px" @close="resetForm">
      <el-form :model="activityForm" :rules="formRules" ref="formRef" label-width="110px">
        <el-form-item label="活动名称" prop="name">
          <el-input v-model="activityForm.name" placeholder="请输入活动名称" />
        </el-form-item>
        <el-form-item label="预算金额" prop="budget">
          <el-input-number v-model="activityForm.budget" :min="1" :precision="2" style="width: 100%" />
        </el-form-item>
        <el-form-item label="开始时间" prop="startTime">
          <el-date-picker v-model="activityForm.startTime" type="datetime" placeholder="选择开始时间" value-format="YYYY-MM-DD HH:mm:ss" style="width: 100%" />
        </el-form-item>
        <el-form-item label="结束时间" prop="endTime">
          <el-date-picker v-model="activityForm.endTime" type="datetime" placeholder="选择结束时间" value-format="YYYY-MM-DD HH:mm:ss" style="width: 100%" />
        </el-form-item>
        <el-form-item label="参与门店">
          <el-select v-model="activityForm.storeIds" multiple placeholder="请选择门店" style="width: 100%">
            <el-option v-for="store in storeOptions" :key="store.id" :label="store.name" :value="store.id" />
          </el-select>
        </el-form-item>
        <el-divider content-position="left">红包规则配置</el-divider>
        <el-form-item label="红包类型">
          <el-radio-group v-model="activityForm.redpacketType">
            <el-radio label="random">随机红包</el-radio>
            <el-radio label="fixed">固定红包</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="最小金额" v-if="activityForm.redpacketType === 'random'">
          <el-input-number v-model="activityForm.minAmount" :min="0.01" :precision="2" />
        </el-form-item>
        <el-form-item label="最大金额" v-if="activityForm.redpacketType === 'random'">
          <el-input-number v-model="activityForm.maxAmount" :min="0.01" :precision="2" />
        </el-form-item>
        <el-form-item label="固定金额" v-if="activityForm.redpacketType === 'fixed'">
          <el-input-number v-model="activityForm.fixedAmount" :min="0.01" :precision="2" />
        </el-form-item>
        <el-form-item label="每日上限">
          <el-input-number v-model="activityForm.dailyLimit" :min="1" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmit" :loading="submitting">确定</el-button>
      </template>
    </el-dialog>

    <!-- 统计弹窗 -->
    <el-dialog v-model="statsDialogVisible" title="活动统计" width="700px">
      <div v-if="currentStats" class="stats-content">
        <el-descriptions :column="2" border>
          <el-descriptions-item label="活动名称">{{ currentStats.name }}</el-descriptions-item>
          <el-descriptions-item label="预算金额">¥{{ formatMoney(currentStats.budget) }}</el-descriptions-item>
          <el-descriptions-item label="实际消耗">¥{{ formatMoney(currentStats.actualCost) }}</el-descriptions-item>
          <el-descriptions-item label="领取人数">{{ currentStats.receiveCount || 0 }}</el-descriptions-item>
          <el-descriptions-item label="领取金额">¥{{ formatMoney(currentStats.receiveAmount) }}</el-descriptions-item>
          <el-descriptions-item label="参与门店">{{ currentStats.storeCount || 0 }}</el-descriptions-item>
        </el-descriptions>
      </div>
      <template #footer>
        <el-button @click="statsDialogVisible = false">关闭</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import {
  getRedpacketActivityList,
  getRedpacketActivityDetail,
  createRedpacketActivity,
  updateRedpacketActivity,
  toggleRedpacketStatus,
  getRedpacketStats,
  getRedpacketBalanceOverview
} from '@/api/index'
import { normalizePagination } from '@/utils/responseHelper'

const loading = ref(false)
const submitting = ref(false)
const dialogVisible = ref(false)
const dialogTitle = ref('添加活动')
const isEdit = ref(false)
const formRef = ref(null)
const statsDialogVisible = ref(false)
const currentStats = ref(null)

const activityList = ref([])
const pagination = reactive({ page: 1, limit: 10, total: 0 })

const balanceOverview = reactive({
  accountBalance: 0,
  totalBudget: 0,
  remainBudget: 0,
  actualCost: 0
})

const storeOptions = ref([
  { id: 1, name: '旗舰店' },
  { id: 2, name: '城西店' },
  { id: 3, name: '城南店' },
  { id: 4, name: '城东店' },
  { id: 5, name: '城北店' }
])

const activityForm = reactive({
  id: null,
  name: '',
  budget: 100,
  startTime: '',
  endTime: '',
  storeIds: [],
  redpacketType: 'random',
  minAmount: 1,
  maxAmount: 10,
  fixedAmount: 5,
  dailyLimit: 100
})

const formRules = {
  name: [{ required: true, message: '请输入活动名称', trigger: 'blur' }],
  budget: [{ required: true, message: '请输入预算金额', trigger: 'blur' }],
  startTime: [{ required: true, message: '请选择开始时间', trigger: 'change' }],
  endTime: [{ required: true, message: '请选择结束时间', trigger: 'change' }]
}


const formatMoney = (val) => {
  if (!val && val !== 0) return '0.00'
  return Number(val).toFixed(2)
}

const getStatusType = (status) => {
  const map = { active: 'success', disabled: 'info', ended: 'danger' }
  return map[status] || 'info'
}

const getStatusText = (status) => {
  const map = { active: '进行中', disabled: '已停用', ended: '已结束' }
  return map[status] || '未知'
}

const loadBalanceOverview = async () => {
  try {
    const res = await getRedpacketBalanceOverview()
    const data = res && typeof res === 'object' ? res : {}
    Object.assign(balanceOverview, data)
  } catch (err) {
    console.error('获取余额概览失败:', err)
    Object.assign(balanceOverview, { accountBalance: 0, totalBudget: 0, remainBudget: 0, actualCost: 0 })
    ElMessage.error('获取余额概览失败，请稍后重试')
  }
}

const loadActivityList = async () => {
  loading.value = true
  try {
    const params = { page: pagination.page, limit: pagination.limit }
    const res = await getRedpacketActivityList(params)
    const { list, total } = normalizePagination(res)
    // 映射后端 RedpacketActivity 字段到前端期望的字段
    activityList.value = list.map(item => ({
      id: item.id,
      name: item.name || '',
      budget: item.budgetAmount || item.budget || 0,
      actualCost: item.consumedAmount || item.actualCost || 0,
      storeCount: item.storeCount || 0,
      startTime: item.startTime || '',
      endTime: item.endTime || '',
      // 后端 status: 0=停用, 1=进行中, 2=已结束
      status: item.status === 1 ? 'active' : (item.status === 2 ? 'ended' : 'disabled')
    }))
    pagination.total = total
  } catch (err) {
    console.error('获取活动列表失败:', err)
    activityList.value = []
    pagination.total = 0
    ElMessage.error('获取活动列表失败，请稍后重试')
  } finally {
    loading.value = false
  }
}

const handleAddActivity = () => {
  dialogTitle.value = '添加活动'
  isEdit.value = false
  Object.assign(activityForm, {
    id: null, name: '', budget: 100, startTime: '', endTime: '',
    storeIds: [], redpacketType: 'random', minAmount: 1, maxAmount: 10, fixedAmount: 5, dailyLimit: 100
  })
  dialogVisible.value = true
}

const handleEditActivity = async (row) => {
  dialogTitle.value = '编辑活动'
  isEdit.value = true
  try {
    const res = await getRedpacketActivityDetail(row.id)
    const data = res && typeof res === 'object' ? res : row
    Object.assign(activityForm, {
      id: data.id, name: data.name, budget: data.budget,
      startTime: data.startTime, endTime: data.endTime,
      storeIds: data.storeIds || [], redpacketType: data.redpacketType || 'random',
      minAmount: data.minAmount || 1, maxAmount: data.maxAmount || 10,
      fixedAmount: data.fixedAmount || 5, dailyLimit: data.dailyLimit || 100
    })
  } catch (err) {
    Object.assign(activityForm, { id: row.id, name: row.name, budget: row.budget, startTime: row.startTime, endTime: row.endTime, storeIds: [], redpacketType: 'random', minAmount: 1, maxAmount: 10, fixedAmount: 5, dailyLimit: 100 })
  }
  dialogVisible.value = true
}

const handleSubmit = async () => {
  if (!formRef.value) return
  await formRef.value.validate()
  submitting.value = true
  try {
    const data = { ...activityForm }
    if (isEdit.value) {
      await updateRedpacketActivity(data)
      ElMessage.success('更新成功')
    } else {
      await createRedpacketActivity(data)
      ElMessage.success('创建成功')
    }
    dialogVisible.value = false
    loadActivityList()
    loadBalanceOverview()
  } catch (err) {
    console.error('提交失败:', err)
    ElMessage.error(err.message || '操作失败')
  } finally {
    submitting.value = false
  }
}

const handleToggleStatus = async (row) => {
  const action = row.status === 'active' ? '停用' : '启用'
  try {
    await ElMessageBox.confirm(`确定${action}活动 "${row.name}" 吗？`, '提示', { type: 'warning' })
    await toggleRedpacketStatus({ id: row.id })
    ElMessage.success('状态已更新')
    loadActivityList()
  } catch (err) {
    if (err !== 'cancel') {
      console.error('切换状态失败:', err)
      ElMessage.error('操作失败')
    }
  }
}

const handleViewStats = async (row) => {
  try {
    const res = await getRedpacketStats(row.id)
    currentStats.value = res && typeof res === 'object' ? res : row
  } catch (err) {
    console.error('获取统计失败:', err)
    currentStats.value = { ...row, receiveCount: 0, receiveAmount: 0 }
    ElMessage.error('获取统计失败，请稍后重试')
  }
  statsDialogVisible.value = true
}

const resetForm = () => {
  formRef.value?.resetFields()
}

onMounted(() => {
  loadBalanceOverview()
  loadActivityList()
})
</script>

<style scoped lang="scss">
.activity-redpackets {
  padding: 20px;

  .account-card {
    margin-bottom: 20px;

    .account-info {
      display: flex;
      justify-content: space-between;
      align-items: center;

      .account-text {
        h3 {
          font-size: 18px;
          font-weight: 600;
          margin: 0 0 6px;
        }

        p {
          font-size: 13px;
          color: #909399;
          margin: 0;
        }
      }
    }
  }

  .balance-cards {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 20px;
  }

  .balance-card {
    background: #fff;
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    border: 1px solid #ebeef5;

    .balance-label {
      font-size: 13px;
      color: #909399;
      margin-bottom: 8px;
    }

    .balance-value {
      font-size: 24px;
      font-weight: 700;
      color: #303133;

      &.primary { color: #409eff; }
      &.success { color: #67c23a; }
      &.danger { color: #f56c6c; }
    }
  }

  .search-card {
    margin-bottom: 20px;

    .search-actions {
      display: flex;
      gap: 10px;
    }
  }

  .table-card {
    :deep(.el-pagination) {
      margin-top: 20px;
      justify-content: flex-end;
    }
  }

  .stats-content {
    padding: 8px 0;
  }
}

@media (max-width: 768px) {
  .activity-redpackets .balance-cards {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>
