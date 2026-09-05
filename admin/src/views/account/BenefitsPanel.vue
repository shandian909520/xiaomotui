<template>
  <div class="benefits-panel">
    <div class="version-tag">
      <el-tag :type="versionTagType" size="large" effect="dark" round>
        {{ versionLabel }}
      </el-tag>
    </div>

    <div class="benefit-items">
      <div class="benefit-row">
        <span class="label">剩余门店数</span>
        <div class="progress-wrap">
          <el-progress
            :percentage="storePercentage"
            :stroke-width="8"
            :color="progressColor"
          />
          <span class="count">{{ benefits.store_used || 0 }}/{{ benefits.store_total || 0 }}</span>
        </div>
      </div>

      <div class="benefit-row">
        <span class="label">剪辑魔力</span>
        <div class="progress-wrap">
          <el-progress
            :percentage="clipPercentage"
            :stroke-width="8"
            :color="progressColor"
          />
          <span class="count">已用 {{ benefits.clip_used || 0 }} / 总量 {{ benefits.clip_total || 0 }}</span>
        </div>
      </div>

      <div class="benefit-row">
        <span class="label">存储空间</span>
        <div class="progress-wrap">
          <el-progress
            :percentage="storagePercentage"
            :stroke-width="8"
            :color="progressColor"
          />
          <span class="count">已用 {{ formatStorage(benefits.storage_used) }} / 总量 {{ formatStorage(benefits.storage_total) }}</span>
        </div>
      </div>

      <div class="benefit-row">
        <span class="label">红包余额</span>
        <span class="money">&yen;{{ formatMoney(benefits.redpacket_balance) }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { accountApi } from '@/api/index.js'

const benefits = ref({
  version: 'basic',
  store_used: 0,
  store_total: 0,
  clip_used: 0,
  clip_total: 0,
  storage_used: 0,
  storage_total: 0,
  redpacket_balance: 0
})

const versionMap = {
  basic: '基础版',
  standard: '标准版',
  chain: '连锁版'
}

const versionTagTypeMap = {
  basic: 'info',
  standard: 'warning',
  chain: 'danger'
}

const progressColor = '#7b50ff'

const versionLabel = computed(() => versionMap[benefits.value.version] || '基础版')
const versionTagType = computed(() => versionTagTypeMap[benefits.value.version] || 'info')

const storePercentage = computed(() => {
  const { store_used, store_total } = benefits.value
  if (!store_total) return 0
  return Math.min(Math.round((store_used / store_total) * 100), 100)
})

const clipPercentage = computed(() => {
  const { clip_used, clip_total } = benefits.value
  if (!clip_total) return 0
  return Math.min(Math.round((clip_used / clip_total) * 100), 100)
})

const storagePercentage = computed(() => {
  const { storage_used, storage_total } = benefits.value
  if (!storage_total) return 0
  return Math.min(Math.round((storage_used / storage_total) * 100), 100)
})

const formatStorage = (bytes) => {
  if (!bytes) return '0B'
  const gb = bytes / (1024 * 1024 * 1024)
  if (gb >= 1) return gb.toFixed(2) + 'GB'
  const mb = bytes / (1024 * 1024)
  if (mb >= 1) return mb.toFixed(0) + 'MB'
  return bytes + 'B'
}

const formatMoney = (val) => {
  return (val || 0).toFixed(2)
}

const loadBenefits = async () => {
  try {
    const res = await accountApi.getAccountBenefits()
    if (res) {
      benefits.value = { ...benefits.value, ...res }
    }
  } catch (e) {
    console.error('[账户权益] 加载失败:', e)
    ElMessage.error('获取账户权益失败，请稍后重试')
  }
}

onMounted(() => {
  loadBenefits()
})

defineExpose({ loadBenefits })
</script>

<style lang="scss" scoped>
.benefits-panel {
  padding: 16px;
  min-width: 280px;
}

.version-tag {
  margin-bottom: 14px;
  text-align: center;
}

.benefit-items {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.benefit-row {
  display: flex;
  align-items: center;
  gap: 10px;

  .label {
    width: 60px;
    flex-shrink: 0;
    font-size: 13px;
    color: #5d5673;
  }
}

.progress-wrap {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 8px;

  :deep(.el-progress) {
    flex: 1;
  }

  .count {
    font-size: 12px;
    color: #8f7d9e;
    white-space: nowrap;
  }
}

.money {
  font-size: 16px;
  font-weight: 700;
  color: #e6234a;
}
</style>
