<template>
  <div class="video-edit-container">
    <div class="page-header">
      <h2>新建视频剪辑</h2>
    </div>

    <el-card class="steps-card">
      <el-steps :active="currentStep" finish-status="success" align-center>
        <el-step title="选择素材" description="选择门店和素材" />
        <el-step title="选择模板" description="选择模板和发布平台" />
        <el-step title="配置发布" description="设置标题文案和发布时间" />
      </el-steps>
    </el-card>

    <el-card class="content-card">
      <!-- Step 1: 选择门店和素材 -->
      <div v-show="currentStep === 0" class="step-content">
        <el-form :model="form" label-width="100px">
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="选择门店">
              <el-select v-model="form.storeId" placeholder="请选择门店" style="width: 100%" @change="handleStoreChange">
                <el-option
                  v-for="store in storeList"
                  :key="store.id"
                  :label="store.name"
                  :value="store.id"
                />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="素材类型">
              <el-select v-model="form.materialType" placeholder="请选择素材类型" style="width: 100%">
                <el-option label="图片" value="image" />
                <el-option label="视频" value="video" />
                <el-option label="图文组合" value="mixed" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>

        <el-form-item label="选择素材">
          <div class="material-list">
            <el-empty v-if="materialList.length === 0" description="请先选择门店" />
            <div v-else class="material-grid">
              <div
                v-for="item in materialList"
                :key="item.id"
                class="material-item"
                :class="{ active: form.materialIds.includes(item.id) }"
                @click="toggleMaterial(item.id)"
              >
                <el-image :src="item.thumbnail" fit="cover" class="material-thumb" />
                <div class="material-info">
                  <span class="material-name">{{ item.name }}</span>
                  <span class="material-duration" v-if="item.duration">{{ item.duration }}s</span>
                </div>
                <div v-if="form.materialIds.includes(item.id)" class="selected-badge">
                  <el-icon><Check /></el-icon>
                </div>
              </div>
            </div>
          </div>
        </el-form-item>
        </el-form>
      </div>

      <!-- Step 2: 选择模板和平台 -->
      <div v-show="currentStep === 1" class="step-content">
        <el-form :model="form" label-width="100px">
        <el-form-item label="选择模板">
          <div class="template-list">
            <el-empty v-if="templateList.length === 0" description="暂无可用模板" />
            <div v-else class="template-grid">
              <div
                v-for="tpl in templateList"
                :key="tpl.id"
                class="template-item"
                :class="{ active: form.templateId === tpl.id }"
                @click="form.templateId = tpl.id"
              >
                <el-image :src="tpl.thumbnail" fit="cover" class="template-thumb" />
                <div class="template-info">
                  <span class="template-name">{{ tpl.name }}</span>
                  <el-tag size="small" type="info">{{ tpl.category }}</el-tag>
                </div>
                <div v-if="form.templateId === tpl.id" class="selected-badge">
                  <el-icon><Check /></el-icon>
                </div>
              </div>
            </div>
          </div>
        </el-form-item>

        <el-form-item label="选择平台">
          <el-checkbox-group v-model="form.platforms">
            <el-checkbox label="douyin">抖音</el-checkbox>
            <el-checkbox label="kuaishou">快手</el-checkbox>
            <el-checkbox label="xiaohongshu">小红书</el-checkbox>
            <el-checkbox label="weixin">视频号</el-checkbox>
          </el-checkbox-group>
        </el-form-item>
        </el-form>
      </div>

      <!-- Step 3: 配置标题文案和发布时间 -->
      <div v-show="currentStep === 2" class="step-content">
        <el-form :model="form" label-width="100px">
          <el-form-item label="视频标题">
            <el-input v-model="form.title" placeholder="请输入视频标题" maxlength="50" show-word-limit />
          </el-form-item>

          <el-form-item label="视频文案">
            <el-input
              v-model="form.description"
              type="textarea"
              :rows="5"
              placeholder="请输入视频文案描述"
              maxlength="500"
              show-word-limit
            />
          </el-form-item>

          <el-form-item label="定时发布">
            <el-switch v-model="form.scheduled" active-text="是" inactive-text="否" />
          </el-form-item>

          <el-form-item v-if="form.scheduled" label="发布时间">
            <el-date-picker
              v-model="form.publishTime"
              type="datetime"
              placeholder="选择发布时间"
              style="width: 100%"
              :disabled-date="disabledDate"
            />
          </el-form-item>

          <el-form-item label="发布设置">
            <el-checkbox v-model="form.allowComment">允许评论</el-checkbox>
            <el-checkbox v-model="form.showLikes">显示点赞数</el-checkbox>
          </el-form-item>
        </el-form>
      </div>

      <!-- 按钮区域 -->
      <div class="button-area">
        <el-button v-if="currentStep > 0" @click="prevStep">上一步</el-button>
        <el-button v-if="currentStep < 2" type="primary" @click="nextStep">下一步</el-button>
        <el-button v-if="currentStep === 2" type="primary" :loading="submitLoading" @click="handleSubmit">
          创建剪辑任务
        </el-button>
      </div>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Check } from '@element-plus/icons-vue'
