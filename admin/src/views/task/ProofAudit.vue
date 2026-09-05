<template>
  <div class="proof-audit">
    <!-- 状态 tab -->
    <el-card class="table-card">
      <el-tabs v-model="activeTab" @tab-change="handleTabChange">
        <el-tab-pane label="待审核" name="pending" />
        <el-tab-pane label="已通过" name="approved" />
        <el-tab-pane label="已驳回" name="rejected" />
      </el-tabs>

      <el-table :data="proofList" v-loading="loading" stripe>
        <el-table-column prop="id" label="凭证ID" width="80" align="center" />
        <el-table-column prop="taskInstanceId" label="任务ID" width="100" align="center">
          <template #default="{ row }">{{ row.taskInstanceId || row.task_instance_id || '-' }}</template>
        </el-table-column>
        <el-table-column prop="actionName" label="动作名" min-width="130">
          <template #default="{ row }">{{ row.actionName || row.action_name || '-' }}</template>
        </el-table-column>
        <el-table-column prop="bundleTitle" label="所属任务包" min-width="140" show-overflow-tooltip>
          <template #default="{ row }">{{ row.bundleTitle || row.bundle_title || '-' }}</template>
        </el-table-column>
        <el-table-column label="凭证截图" width="120" align="center">
          <template #default="{ row }">
            <el-image
              v-if="row.fileUrl || row.file_url"
              :src="row.fileUrl || row.file_url"
              :preview-src-list="[row.fileUrl || row.file_url]"
              preview-teleported
              fit="cover"
              style="width: 60px; height: 60px; border-radius: 4px"
            />
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column prop="createTime" label="提交时间" width="170">
          <template #default="{ row }">{{ row.createTime || row.create_time || '-' }}</template>
        </el-table-column>
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="statusTagType(row.auditStatus ?? row.audit_status)">
              {{ statusText(row.auditStatus ?? row.audit_status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="审核备注" min-width="130" show-overflow-tooltip>
          <template #default="{ row }">{{ row.remark || row.auditRemark || row.audit_remark || '-' }}</template>
        </el-table-column>
        <el-table-column label="操作" width="140" fixed="right" align="center">
          <template #default="{ row }">
            <template v-if="normStatus(row.auditStatus ?? row.audit_status) === 'pending'">
              <el-button size="small" type="success" link @click="handleApprove(row)">通过</el-button>
              <el-button size="small" type="danger" link @click="openReject(row)">驳回</el-button>
            </template>
            <span v-else class="done-text">已处理</span>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        v-model:current-page="pagination.page"
        v-model:page-size="pagination.limit"
        :total="pagination.total"
        :page-sizes="[10, 20, 50]"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="loadList"
        @current-change="loadList"
      />
    </el-card>

    <!-- 驳回备注弹窗 -->
    <el-dialog v-model="rejectDialogVisible" title="驳回凭证" width="440px">
      <el-form label-width="80px">
        <el-form-item label="驳回原因" required>
          <el-input v-model="rejectRemark" type="textarea" :rows="3" placeholder="请填写驳回原因（将展示给用户）" maxlength="200" show-word-limit />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="rejectDialogVisible = false">取消</el-button>
        <el-button type="danger" :loading="submitting" @click="handleReject">确认驳回</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getProofList, auditProof } from '@/api/task'
import { normalizePagination } from '@/utils/responseHelper'

const loading = ref(false)
const submitting = ref(false)
const activeTab = ref('pending')
const proofList = ref([])
const pagination = reactive({ page: 1, limit: 10, total: 0 })

const rejectDialogVisible = ref(false)
const rejectRemark = ref('')
const currentProof = ref(null)

// 兼容后端大写枚举/小写/数字状态
const normStatus = (s) => {
  const v = typeof s === 'string' ? s.toUpperCase() : s
  if (v === 'PENDING' || v === 0) return 'pending'
  if (v === 'APPROVED' || v === 1) return 'approved'
  if (v === 'REJECTED' || v === 2) return 'rejected'
  return 'pending'
}

const statusText = (s) => ({ pending: '待审核', approved: '已通过', rejected: '已驳回' })[normStatus(s)]
const statusTagType = (s) => ({ pending: 'warning', approved: 'success', rejected: 'danger' })[normStatus(s)]

const loadList = async () => {
  loading.value = true
  try {
    const params = { page: pagination.page, limit: pagination.limit, audit_status: activeTab.value.toUpperCase() }
    const res = await getProofList(params)
    const { list, total } = normalizePagination(res)
    proofList.value = list
    pagination.total = total
  } catch (err) {
    console.error('获取凭证列表失败:', err)
    proofList.value = []
    pagination.total = 0
    ElMessage.error('获取凭证列表失败，请稍后重试')
  } finally {
    loading.value = false
  }
}

const handleTabChange = () => {
  pagination.page = 1
  loadList()
}

const handleApprove = async (row) => {
  try {
    await ElMessageBox.confirm('确认通过该凭证吗？通过后对应动作将记为完成。', '提示', { type: 'warning' })
    await auditProof(row.id, { result: 'approved' })
    ElMessage.success('已通过')
    loadList()
  } catch (err) {
    if (err !== 'cancel') {
      console.error('审核失败:', err)
      ElMessage.error('操作失败，请稍后重试')
    }
  }
}

const openReject = (row) => {
  currentProof.value = row
  rejectRemark.value = ''
  rejectDialogVisible.value = true
}

const handleReject = async () => {
  if (!rejectRemark.value.trim()) {
    ElMessage.warning('请填写驳回原因')
    return
  }
  submitting.value = true
  try {
    await auditProof(currentProof.value.id, { result: 'rejected', remark: rejectRemark.value.trim() })
    ElMessage.success('已驳回')
    rejectDialogVisible.value = false
    loadList()
  } catch (err) {
    console.error('驳回失败:', err)
    ElMessage.error('驳回失败，请稍后重试')
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  loadList()
})
</script>

<style scoped lang="scss">
.proof-audit {
  padding: 20px;

  .table-card {
    :deep(.el-tabs) {
      margin-bottom: 8px;
    }

    :deep(.el-pagination) {
      margin-top: 20px;
      justify-content: flex-end;
    }
  }

  .done-text {
    color: #c0c4cc;
    font-size: 12px;
  }
}
</style>
