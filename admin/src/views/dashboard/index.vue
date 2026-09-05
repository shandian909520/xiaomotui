<template>
  <div class="dashboard-page">
    <!-- 四步流程卡片 -->
    <section class="flow-section">
      <div class="flow-header">
        <div class="flow-title-area">
          <span class="flow-badge">4</span>
          <strong>4步打造高效运营体系</strong>
          <el-tag class="effect-tag" round>轻松提升运营效果</el-tag>
        </div>
        <el-button round>详见《商家后台操作手册》</el-button>
      </div>
      <div class="flow-steps">
        <div
          v-for="(step, idx) in flowSteps"
          :key="step.key"
          class="flow-step"
          :class="{ completed: step.completed }"
        >
          <div class="step-top">
            <span class="step-icon">{{ step.icon }}</span>
            <div class="step-info">
              <strong>{{ step.title }}</strong>
              <em>{{ step.no }}</em>
            </div>
            <span v-if="step.completed" class="step-check">
              <el-icon><CircleCheckFilled /></el-icon>
            </span>
            <span v-else class="step-circle"></span>
          </div>
          <p>{{ step.desc }}</p>
          <div v-if="idx < flowSteps.length - 1" class="step-arrow">
            <el-icon><ArrowRight /></el-icon>
          </div>
        </div>
      </div>
    </section>

    <!-- Agent E: 漏斗 4 卡片(NFC 触发 / H5 落地 / 任务完成 / 加粉转化) -->
    <section class="funnel-section panel">
      <div class="funnel-head">
        <div>
          <h2>C 端漏斗</h2>
          <span class="hint-text">最近 7 天 · 点击刷新</span>
        </div>
        <div class="filters">
          <el-button size="small" @click="loadFunnel">刷新</el-button>
        </div>
      </div>
      <div class="funnel-grid" v-loading="funnelLoading">
        <div
          v-for="card in funnelCards"
          :key="card.key"
          class="funnel-card"
          :style="{ '--accent': card.color, '--tint': card.tint }"
        >
          <div class="funnel-card-icon">{{ card.icon }}</div>
          <div class="funnel-card-meta">
            <strong>{{ card.count }}</strong>
            <span>{{ card.title }}</span>
          </div>
          <div class="funnel-card-bar">
            <span class="bar" :style="{ width: card.rate + '%', background: card.color }"></span>
          </div>
          <em>转化率 {{ card.rate }}%</em>
        </div>
      </div>
    </section>

    <div class="main-layout">
      <div class="left-column">
        <!-- 数据统计区域 -->
        <section class="panel stats-panel">
          <div class="section-head">
            <div>
              <h2>数据统计</h2>
              <span class="hint-text">数据为0则不展示在折线图中</span>
            </div>
            <div class="filters">
              <el-date-picker
                v-model="dateRange"
                type="daterange"
                range-separator="-"
                start-placeholder="开始日期"
                end-placeholder="结束日期"
                size="small"
                :disabled-date="disableDate"
                style="width: 260px"
                @change="handleDateChange"
              />
              <el-select v-model="storeFilter" placeholder="选择门店" size="small" style="width: 136px">
                <el-option label="全部门店" value="all" />
                <el-option v-for="s in storeOptions" :key="s.id" :label="s.name" :value="s.id" />
              </el-select>
              <el-button size="small" @click="exportStats">导出</el-button>
            </div>
          </div>

          <div class="metric-grid">
            <div
              v-for="group in metricGroups"
              :key="group.title"
              class="metric-card"
              :style="{ '--accent': group.color, '--tint': group.tint }"
            >
              <div class="metric-title">
                <span class="metric-icon">{{ group.icon }}</span>
                <strong>{{ group.title }}</strong>
              </div>
              <div class="metric-items">
                <div v-for="item in group.items" :key="item.label">
                  <strong>{{ item.value }}</strong>
                  <span>{{ item.label }}</span>
                </div>
              </div>
              <el-icon class="down-arrow"><ArrowDownBold /></el-icon>
            </div>
          </div>

          <div class="line-chart">
            <div class="grid-lines">
              <span v-for="n in 7" :key="n"></span>
            </div>
            <svg viewBox="0 0 1080 150" preserveAspectRatio="none" aria-label="数据趋势">
              <path
                v-for="(path, index) in trendPaths"
                :key="index"
                :d="path"
                :class="['trend-line', ['blue', 'green', 'orange'][index % 3]]"
              />
            </svg>
            <div class="x-axis">
              <span v-for="date in trendDates" :key="date">{{ date }}</span>
            </div>
          </div>
        </section>
      </div>

      <aside class="right-column">
        <!-- 消耗总览 -->
        <section class="panel overview-panel">
          <div class="aside-head">
            <h2>消耗总览</h2>
            <a class="link-text">消耗明细 ></a>
          </div>
          <div class="overview-grid">
            <div v-for="item in consumptionData" :key="item.label" class="overview-item">
              <span class="ov-label">{{ item.label }}</span>
              <strong class="ov-value">{{ item.value }}</strong>
              <el-progress
                v-if="item.percent != null"
                :percentage="item.percent"
                :stroke-width="6"
                :color="item.color || '#a855f7'"
                :show-text="false"
                style="margin-top: 6px"
              />
            </div>
          </div>
        </section>

        <!-- 智能员工快捷入口 -->
        <section class="panel staff-panel">
          <div class="aside-head">
            <h2>智能员工</h2>
            <a class="link-text" @click="goToStaff">查看更多 ></a>
          </div>
          <div class="staff-grid">
            <div
              v-for="person in quickStaff"
              :key="person.id"
              class="staff-card"
              @click="goToStaffDetail(person)"
            >
              <el-tag v-if="person.is_hot" type="danger" size="small" class="hot-tag" effect="dark">HOT</el-tag>
              <div class="portrait" :style="{ background: person.avatarBg }">
                {{ person.avatar }}
              </div>
              <strong>{{ person.nickname }}</strong>
              <span class="staff-role">{{ person.role }}</span>
              <el-button size="small" plain round>安排工作</el-button>
            </div>
          </div>
        </section>

        <!-- 商家管理端小程序二维码 -->
        <section class="panel qr-panel">
          <div class="qr-strip">手机端补充视频、图文等素材，轻松搞定</div>
          <div class="qr-content">
            <div class="qr-placeholder">
              <img v-if="qrCodeUrl" :src="qrCodeUrl" alt="小程序二维码" class="qr-img" />
              <span v-else class="qr-text">码</span>
            </div>
            <div>
              <h3>商家管理小程序</h3>
              <p>微信扫一扫</p>
            </div>
          </div>
        </section>
      </aside>
    </div>

    <!-- 底部快捷入口 -->
    <section class="panel creative-panel">
      <div class="creative-head">
        <h2>用户创作广场</h2>
        <div class="counter">
          平台<span>累计生成</span>视频
          <strong>{{ totalVideos }}</strong>
        </div>
      </div>
      <div class="creative-tabs">
        <button
          v-for="tab in creativeTabs"
          :key="tab"
          :class="{ active: tab === activeCreativeTab }"
          @click="activeCreativeTab = tab"
        >{{ tab }}</button>
      </div>
      <div class="work-grid">
        <div
          v-for="work in creativeWorks"
          :key="work.title"
          class="work-card"
          :style="{ background: work.bg }"
        >
          <span>{{ work.title }}</span>
        </div>
      </div>
    </section>

    <button class="chat-fab" @click="openSmartQA">智能问答</button>

    <!-- 智能问答弹窗 -->
    <el-dialog v-model="smartQaVisible" title="智能问答" width="520px" :close-on-click-modal="false" class="smart-qa-dialog">
      <div class="qa-chat-area" ref="chatAreaRef">
        <div v-if="chatMessages.length === 0" class="qa-welcome">
          <p>你好！我是 AI 智能助手，可以回答运营相关问题、生成文案、提供建议。</p>
          <div class="qa-suggestions">
            <el-tag v-for="s in qaSuggestions" :key="s" size="small" @click="sendQuickQuestion(s)" style="cursor:pointer;margin:4px">{{ s }}</el-tag>
          </div>
        </div>
        <div v-for="(msg, i) in chatMessages" :key="i" class="qa-message" :class="msg.role">
          <div class="qa-bubble">{{ msg.content }}</div>
        </div>
        <div v-if="qaLoading" class="qa-message assistant">
          <div class="qa-bubble qa-typing">思考中...</div>
        </div>
      </div>
      <template #footer>
        <div class="qa-input-bar">
          <el-input v-model="qaInput" placeholder="输入你的问题..." @keyup.enter="sendMessage" :disabled="qaLoading" />
          <el-button type="primary" @click="sendMessage" :loading="qaLoading" :disabled="!qaInput.trim()">发送</el-button>
        </div>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, nextTick, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowRight, ArrowDownBold, CircleCheckFilled } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import {
  getDashboardFlowSteps,
  getDashboardDataStats,
  getDashboardConsumption,
  getDashboardQuickEntries,
  getDashboardQrCode
} from '@/api/index.js'
import { statsApi } from '@/api/index.js'
import { generateContent } from '@/api/ai'
import { nfcConfigApi } from '@/api/nfc-config'
import { funnelApi as funnelAdminApi } from '@/api/funnel'