import { createVideoTask, getStoreList, getMaterialList, getTemplateList } from '@/api/video'

const currentStep = ref(0)
const submitLoading = ref(false)

const form = reactive({
  storeId: '',
  materialType: 'image',
  materialIds: [],
  templateId: '',
  platforms: ['douyin'],
  title: '',
  description: '',
  scheduled: false,
  publishTime: '',
  allowComment: true,
  showLikes: true
})

const storeList = ref([])
const materialList = ref([])
const templateList = ref([])

const disabledDate = (time) => {
  return time.getTime() < Date.now() - 8.64e7
}

const handleStoreChange = async () => {
  form.materialIds = []
  await loadMaterials()
}

const loadStores = async () => {
  try {
    const res = await getStoreList()
    storeList.value = res?.list || (Array.isArray(res) ? res : [])
  } catch (error) {
    console.error('获取门店列表失败', error)
    storeList.value = []
    ElMessage.error('获取门店列表失败，请稍后重试')
    } else {
      storeList.value = []
    }
  }
}

const loadMaterials = async () => {
  if (!form.storeId) {
    materialList.value = []
    return
  }
  try {
    const res = await getMaterialList({ storeId: form.storeId, type: form.materialType })
    materialList.value = res?.list || (Array.isArray(res) ? res : [])
  } catch (error) {
    console.error('获取素材列表失败', error)
    materialList.value = []
    ElMessage.error('获取素材列表失败，请稍后重试')
      ]
    } else {
      materialList.value = []
    }
  }
}

const loadTemplates = async () => {
  try {
    const res = await getTemplateList()
    templateList.value = res?.list || (Array.isArray(res) ? res : [])
  } catch (error) {
    console.error('获取模板列表失败', error)
    templateList.value = []
    ElMessage.error('获取模板列表失败，请稍后重试')
      ]
    } else {
      templateList.value = []
    }
  }
}

const toggleMaterial = (id) => {
  const index = form.materialIds.indexOf(id)
  if (index > -1) {
    form.materialIds.splice(index, 1)
  } else {
    form.materialIds.push(id)
  }
}

const nextStep = () => {
  if (currentStep.value === 0) {
    if (!form.storeId) {
      ElMessage.warning('请选择门店')
      return
    }
    if (form.materialIds.length === 0) {
      ElMessage.warning('请选择至少一个素材')
      return
    }
  }
  if (currentStep.value === 1) {
    if (!form.templateId) {
      ElMessage.warning('请选择模板')
      return
    }
    if (form.platforms.length === 0) {
      ElMessage.warning('请选择至少一个发布平台')
      return
    }
  }
  currentStep.value++
}

const prevStep = () => {
  currentStep.value--
}

const handleSubmit = async () => {
  if (!form.title) {
    ElMessage.warning('请输入视频标题')
    return
  }
  if (form.scheduled && !form.publishTime) {
    ElMessage.warning('请选择发布时间')
    return
  }

  submitLoading.value = true
  try {
    const submitData = {
      storeId: form.storeId,
      materialIds: form.materialIds,
      templateId: form.templateId,
      platforms: form.platforms,
      title: form.title,
      description: form.description,
      scheduled: form.scheduled,
      publishTime: form.publishTime,
      allowComment: form.allowComment,
      showLikes: form.showLikes
    }
    await createVideoTask(submitData)
    ElMessage.success('创建成功')
  } catch (error) {
    ElMessage.error('创建失败')
  } finally {
    submitLoading.value = false
  }
}

onMounted(() => {
  loadStores()
  loadMaterials()
  loadTemplates()
})
</script>

<style scoped lang="scss">
.video-edit-container {
  padding: 20px;

  .page-header {
    margin-bottom: 20px;

    h2 {
      margin: 0;
      font-weight: 500;
    }
  }

  .steps-card {
    margin-bottom: 20px;
  }

  .content-card {
    min-height: 500px;

    .step-content {
      min-height: 400px;
      padding: 20px 0;
    }

    .material-list, .template-list {
      margin-top: 10px;
    }

    .material-grid, .template-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 15px;
    }

    .material-item, .template-item {
      position: relative;
      border: 2px solid #eee;
      border-radius: 8px;
      overflow: hidden;
      cursor: pointer;
      transition: all 0.3s;

      &:hover {
        border-color: #409eff;
      }

      &.active {
        border-color: #409eff;
        background-color: #ecf5ff;
      }

      .material-thumb, .template-thumb {
        width: 100%;
        height: 120px;
        background-color: #f5f5f5;
      }

      .material-info, .template-info {
        padding: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;

        .material-name, .template-name {
          font-size: 13px;
          overflow: hidden;
          text-overflow: ellipsis;
          white-space: nowrap;
        }

        .material-duration {
          font-size: 12px;
          color: #999;
        }
      }

      .selected-badge {
        position: absolute;
        top: 5px;
        right: 5px;
        width: 24px;
        height: 24px;
        background-color: #409eff;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 14px;
      }
    }

    .button-area {
      display: flex;
      justify-content: center;
      gap: 15px;
      padding-top: 20px;
      border-top: 1px solid #eee;
    }
  }
}
</style>