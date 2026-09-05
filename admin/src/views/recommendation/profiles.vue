<template>
  <div class="profile-container">
    <div class="page-header">
      <div class="header-left">
        <h1 class="page-title">用户画像</h1>
      </div>
      <div class="header-actions">
        <el-input
          v-model="searchUserId"
          placeholder="输入用户ID查询"
          style="width: 200px"
          clearable
          @keyup.enter="loadProfile"
        >
          <template #prefix><el-icon><Search /></el-icon></template>
        </el-input>
        <el-button type="primary" @click="loadProfile">查询</el-button>
      </div>
    </div>

    <template v-if="profileData">
      <!-- 活跃度评分 -->
      <el-card class="score-card" shadow="hover" style="margin-bottom: 20px;">
        <template #header>
          <div class="card-header"><span>活跃度评分</span></div>
        </template>
        <div class="score-content">
          <div class="score-ring">
            <el-progress type="circle" :percentage="activityScore" :width="120" :color="scoreColor" />
          </div>
          <div class="score-detail">
            <div class="score-item">
              <span class="label">用户ID</span>
              <span class="value">{{ searchUserId }}</span>
            </div>
            <div class="score-item">
              <span class="label">活跃等级</span>
              <el-tag :type="activityLevel.type">{{ activityLevel.text }}</el-tag>
            </div>
          </div>
        </div>
      </el-card>

      <!-- 兴趣标签 -->
      <el-card shadow="hover" style="margin-bottom: 20px;">
        <template #header>
          <div class="card-header"><span>兴趣标签分布</span></div>
        </template>
        <div ref="tagsChartRef" class="chart"></div>
      </el-card>

      <!-- 偏好标签 -->
      <el-card shadow="hover" style="margin-bottom: 20px;">
        <template #header>
          <div class="card-header"><span>偏好标签</span></div>
        </template>
        <div class="tag-cloud" v-if="preferenceTags.length > 0">
          <el-tag
            v-for="tag in preferenceTags"
            :key="tag.name"
            :size="getTagSize(tag.weight)"
            :type="getTagType(tag.weight)"
            effect="plain"
            class="tag-item"
          >
            {{ tag.name }}
            <span class="tag-weight">{{ (tag.weight * 100).toFixed(0) }}%</span>
          </el-tag>
        </div>
        <el-empty v-else description="暂无偏好标签" />
      </el-card>

      <!-- 用户行为热力图 -->
      <el-card shadow="hover">
        <template #header>
          <div class="card-header"><span>行为时段分布</span></div>
        </template>
        <div ref="heatmapChartRef" class="chart"></div>
      </el-card>
    </template>

    <el-empty v-else description="请输入用户ID查询画像" />
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { ElMessage } from 'element-plus'
import { Search } from '@element-plus/icons-vue'
import { useEcharts, getBarChartOption } from '@/composables/useEcharts'
import { getUserProfile } from '@/api/recommendation'

const searchUserId = ref('')
const profileData = ref(null)
const activityScore = ref(0)
const preferenceTags = ref([])

const tagsChartRef = ref(null)
const heatmapChartRef = ref(null)
const tagsChart = useEcharts(tagsChartRef)
const heatmapChart = useEcharts(heatmapChartRef)

const scoreColor = computed(() => {
  if (activityScore.value >= 80) return '#67C23A'
  if (activityScore.value >= 50) return '#E6A23C'
  return '#F56C6C'
})

const activityLevel = computed(() => {
  const s = activityScore.value
  if (s >= 80) return { text: '高活跃', type: 'success' }
  if (s >= 50) return { text: '中活跃', type: 'warning' }
  return { text: '低活跃', type: 'danger' }
})

const getTagSize = (weight) => weight > 0.7 ? 'large' : weight > 0.4 ? 'default' : 'small'
const getTagType = (weight) => weight > 0.7 ? '' : weight > 0.4 ? 'success' : 'info'

const loadProfile = async () => {
  if (!searchUserId.value) {
    ElMessage.warning('请输入用户ID')
    return
  }
  try {
    const res = await getUserProfile({ user_id: searchUserId.value })
    profileData.value = res
    activityScore.value = res.activity_score || 0
    preferenceTags.value = res.preference_tags || []

    // 标签分布图
    if (preferenceTags.value.length > 0) {
      const option = getBarChartOption(
        preferenceTags.value.map(t => t.name),
        [{ name: '权重', data: preferenceTags.value.map(t => (t.weight * 100).toFixed(1)) }],
        { xAxis: { type: 'category' }, yAxis: { type: 'value' } }
      )
      tagsChart.setOption(option)
    }

    // 行为时段图
    const hours = Array.from({ length: 24 }, (_, i) => `${i}:00`)
    const hourDistribution = res.hour_distribution || res.hourly_activity || Array(24).fill(0)
    const values = hourDistribution.map(v => Number(v) || 0)
    const heatOption = getBarChartOption(hours, [{ name: '活动次数', data: values }])
    heatmapChart.setOption(heatOption)
  } catch (e) {
    ElMessage.error('获取用户画像失败')
  }
}
</script>

<style lang="scss" scoped>
.profile-container {
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

  .score-card {
    .card-header {
      font-size: 16px;
      font-weight: 600;
    }

    .score-content {
      display: flex;
      align-items: center;
      gap: 40px;

      .score-detail {
        .score-item {
          display: flex;
          align-items: center;
          gap: 12px;
          margin-bottom: 12px;

          .label {
            color: #909399;
            font-size: 14px;
          }

          .value {
            font-size: 16px;
            font-weight: 600;
            color: #303133;
          }
        }
      }
    }
  }

  .card-header {
    font-size: 16px;
    font-weight: 600;
  }

  .chart {
    width: 100%;
    height: 350px;
  }

  .tag-cloud {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;

    .tag-item {
      .tag-weight {
        margin-left: 4px;
        font-size: 12px;
        color: #909399;
      }
    }
  }
}

@media (max-width: 768px) {
  .profile-container {
    padding: 12px;

    .score-card .score-content {
      flex-direction: column;
      gap: 20px;
    }

    .chart {
      height: 280px;
    }
  }
}
</style>