const router = useRouter()

// 默认空状态：流程步骤
const defaultFlowSteps = []

// 默认空状态：指标分组
const defaultMetricGroups = []

// 默认空状态：消耗总览
const defaultConsumption = []

// 默认空状态：员工
const defaultStaff = []

// 默认空状态：趋势
const defaultTrendPaths = []
const defaultTrendDates = []

// 响应式状态
const flowSteps = ref([...defaultFlowSteps])
const dateRange = ref(['2026-05-18', '2026-05-24'])
const storeFilter = ref('all')
const storeOptions = ref([])
const metricGroups = ref([...defaultMetricGroups])
const trendPaths = ref([...defaultTrendPaths])
const trendDates = ref([...defaultTrendDates])
const consumptionData = ref([...defaultConsumption])
const quickStaff = ref([...defaultStaff])
const qrCodeUrl = ref('')
const totalVideos = ref('105,578,851')

// Agent E: 漏斗 4 卡片(默认空)
const funnelLoading = ref(false)
const funnelCards = ref([
  { key: 'nfc_trigger',    title: 'NFC 触发数', count: 0, rate: 0, icon: '📡', color: '#6366f1', tint: 'rgba(99, 102, 241, 0.08)' },
  { key: 'h5_enter',       title: 'H5 落地',    count: 0, rate: 0, icon: '🌐', color: '#8b5cf6', tint: 'rgba(139, 92, 246, 0.08)' },
  { key: 'task_complete',  title: '任务完成',   count: 0, rate: 0, icon: '✅', color: '#10b981', tint: 'rgba(16, 185, 129, 0.08)' },
  { key: 'add_wechat',     title: '加粉转化',   count: 0, rate: 0, icon: '💬', color: '#f59e0b', tint: 'rgba(245, 158, 11, 0.08)' }
])
const activeCreativeTab = ref('全部')
const creativeTabs = ['全部', '餐饮', '美业', '游玩', '酒店', '三农', '家具建材', '休闲娱乐', '黄金珠宝']
const creativeWorks = [
  { title: '湘中缘', bg: 'linear-gradient(135deg, #6a3518, #d9a44f)' },
  { title: '相逢酒店', bg: 'linear-gradient(135deg, #9d8c7e, #e3ded3)' },
  { title: '金福正珠宝直播', bg: 'linear-gradient(135deg, #233a5c, #c5a564)' },
  { title: '老街茶馆', bg: 'linear-gradient(135deg, #406a51, #d6c27d)' },
  { title: '长城建材', bg: 'linear-gradient(135deg, #74614d, #f0d3a1)' }
]

