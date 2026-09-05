<template>
  <div class="ai-staff-page">
    <div class="page-header">
      <h2>智能员工</h2>
      <p class="subtitle">AI智能助手，为您提供专业的创作服务</p>
    </div>

    <!-- 智能员工卡片列表 -->
    <section class="staff-section">
      <div class="section-head">
        <h3>员工列表</h3>
      </div>
      <div v-loading="loading" class="staff-grid">
        <div
          v-for="person in staffList"
          :key="person.id"
          class="staff-card"
          :class="{ 'is-hot': person.hot }"
        >
          <span v-if="person.hot" class="hot-tag">HOT</span>
          <div class="portrait" :style="{ background: person.avatarBg }">
            {{ person.avatar }}
          </div>
          <strong class="staff-name">{{ person.name }}</strong>
          <span class="staff-role">{{ person.role }}</span>
          <p class="staff-desc">{{ person.description }}</p>
          <el-button size="small" plain round @click="openAssignDialog(person)">
            安排工作
          </el-button>
        </div>
      </div>
    </section>

    <!-- 员工能力说明 -->
    <section class="abilities-section">
      <div class="section-head">
        <h3>员工能力说明</h3>
      </div>
      <div class="abilities-grid">
        <div
          v-for="ability in abilities"
          :key="ability.type"
          class="ability-card"
        >
          <div class="ability-icon">{{ ability.icon }}</div>
          <div class="ability-info">
            <h4>{{ ability.name }}</h4>
            <p>{{ ability.desc }}</p>
            <div class="ability-tags">
              <el-tag
                v-for="tag in ability.tags"
                :key="tag"
                size="small"
                type="info"
              >
                {{ tag }}
              </el-tag>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- 安排工作弹窗 -->
    <el-dialog
      v-model="assignDialogVisible"
      :title="`安排工作 - ${currentStaff?.name}`"
      width="600px"
      @close="closeAssignDialog"
    >
      <div v-if="currentStaff" class="assign-form">
        <el-form :model="assignForm" label-width="100px">
          <el-form-item label="选择员工">
            <el-input :value="currentStaff.name" disabled />
          </el-form-item>

          <el-form-item label="任务类型">
            <el-select v-model="assignForm.taskType" placeholder="请选择任务类型" @change="handleTaskTypeChange">
              <el-option
                v-for="task in taskTypes"
                :key="task.value"
                :label="task.label"
                :value="task.value"
              />
            </el-select>
          </el-form-item>

          <el-form-item v-if="assignForm.taskType" label="任务描述">
            <el-input
              v-model="assignForm.description"
              type="textarea"
              :rows="4"
              :placeholder="getTaskPlaceholder(assignForm.taskType)"
            />
          </el-form-item>

          <el-form-item v-if="showStoreSelect" label="选择门店">
            <el-select v-model="assignForm.storeId" placeholder="请选择门店">
              <el-option
                v-for="store in storeList"
                :key="store.id"
                :label="store.name"
                :value="store.id"
              />
            </el-select>
          </el-form-item>

          <el-form-item v-if="showPlatformSelect" label="选择平台">
            <el-checkbox-group v-model="assignForm.platforms">
              <el-checkbox label="douyin">抖音</el-checkbox>
              <el-checkbox label="kuaishou">快手</el-checkbox>
              <el-checkbox label="xiaohongshu">小红书</el-checkbox>
              <el-checkbox label="wechat">微信</el-checkbox>
            </el-checkbox-group>
          </el-form-item>
        </el-form>

        <!-- 生成结果展示 -->
        <div v-if="generationResult" class="generation-result">
          <h4>生成结果</h4>
          <div class="result-content">
            <el-input
              v-model="generationResult"
              type="textarea"
              :rows="6"
              readonly
            />
            <div class="result-actions">
              <el-button type="primary" @click="copyResult">复制文案</el-button>
              <el-button @click="downloadResult">下载</el-button>
            </div>
          </div>
        </div>
      </div>

      <template #footer>
        <el-button @click="closeAssignDialog">取消</el-button>
        <el-button type="primary" :loading="generating" @click="handleGenerate">
          {{ generationResult ? '重新生成' : '生成' }}
        </el-button>
        <el-button v-if="generationResult" type="success" @click="handleConfirm">
          确认使用
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getStaffList, generateContent, assignTask } from '@/api/ai'
import { getStoreList } from '@/api/video'
import { normalizeListPayload } from '@/utils/responseHelper'


const staffList = ref([])

