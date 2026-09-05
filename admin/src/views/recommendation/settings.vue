<template>
  <div class="settings-container">
    <div class="page-header">
      <div class="header-left">
        <h1 class="page-title">算法配置</h1>
      </div>
      <div class="header-actions">
        <el-button @click="resetConfig">恢复默认</el-button>
        <el-button type="primary" :icon="Check" @click="saveConfig">保存配置</el-button>
      </div>
    </div>

    <!-- 推荐算法参数 -->
    <el-card shadow="hover" style="margin-bottom: 20px;">
      <template #header>
        <div class="card-header"><span>推荐算法参数</span></div>
      </template>
      <el-form :model="algorithmConfig" label-width="160px">
        <el-form-item label="默认算法">
          <el-select v-model="algorithmConfig.default_algorithm" style="width: 300px;">
            <el-option label="混合推荐 (hybrid)" value="hybrid" />
            <el-option label="协同过滤 (collaborative)" value="collaborative" />
            <el-option label="内容推荐 (content)" value="content" />
            <el-option label="热门推荐 (popularity)" value="popularity" />
          </el-select>
        </el-form-item>
        <el-form-item label="推荐数量">
          <el-slider v-model="algorithmConfig.recommend_count" :min="3" :max="20" show-input style="width: 300px;" />
        </el-form-item>
        <el-form-item label="缓存时间(分钟)">
          <el-input-number v-model="algorithmConfig.cache_ttl" :min="5" :max="1440" :step="5" style="width: 300px;" />
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 权重配置 -->
    <el-card shadow="hover" style="margin-bottom: 20px;">
      <template #header>
        <div class="card-header"><span>权重配置</span></div>
      </template>
      <el-form :model="weightConfig" label-width="160px">
        <el-form-item label="协同过滤权重">
          <el-slider v-model="weightConfig.collaborative" :min="0" :max="100" show-input style="width: 300px;" />
        </el-form-item>
        <el-form-item label="内容推荐权重">
          <el-slider v-model="weightConfig.content" :min="0" :max="100" show-input style="width: 300px;" />
        </el-form-item>
        <el-form-item label="热门推荐权重">
          <el-slider v-model="weightConfig.popularity" :min="0" :max="100" show-input style="width: 300px;" />
        </el-form-item>
        <el-form-item label="个性化权重">
          <el-slider v-model="weightConfig.personalized" :min="0" :max="100" show-input style="width: 300px;" />
        </el-form-item>
        <el-form-item>
          <div class="weight-total" :class="{ 'is-error': totalWeight !== 100 }">
            权重总和: {{ totalWeight }}%
            <span v-if="totalWeight !== 100" class="error-text">（必须等于100%）</span>
          </div>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 冷启动策略 -->
    <el-card shadow="hover" style="margin-bottom: 20px;">
      <template #header>
        <div class="card-header"><span>冷启动策略</span></div>
      </template>
      <el-form :model="coldStartConfig" label-width="160px">
        <el-form-item label="冷启动算法">
          <el-select v-model="coldStartConfig.algorithm" style="width: 300px;">
            <el-option label="热门推荐" value="popularity" />
            <el-option label="最新推荐" value="latest" />
            <el-option label="分类推荐" value="category" />
            <el-option label="随机推荐" value="random" />
          </el-select>
        </el-form-item>
        <el-form-item label="冷启动阈值(行为次数)">
          <el-input-number v-model="coldStartConfig.threshold" :min="1" :max="50" style="width: 300px;" />
          <div class="form-tip">用户行为次数低于此值时使用冷启动策略</div>
        </el-form-item>
        <el-form-item label="探索比例(%)">
          <el-slider v-model="coldStartConfig.explore_ratio" :min="0" :max="50" show-input style="width: 300px;" />
          <div class="form-tip">为冷启动用户随机推荐新内容的比例</div>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 高级设置 -->
    <el-card shadow="hover">
      <template #header>
        <div class="card-header"><span>高级设置</span></div>
      </template>
      <el-form :model="advancedConfig" label-width="160px">
        <el-form-item label="多样性因子">
          <el-slider v-model="advancedConfig.diversity_factor" :min="0" :max="100" show-input style="width: 300px;" />
        </el-form-item>
        <el-form-item label="新鲜度因子">
          <el-slider v-model="advancedConfig.freshness_factor" :min="0" :max="100" show-input style="width: 300px;" />
        </el-form-item>
        <el-form-item label="相似度阈值">
          <el-slider v-model="advancedConfig.similarity_threshold" :min="0" :max="100" show-input style="width: 300px;" />
        </el-form-item>
        <el-form-item label="去重策略">
          <el-select v-model="advancedConfig.dedup_strategy" style="width: 300px;">
            <el-option label="不重复" value="strict" />
            <el-option label="24小时不重复" value="daily" />
            <el-option label="7天不重复" value="weekly" />
          </el-select>
        </el-form-item>
      </el-form>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Check } from '@element-plus/icons-vue'