// 日期禁用（最多91天）
const disableDate = (date) => {
  if (!dateRange.value || !dateRange.value[0]) return false
  const start = new Date(dateRange.value[0])
  const diff = Math.abs(date.getTime() - start.getTime())
  return diff > 91 * 24 * 60 * 60 * 1000
}

const handleDateChange = () => {
  loadDataStats()
}

const exportStats = () => {
  if (!metricGroups.value.length) {
    ElMessage.warning('暂无数据可导出')
    return
  }
  try {
    const columns = [
      { label: '指标分组', key: 'group' },
      { label: '指标名称', key: 'label' },
      { label: '指标值', key: 'value' }
    ]
    const rows = []
    metricGroups.value.forEach(group => {
      const title = group.title || ''
      if (group.items && group.items.length) {
        group.items.forEach(item => {
          rows.push({ group: title, label: item.label || '', value: String(item.value ?? '') })
        })
      }
    })
    if (!rows.length) {
      ElMessage.warning('暂无数据可导出')
      return
    }
    const header = columns.map(c => c.label).join(',')
    const csvRows = rows.map(row =>
      columns.map(c => {
        let val = String(row[c.key] ?? '')
        if (val.includes(',') || val.includes('"') || val.includes('\n')) {
          val = '"' + val.replace(/"/g, '""') + '"'
        }
        return val
      }).join(',')
    )
    const csv = '\uFEFF' + header + '\n' + csvRows.join('\n')
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    const dateStr = (dateRange.value && dateRange.value[0]) ? `${dateRange.value[0]}_${dateRange.value[1]}` : new Date().toISOString().slice(0, 10)
    link.download = `仪表盘统计_${dateStr}.csv`
    link.click()
    URL.revokeObjectURL(url)
    ElMessage.success('导出成功')
  } catch (e) {
    console.error('导出失败:', e)
    ElMessage.error('导出失败')
  }
}

const goToStaff = () => {
  router.push('/ai/staff')
}

const goToStaffDetail = (person) => {
  router.push(`/ai/staff?highlight=${person.id}`)
}

// 智能问答
const smartQaVisible = ref(false)
const qaInput = ref('')
const qaLoading = ref(false)
const chatMessages = ref([])
const chatAreaRef = ref(null)
const qaSuggestions = ['如何提升店铺评分？', '帮我写一段口播文案', '团购活动怎么策划？', '怎样提高视频播放量？']

const openSmartQA = () => {
  smartQaVisible.value = true
}

const scrollChatToBottom = () => {
  nextTick(() => {
    if (chatAreaRef.value) chatAreaRef.value.scrollTop = chatAreaRef.value.scrollHeight
  })
}

const sendMessage = async () => {
  const text = qaInput.value.trim()
  if (!text || qaLoading.value) return

  chatMessages.value.push({ role: 'user', content: text })
  qaInput.value = ''
  qaLoading.value = true
  scrollChatToBottom()

  try {
    const res = await generateContent({
      scene: text,
      style: '专业',
      platform: 'ALL',
      requirements: '',
      provider: 'minimax',
    })
    const reply = res?.text || res?.data?.text || res?.content || JSON.stringify(res)
    chatMessages.value.push({ role: 'assistant', content: reply })
  } catch (e) {
    chatMessages.value.push({ role: 'assistant', content: '抱歉，请求失败了：' + (e?.message || '请稍后再试') })
  } finally {
    qaLoading.value = false
    scrollChatToBottom()
  }
}

const sendQuickQuestion = (question) => {
  qaInput.value = question
  sendMessage()
}


const loadDataStats = async () => {
  try {
    const res = await getDashboardDataStats({
      start_date: dateRange.value?.[0],
      end_date: dateRange.value?.[1],
      store_id: storeFilter.value === 'all' ? undefined : storeFilter.value
    })
    if (res.metricGroups) metricGroups.value = res.metricGroups
    if (res.dates) trendDates.value = res.dates
    if (res.paths) trendPaths.value = res.paths
    if (res.stores) storeOptions.value = res.stores
  } catch {
    ElMessage.error('获取统计数据失败，请稍后重试')
  }
}

/**
 * Agent E: 加载商家级漏斗 4 卡片
 * 取最大数为分母(避免空数据除零)
 */
const loadFunnel = async () => {
  funnelLoading.value = true
  try {
    const today = new Date()
    const sevenDaysAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000)
    const fmt = (d) => d.toISOString().slice(0, 10)
    const res = await funnelAdminApi.getMerchantFunnel({
      date_from: fmt(sevenDaysAgo),
      date_to: fmt(today)
    })
    const data = res?.data || res || {}
    const cards = data.cards || []
    const counts = {}
    for (const c of cards) {
      counts[c.key] = Number(c.count) || 0
    }
    const max = Math.max(...Object.values(counts), 0)
    funnelCards.value = funnelCards.value.map((card) => {
      const count = counts[card.key] || 0
      return {
        ...card,
        count,
        rate: max > 0 ? Math.round((count / max) * 100) : 0
      }
    })
  } catch (e) {
    console.error('加载漏斗失败:', e)
    // 不弹 toast,避免首屏失败刷屏
  } finally {
    funnelLoading.value = false
  }
}

