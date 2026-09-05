<template>
  <div class="staff-roles-page">
    <!-- 顶部标题区 -->
    <div class="page-header">
      <div class="header-content">
        <h2>智能员工</h2>
        <p class="subtitle">AI赋能经营，专业员工帮你管好店</p>
      </div>
      <div class="header-stats">
        <span class="stat-item">
          <strong>14</strong> 个专业员工
        </span>
        <span class="stat-divider">·</span>
        <span class="stat-item">
          <strong>4</strong> 个业务组
        </span>
        <span class="stat-divider">·</span>
        <span class="stat-item">随时待命</span>
      </div>
    </div>

    <!-- 分组区域 -->
    <div v-loading="loading" class="groups-container">
      <el-collapse v-model="expandedGroups" class="group-collapse">
        <el-collapse-item
          v-for="group in staffGroups"
          :key="group.id"
          :name="group.id"
        >
          <template #title>
            <div class="group-header">
              <el-icon class="group-icon" :style="{ color: group.color }">
                <component :is="group.icon" />
              </el-icon>
              <div class="group-info">
                <h3>{{ group.name }}</h3>
                <p>{{ group.description }}</p>
              </div>
              <el-tag size="small" type="info" class="group-count">
                {{ group.staff.length }} 人
              </el-tag>
            </div>
          </template>

          <div class="staff-grid">
            <div
              v-for="person in group.staff"
              :key="person.id"
              class="staff-card"
            >
              <el-tag
                v-if="person.is_hot"
                type="danger"
                size="small"
                class="hot-tag"
                effect="dark"
              >
                HOT
              </el-tag>
              <div
                class="staff-avatar"
                :style="{ background: group.avatarBg }"
              >
                {{ person.avatar }}
              </div>
              <strong class="staff-name">{{ person.nickname }}</strong>
              <span class="staff-role">{{ person.role }}</span>
              <p class="staff-desc">{{ person.description }}</p>
              <div class="staff-usage">
                已用 <strong>{{ person.used_count || 0 }}</strong> / 免费 {{ person.free_count || 10 }} 次
              </div>
              <el-button
                type="primary"
                size="small"
                round
                @click="openAssignDialog(person)"
              >
                安排工作
              </el-button>
            </div>
          </div>
        </el-collapse-item>
      </el-collapse>
    </div>

    <!-- 安排工作弹窗 -->
    <el-dialog
      v-model="assignDialogVisible"
      width="640px"
      class="assign-dialog"
      @close="closeAssignDialog"
    >
      <template #header>
        <div class="dialog-header">
          <div
            class="dialog-avatar"
            :style="{ background: currentStaffGroup?.avatarBg }"
          >
            {{ currentStaff?.avatar }}
          </div>
          <div class="dialog-title-info">
            <h3>{{ currentStaff?.nickname }}</h3>
            <span>{{ currentStaff?.role }}</span>
          </div>
        </div>
      </template>

      <el-form
        :model="assignForm"
        :rules="assignRules"
        ref="assignFormRef"
        label-width="90px"
        class="assign-form"
      >
        <el-form-item label="任务类型" prop="taskType">
          <el-select
            v-model="assignForm.taskType"
            placeholder="请选择任务类型"
            style="width: 100%"
          >
            <el-option
              v-for="tt in currentTaskTypes"
              :key="tt.value"
              :label="tt.label"
              :value="tt.value"
            />
          </el-select>
        </el-form-item>

        <el-form-item label="任务描述" prop="description">
          <el-input
            v-model="assignForm.description"
            type="textarea"
            :rows="4"
            :placeholder="taskPlaceholder"
          />
        </el-form-item>

        <el-form-item label="选择门店">
          <el-select
            v-model="assignForm.storeId"
            placeholder="请选择门店"
            style="width: 100%"
          >
            <el-option
              v-for="store in storeList"
              :key="store.id"
              :label="store.name"
              :value="store.id"
            />
          </el-select>
        </el-form-item>

        <el-form-item label="选择平台">
          <el-select
            v-model="assignForm.platform"
            placeholder="请选择平台"
            style="width: 100%"
          >
            <el-option label="抖音" value="douyin" />
            <el-option label="快手" value="kuaishou" />
            <el-option label="小红书" value="xiaohongshu" />
            <el-option label="视频号" value="shipinhao" />
            <el-option label="朋友圈" value="pengyouquan" />
          </el-select>
        </el-form-item>
      </el-form>

      <!-- 生成结果 -->
      <div v-if="generationResult" class="generation-result">
        <div class="result-label">生成结果</div>
        <div class="result-content">
          {{ generationResult }}
        </div>
        <div class="result-actions">
          <el-button size="small" @click="copyResult">
            复制内容
          </el-button>
          <el-button size="small" @click="handleGenerate">
            重新生成
          </el-button>
          <el-button size="small" type="primary" @click="handleConfirm">
            确认使用
          </el-button>
        </div>
      </div>

      <template #footer>
        <el-button @click="closeAssignDialog">取消</el-button>
        <el-button
          type="primary"
          :loading="generating"
          @click="handleGenerate"
        >
          {{ generationResult ? '重新生成' : '生成' }}
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { EditPen, Brush, Shop, Star } from '@element-plus/icons-vue'
import { getStaffList, generateContent, assignTask } from '@/api/ai'
import { getStoreList } from '@/api/video'
import { getStaffGroups } from '@/api/index'
import { normalizeListPayload } from '@/utils/responseHelper'


