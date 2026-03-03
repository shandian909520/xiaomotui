<template>
  <div class="app-container">
    <div class="filter-container">
      <el-input
        v-model="listQuery.keyword"
        placeholder="模板名称"
        style="width: 200px;"
        class="filter-item"
        @keyup.enter="handleFilter"
      />
      <el-select v-model="listQuery.type" placeholder="类型" clearable class="filter-item" style="width: 130px">
        <el-option label="视频" value="VIDEO" />
        <el-option label="文本" value="TEXT" />
        <el-option label="图片" value="IMAGE" />
      </el-select>
      <el-button type="primary" class="filter-item" icon="Search" @click="handleFilter">
        搜索
      </el-button>
      <el-button type="primary" class="filter-item" icon="Plus" @click="handleCreate">
        新增
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
      <el-table-column label="ID" prop="id" align="center" width="80">
        <template #default="{ row }">
          <span>{{ row.id }}</span>
        </template>
      </el-table-column>

      <el-table-column label="模板名称" min-width="150px">
        <template #default="{ row }">
          <span>{{ row.name }}</span>
        </template>
      </el-table-column>

      <el-table-column label="类型" width="100px" align="center">
        <template #default="{ row }">
          <el-tag :type="row.type === 'VIDEO' ? 'success' : 'info'">{{ row.type }}</el-tag>
        </template>
      </el-table-column>

      <el-table-column label="分类" width="100px" align="center">
        <template #default="{ row }">
          <span>{{ row.category }}</span>
        </template>
      </el-table-column>

      <el-table-column label="状态" class-name="status-col" width="100">
        <template #default="{ row }">
          <el-tag :type="row.status === 1 ? 'success' : 'danger'">
            {{ row.status === 1 ? '启用' : '禁用' }}
          </el-tag>
        </template>
      </el-table-column>

      <el-table-column label="创建时间" width="160px" align="center">
        <template #default="{ row }">
          <span>{{ row.create_time }}</span>
        </template>
      </el-table-column>

      <el-table-column label="操作" align="center" width="230" class-name="small-padding fixed-width">
        <template #default="{ row }">
          <el-button type="primary" size="small" @click="handleUpdate(row)">
            编辑
          </el-button>
          <el-button v-if="row.status!='deleted'" size="small" type="danger" @click="handleDelete(row)">
            删除
          </el-button>
        </template>
      </el-table-column>
    </el-table>

    <div class="pagination-container">
      <el-pagination
        v-model:current-page="listQuery.page"
        v-model:page-size="listQuery.limit"
        :total="total"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="handleFilter"
        @current-change="handleFilter"
      />
    </div>

    <el-dialog :title="textMap[dialogStatus]" v-model="dialogFormVisible">
      <el-form
        ref="dataForm"
        :rules="rules"
        :model="temp"
        label-position="left"
        label-width="100px"
        style="width: 400px; margin-left:50px;"
      >
        <el-form-item label="类型" prop="type">
          <el-select v-model="temp.type" class="filter-item" placeholder="请选择">
            <el-option label="视频" value="VIDEO" />
            <el-option label="文本" value="TEXT" />
            <el-option label="图片" value="IMAGE" />
          </el-select>
        </el-form-item>
        <el-form-item label="模板名称" prop="name">
          <el-input v-model="temp.name" />
        </el-form-item>
        <el-form-item label="分类" prop="category">
          <el-select v-model="temp.category" class="filter-item" placeholder="请选择">
            <el-option label="营销" value="marketing" />
            <el-option label="节日" value="festival" />
            <el-option label="日常" value="daily" />
            <el-option label="其他" value="other" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="temp.status" class="filter-item" placeholder="请选择">
            <el-option label="启用" :value="1" />
            <el-option label="禁用" :value="0" />
          </el-select>
        </el-form-item>
        <el-form-item label="内容/URL" prop="content">
          <el-input v-model="temp.content" type="textarea" :rows="2" placeholder="如果是视频/图片请输入URL，如果是文本请输入内容" />
        </el-form-item>
        <el-form-item label="描述">
          <el-input v-model="temp.description" type="textarea" />
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
import { getTemplateList, deleteTemplate, createTemplate, updateTemplate } from '@/api/content'
import { ElMessage, ElMessageBox } from 'element-plus'

const list = ref([])
const total = ref(0)
const listLoading = ref(true)
const listQuery = reactive({
  page: 1,
  limit: 20,
  keyword: undefined,
  type: undefined,
  sort: '+id'
})

const dialogFormVisible = ref(false)
const dialogStatus = ref('')
const textMap = {
  update: '编辑',
  create: '创建'
}
const temp = reactive({
  id: undefined,
  type: 'VIDEO',
  name: '',
  category: 'marketing',
  status: 1,
  content: '',
  description: ''
})
const dataForm = ref(null)

const rules = {
  type: [{ required: true, message: '类型是必填项', trigger: 'change' }],
  name: [{ required: true, message: '名称是必填项', trigger: 'blur' }],
  category: [{ required: true, message: '分类是必填项', trigger: 'change' }],
  content: [{ required: true, message: '内容是必填项', trigger: 'blur' }]
}

function getList() {
  listLoading.value = true
  getTemplateList(listQuery).then(response => {
    if (response.data) {
      list.value = response.data.data || response.data.list || []
      total.value = response.data.total || 0
    }
    listLoading.value = false
  }).catch(() => {
    listLoading.value = false
  })
}

function handleFilter() {
  listQuery.page = 1
  getList()
}

function resetTemp() {
  temp.id = undefined
  temp.type = 'VIDEO'
  temp.name = ''
  temp.category = 'marketing'
  temp.status = 1
  temp.content = ''
  temp.description = ''
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
      createTemplate(temp).then(() => {
        list.value.unshift(temp)
        dialogFormVisible.value = false
        ElMessage.success({
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
  temp.id = row.id
  temp.type = row.type
  temp.name = row.name
  temp.category = row.category
  temp.status = row.status
  temp.content = row.content || '' // 假设row中有content
  temp.description = row.description || '' // 假设row中有description
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
      updateTemplate(tempData).then(() => {
        const index = list.value.findIndex(v => v.id === temp.id)
        list.value.splice(index, 1, temp)
        dialogFormVisible.value = false
        ElMessage.success({
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
  ElMessageBox.confirm('确认删除该模板?', '提示', {
    confirmButtonText: '确定',
    cancelButtonText: '取消',
    type: 'warning'
  }).then(() => {
    deleteTemplate(row.id).then(() => {
      ElMessage.success('删除成功')
      getList()
    })
  })
}

onMounted(() => {
  getList()
})
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
.pagination-container {
  margin-top: 20px;
}
</style>