onMounted(async () => {
  try {
    const [flowRes, statsRes, consumeRes, staffRes, qrRes] = await Promise.allSettled([
      getDashboardFlowSteps(),
      getDashboardDataStats({
        start_date: dateRange.value[0],
        end_date: dateRange.value[1]
      }),
      getDashboardConsumption(),
      getDashboardQuickEntries(),
      getDashboardQrCode()
    ])

    if (flowRes.status === 'fulfilled' && flowRes.value) {
      const d = flowRes.value
      if (d.steps && d.steps.length) flowSteps.value = d.steps
    }

    if (statsRes.status === 'fulfilled' && statsRes.value) {
      const d = statsRes.value
      if (d.metricGroups) metricGroups.value = d.metricGroups
      if (d.dates) trendDates.value = d.dates
      if (d.paths) trendPaths.value = d.paths
      if (d.stores) storeOptions.value = d.stores
    }

    if (consumeRes.status === 'fulfilled' && consumeRes.value) {
      const d = consumeRes.value
      if (d.items) consumptionData.value = d.items
    }

    if (staffRes.status === 'fulfilled' && staffRes.value) {
      const d = staffRes.value
      if (d.list) quickStaff.value = d.list
    }

    if (qrRes.status === 'fulfilled' && qrRes.value) {
      const d = qrRes.value
      if (d.url) qrCodeUrl.value = d.url
    }
  } catch {
    ElMessage.error('加载首页数据失败，请刷新页面重试')
  }
  // Agent E: 漏斗(异步,不阻塞主流程)
  loadFunnel()
})
</script>

