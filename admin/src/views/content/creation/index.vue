<template>
  <div class="creation-container">
    <div class="creation-header">
      <h2>AI 智能创作</h2>
      <p>输入关键词和要求，AI 帮你快速生成高质量营销文案</p>
    </div>

    <div class="creation-content">
      <el-row :gutter="20">
        <!-- 左侧输入区 -->
        <el-col :span="10">
          <el-card class="input-card">
            <template #header>
              <div class="card-header">
                <span>创作设置</span>
              </div>
            </template>

            <el-form :model="form" label-width="80px">
              <el-form-item label="营销场景">
                <el-select v-model="form.scene" placeholder="请选择营销场景" style="width: 100%">
                  <el-option label="探店推广" value="探店推广" />
                  <el-option label="新品上市" value="新品上市" />
                  <el-option label="节日促销" value="节日促销" />
                  <el-option label="品牌宣传" value="品牌宣传" />
                  <el-option label="活动预热" value="活动预热" />
                </el-select>
              </el-form-item>

              <el-form-item label="行业分类">
                <el-select v-model="form.category" placeholder="请选择行业" style="width: 100%">
                  <el-option label="餐饮美食" value="餐饮美食" />
                  <el-option label="休闲娱乐" value="休闲娱乐" />
                  <el-option label="美容美发" value="美容美发" />
                  <el-option label="生活服务" value="生活服务" />
                  <el-option label="教育培训" value="教育培训" />
                </el-select>
              </el-form-item>

              <el-form-item label="投放平台">
                <el-radio-group v-model="form.platform">
                  <el-radio label="douyin">抖音</el-radio>
                  <el-radio label="red">小红书</el-radio>
                  <el-radio label="kuaishou">快手</el-radio>
                  <el-radio label="video">视频号</el-radio>
                </el-radio-group>
              </el-form-item>

              <el-form-item label="文案风格">
                <el-radio-group v-model="form.style">
                  <el-radio label="专业">专业权威</el-radio>
                  <el-radio label="幽默">幽默风趣</el-radio>
                  <el-radio label="亲切">亲切自然</el-radio>
                  <el-radio label="激情">激情促销</el-radio>
                </el-radio-group>
              </el-form-item>

              <el-form-item label="核心卖点">
                <el-input
                  v-model="form.requirements"
                  type="textarea"
                  :rows="6"
                  placeholder="请输入核心卖点、优惠信息或具体要求，例如：
1. 全场8折优惠
2. 赠送精美小吃
3. 环境优雅适合拍照"
                />
              </el-form-item>

              <el-form-item>
                <el-button type="primary" :loading="loading" @click="handleGenerate" style="width: 100%">
                  {{ loading ? 'AI 正在创作中...' : '开始生成' }}
                </el-button>
              </el-form-item>
            </el-form>
          </el-card>

          <!-- 历史记录 -->
          <el-card class="history-card" style="margin-top: 20px;">
            <template #header>
              <div class="card-header">
                <span>创作历史</span>
                <el-button link type="primary" @click="loadHistory">刷新</el-button>
              </div>
            </template>
            <div v-loading="historyLoading" class="history-list">
              <div v-if="historyList.length === 0" class="empty-history">暂无历史记录</div>
              <div 
                v-for="item in historyList" 
                :key="item.id" 
                class="history-item"
                @click="useHistory(item)"
              >
                <div class="history-content">{{ item.content }}</div>
                <div class="history-time">{{ item.create_time }}</div>
              </div>
            </div>
          </el-card>
        </el-col>

        <!-- 右侧结果区 -->
        <el-col :span="14">
          <el-card class="result-card">
            <template #header>
              <div class="card-header">
                <span>生成结果</span>
                <div v-if="result" class="actions">
                  <el-button link type="primary" @click="copyResult">复制</el-button>
                  <el-button link type="primary" @click="saveToTemplate">存为模板</el-button>
                </div>
              </div>
            </template>

            <div v-loading="loading" class="result-content">
              <div v-if="result" class="generated-text">
                <el-input
                  v-model="result"
                  type="textarea"
                  :rows="15"
                  resize="none"
                />
              </div>
              <div v-else class="empty-state">
                <el-empty description="在左侧输入要求，点击生成即可获取文案" />
              </div>
            </div>
          </el-card>
        </el-col>
      </el-row>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { generateText, getCreationHistory } from '@/api/ai-content'