// 状态
const loading = ref(false)
const staffGroups = ref([])
const expandedGroups = ref([])
const storeList = ref([])
const assignDialogVisible = ref(false)
const generating = ref(false)
const currentStaff = ref(null)
const generationResult = ref('')
const assignFormRef = ref(null)

const assignForm = reactive({
  taskType: '',
  description: '',
  storeId: '',
  platform: ''
})

const assignRules = {
  taskType: [{ required: true, message: '请选择任务类型', trigger: 'change' }],
  description: [{ required: true, message: '请输入任务描述', trigger: 'blur' }]
}

// 当前员工所属分组
const currentStaffGroup = computed(() => {
  if (!currentStaff.value) return null
  return staffGroups.value.find(g =>
    g.staff.some(s => s.id === currentStaff.value.id)
  )
})

// 当前员工的任务类型列表
const currentTaskTypes = computed(() => {
  return currentStaff.value?.task_types || []
})

// 任务描述 placeholder
const taskPlaceholder = computed(() => {
  const map = {
    video_script: '请描述你需要的视频口播文案风格...',
    video_outline: '请描述视频大纲需求...',
    live_script: '请描述直播脚本需求...',
    redbook_note: '请描述小红书笔记需求...',
    friends_circle: '请描述朋友圈文案需求...',
    product_review: '请描述产品评价文案需求...',
    rewrite: '请粘贴需要改写的文案...',
    polish: '请粘贴需要润色的文案...',
    headline: '请描述产品或活动，生成爆款标题...',
    short_drama: '请描述短剧需求...',
    scenario: '请描述情景剧需求...',
    storyboard: '请描述分镜需求...',
    poster: '请描述海报设计需求...',
    cover: '请描述封面设计需求...',
    graphic_layout: '请描述图文排版需求...',
    video_edit: '请描述视频剪辑需求...',
    subtitle: '请描述字幕添加需求...',
    special_effect: '请描述特效处理需求...',
    photo_retouch: '请描述图片精修需求...',
    color_adjust: '请描述调色需求...',
    background_remove: '请上传需要抠图的图片...',
    online_activity: '请描述线上活动需求...',
    offline_event: '请描述线下活动需求...',
    holiday_plan: '请描述节日营销需求...',
    community_manage: '请描述社群管理需求...',
    user_operate: '请描述用户运营需求...',
    fan_interact: '请描述粉丝互动需求...',
    store_promo: '请描述门店引流需求...',
    local_marketing: '请描述本地推广需求...',
    customer_expand: '请描述商圈拓客需求...',
    data_analysis: '请描述数据分析需求...',
    performance_diag: '请描述业绩诊断需求...',
    competitor_analysis: '请描述竞品分析需求...',
    training_plan: '请描述培训需求...',
    service_script: '请描述服务话术需求...',
    sales_skill: '请描述销售技巧需求...',
    review_reply: '请粘贴需要回复的评价...',
    reputation_manage: '请描述口碑管理需求...',
    crisis_handle: '请描述舆情处理需求...',
    visit_script: '请描述回访话术需求...',
    satisfaction: '请描述满意度提升需求...',
    vip_service: '请描述VIP服务需求...'
  }
  return map[assignForm.taskType] || '请输入任务描述...'
})