<style lang="scss" scoped>
.dashboard-page {
  position: relative;
  padding-bottom: 20px;
}

/* 流程步骤 */
.flow-section {
  padding: 30px 24px 24px;
  border-radius: 18px;
  background:
    radial-gradient(circle at 3% 18%, rgba(177, 82, 255, 0.22), transparent 16%),
    linear-gradient(115deg, #f3ddff 0%, #fbf3ff 58%, #d9c5ff 100%);
  margin-bottom: 12px;
}

.flow-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 14px;
}

.flow-title-area {
  display: flex;
  align-items: center;
  gap: 10px;
  color: #282034;
  font-size: 22px;
}

.flow-badge {
  width: 42px;
  height: 42px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 12px;
  color: #a54cff;
  background: rgba(255, 255, 255, 0.6);
  font-size: 36px;
  font-weight: 900;
  transform: rotate(-10deg);
}

.effect-tag {
  margin-left: 12px;
  border: 0;
  color: #8d5a24;
  background: #ffd6a0;
  font-weight: 600;
}

.flow-steps {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 10px;
  position: relative;
}

.flow-step {
  position: relative;
  min-height: 130px;
  padding: 19px 20px;
  border-radius: 15px;
  background: rgba(255, 255, 255, 0.82);
  transition: box-shadow 0.25s;

  &:hover {
    box-shadow: 0 4px 16px rgba(120, 60, 200, 0.1);
  }
}

.step-top {
  display: flex;
  align-items: center;
  gap: 8px;
}

.step-icon {
  width: 30px;
  height: 30px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  background: #ead5ff;
  font-size: 16px;
}

.step-info {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 18px;
}

.step-info em {
  color: rgba(162, 87, 255, 0.22);
  font-size: 42px;
  font-weight: 900;
  font-style: italic;
}

.step-check {
  margin-left: auto;
  color: #22c55e;
  font-size: 22px;
}

.step-circle {
  margin-left: auto;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  border: 2px solid #ccc;
}

.flow-step.completed .step-icon {
  background: #dcfce7;
}

.flow-step p {
  margin: 9px 0 0;
  color: #6a6276;
  font-size: 13px;
  line-height: 1.55;
}

.step-arrow {
  position: absolute;
  right: -12px;
  top: 50%;
  transform: translateY(-50%);
  z-index: 1;
  color: #c084fc;
  font-size: 20px;
}

/* Agent E: 漏斗 4 卡片 */
.funnel-section {
  margin-top: 12px;
  padding: 24px;
}

.funnel-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.funnel-head h2 {
  margin: 0;
  color: #161224;
  font-size: 18px;
}

.funnel-head .hint-text {
  margin-left: 6px;
  color: #909399;
  font-size: 12px;
  font-weight: 500;
}

.funnel-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
}

.funnel-card {
  position: relative;
  padding: 18px 16px;
  border-radius: 14px;
  background: var(--tint);
  border: 1px solid rgba(0, 0, 0, 0.04);
  display: flex;
  flex-direction: column;
  gap: 8px;
  transition: transform 0.2s, box-shadow 0.2s;

  &:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(99, 102, 241, 0.12);
  }
}

