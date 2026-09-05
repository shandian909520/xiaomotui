<template>
  <div class="topic-library">
    <PageHeader title="话题库" subtitle="管理话题库，添加和编辑热门话题" />

    <!-- 搜索和操作栏 -->
    <el-card class="search-card">
      <el-form :inline="true" :model="searchForm">
        <el-form-item label="名称">
          <el-input v-model="searchForm.name" placeholder="搜索话题库名称" clearable @keyup.enter="handleSearch" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">搜索</el-button>
          <el-button @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>
      <div class="actions">
        <el-button type="primary" @click="handleAddLibrary">
          <el-icon><Plus /></el-icon>
          新增话题库
        </el-button>
      </div>
    </el-card>

    <!-- 话题库列表 -->
    <el-card class="table-card">
      <el-table :data="libraryList" v-loading="loading" stripe>
        <el-table-column prop="name" label="库名称" min-width="200" />
        <el-table-column label="话题数量" width="120" align="center">
          <template #default="{ row }">{{ row.topicCount || row.total_count || 0 }}</template>
        </el-table-column>
        <el-table-column label="已使用" width="100" align="center">
          <template #default="{ row }">{{ row.usedCount || row.used_count || 0 }}</template>
        </el-table-column>
        <el-table-column label="创建时间" width="180">
          <template #default="{ row }">{{ row.createdAt || row.create_time || '-' }}</template>
        </el-table-column>
        <el-table-column label="操作" width="220" fixed="right">
          <template #default="{ row }">
            <el-button size="small" type="primary" @click="handleDetail(row)">查看详情</el-button>
            <el-button size="small" @click="handleRename(row)">修改名称</el-button>
            <el-button size="small" type="danger" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        v-model:current-page="pagination.page"
        v-model:page-size="pagination.limit"
        :total="pagination.total"
        :page-sizes="[10, 20, 50, 100]"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="loadList"
        @current-change="loadList"
      />
    </el-card>

    <!-- 新增话题库弹窗 -->
    <el-dialog v-model="addDialogVisible" title="新增话题库" width="400px" @close="addFormRef?.resetFields()">
      <el-form :model="addForm" :rules="addFormRules" ref="addFormRef" label-width="80px">
        <el-form-item label="库名称" prop="name">
          <el-input v-model="addForm.name" placeholder="请输入话题库名称" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="addDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submitAddLibrary" :loading="addSaving">确定</el-button>
      </template>
    </el-dialog>

    <!-- 修改名称弹窗 -->
    <el-dialog v-model="renameDialogVisible" title="修改话题库名称" width="400px">
      <el-form label-width="80px">
        <el-form-item label="新名称">
          <el-input v-model="renameForm.name" placeholder="请输入新名称" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="renameDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submitRename" :loading="renameSaving">确定</el-button>
      </template>
    </el-dialog>

    <!-- 库详情弹窗 -->
    <el-dialog v-model="detailVisible" :title="`${currentLib.name} - 话题详情`" width="700px" top="5vh">
      <div class="topic-add-bar">
        <el-input
          v-model="newTopic"
          placeholder="输入新话题，按回车添加"
          @keyup.enter="handleAddTopic"
          style="flex: 1"
        >
          <template #append>
            <el-button @click="handleAddTopic" :loading="topicAdding">添加</el-button>
          </template>
        </el-input>
      </div>

      <div class="topic-list" v-loading="detailLoading">
        <div v-for="(topic, index) in topicList" :key="topic.id" class="topic-item">
          <span class="topic-index">{{ index + 1 }}.</span>
          <span class="topic-text">#{{ topic.content }}#</span>
          <el-button size="small" type="danger" text @click="handleDeleteTopic(topic)">删除</el-button>
        </div>
      </div>

      <EmptyPanel v-if="topicList.length === 0 && !detailLoading" message="暂无话题，请添加" />
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { PageHeader, EmptyPanel } from '@/components/xmt'
import {
  getTopicLibraryList, createTopicLibrary, getTopicLibraryDetail,
  addTopic, renameTopicLibrary, deleteTopicLibrary, deleteLibraryItem
} from '@/api/index'
import { normalizePagination, normalizeListPayload } from '@/utils/responseHelper'

const loading = ref(false)
const libraryList = ref([])
const searchForm = reactive({ name: '' })
const pagination = reactive({ page: 1, limit: 10, total: 0 })

const addDialogVisible = ref(false)
const addFormRef = ref(null)
const addSaving = ref(false)
const addForm = reactive({ name: '' })
const addFormRules = {
  name: [{ required: true, message: '请输入话题库名称', trigger: 'blur' }]
}