import { createTemplate } from '@/api/content'

const loading = ref(false)
const result = ref('')

const form = reactive({
  scene: '',
  category: '',
  platform: 'douyin',
  style: '亲切',
  requirements: ''
})

// 历史记录
const historyList = ref([])
const historyLoading = ref(false)

const loadHistory = async () => {
  historyLoading.value = true
  try {
    const res = await getCreationHistory({ limit: 5 })
    if (res) {
      historyList.value = res || []
    }
  } catch (error) {
    console.error('获取历史记录失败', error)
  } finally {
    historyLoading.value = false
  }
}

// 使用历史记录
const useHistory = (item) => {
  result.value = item.content
  // 回填表单
  if (item.params) {
    try {
      const params = JSON.parse(item.params)
      Object.assign(form, params)
    } catch (e) {
      console.error('解析参数失败', e)
    }
  }
}

// 生成文案
const handleGenerate = async () => {
  if (!form.requirements) {
    ElMessage.warning('请输入核心卖点或要求')
    return
  }

  loading.value = true
  try {
    const response = await generateText(form)
    if (response) {
      result.value = response.text
      ElMessage.success('生成成功')
    }
  } catch (error) {
    ElMessage.error('生成失败，请重试')
    console.error(error)
  } finally {
    loading.value = false
  }
}

// 复制结果
const copyResult = async () => {
  try {
    await navigator.clipboard.writeText(result.value)
    ElMessage.success('复制成功')
  } catch (err) {
    ElMessage.error('复制失败')
  }
}

// 存为模板
const saveToTemplate = async () => {
  try {
    const { value: name } = await ElMessageBox.prompt('请输入模板名称', '保存模板', {
      confirmButtonText: '保存',
      cancelButtonText: '取消',
      inputPattern: /\S/,
      inputErrorMessage: '模板名称不能为空'
    })

    if (name) {
      const templateData = {
        name,
        type: 'TEXT',
        content: result.value,
        category: form.category,
        platform: form.platform,
        status: 1
      }
      
      const response = await createTemplate(templateData)
      if (response.code === 200) {
        ElMessage.success('保存成功')
      }
    }
  } catch (error) {
    if (error !== 'cancel') {
      ElMessage.error('保存失败')
    }
  }
}
onMounted(() => {
  loadHistory()
})
</script>

<style scoped lang="scss">
.creation-container {
  padding: 20px;
  
  .creation-header {
    margin-bottom: 30px;
    
    h2 {
      margin: 0 0 10px;
      font-weight: 500;
    }
    
    p {
      color: #666;
      margin: 0;
    }
  }

  .history-item {
    padding: 10px;
    border-bottom: 1px solid #eee;
    cursor: pointer;
    transition: background-color 0.3s;

    &:hover {
      background-color: #f5f7fa;
    }

    .history-content {
      font-size: 13px;
      color: #333;
      margin-bottom: 5px;
      overflow: hidden;
      text-overflow: ellipsis;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
    }

    .history-time {
      font-size: 12px;
      color: #999;
    }
  }
  
  .empty-history {
    text-align: center;
    color: #999;
    padding: 20px 0;
    font-size: 13px;
  }
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-weight: bold;
}

.input-card, .result-card {
  height: 600px;
  display: flex;
  flex-direction: column;
  
  :deep(.el-card__body) {
    flex: 1;
    overflow-y: auto;
  }
}

.generated-text {
  height: 100%;
  
  :deep(.el-textarea__inner) {
    height: 100%;
    padding: 15px;
    font-size: 16px;
    line-height: 1.6;
    background-color: #f8f9fa;
  }
}

.empty-state {
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
}
</style>