.funnel-card-icon {
  font-size: 24px;
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: var(--accent);
  color: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.funnel-card-meta {
  display: flex;
  flex-direction: column;
  gap: 2px;

  strong {
    font-size: 28px;
    color: #161224;
    font-weight: 700;
  }
  span {
    font-size: 13px;
    color: #6b7280;
  }
}

.funnel-card-bar {
  margin-top: 4px;
  height: 6px;
  background: rgba(0, 0, 0, 0.06);
  border-radius: 3px;
  overflow: hidden;

  .bar {
    display: block;
    height: 100%;
    border-radius: 3px;
    transition: width 0.5s ease;
  }
}

.funnel-card em {
  font-style: normal;
  font-size: 12px;
  color: #6b7280;
}

/* 主布局 */
.main-layout {
  display: grid;
  grid-template-columns: minmax(860px, 1fr) 408px;
  gap: 12px;
  margin-top: 12px;
}

.left-column,
.right-column {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.panel {
  border-radius: 18px;
  background: #fff;
  box-shadow: 0 10px 30px rgba(91, 66, 138, 0.05);
}

/* 数据统计 */
.stats-panel {
  padding: 24px;
}

.section-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.section-head h2,
.aside-head h2,
.creative-head h2 {
  margin: 0;
  color: #161224;
  font-size: 20px;
}

.hint-text {
  margin-left: 4px;
  color: #536494;
  font-size: 12px;
  font-weight: 600;
}

.filters {
  display: flex;
  gap: 10px;
}

.metric-grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 14px;
  margin: 28px 0 12px;
}

.metric-card {
  min-height: 174px;
  padding: 14px 10px 6px;
  border-radius: 13px;
  background: var(--tint);
}

.metric-title {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #161224;
}

.metric-icon {
  width: 20px;
  height: 20px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 6px;
  color: #fff;
  background: var(--accent);
  font-size: 11px;
}

.metric-items {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 9px;
  margin-top: 13px;
}

.metric-items div {
  height: 48px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.78);
}

.metric-items strong {
  font-size: 15px;
}

.metric-items span {
  margin-top: 3px;
  color: #1c1728;
  font-size: 12px;
}

.down-arrow {
  display: block;
  margin: 16px auto 0;
  color: #151224;
  font-size: 16px;
}

.line-chart {
  position: relative;
  height: 176px;
  margin-top: 4px;
}

.grid-lines {
  position: absolute;
  inset: 0 0 22px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}

.grid-lines span {
  border-top: 1px dashed #e4e1e8;
}

.line-chart svg {
  position: absolute;
  inset: 0 0 20px;
}

.trend-line {
  fill: none;
  stroke-width: 2.5;
}

