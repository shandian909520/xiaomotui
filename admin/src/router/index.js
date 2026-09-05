import { createRouter, createWebHashHistory } from 'vue-router'
import Layout from '@/layout/index.vue'
import { useUserStore } from '@/stores/user'

const Placeholder = () => import('@/views/common/placeholder.vue')

export const constantRoutes = [
  {
    path: '/login',
    component: () => import('@/views/login/index.vue'),
    hidden: true,
    meta: { title: '登录', requiresAuth: false }
  },
  {
    path: '/',
    component: Layout,
    redirect: '/home',
    children: [
      {
        path: 'home',
        name: 'Home',
        component: () => import('@/views/dashboard/index.vue'),
        meta: { title: '首页', icon: 'House' }
      }
    ]
  },
  {
    path: '/stores',
    component: Layout,
    children: [
      {
        path: '',
        name: 'Stores',
        component: () => import('@/views/stores/index.vue'),
        meta: { title: '门店列表', icon: 'Shop', permission: 'store/*' }
      }
    ]
  },
  {
    path: '/materials',
    component: Layout,
    children: [
      {
        path: '',
        name: 'Materials',
        component: () => import('@/views/materials/index.vue'),
        meta: { title: '素材管理', icon: 'Folder', permission: 'material/*' }
      }
    ]
  },
  {
    path: '/video',
    component: Layout,
    meta: { title: '视频创作', icon: 'Collection' },
    children: [
      { path: 'edit', name: 'VideoEdit', component: () => import('@/views/video/EditWorkbench.vue'), meta: { title: '剪辑工作台', permission: 'video/*' } },
      { path: 'project', name: 'VideoProject', component: () => import('@/views/video/project.vue'), meta: { title: '剪辑工程', permission: 'video/*' } }
    ]
  },
  {
    path: '/ai',
    component: Layout,
    meta: { title: 'AI实验室', icon: 'MagicStick' },
    children: [
      { path: 'staff', name: 'AiStaff', component: () => import('@/views/ai/StaffRoles.vue'), meta: { title: '智能员工', permission: 'ai/*' } }
    ]
  },
  {
    path: '/library',
    component: Layout,
    meta: { title: 'AI成品库', icon: 'VideoCamera' },
    children: [
      { path: 'videos', name: 'VideoLibrary', component: () => import('@/views/library/VideoLibrary.vue'), meta: { title: '视频库', permission: 'library/*' } },
      { path: 'images', name: 'ImageLibrary', component: () => import('@/views/library/GraphicLibrary.vue'), meta: { title: '图文库', permission: 'library/*' } },
      { path: 'topics', name: 'TopicLibrary', component: () => import('@/views/library/TopicLibrary.vue'), meta: { title: '话题库', permission: 'library/*' } }
    ]
  },
  {
    path: '/publish',
    component: Layout,
    meta: { title: '发布管理', icon: 'Promotion' },
    children: [
      { path: '', name: 'PublishManage', component: () => import('@/views/publish/index.vue'), meta: { title: '发布任务', permission: 'publish/*' } },
      { path: 'accounts', name: 'PublishAccounts', component: () => import('@/views/publish/accounts.vue'), meta: { title: '平台账号', permission: 'publish/*' } }
    ]
  },
  {
    path: '/activity',
    component: Layout,
    meta: { title: '活动管理', icon: 'Present' },
    children: [
      { path: 'scenes', name: 'Scenes', component: () => import('@/views/activity/SceneConfigMatrix.vue'), meta: { title: '场景配置', permission: 'activity/*' } },
      { path: 'redpackets', name: 'Redpackets', component: () => import('@/views/activity/redpackets.vue'), meta: { title: '发红包', permission: 'activity/*' } },
      { path: 'group-buy', name: 'GroupBuy', component: () => import('@/views/activity/group-buy.vue'), meta: { title: '拼团管理', permission: 'activity/*' } }
    ]
  },
  {
    path: '/groupbuy',
    component: Layout,
    meta: { title: '团购商品', icon: 'Goods' },
    children: [
      { path: 'item-list', name: 'GroupbuyItemList', component: () => import('@/views/groupbuy/ItemList.vue'), meta: { title: '团购商品', permission: 'activity/*' } }
    ]
  },
  {
    path: '/lottery',
    component: Layout,
    meta: { title: '抽奖活动', icon: 'Trophy' },
    children: [
      { path: 'activity-list', name: 'LotteryActivityList', component: () => import('@/views/lottery/ActivityList.vue'), meta: { title: '活动管理', permission: 'activity/*' } },
      { path: 'prize-list', name: 'LotteryPrizeList', component: () => import('@/views/lottery/PrizeList.vue'), meta: { title: '奖项管理', permission: 'activity/*' }, hidden: true },
      { path: 'record-list', name: 'LotteryRecordList', component: () => import('@/views/lottery/RecordList.vue'), meta: { title: '中奖记录', permission: 'activity/*' } }
    ]
  },
  {
    path: '/copywriting',
    component: Layout,
    meta: { title: '文案池', icon: 'ChatLineRound' },
    children: [
      { path: 'pool-list', name: 'CopywritingPoolList', component: () => import('@/views/copywriting/PoolList.vue'), meta: { title: '文案池管理', permission: 'activity/*' } }
    ]
  },
  {
    path: '/review',
    component: Layout,
    meta: { title: '点评配置', icon: 'Star' },
    children: [
      { path: 'config', name: 'ReviewConfig', component: () => import('@/views/review/ConfigList.vue'), meta: { title: '点评配置', permission: 'activity/*' } }
    ]
  },
  {
    path: '/monitor',
    component: Layout,
    meta: { title: '数据监控', icon: 'DataLine' },
    children: [
      { path: 'topics', name: 'TopicMonitor', component: () => import('@/views/monitor/topics.vue'), meta: { title: '话题监控', permission: 'monitor/*' } }
    ]
  },
  {
    path: '/design',
    component: Layout,
    meta: { title: '运营设计', icon: 'Brush' },
    children: [
      { path: 'materials', name: 'MaterialDesign', component: () => import('@/views/design/materials.vue'), meta: { title: '物料设计', permission: 'design/*' } }
    ]
  },
  {
    path: '/tasks',
    component: Layout,
    meta: { title: '任务中心', icon: 'List' },
    children: [
      { path: '', name: 'Tasks', component: () => import('@/views/tasks/index.vue'), meta: { title: '任务中心', permission: 'task/*' } }
    ]
  },
  {
    path: '/task',
    component: Layout,
    meta: { title: '碰一碰任务', icon: 'Pointer' },
    children: [
      { path: 'bundles', name: 'TaskBundles', component: () => import('@/views/task/BundleList.vue'), meta: { title: '任务包管理', permission: 'task/*' } },
      { path: 'edit/:id?', name: 'TaskBundleEdit', component: () => import('@/views/task/BundleEdit.vue'), meta: { title: '任务包编辑', permission: 'task/*' }, hidden: true },
      { path: 'proofs', name: 'TaskProofAudit', component: () => import('@/views/task/ProofAudit.vue'), meta: { title: '凭证审核', permission: 'task/*' } },
      { path: 'instances', name: 'TaskInstances', component: () => import('@/views/task/UserTaskList.vue'), meta: { title: '用户任务', permission: 'task/*' } }
    ]
  },
  {
    path: '/system',
    component: Layout,
    meta: { title: '系统管理', icon: 'Setting' },
    children: [
      { path: 'users', name: 'SystemUsers', component: () => import('@/views/system/users.vue'), meta: { title: '用户管理', permission: 'system/*' } },
      { path: 'settings', name: 'SystemSettings', component: () => import('@/views/system/settings.vue'), meta: { title: '系统设置', permission: 'system/*' } },
      { path: 'logs', name: 'SystemLogs', component: () => import('@/views/system/logs.vue'), meta: { title: '操作日志', permission: 'system/*' } },
      { path: 'blacklist', name: 'IpBlacklist', component: () => import('@/views/system/blacklist.vue'), meta: { title: 'IP黑名单', permission: 'system/*' } }
    ]
  },
{
  path: '/device',
  component: Layout,
  meta: { title: '设备管理', icon: 'Monitor' },
  children: [
    { path: '', name: 'Device', component: () => import('@/views/device/index.vue'), meta: { title: '设备列表', permission: 'device/*' } },
    { path: 'alerts', name: 'DeviceAlerts', component: () => import('@/views/device/alerts.vue'), meta: { title: '设备告警', permission: 'device/*' } },
    { path: 'alert-rules', name: 'AlertRules', component: () => import('@/views/device/alert-rules.vue'), meta: { title: '告警规则', permission: 'device/*' } },
    { path: 'alert-monitor', name: 'AlertMonitor', component: () => import('@/views/device/alert-monitor.vue'), meta: { title: '告警监控', permission: 'device/*' } },
    { path: 'nfc-config/:id?', name: 'DeviceNfcConfig', component: () => import('@/views/device/NfcConfig.vue'), meta: { title: '设备配置', permission: 'device/*' }, hidden: true }
  ]
},
  {
    path: '/nfc',
    component: Layout,
    meta: { title: 'NFC管理', icon: 'Connection' },
    children: [
      { path: 'triggers', name: 'NfcTriggers', component: () => import('@/views/nfc/triggers.vue'), meta: { title: '触发记录', permission: 'nfc/*' } }
    ]
  },
  {
    path: '/coupon',
    component: Layout,
    meta: { title: '优惠券', icon: 'Ticket' },
    children: [
      { path: '', name: 'Coupon', component: () => import('@/views/coupon/index.vue'), meta: { title: '优惠券管理', permission: 'coupon/*' } },
      { path: 'users', name: 'CouponUsers', component: () => import('@/views/coupon/users.vue'), meta: { title: '使用记录', permission: 'coupon/*' } }
    ]
  },
  {
    path: '/promo',
    component: Layout,
    meta: { title: '推广管理', icon: 'Promotion' },
    children: [
      { path: 'materials', name: 'PromoMaterials', component: () => import('@/views/promo-material/index.vue'), meta: { title: '推广物料', permission: 'promo/*' } },
      { path: 'templates', name: 'PromoTemplates', component: () => import('@/views/promo-template/index.vue'), meta: { title: '推广模板', permission: 'promo/*' } },
      { path: 'variants', name: 'PromoVariants', component: () => import('@/views/promo-variant/index.vue'), meta: { title: '推广变体', permission: 'promo/*' } },
      { path: 'campaigns', name: 'PromoCampaigns', component: () => import('@/views/promo-campaign/index.vue'), meta: { title: '推广活动', permission: 'promo/*' } },
      { path: 'campaigns/:id', name: 'PromoCampaignDetail', component: () => import('@/views/promo-campaign/detail.vue'), meta: { title: '活动详情', permission: 'promo/*' }, hidden: true },
      { path: 'stats', name: 'PromoStats', component: () => import('@/views/promo-stats/index.vue'), meta: { title: '推广统计', permission: 'promo/*' } }
    ]
  },
  {
    path: '/chain',
    component: Layout,
    meta: { title: '连锁版管理', icon: 'UserFilled', permission: 'chain/*' },
    children: [
      { path: 'employees', name: 'ChainEmployees', component: () => import('@/views/chain/EmployeeManagement.vue'), meta: { title: '员工管理', permission: 'chain/*' } }
    ]
  },
  {
    path: '/statistics',
    component: Layout,
    meta: { title: '数据统计', icon: 'DataAnalysis' },
    children: [
      { path: '', name: 'Statistics', component: () => import('@/views/statistics/index.vue'), meta: { title: '数据统计', permission: 'statistics/*' } }
    ]
  },
  {
    path: '/recommendation',
    component: Layout,
    meta: { title: '推荐引擎', icon: 'MagicStick' },
    children: [
      { path: '', name: 'RecommendationIndex', component: () => import('@/views/recommendation/index.vue'), meta: { title: '推荐总览', permission: 'recommendation/*' } },
      { path: 'profiles', name: 'RecommendationProfiles', component: () => import('@/views/recommendation/profiles.vue'), meta: { title: '用户画像', permission: 'recommendation/*' } },
      { path: 'experiments', name: 'RecommendationExperiments', component: () => import('@/views/recommendation/experiments.vue'), meta: { title: 'A/B测试', permission: 'recommendation/*' } },
      { path: 'settings', name: 'RecommendationSettings', component: () => import('@/views/recommendation/settings.vue'), meta: { title: '算法配置', permission: 'recommendation/*' } }
    ]
  },
  {
    path: '/video/library',
    component: Layout,
    meta: { title: '视频模板库', icon: 'Film' },
    children: [
      { path: '', name: 'VideoLibraryPage', component: () => import('@/views/video-library/index.vue'), meta: { title: '视频模板库', permission: 'video/*' } }
    ]
  },
  {
    path: '/merchant',
    component: Layout,
    meta: { title: '商户管理', icon: 'Stamp' },
    children: [
      { path: 'audit', name: 'MerchantAudit', component: () => import('@/views/merchant/audit.vue'), meta: { title: '商户审核', permission: 'merchant/*' } }
    ]
  },
  {
    path: '/account',
    component: Layout,
    hidden: true,
    children: [
      { path: 'change-password', name: 'ChangePassword', component: () => import('@/views/account/ChangePassword.vue'), meta: { title: '修改密码' } }
    ]
  },
  {
    path: '/content',
    component: Layout,
    meta: { title: '内容管理', icon: 'Document' },
    children: [
      { path: 'tasks', name: 'ContentTasks', component: () => import('@/views/content/tasks/index.vue'), meta: { title: '发布任务', permission: 'content/*' } },
      { path: 'templates', name: 'ContentTemplates', component: () => import('@/views/content/templates/index.vue'), meta: { title: '内容模板', permission: 'content/*' } },
      { path: 'creation', name: 'ContentCreation', component: () => import('@/views/content/creation/index.vue'), meta: { title: 'AI内容生成', permission: 'content/*' } },
      { path: 'audit', name: 'ContentAudit', component: () => import('@/views/content/audit.vue'), meta: { title: '内容审核', permission: 'content/*' } }
    ]
  },
  {
    path: '/notifications',
    component: Layout,
    hidden: true,
    children: [
      { path: '', name: 'Notifications', component: () => import('@/views/notifications/index.vue'), meta: { title: '功能通知' } }
    ]
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: '/home',
    hidden: true
  }
]

const router = createRouter({
  history: createWebHashHistory(),
  routes: constantRoutes
})

// 权限白名单：这些路由不需要权限检查
const permissionWhiteList = ['/login', '/account/change-password', '/notifications']

let permissionsFetched = false

router.beforeEach(async (to) => {
  document.title = to.meta.title ? `${to.meta.title} - 小魔推` : '小魔推'

  const token = localStorage.getItem('token')
  if (to.meta.requiresAuth !== false && !token) {
    return { path: '/login' }
  }

  // 无权限要求或白名单路由，直接放行
  if (!to.meta.permission || permissionWhiteList.some(p => to.path.startsWith(p))) {
    return
  }

  // 未登录直接放行（后续会被 token 检查拦截）
  if (!token) return

  // 首次进入时获取权限
  const userStore = useUserStore()
  if (!permissionsFetched) {
    try {
      await userStore.fetchPermissions()
      permissionsFetched = true
    } catch {
      return { path: '/login' }
    }
  }

  // 检查权限
  const requiredPermission = to.meta.permission
  if (requiredPermission && !userStore.hasPermission(requiredPermission)) {
    return { path: '/home' }
  }
})

router.afterEach(() => {
  window.scrollTo(0, 0)
})

export default router
