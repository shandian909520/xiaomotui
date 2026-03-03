<template>
  <div class="app-container">
    <div class="filter-container">
      <el-select v-model="listQuery.status" placeholder="状态" clearable style="width: 120px" class="filter-item">
        <el-option label="启用" :value="1" />
        <el-option label="禁用" :value="0" />
      </el-select>
      <el-button class="filter-item" type="primary" icon="Plus" @click="handleCreate">
        新建优惠券
      </el-button>
    </div>

    <el-table
      v-loading="listLoading"
      :data="list"
      border
      fit
      highlight-current-row
      style="width: 100%;"
    >
      <el-table-column label="ID" prop="id" align="center" width="80" />
      <el-table-column label="名称" prop="name" align="center" />
      <el-table-column label="类型" align="center">
        <template #default="{ row }">
          <el-tag>{{ row.type === 'FULL_REDUCE' ? '满减' : (row.type === 'DISCOUNT' ? '折扣' : row.type) }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="面值" align="center">
        <template #default="{ row }">
          <span v-if="row.type === 'FULL_REDUCE'">¥{{ row.value }}</span>
          <span v-else>{{ row.value }}折</span>
        </template>
      </el-table-column>
      <el-table-column label="库存" prop="total_count" align="center" />
      <el-table-column label="剩余" prop="remain_count" align="center" />
      <el-table-column label="有效期" width="300" align="center">
        <template #default="{ row }">
          {{ formatDate(row.start_time) }} - {{ formatDate(row.end_time) }}
        </template>
      </el-table-column>
      <el-table-column label="状态" align="center">
        <template #default="{ row }">
          <el-tag :type="row.status === 1 ? 'success' : 'info'">
            {{ row.status === 1 ? '启用' : '禁用' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="操作" align="center" width="230" class-name="small-padding fixed-width">
        <template #default="{ row }">
          <el-button type="primary" size="small" @click="handleUpdate(row)">
            编辑
          </el-button>
          <el-button type="danger" size="small" @click="handleDelete(row)">
            删除
          </el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog :title="textMap[dialogStatus]" v-model="dialogFormVisible">
      <el-form ref="dataForm" :rules="rules" :model="temp" label-position="left" label-width="100px" style="width: 400px; margin-left:50px;">
        <el-form-item label="名称" prop="name">
          <el-input v-model="temp.name" />
        </el-form-item>
        <el-form-item label="类型" prop="type">
          <el-select v-model="temp.type" class="filter-item" placeholder="请选择">
            <el-option label="满减" value="FULL_REDUCE" />
            <el-option label="折扣" value="DISCOUNT" />
          </el-select>
        </el-form-item>
        <el-form-item label="面值" prop="value">
          <el-input v-model="temp.value" />
        </el-form-item>
        <el-form-item label="使用门槛" prop="min_amount">
          <el-input v-model="temp.min_amount" placeholder="0表示无门槛" />
        </el-form-item>
        <el-form-item label="总数量" prop="total_count">
          <el-input v-model="temp.total_count" />
        </el-form-item>
        <el-form-item label="每人限领" prop="per_limit">
          <el-input v-model="temp.per_limit" />
        </el-form-item>
        <el-form-item label="有效期">
          <el-date-picker
            v-model="temp.timeRange"
            type="datetimerange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            value-format="YYYY-MM-DD HH:mm:ss"
          />
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="temp.status" :active-value="1" :inactive-value="0" />
        </el-form-item>
      </el-form>
      <template #footer>
        <div class="dialog-footer">
          <el-button @click="dialogFormVisible = false">
            取消
          </el-button>
          <el-button type="primary" @click="dialogStatus==='create'?createData():updateData()">
            确认
          </el-button>
        </div>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, nextTick } from 'vue'
import { getCouponList, createCoupon, updateCoupon, deleteCoupon } from '@/api/coupon'
import { ElMessage, ElMessageBox } from 'element-plus'

const list = ref([])
const listLoading = ref(true)
const listQuery = reactive({
  page: 1,
  limit: 20,
  status: undefined
})

const temp = reactive({
  id: undefined,
  name: '',
  type: 'FULL_REDUCE',
  value: 0,
  min_amount: 0,
  total_count: 100,
  per_limit: 1,
  status: 1,
  timeRange: []
})

const dialogFormVisible = ref(false)
const dialogStatus = ref('')
const textMap = {
  update: '编辑',
  create: '新建'
}

const rules = {
  name: [{ required: true, message: '请输入名称', trigger: 'blur' }],
  type: [{ required: true, message: '请选择类型', trigger: 'change' }],
  value: [{ required: true, message: '请输入面值', trigger: 'blur' }]
}

const dataForm = ref(null)

onMounted(() => {
  getList()
})

function getList() {
  listLoading.value = true
  getCouponList(listQuery).then(response => {
    console.log('Coupon List API Response:', response)
    if (response && response.data && Array.isArray(response.data)) {
        list.value = response.data
    } else if (response && response.data) { // pagination object but data might be inside
        // If response.data is { total: ..., data: [...] }
        if (Array.isArray(response.data)) {
           list.value = response.data
        } else if (response.data.data && Array.isArray(response.data.data)) {
           list.value = response.data.data
        } else {
           list.value = []
        }
    } else if (Array.isArray(response)) {
        list.value = response
    } else {
        list.value = []
    }
    listLoading.value = false
  }).catch(err => {
      console.error('Coupon List API Error:', err)
      listLoading.value = false
  })
}

function resetTemp() {
  temp.id = undefined
  temp.name = ''
  temp.type = 'FULL_REDUCE'
  temp.value = 0
  temp.min_amount = 0
  temp.total_count = 100
  temp.per_limit = 1
  temp.status = 1
  temp.timeRange = []
}

function handleCreate() {
  resetTemp()
  dialogStatus.value = 'create'
  dialogFormVisible.value = true
  nextTick(() => {
    dataForm.value?.clearValidate()
  })
}

function createData() {
  dataForm.value?.validate((valid) => {
    if (valid) {
      if (temp.timeRange && temp.timeRange.length === 2) {
        temp.start_time = temp.timeRange[0]
        temp.end_time = temp.timeRange[1]
      }
      createCoupon(temp).then(() => {
        dialogFormVisible.value = false
        ElMessage({
          message: '创建成功',
          type: 'success',
          duration: 2000
        })
        getList()
      })
    }
  })
}

function handleUpdate(row) {
  Object.assign(temp, row)
  if (row.start_time && row.end_time) {
      temp.timeRange = [row.start_time, row.end_time]
  } else {
      temp.timeRange = []
  }
  dialogStatus.value = 'update'
  dialogFormVisible.value = true
  nextTick(() => {
    dataForm.value?.clearValidate()
  })
}

function updateData() {
  dataForm.value?.validate((valid) => {
    if (valid) {
      const tempData = Object.assign({}, temp)
      if (tempData.timeRange && tempData.timeRange.length === 2) {
        tempData.start_time = tempData.timeRange[0]
        tempData.end_time = tempData.timeRange[1]
      }
      updateCoupon(tempData).then(() => {
        dialogFormVisible.value = false
        ElMessage({
          message: '更新成功',
          type: 'success',
          duration: 2000
        })
        getList()
      })
    }
  })
}

function handleDelete(row) {
  ElMessageBox.confirm('确认删除?', '提示', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(() => {
    deleteCoupon(row.id).then(() => {
      ElMessage({
        message: '删除成功',
        type: 'success',
        duration: 2000
      })
      getList()
    })
  })
}

function formatDate(time) {
  if (!time) return ''
  return time
}
</script>

<style scoped>
.filter-container {
  padding-bottom: 10px;
}
.filter-item {
  display: inline-block;
  vertical-align: middle;
  margin-bottom: 10px;
  margin-right: 10px;
}
</style>