// 员工能力说明
const abilities = ref([
  {
    type: 'video_script',
    icon: '🎬',
    name: '视频口播文案',
    desc: '专业创作各类视频口播脚本，包括产品介绍、品牌宣传、活动推广等',
    tags: ['口播脚本', '产品介绍', '宣传文案', '品牌故事']
  },
  {
    type: 'notes',
    icon: '📝',
    name: '种草笔记',
    desc: '创作吸引人的种草文案，适用于小红书、朋友圈等社交平台',
    tags: ['小红书笔记', '朋友圈文案', '好物推荐', '攻略分享']
  },
  {
    type: 'rewrite',
    icon: '✏️',
    name: '文案改写',
    desc: '对现有文案进行优化改写，提升文案吸引力和转化率',
    tags: ['文案改写', '润色优化', '风格转换', '爆款标题']
  },
  {
    type: 'video_edit',
    icon: '🎞️',
    name: '视频剪辑',
    desc: '提供专业视频剪辑服务，包括拼接、字幕、特效、配乐等',
    tags: ['剪辑拼接', '字幕添加', '特效处理', '背景音乐']
  }
])

// 任务类型
const taskTypes = [
  { value: 'video_script', label: '视频口播文案' },
  { value: 'notes', label: '种草笔记' },
  { value: 'rewrite', label: '文案改写' },
  { value: 'video_edit', label: '视频剪辑' },
  { value: 'image_design', label: '图文设计' },
  { value: 'topic_plan', label: '话题策划' }
]


const storeList = ref([])

// 状态
const loading = ref(false)
const assignDialogVisible = ref(false)
const generating = ref(false)
const currentStaff = ref(null)
// 文案对比：5 套候选 + 当前选中索引
const candidates = ref([])
const selectedIdx = ref(-1)

// 表单
const assignForm = reactive({
  taskType: '',
  description: '',
  storeId: '',
  platforms: []
})

// 显示状态
const showStoreSelect = computed(() => {
  return ['video_script', 'notes', 'rewrite'].includes(assignForm.taskType)
})

const showPlatformSelect = computed(() => {
  return assignForm.taskType === 'topic_plan'
})

// 获取任务描述placeholder
const getTaskPlaceholder = (taskType) => {
  const placeholders = {
    video_script: '请输入产品名称、特点、宣传重点等信息...',
    notes: '请输入产品名称、核心卖点、使用场景等...',
    rewrite: '请粘贴需要改写的文案...',
    video_edit: '请描述视频需求，包括时长、风格、内容等...',
    image_design: '请描述设计需求，包括尺寸、风格、主题等...',
    topic_plan: '请输入活动主题、目标、参与方式等...'
  }
  return placeholders[taskType] || '请输入任务描述...'
}

// 打开安排工作弹窗
const openAssignDialog = (staff) => {
  currentStaff.value = staff
  assignDialogVisible.value = true
}

// 关闭安排工作弹窗
const closeAssignDialog = () => {
  assignDialogVisible.value = false
  currentStaff.value = null
  assignForm.taskType = ''
  assignForm.description = ''
  assignForm.storeId = ''
  assignForm.platforms = []
  generationResult.value = ''
}

// 任务类型变化
const handleTaskTypeChange = () => {
  generationResult.value = ''
}

// 生成
const handleGenerate = async () => {
  if (!assignForm.taskType) {
    ElMessage.warning('请选择任务类型')
    return
  }

  generating.value = true

  try {
    const res = await generateContent({
      taskType: assignForm.taskType,
      description: assignForm.description,
      storeId: assignForm.storeId,
      platforms: assignForm.platforms,
      staffId: currentStaff.value?.id
    })
    // bug B2 兜底: 后端可能返回 {code,data:{text}} / {code,data:{content}} / {code,text} / {code,content} 任一种
    const candidate = res?.data?.text ?? res?.text ?? res?.data?.content ?? res?.content ?? res?.data?.data ?? ''
    generationResult.value = candidate || '生成内容为空'
    ElMessage.success('生成成功！')
  } catch (err) {
    console.error('生成文案失败:', err)
    ElMessage.error('生成失败，请稍后重试')
  } finally {
    generating.value = false
  }
}

// 复制结果
const copyResult = () => {
  navigator.clipboard.writeText(generationResult.value).then(() => {
    ElMessage.success('已复制到剪贴板')
  })
}

