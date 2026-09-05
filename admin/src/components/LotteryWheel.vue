<!--
  LotteryWheel 六宫格转盘组件
  视频里抽奖/盲盒环节的展示样式。
  使用 CSS transform: rotate() + transform-origin 实现扇区划分。
-->
<template>
  <div class="xmt-wheel-wrap">
    <div class="xmt-wheel" :style="wheelStyle">
      <div
        v-for="(p, idx) in prizes"
        :key="idx"
        class="xmt-wheel-segment"
        :style="segmentStyle(idx, p.color)"
      >
        <div class="xmt-wheel-segment-content">
          <span class="xmt-wheel-icon">{{ p.icon || '🎁' }}</span>
          <span class="xmt-wheel-name">{{ p.name }}</span>
        </div>
      </div>
      <div class="xmt-wheel-center" @click="onSpin">
        <span v-if="spinning">抽奖中</span>
        <span v-else>开始</span>
      </div>
      <div class="xmt-wheel-pointer" />
    </div>
    <div v-if="resultName" class="xmt-wheel-result">
      恭喜抽中：<strong>{{ resultName }}</strong>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

/**
 * @typedef {Object} PrizeDef
 * @property {string} name
 * @property {string} color
 * @property {string} [icon] emoji
 */

/**
 * @typedef {Object} LotteryWheelProps
 * @property {PrizeDef[]} prizes
 * @property {number} [size] 直径 px，默认 320
 */
const props = defineProps({
  prizes: { type: Array, default: () => [] },
  size: { type: Number, default: 320 }
})

const emit = defineEmits(['done'])

const rotation = ref(0)
const spinning = ref(false)
const resultName = ref('')

const segmentStyle = (idx, color) => {
  const total = props.prizes.length
  if (!total) return {}
  const segDeg = 360 / total
  const startDeg = idx * segDeg
  return {
    background: color,
    transform: `rotate(${startDeg}deg)`,
    'clip-path': `polygon(50% 50%, 50% 0%, ${50 + 50 * Math.tan((segDeg * Math.PI) / 360 / 1) * Math.cos((segDeg * Math.PI) / 360)}% ${50 - 50 * Math.tan((segDeg * Math.PI) / 360) * Math.sin((segDeg * Math.PI) / 360)}%)`
  }
}

const wheelStyle = computed(() => ({
  width: `${props.size}px`,
  height: `${props.size}px`,
  transform: `rotate(${rotation.value}deg)`,
  transition: spinning.value
    ? 'transform 4s cubic-bezier(0.2, 0.8, 0.3, 1)'
    : 'none'
}))

const onSpin = () => {
  if (spinning.value || !props.prizes.length) return
  resultName.value = ''
  const total = props.prizes.length
  const winIdx = Math.floor(Math.random() * total)
  const segDeg = 360 / total
  // 让 winIdx 停在指针方向（上）
  const baseTurn = 360 * 6
  const target = baseTurn + (360 - winIdx * segDeg - segDeg / 2)
  const currentMod = rotation.value % 360
  rotation.value = rotation.value + (target - currentMod)
  spinning.value = true
  setTimeout(() => {
    spinning.value = false
    resultName.value = props.prizes[winIdx]?.name || ''
    emit('done', winIdx)
  }, 4200)
}

defineExpose({ onSpin, reset: () => { rotation.value = 0; resultName.value = '' } })
</script>

<script>
export default { name: 'LotteryWheel' }
</script>

<style lang="scss" scoped>
.xmt-wheel-wrap {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
}

.xmt-wheel {
  position: relative;
  border-radius: 50%;
  background: #fff;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
  overflow: hidden;
}

.xmt-wheel-segment {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.xmt-wheel-segment-content {
  position: absolute;
  top: 18%;
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  flex-direction: column;
  align-items: center;
  color: #fff;
  font-weight: 600;
  text-align: center;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
}

.xmt-wheel-icon {
  font-size: 26px;
  margin-bottom: 6px;
}

.xmt-wheel-name {
  font-size: 13px;
  white-space: nowrap;
}

.xmt-wheel-center {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 84px;
  height: 84px;
  border-radius: 50%;
  background: linear-gradient(135deg, #FF6B35, #FF8E53);
  color: #fff;
  font-size: 16px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: 0 2px 8px rgba(255, 107, 53, 0.4);
  z-index: 2;
}

.xmt-wheel-pointer {
  position: absolute;
  top: -8px;
  left: 50%;
  transform: translateX(-50%);
  width: 0;
  height: 0;
  border-left: 14px solid transparent;
  border-right: 14px solid transparent;
  border-top: 24px solid #FF6B35;
  z-index: 3;
  filter: drop-shadow(0 2px 2px rgba(0, 0, 0, 0.2));
}

.xmt-wheel-result {
  padding: 8px 16px;
  background: rgba(255, 107, 53, 0.1);
  color: #FF6B35;
  border-radius: 20px;
  font-size: 14px;

  strong { font-size: 16px; }
}
</style>
