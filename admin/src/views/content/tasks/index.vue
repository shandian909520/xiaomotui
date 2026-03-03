<template>
  <div class="tasks-container">
    <!-- 顶部操作栏 -->
    <div class="operation-bar">
      <el-form :inline="true" :model="queryParams" class="search-form">
        <el-form-item label="任务状态">
          <el-select v-model="queryParams.status" placeholder="全部状态" clearable>
            <el-option label="待发布" value="PENDING" />
            <el-option label="发布中" value="PROCESSING" />
            <el-option label="发布成功" value="SUCCESS" />
            <el-option label="发布失败" value="FAILED" />
          </el-select>
        </el-form-item>
        <el-form-item label="发布平台">
          <el-select v-model="queryParams.platform" placeholder="全部平台" clearable>
            <el-option label="抖音" value="DOUYIN" />
            <el-option label="小红书" value="XIAOHONGSHU" />
            <el-option label="快手" value="KUAISHOU" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">查询</el-button>
          <el-button @click="resetQuery">重置</el-button>
        </el-form-item>
      </el-form>
      
      <div class="right-actions">
        <el-button type="primary" icon="Plus" @click="handleCreate">新建发布</el-button>
      </div>
    </div>

    <!-- 任务列表 -->
    <el-table
      v-loading="loading"
      :data="taskList"
      border
      style="width: 100%"
    >
      <el-table-column prop="id" label="任务ID" width="80" align="center" />
      <el-table-column prop="title" label="任务标题" min-width="150" show-overflow-tooltip />
      <el-table-column prop="platform" label="发布平台" width="120" align="center">
        <template #default="{ row }">
          <el-tag :type="getPlatformType(row.platform)">
            {{ getPlatformName(row.platform) }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="status" label="状态" width="100" align="center">
        <template #default="{ row }">
          <el-tag :type="getStatusType(row.status)">
            {{ getStatusName(row.status) }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="publish_time" label="发布时间" width="180" align="center" />
      <el-table-column prop="create_time" label="创建时间" width="180" align="center" />
      <el-table-column label="操作" width="150" align="center" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" @click="handleDetail(row)">详情</el-button>
          <el-button 
            v-if="row.status === 'PENDING'"
            link 
            type="danger" 
            @click="handleCancel(row)"
          >取消</el-button>
        </template>
      </el-table-column>
    </el-table>

    <!-- 分页 -->
    <div class="pagination-container">
      <el-pagination
        v-model:current-page="queryParams.page"
        v-model:page-size="queryParams.limit"
        :total="total"
        :page-sizes="[10, 20, 50, 100]"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="handleSearch"
        @current-change="handleSearch"
      />
    </div>

    <!-- 新建发布对话框 -->
    <el-dialog
      v-model="dialogVisible"
      title="新建发布任务"
      width="600px"
      :close-on-click-modal="false"
    >
      <el-form
        ref="formRef"
        :model="form"
        :rules="rules"
        label-width="100px"
      >
        <el-form-item label="选择内容" prop="content_id">
          <el-select 
            v-model="form.content_id" 
            placeholder="请选择要发布的内容" 
            style="width: 100%"
            filterable
            remote
            :remote-method="searchContents"
            :loading="contentLoading"
          >
            <el-option
              v-for="item in contentOptions"
              :key="item.id"
              :label="item.title"
              :value="item.id"
            />
          </el-select>
        </el-form-item>

        <el-form-item label="文案模板">
          <el-select 
            v-model="form.template_id" 
            placeholder="选择AI生成的文案模板" 
            style="width: 100%"
            clearable
            @change="handleTemplateChange"
          >
            <el-option
              v-for="item in textTemplates"
              :key="item.id"
              :label="item.name"
              :value="item.id"
            />
          </el-select>
        </el-form-item>

        <el-form-item label="发布文案" prop="description">
          <el-input 
            v-model="form.description" 
            type="textarea" 
            :rows="4" 
            placeholder="请输入或选择发布文案" 
          />
        </el-form-item>

        <el-form-item label="发布平台" prop="platforms">
          <el-checkbox-group v-model="form.platforms">
            <el-checkbox label="DOUYIN">抖音</el-checkbox>
            <el-checkbox label="XIAOHONGSHU">小红书</el-checkbox>
            <el-checkbox label="KUAISHOU">快手</el-checkbox>
          </el-checkbox-group>
        </el-form-item>

        <el-form-item label="发布时间" prop="publish_type">
          <el-radio-group v-model="form.publish_type">
            <el-radio label="now">立即发布</el-radio>
            <el-radio label="schedule">定时发布</el-radio>
          </el-radio-group>
        </el-form-item>

        <el-form-item 
          v-if="form.publish_type === 'schedule'" 
          label="定时时间" 
          prop="scheduled_time"
        >
          <el-date-picker
            v-model="form.scheduled_time"
            type="datetime"
            placeholder="选择发布时间"
            value-format="YYYY-MM-DD HH:mm:ss"
            style="width: 100%"
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <div class="dialog-footer">
          <el-button @click="dialogVisible = false">取消</el-button>
          <el-button type="primary" :loading="submitLoading" @click="handleSubmit">
            确定
          </el-button>
        </div>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getPublishTasks, createPublishTask, cancelPublishTask } from '@/api/publish'
import { getVideoLibraryList } from '@/api/video-library'
import { getTemplateList } from '@/api/content'

// 列表数据
const loading = ref(false)
const taskList = ref([])
const total = ref(0)
const queryParams = reactive({
  page: 1,
  limit: 10,
  status: '',
  platform: ''
})

// 表单数据
const dialogVisible = ref(false)
const submitLoading = ref(false)
const formRef = ref(null)
const form = reactive({
  content_id: '',
  platforms: [],
  publish_type: 'now',
  scheduled_time: '',
  description: '', // 文案/标题
  template_id: ''  // 选中的文案模板ID
})

// 内容搜索
const contentLoading = ref(false)
const contentOptions = ref([])

// 文案模板
const textTemplates = ref([])
const loadTextTemplates = async () => {
  try {
    const res = await getTemplateList({ type: 'TEXT', limit: 50 })
    if (res.code === 200) {
      textTemplates.value = res.data.data || []
    }
  } catch (error) {
    console.error('获取文案模板失败', error)
  }
}

// 选择文案模板
const handleTemplateChange = (val) => {
  const template = textTemplates.value.find(t => t.id === val)
  if (template) {
    form.description = template.content
  }
}

// 表单校验规则
const rules = {
  content_id: [{ required: true, message: '请选择要发布的内容', trigger: 'change' }],
  platforms: [{ type: 'array', required: true, message: '请至少选择一个平台', trigger: 'change' }],
  scheduled_time: [{ required: true, message: '请选择发布时间', trigger: 'change' }]
}

// 获取任务列表
const getList = async () => {
  loading.value = true
  try {
    const response = await getPublishTasks(queryParams)
    if (response.code === 200) {
      taskList.value = response.data.list
      total.value = response.data.total
    }
  } catch (error) {
    console.error('获取任务列表失败:', error)
  } finally {
    loading.value = false
  }
}

// 搜索内容
const searchContents = async (query) => {
  if (query) {
    contentLoading.value = true
    try {
      const response = await getVideoLibraryList({ keyword: query, limit: 20 })
      if (response.code === 200) {
        contentOptions.value = response.data.list
      }
    } catch (error) {
      console.error('搜索内容失败:', error)
    } finally {
      contentLoading.value = false
    }
  }
}

// 处理搜索
const handleSearch = () => {
  queryParams.page = 1
  getList()
}

// 重置查询
const resetQuery = () => {
  queryParams.status = ''
  queryParams.platform = ''
  handleSearch()
}

// 打开新建弹窗
const handleCreate = () => {
  dialogVisible.value = true
  // 重置表单
  Object.assign(form, {
    content_id: '',
    platforms: [],
    publish_type: 'now',
    scheduled_time: ''
  })
  // 预加载一些内容选项
  searchContents('')
}

// 提交发布任务
const handleSubmit = async () => {
  if (!formRef.value) return
  
  await formRef.value.validate(async (valid) => {
    if (valid) {
      submitLoading.value = true
      try {
        const data = {
          content_task_id: form.content_id,
          description: form.description,
          platforms: form.platforms.map(p => ({
            platform: p,
            // 实际项目中这里可能需要选择账号，暂时简化处理
            account_id: 0
          })),
          scheduled_time: form.publish_type === 'schedule' ? form.scheduled_time : null
        }
        
        const response = await createPublishTask(data)
        if (response.code === 200) {
          ElMessage.success('任务创建成功')
          dialogVisible.value = false
          getList()
        }
      } catch (error) {
        ElMessage.error('任务创建失败')
      } finally {
        submitLoading.value = false
      }
    }
  })
}

// 取消任务
const handleCancel = async (row) => {
  try {
    await ElMessageBox.confirm('确定要取消该发布任务吗？', '提示', {
      type: 'warning'
    })
    
    await cancelPublishTask(row.id)
    ElMessage.success('任务已取消')
    getList()
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error('操作失败')
    }
  }
}