// 打开安排工作弹窗
const openAssignDialog = (person) => {
  currentStaff.value = person
  assignDialogVisible.value = true
}

// 关闭弹窗
const closeAssignDialog = () => {
  assignDialogVisible.value = false
  currentStaff.value = null
  generationResult.value = ''
  assignForm.taskType = ''
  assignForm.description = ''
  assignForm.storeId = ''
  assignForm.platform = ''
}

// 生成内容
const handleGenerate = async () => {
  if (!assignFormRef.value) return
  try {
    await assignFormRef.value.validate()
  } catch {
    return
  }

  generating.value = true
  try {
    const res = await generateContent({
      taskType: assignForm.taskType,
      description: assignForm.description,
      storeId: assignForm.storeId,
      platform: assignForm.platform,
      staffId: currentStaff.value?.id
    })
    generationResult.value = res?.content || res?.data?.content || res || '生成内容为空'
    ElMessage.success('生成成功')
  } catch (err) {
    console.error('生成失败:', err)
    ElMessage.error('生成失败，请稍后重试')
  } finally {
    generating.value = false
  }
}

// 复制结果
const copyResult = () => {
  navigator.clipboard.writeText(generationResult.value).then(() => {
    ElMessage.success('已复制到剪贴板')
  }).catch(() => {
    const textarea = document.createElement('textarea')
    textarea.value = generationResult.value
    document.body.appendChild(textarea)
    textarea.select()
    document.execCommand('copy')
    document.body.removeChild(textarea)
    ElMessage.success('已复制到剪贴板')
  })
}

// 确认使用
const handleConfirm = async () => {
  try {
    await ElMessageBox.confirm('确认使用当前生成的内容？', '确认', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'success'
    })
    try {
      await assignTask(currentStaff.value.id, {
        ...assignForm,
        result: generationResult.value
      })
      ElMessage.success('已确认使用')
      closeAssignDialog()
    } catch (err) {
      console.error('确认使用失败:', err)
      ElMessage.error('操作失败，请稍后重试')
    }
  } catch {
    // 用户取消
  }
}

// 分组颜色和图标配置
const groupConfig = {
  '内容文案组': { color: '#409eff', avatarBg: '#7b50ff', icon: 'EditPen' },
  '视觉设计组': { color: '#67c23a', avatarBg: '#e6a23c', icon: 'Brush' },
  '门店运营组': { color: '#e6a23c', avatarBg: '#409eff', icon: 'Shop' },
  '口碑管理组': { color: '#f56c6c', avatarBg: '#67c23a', icon: 'Star' }
}
const defaultGroupConfig = { color: '#909399', avatarBg: '#7b50ff', icon: 'Star' }

