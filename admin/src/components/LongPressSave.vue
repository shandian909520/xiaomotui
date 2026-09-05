<!--
  LongPressSave 长按保存图片组件
  视频里海报/中奖结果页：长按图片弹出系统保存菜单（H5 场景）。
  PC 端支持右键另存为 / 直接下载。
-->
<template>
  <div
    class="xmt-long-press"
    @touchstart="onTouchStart"
    @touchend="onTouchEnd"
    @touchcancel="onTouchEnd"
    @contextmenu.prevent
  >
    <img v-if="src" :src="src" class="xmt-long-press-img" :alt="alt" />
    <div class="xmt-long-press-mask" :class="{ 'is-pressing': pressing }">
      <span v-if="pressing" class="xmt-long-press-tip">长按保存图片…</span>
      <span v-else class="xmt-long-press-tip-soft">长按图片保存到相册</span>
    </div>
    <div v-if="progress > 0" class="xmt-long-press-progress">
      <div class="xmt-long-press-progress-bar" :style="`width: ${progress}%`" />
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'

/**
 * @typedef {Object} LongPressSaveProps
 * @property {string} src   图片地址（必填）
 * @property {string} [alt] 替代文本
 * @property {number} [duration] 长按触发时长 ms，默认 600
 */
const props = defineProps({
  src: { type: String, required: true },
  alt: { type: String, default: '长按保存' },
  duration: { type: Number, default: 600 }
})

const emit = defineEmits(['saved', 'progress'])

const pressing = ref(false)
const progress = ref(0)
let timer = null
let startTs = 0

const clearTimers = () => {
  if (timer) { clearTimeout(timer); timer = null }
}

const fetchBlob = async (url) => {
  if (url.startsWith('data:') || url.startsWith('blob:')) {
    const res = await fetch(url)
    return res.blob()
  }
  try {
    const res = await fetch(url, { mode: 'cors' })
    if (res.ok) return res.blob()
  } catch (_) {}
  return null
}

const doSave = async () => {
  const blob = await fetchBlob(props.src)
  if (!blob) {
    window.open(props.src, '_blank')
    return
  }
  const objUrl = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = objUrl
  a.download = `${props.alt || 'image'}_${Date.now()}.png`
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  setTimeout(() => URL.revokeObjectURL(objUrl), 500)
  emit('saved')
}

const tick = () => {
  const elapsed = Date.now() - startTs
  progress.value = Math.min(100, Math.round((elapsed / props.duration) * 100))
  emit('progress', progress.value)
  if (elapsed >= props.duration) {
    clearTimers()
    doSave()
    return
  }
  timer = setTimeout(tick, 40)
}

const onTouchStart = () => {
  pressing.value = true
  progress.value = 0
  startTs = Date.now()
  tick()
}

const onTouchEnd = () => {
  pressing.value = false
  progress.value = 0
  clearTimers()
}
</script>

<script>
export default { name: 'LongPressSave' }
</script>

<style lang="scss" scoped>
.xmt-long-press {
  position: relative;
  display: inline-block;
  width: 100%;
  user-select: none;
  -webkit-user-select: none;
}

.xmt-long-press-img {
  display: block;
  width: 100%;
  height: auto;
  border-radius: 12px;
  pointer-events: none;
}

.xmt-long-press-mask {
  position: absolute;
  inset: 0;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0);
  transition: background 0.2s;
  pointer-events: none;

  &.is-pressing {
    background: rgba(0, 0, 0, 0.45);
  }
}

.xmt-long-press-tip,
.xmt-long-press-tip-soft {
  color: #fff;
  font-size: 14px;
  font-weight: 600;
  padding: 6px 14px;
  background: rgba(255, 107, 53, 0.95);
  border-radius: 20px;
  opacity: 0;
  transition: opacity 0.2s;
}

.xmt-long-press-tip-soft {
  background: rgba(0, 0, 0, 0.5);
}

.xmt-long-press-mask.is-pressing .xmt-long-press-tip {
  opacity: 1;
}

.xmt-long-press:hover .xmt-long-press-tip-soft {
  opacity: 1;
}

.xmt-long-press-progress {
  position: absolute;
  bottom: 0;
  left: 0;
  width: 100%;
  height: 4px;
  background: rgba(0, 0, 0, 0.15);
  border-radius: 0 0 12px 12px;
  overflow: hidden;
}

.xmt-long-press-progress-bar {
  height: 100%;
  background: linear-gradient(90deg, #FF6B35, #FF8E53);
  transition: width 0.05s linear;
}
</style>