const renameDialogVisible = ref(false)
const renameSaving = ref(false)
const renameForm = reactive({ id: null, name: '' })

const detailVisible = ref(false)
const detailLoading = ref(false)
const currentLib = reactive({ id: null, name: '' })
const topicList = ref([])
const newTopic = ref('')
const topicAdding = ref(false)


const loadList = async () => {
  loading.value = true
  try {
    const params = { page: pagination.page, limit: pagination.limit }
    if (searchForm.name) params.name = searchForm.name
    const res = await getTopicLibraryList(params)
    const { list, total } = normalizePagination(res)
    libraryList.value = list
    pagination.total = total
  } catch (err) {
    console.error('获取话题库列表失败:', err)
    libraryList.value = []
    pagination.total = 0
    ElMessage.error('获取话题库列表失败，请稍后重试')
  } finally {
    loading.value = false
  }
}

const handleSearch = () => {
  pagination.page = 1
  loadList()
}

const handleReset = () => {
  searchForm.name = ''
  handleSearch()
}

const handleAddLibrary = () => {
  addForm.name = ''
  addDialogVisible.value = true
}

const submitAddLibrary = async () => {
  if (!addFormRef.value) return
  await addFormRef.value.validate()
  addSaving.value = true
  try {
    await createTopicLibrary({ name: addForm.name })
    ElMessage.success('新增成功')
    addDialogVisible.value = false
    loadList()
  } catch (err) {
    ElMessage.error(err.message || '新增失败')
  } finally {
    addSaving.value = false
  }
}

const handleRename = (row) => {
  renameForm.id = row.id
  renameForm.name = row.name
  renameDialogVisible.value = true
}

const submitRename = async () => {
  if (!renameForm.name.trim()) {
    ElMessage.warning('请输入新名称')
    return
  }
  renameSaving.value = true
  try {
    await renameTopicLibrary(renameForm.id, { name: renameForm.name })
    ElMessage.success('修改成功')
    renameDialogVisible.value = false
    loadList()
  } catch (err) {
    ElMessage.error(err.message || '修改失败')
  } finally {
    renameSaving.value = false
  }
}

const handleDelete = async (row) => {
  try {
    await ElMessageBox.confirm(`确定删除 "${row.name}" 吗？`, '提示', { type: 'warning' })
    await deleteTopicLibrary(row.id)
    ElMessage.success('删除成功')
    loadList()
  } catch (err) {
    if (err !== 'cancel') ElMessage.error(err.message || '删除失败')
  }
}

const handleDetail = async (row) => {
  currentLib.id = row.id
  currentLib.name = row.name
  detailVisible.value = true
  detailLoading.value = true
  try {
    const res = await getTopicLibraryDetail(row.id)
    topicList.value = normalizeListPayload(res)
  } catch (err) {
    console.error('获取话题详情失败:', err)
    topicList.value = []
    ElMessage.error('获取话题详情失败，请稍后重试')
  } finally {
    detailLoading.value = false
  }
}

const handleAddTopic = async () => {
  if (!newTopic.value.trim()) {
    ElMessage.warning('请输入话题内容')
    return
  }
  topicAdding.value = true
  try {
    await addTopic(currentLib.id, { content: newTopic.value.trim() })
    ElMessage.success('添加成功')
    newTopic.value = ''
    handleDetail(currentLib)
  } catch (err) {
    ElMessage.error(err.message || '添加失败')
  } finally {
    topicAdding.value = false
  }
}

const handleDeleteTopic = async (topic) => {
  try {
    await ElMessageBox.confirm(`确定删除话题 "#${topic.content}#" 吗？`, '提示', { type: 'warning' })
    await deleteLibraryItem(topic.id)
    ElMessage.success('删除成功')
    handleDetail(currentLib)
  } catch (err) {
    if (err !== 'cancel') ElMessage.error(err.message || '删除失败')
  }
}

onMounted(() => {
  loadList()
})
</script>

<style scoped lang="scss">
.topic-library {
  padding: 20px;
}
.search-card {
  margin-bottom: 20px;
  .actions {
    margin-top: 10px;
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
.topic-add-bar {
  display: flex;
  gap: 12px;
  margin-bottom: 20px;
  padding-bottom: 16px;
  border-bottom: 1px solid #eee;
}
.topic-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-height: 500px;
  overflow-y: auto;
}
.topic-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 12px;
  background: #fafafa;
  border-radius: 6px;
  &:hover { background: #f0f0f0; }
  .topic-index { color: #909399; font-size: 12px; flex-shrink: 0; width: 24px; }
  .topic-text { flex: 1; font-size: 14px; color: #7b50ff; font-weight: 500; }
}
</style>