// 下载结果
const downloadResult = () => {
  const blob = new Blob([generationResult.value], { type: 'text/plain' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `生成文案_${Date.now()}.txt`
  a.click()
  URL.revokeObjectURL(url)
  ElMessage.success('下载成功')
}

// 确认使用
const handleConfirm = async () => {
  try {
    await ElMessageBox.confirm('确认使用当前生成的文案？', '确认', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'success'
    })

    try {
      await assignTask(currentStaff.value.id, {
        ...assignForm,
        result: generationResult.value
      })
      ElMessage.success('已确认使用！')
    } catch (err) {
      console.error('确认使用失败:', err)
      ElMessage.error('操作失败，请稍后重试')
    }

    closeAssignDialog()
  } catch {
    // 取消
  }
}

// 加载员工列表
const loadStaffList = async () => {
  loading.value = true
  try {
    const res = await getStaffList()
    const rawList = normalizeListPayload(res)
    // 映射后端 AiStaffRole 字段到前端期望的字段
    staffList.value = rawList.map(item => ({
      id: item.id,
      name: item.name || item.nickname || '',
      role: item.role || item.roleName || '',
      description: item.description || '',
      avatarBg: item.avatarBg || '#7b50ff',
      avatar: item.avatar || (item.nickname ? item.nickname.charAt(0) : '?'),
      hot: item.hot || item.isHot || false,
      usedCount: item.usedCount || item.usedCount || 0,
      freeCount: item.freeCount || item.freeCount || 10,
      taskTypes: item.taskTypes || []
    }))
  } catch (err) {
    console.error('获取员工列表失败:', err)
    staffList.value = []
    ElMessage.error('获取员工列表失败，请稍后重试')
  } finally {
    loading.value = false
  }
}

// 加载门店列表
const loadStoreList = async () => {
  try {
    const res = await getStoreList()
    storeList.value = res?.list || (Array.isArray(res) ? res : [])
  } catch (err) {
    console.error('获取门店列表失败', err)
    storeList.value = []
    ElMessage.error('获取门店列表失败，请稍后重试')
  }
}

// 初始化
onMounted(() => {
  loadStaffList()
  loadStoreList()
})
</script>

<style scoped lang="scss">
.ai-staff-page {
  padding: 20px;

  .page-header {
    margin-bottom: 30px;

    h2 {
      font-size: 28px;
      font-weight: 600;
      margin: 0 0 8px 0;
      color: #303133;
    }

    .subtitle {
      font-size: 14px;
      color: #909399;
      margin: 0;
    }
  }

  .staff-section,
  .abilities-section {
    background: #fff;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 20px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
  }

  .section-head {
    margin-bottom: 20px;

    h3 {
      font-size: 18px;
      font-weight: 600;
      margin: 0;
      color: #303133;
    }
  }

  .staff-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
  }

  .staff-card {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 24px 16px;
    border: 1px solid #ebeef5;
    border-radius: 12px;
    transition: all 0.3s;

    &:hover {
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
      transform: translateY(-4px);
    }

    &.is-hot {
      border-color: #ff4d4f;
    }

    .hot-tag {
      position: absolute;
      top: 12px;
      right: 12px;
      padding: 2px 8px;
      background: #ff4d4f;
      color: #fff;
      font-size: 12px;
      font-weight: 800;
      border-radius: 4px;
    }

    .portrait {
      width: 72px;
      height: 72px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 50%;
      color: #fff;
      font-size: 28px;
      font-weight: 800;
      margin-bottom: 16px;
    }

    .staff-name {
      font-size: 16px;
      margin-bottom: 4px;
    }

    .staff-role {
      color: #909399;
      font-size: 13px;
      margin-bottom: 12px;
    }

    .staff-desc {
      color: #606266;
      font-size: 13px;
      text-align: center;
      margin: 0 0 16px 0;
      line-height: 1.5;
    }
  }

  .abilities-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
  }

  .ability-card {
    display: flex;
    gap: 16px;
    padding: 20px;
    background: linear-gradient(135deg, #FFF5F0 0%, #FFFFFF 100%);
    border-radius: 12px;
    border: 1px solid rgba(255, 107, 53, 0.12);
    transition: all 0.2s;

    &:hover {
      box-shadow: 0 4px 16px rgba(255, 107, 53, 0.12);
      transform: translateY(-2px);
    }

    .ability-icon {
      width: 48px;
      height: 48px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #fff;
      border-radius: 8px;
      font-size: 24px;
      flex-shrink: 0;
    }

    .ability-info {
      flex: 1;

      h4 {
        margin: 0 0 8px 0;
        font-size: 15px;
        font-weight: 600;
      }

      p {
        margin: 0 0 12px 0;
        font-size: 13px;
        color: #606266;
        line-height: 1.5;
      }

      .ability-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
      }
    }
  }

  .assign-form {
    .el-select {
      width: 100%;
    }

    .el-textarea {
      width: 100%;
    }
  }

  .generation-result {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #ebeef5;

    h4 {
      margin: 0 0 12px 0;
      font-size: 14px;
      font-weight: 600;
    }

    .result-content {
      .el-textarea {
        margin-bottom: 12px;
      }

      .result-actions {
        display: flex;
        gap: 12px;
      }
    }
  }
}
</style>