// 加载员工列表 - 从扁平列表构建分组结构
const loadStaffList = async () => {
  loading.value = true
  try {
    const res = await getStaffList({ limit: 100 })
    const rawList = normalizeListPayload(res)

    // 按 group_name 分组
    const groupMap = {}
    rawList.forEach(item => {
      const groupName = item.groupName || '其他'
      if (!groupMap[groupName]) {
        groupMap[groupName] = []
      }
      const cfg = groupConfig[groupName] || defaultGroupConfig
      groupMap[groupName].push({
        id: item.id,
        nickname: item.nickname || '?',
        role: item.roleName || '',
        description: item.description || '',
        avatar: item.nickname ? item.nickname.charAt(0) : '?',
        avatarBg: cfg.avatarBg,
        is_hot: item.isHot || false,
        used_count: item.usedCount || 0,
        free_count: item.freeCount || 10,
        task_types: item.taskTypes || []
      })
    })

    // 构建分组数组
    staffGroups.value = Object.entries(groupMap).map(([name, staff], idx) => {
      const cfg = groupConfig[name] || defaultGroupConfig
      return {
        id: idx + 1,
        name,
        description: `${name}，共${staff.length}人`,
        color: cfg.color,
        avatarBg: cfg.avatarBg,
        icon: cfg.icon,
        staff
      }
    })
  } catch (err) {
    console.error('获取员工列表失败:', err)
    staffGroups.value = []
    ElMessage.error('获取员工列表失败，请稍后重试')
  } finally {
    loading.value = false
  }
  // 默认展开所有分组
  expandedGroups.value = staffGroups.value.map(g => g.id)
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
.staff-roles-page {
  padding: 20px;

  .page-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    padding: 28px 32px;
    margin-bottom: 24px;
    color: #fff;

    .header-content {
      h2 {
        font-size: 28px;
        font-weight: 700;
        margin: 0 0 6px 0;
      }

      .subtitle {
        font-size: 14px;
        opacity: 0.85;
        margin: 0;
      }
    }

    .header-stats {
      margin-top: 16px;
      font-size: 13px;
      opacity: 0.9;

      strong {
        font-size: 16px;
      }

      .stat-divider {
        margin: 0 8px;
        opacity: 0.5;
      }
    }
  }

  .groups-container {
    min-height: 200px;
  }

  .group-collapse {
    border: none;

    :deep(.el-collapse-item__header) {
      border-bottom: 1px solid #ebeef5;
      height: auto;
      padding: 8px 0;
    }

    :deep(.el-collapse-item__wrap) {
      border-bottom: none;
    }

    :deep(.el-collapse-item__content) {
      padding: 16px 0 8px;
    }
  }

  .group-header {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;

    .group-icon {
      font-size: 24px;
    }

    .group-info {
      flex: 1;

      h3 {
        font-size: 16px;
        font-weight: 600;
        margin: 0;
        color: #303133;
      }

      p {
        font-size: 12px;
        color: #909399;
        margin: 2px 0 0;
      }
    }

    .group-count {
      margin-left: auto;
    }
  }

  .staff-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 16px;
  }

  .staff-card {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 20px 14px 16px;
    background: #fff;
    border: 1px solid #ebeef5;
    border-radius: 10px;
    transition: all 0.25s ease;

    &:hover {
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
      transform: translateY(-2px);
    }

    .hot-tag {
      position: absolute;
      top: 8px;
      right: 8px;
    }

    .staff-avatar {
      width: 56px;
      height: 56px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 50%;
      color: #fff;
      font-size: 22px;
      font-weight: 800;
      margin-bottom: 10px;
    }

    .staff-name {
      font-size: 15px;
      font-weight: 700;
      color: #303133;
      margin-bottom: 2px;
    }

    .staff-role {
      font-size: 12px;
      color: #909399;
      margin-bottom: 8px;
    }

    .staff-desc {
      font-size: 12px;
      color: #606266;
      text-align: center;
      line-height: 1.5;
      margin: 0 0 8px;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    .staff-usage {
      font-size: 11px;
      color: #909399;
      margin-bottom: 10px;

      strong {
        color: #409eff;
      }
    }

    .el-button {
      width: 100%;
    }
  }

  // 弹窗样式
  .dialog-header {
    display: flex;
    align-items: center;
    gap: 12px;

    .dialog-avatar {
      width: 44px;
      height: 44px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 50%;
      color: #fff;
      font-size: 18px;
      font-weight: 800;
      flex-shrink: 0;
    }

    .dialog-title-info {
      h3 {
        font-size: 16px;
        font-weight: 600;
        margin: 0;
      }

      span {
        font-size: 12px;
        color: #909399;
      }
    }
  }

  .assign-form {
    margin-top: 8px;
  }

  .generation-result {
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid #ebeef5;

    .result-label {
      font-size: 13px;
      font-weight: 600;
      color: #303133;
      margin-bottom: 8px;
    }

    .result-content {
      background: #fafafa;
      border: 1px solid #ebeef5;
      border-radius: 6px;
      padding: 12px;
      font-size: 13px;
      line-height: 1.7;
      color: #303133;
      white-space: pre-wrap;
      max-height: 240px;
      overflow-y: auto;
    }

    .result-actions {
      display: flex;
      gap: 8px;
      margin-top: 12px;
    }
  }
}

// 响应式
@media (max-width: 768px) {
  .staff-roles-page {
    padding: 12px;

    .page-header {
      padding: 20px;

      .header-content h2 {
        font-size: 22px;
      }
    }

    .staff-grid {
      grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
      gap: 10px;
    }

    .staff-card {
      padding: 14px 10px 12px;

      .staff-avatar {
        width: 44px;
        height: 44px;
        font-size: 18px;
      }

      .staff-name {
        font-size: 13px;
      }
    }
  }
}
</style>