import { getConfig, updateConfig, resetConfig as resetConfigApi } from '@/api/recommendation'

const loading = ref(false)

const defaultConfig = {
  algorithm: { default_algorithm: 'hybrid', recommend_count: 10, cache_ttl: 60 },
  weight: { collaborative: 40, content: 30, popularity: 20, personalized: 10 },
  coldStart: { algorithm: 'popularity', threshold: 5, explore_ratio: 20 },
  advanced: { diversity_factor: 30, freshness_factor: 20, similarity_threshold: 60, dedup_strategy: 'daily' }
}

const algorithmConfig = reactive({ ...defaultConfig.algorithm })
const weightConfig = reactive({ ...defaultConfig.weight })
const coldStartConfig = reactive({ ...defaultConfig.coldStart })
const advancedConfig = reactive({ ...defaultConfig.advanced })

const totalWeight = computed(() =>
  weightConfig.collaborative + weightConfig.content + weightConfig.popularity + weightConfig.personalized
)

const loadConfig = async () => {
  loading.value = true
  try {
    const res = await getConfig()
    const data = res || {}
    if (data.algorithm) Object.assign(algorithmConfig, data.algorithm)
    if (data.weight) Object.assign(weightConfig, data.weight)
    if (data.cold_start) Object.assign(coldStartConfig, data.cold_start)
    if (data.advanced) Object.assign(advancedConfig, data.advanced)
  } catch (e) {
    console.error('加载配置失败:', e)
  } finally {
    loading.value = false
  }
}

const saveConfig = async () => {
  if (totalWeight.value !== 100) {
    ElMessage.warning('权重总和必须等于100%')
    return
  }
  try {
    await updateConfig({
      algorithm: { ...algorithmConfig },
      weight: { ...weightConfig },
      cold_start: { ...coldStartConfig },
      advanced: { ...advancedConfig }
    })
    ElMessage.success('配置保存成功')
  } catch (e) {
    ElMessage.error('配置保存失败')
  }
}

const resetConfig = async () => {
  try {
    await ElMessageBox.confirm('确定要恢复默认配置吗？', '提示', { type: 'warning' })
    await resetConfigApi()
    Object.assign(algorithmConfig, defaultConfig.algorithm)
    Object.assign(weightConfig, defaultConfig.weight)
    Object.assign(coldStartConfig, defaultConfig.coldStart)
    Object.assign(advancedConfig, defaultConfig.advanced)
    ElMessage.success('已恢复默认配置')
  } catch { /* cancel */ }
}

onMounted(() => {
  loadConfig()
})
</script>

<style lang="scss" scoped>
.settings-container {
  padding: 20px;
  background: #f5f7fa;
  min-height: 100vh;

  .page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;

    .header-left {
      display: flex;
      align-items: center;
      gap: 12px;

      .page-title {
        margin: 0;
        font-size: 24px;
        font-weight: 600;
        color: #303133;
      }
    }

    .header-actions {
      display: flex;
      gap: 12px;
    }
  }

  .card-header {
    font-size: 16px;
    font-weight: 600;
  }

  .weight-total {
    font-size: 14px;
    color: #67C23A;
    font-weight: 600;

    &.is-error {
      color: #F56C6C;
    }

    .error-text {
      font-size: 12px;
      margin-left: 4px;
    }
  }

  .form-tip {
    font-size: 12px;
    color: #909399;
    margin-top: 4px;
  }
}

@media (max-width: 768px) {
  .settings-container {
    padding: 12px;
  }
}
</style>