.trend-line.blue { stroke: #5573dc; }
.trend-line.green { stroke: #7ac66b; }
.trend-line.orange { stroke: #f1b332; }

.x-axis {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  display: flex;
  justify-content: space-between;
  color: #c8c3cc;
  font-size: 13px;
}

/* 消耗总览 */
.overview-panel,
.staff-panel {
  padding: 24px;
}

.aside-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.link-text {
  color: #4f485b;
  font-size: 13px;
  text-decoration: none;
  cursor: pointer;
}

.overview-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 9px;
  margin-top: 22px;
}

.overview-item {
  padding: 14px;
  border-radius: 8px;
  background: #f7f7fb;
}

.ov-label {
  display: block;
  font-size: 13px;
  color: #606266;
}

.ov-value {
  display: block;
  margin-top: 5px;
  font-size: 14px;
  color: #161224;
}

/* 智能员工 */
.staff-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 11px;
  margin-top: 20px;
}

.staff-card {
  position: relative;
  min-height: 180px;
  padding: 14px 10px;
  display: flex;
  flex-direction: column;
  align-items: center;
  border: 1px solid #ece8f4;
  border-radius: 14px;
  cursor: pointer;
  transition: box-shadow 0.25s, transform 0.25s;

  &:hover {
    box-shadow: 0 4px 16px rgba(120, 60, 200, 0.1);
    transform: translateY(-2px);
  }
}

.hot-tag {
  position: absolute;
  top: 0;
  right: 10px;
  border-radius: 0 0 8px 8px;
}

.portrait {
  width: 58px;
  height: 58px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 12px;
  border-radius: 50%;
  color: #fff;
  font-size: 24px;
  font-weight: 800;
}

.staff-card strong {
  font-size: 14px;
}

.staff-role {
  margin: 8px 0 18px;
  color: #7c7288;
  font-size: 12px;
}

/* 二维码 */
.qr-panel {
  overflow: hidden;
}

.qr-strip {
  height: 45px;
  display: flex;
  align-items: center;
  padding-left: 46px;
  color: #3a2f52;
  background: linear-gradient(90deg, #d9b9ff, #e1f7ff);
  font-weight: 700;
}

.qr-content {
  display: flex;
  align-items: center;
  gap: 26px;
  padding: 20px 34px;
}

.qr-placeholder {
  width: 72px;
  height: 72px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px dashed #1d1728;
  border-radius: 4px;
  flex-shrink: 0;
}

.qr-img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.qr-text {
  font-weight: 800;
}

.qr-content h3 {
  margin: 0 0 8px;
}

.qr-content p {
  margin: 0;
  color: #909399;
}

/* 创作广场 */
.creative-panel {
  margin-top: 12px;
  padding: 28px 24px;
}

.creative-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.counter {
  color: #282034;
  font-size: 18px;
  font-weight: 700;
}

.counter span {
  color: #ff22b6;
}

.counter strong {
  display: inline-block;
  margin-left: 8px;
  padding: 0 14px;
  color: #fff;
  background: linear-gradient(180deg, #d642ff, #6300b7);
  border: 2px solid #f451ff;
  font-size: 26px;
  line-height: 36px;
  text-shadow: 0 1px 4px rgba(0, 0, 0, 0.3);
}

.creative-tabs {
  display: flex;
  gap: 12px;
  margin: 24px 0 16px;
}

.creative-tabs button {
  height: 38px;
  padding: 0 18px;
  border: 1px solid #ece8f4;
  border-radius: 19px;
  background: #faf9fe;
  color: #20182b;
  cursor: pointer;
  transition: all 0.2s;

  &:hover {
    background: #fff;
    box-shadow: 0 2px 8px rgba(120, 60, 200, 0.08);
  }

  &.active {
    background: #fff;
    font-weight: 800;
    border-color: #c084fc;
  }
}

.work-grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 16px;
}

.work-card {
  height: 120px;
  display: flex;
  align-items: flex-end;
  padding: 14px;
  border-radius: 8px;
  color: #fff;
  font-weight: 800;
  box-shadow: inset 0 -45px 60px rgba(0, 0, 0, 0.28);
  cursor: pointer;
  transition: transform 0.2s;

  &:hover {
    transform: translateY(-3px);
  }
}

.chat-fab {
  position: fixed;
  right: 18px;
  bottom: 118px;
  width: 54px;
  height: 142px;
  border: 2px solid #7d8dff;
  border-radius: 28px;
  color: #161224;
  background: #fff;
  font-size: 16px;
  font-weight: 800;
  writing-mode: vertical-rl;
  cursor: pointer;
  box-shadow: 0 4px 16px rgba(100, 80, 200, 0.12);
  transition: box-shadow 0.2s;

  &:hover {
    box-shadow: 0 6px 24px rgba(100, 80, 200, 0.2);
  }
}

.smart-qa-dialog {
  .qa-chat-area {
    max-height: 400px;
    overflow-y: auto;
    padding: 8px 0;
  }
  .qa-welcome {
    text-align: center;
    color: #909399;
    padding: 20px 0;
    p { margin-bottom: 12px; }
  }
  .qa-message {
    display: flex;
    margin-bottom: 12px;
    &.user { justify-content: flex-end; }
    &.assistant { justify-content: flex-start; }
  }
  .qa-bubble {
    max-width: 80%;
    padding: 10px 14px;
    border-radius: 12px;
    font-size: 14px;
    line-height: 1.6;
    white-space: pre-wrap;
    word-break: break-word;
  }
  .qa-message.user .qa-bubble {
    background: #7d8dff;
    color: #fff;
    border-bottom-right-radius: 4px;
  }
  .qa-message.assistant .qa-bubble {
    background: #f4f5f7;
    color: #303133;
    border-bottom-left-radius: 4px;
  }
  .qa-typing {
    color: #909399;
    font-style: italic;
  }
  .qa-input-bar {
    display: flex;
    gap: 8px;
    .el-input { flex: 1; }
  }
}
</style>