// 辅助函数
const getPlatformName = (platform) => {
  const map = {
    'DOUYIN': '抖音',
    'XIAOHONGSHU': '小红书',
    'KUAISHOU': '快手'
  }
  return map[platform] || platform
}

const getPlatformType = (platform) => {
  const map = {
    'DOUYIN': '',
    'XIAOHONGSHU': 'danger',
    'KUAISHOU': 'warning'
  }
  return map[platform] || 'info'
}

const getStatusName = (status) => {
  const map = {
    'PENDING': '待发布',
    'PROCESSING': '发布中',
    'SUCCESS': '发布成功',
    'FAILED': '发布失败',
    'CANCELLED': '已取消'
  }
  return map[status] || status
}

const getStatusType = (status) => {
  const map = {
    'PENDING': 'info',
    'PROCESSING': 'primary',
    'SUCCESS': 'success',
    'FAILED': 'danger',
    'CANCELLED': 'info'
  }
  return map[status] || ''
}

onMounted(() => {
  getList()
})
</script>

<style scoped lang="scss">
.tasks-container {
  padding: 20px;
  background-color: #fff;
  border-radius: 4px;

  .operation-bar {
    display: flex;
    justify-content: space-between;
    margin-bottom: 20px;
    
    .search-form {
      margin-bottom: 0;
    }
  }

  .pagination-container {
    margin-top: 20px;
    display: flex;
    justify-content: flex-end;
  }
}
</style>
