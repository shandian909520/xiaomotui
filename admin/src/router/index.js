import { createRouter, createWebHistory } from 'vue-router'
import Layout from '@/layout/index.vue'
import { getToken } from '@/utils/request'

// 路由配置
export const constantRoutes = [
  {
    path: '/login',
    component: () => import('@/views/login/index.vue'),
    hidden: true,
    meta: {
      title: '登录',
      requiresAuth: false
    }
  },
  {
    path: '/',
    component: Layout,
    redirect: '/dashboard',
    children: [
      {
        path: 'dashboard',
        name: 'Dashboard',
        component: () => import('@/views/dashboard/index.vue'),
        meta: { title: '仪表盘', icon: 'dashboard', requiresAuth: true }
      }
    ]
  },
  {
    path: '/devices',
    component: Layout,
    children: [
      {
        path: '',
        name: 'DeviceManage',
        component: () => import('@/views/device/index.vue'),
        meta: { title: '设备管理', icon: 'monitor', requiresAuth: true }
      }
    ]
  },
  {
    path: '/nfc',
    component: Layout,
    meta: { title: 'NFC管理', icon: 'postcard' },
    children: [
      {
        path: 'triggers',
        name: 'NfcTriggers',
        component: () => import('@/views/nfc/triggers.vue'),
        meta: { title: '触发记录', requiresAuth: true }
      }
    ]
  },
  {
    path: '/content',
    component: Layout,
    meta: { title: '内容管理', icon: 'document' },
    children: [
      {
        path: 'creation',
        name: 'AiCreation',
        component: () => import('@/views/content/creation/index.vue'),
        meta: { title: 'AI创作', requiresAuth: true }
      },
      {
        path: 'tasks',
        name: 'ContentTasks',
        component: () => import('@/views/content/tasks/index.vue'),
        meta: { title: '生成任务', requiresAuth: true }
      },
      {
        path: 'templates',
        name: 'ContentTemplates',
        component: () => import('@/views/content/templates/index.vue'),
        meta: { title: '模板管理', requiresAuth: true }
      }
    ]
  },
  {
    path: '/video-library',
    component: Layout,
    children: [
      {
        path: '',
        name: 'VideoLibrary',
        component: () => import('@/views/video-library/index.vue'),
        meta: { title: '视频库', icon: 'video-play', requiresAuth: true }
      }
    ]
  },
  {
    path: '/promo',
    component: Layout,
    meta: { title: '推广管理', icon: 'promotion' },
    children: [
      {
        path: 'material',
        name: 'PromoMaterial',
        component: () => import('@/views/promo-material/index.vue'),
        meta: { title: '素材库', requiresAuth: true }
      },
      {
        path: 'template',
        name: 'PromoTemplate',
        component: () => import('@/views/promo-template/index.vue'),
        meta: { title: '视频模板', requiresAuth: true }
      },
      {
        path: 'variant',
        name: 'PromoVariant',
        component: () => import('@/views/promo-variant/index.vue'),
        meta: { title: '视频变体', requiresAuth: true }
      },
      {
        path: 'campaign',
        name: 'PromoCampaign',
        component: () => import('@/views/promo-campaign/index.vue'),
        meta: { title: '推广活动', requiresAuth: true }
      },
      {
        path: 'campaign/detail/:id',
        name: 'PromoCampaignDetail',
        component: () => import('@/views/promo-campaign/detail.vue'),
        meta: { title: '活动详情', requiresAuth: true },
        hidden: true
      },
      {
        path: 'stats',
        name: 'PromoStats',
        component: () => import('@/views/promo-stats/index.vue'),
        meta: { title: '推广统计', requiresAuth: true }
      }
    ]
  },
  {
    path: '/coupon',
    component: Layout,
    meta: { title: '券码管理', icon: 'ticket' },
    children: [
      {
        path: 'list',
        name: 'CouponList',
        component: () => import('@/views/coupon/index.vue'),
        meta: { title: '券码列表', requiresAuth: true }
      },
      {
        path: 'users',
        name: 'CouponUsers',
        component: () => import('@/views/coupon/users.vue'),
        meta: { title: '用户领取', requiresAuth: true }
      }
    ]
  },
  {
    path: '/merchant',
    component: Layout,
    children: [
      {
        path: 'list',
        name: 'MerchantList',
        component: () => import('@/views/merchant/audit.vue'),
        meta: { title: '商户列表', icon: 'shop', requiresAuth: true }
      }
    ]
  },
  {
    path: '/statistics',
    component: Layout,
    children: [
      {
        path: '',
        name: 'Statistics',
        component: () => import('@/views/statistics/index.vue'),
        meta: { title: '数据统计', icon: 'data-line', requiresAuth: true }
      }
    ]
  },
  {
    path: '/system',
    component: Layout,
    meta: { title: '系统管理', icon: 'setting' },
    children: [
      {
        path: 'users',
        name: 'SystemUsers',
        component: () => import('@/views/system/users.vue'),
        meta: { title: '用户管理', requiresAuth: true }
      },
      {
        path: 'settings',
        name: 'SystemSettings',
        component: () => import('@/views/system/settings.vue'),
        meta: { title: '系统设置', requiresAuth: true }
      },
      {
        path: 'logs',
        name: 'OperationLogs',
        component: () => import('@/views/system/logs.vue'),
        meta: { title: '操作日志', requiresAuth: true }
      }
    ]
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: '/dashboard',
    hidden: true
  }
]

// 创建路由实例
const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: constantRoutes
})

// 全局前置守卫
router.beforeEach((to, from, next) => {
  // 设置页面标题
  document.title = to.meta.title ? `${to.meta.title} - 小魔推管理后台` : '小魔推管理后台'

  // 获取token
  const token = getToken()

  // 如果路由需要认证
  if (to.meta.requiresAuth !== false) { // 默认都需要认证，除非显式设为false
    if (token) {
      // 已登录，允许访问
      next()
    } else {
      // 未登录，跳转到登录页
      next({
        path: '/login',
        query: { redirect: to.fullPath } // 保存原始路径，登录后跳转
      })
    }
  } else {
    // 不需要认证的路由
    if (to.path === '/login' && token) {
      // 已登录，访问登录页时跳转到首页
      next({ path: '/dashboard' })
    } else {
      next()
    }
  }
})

// 全局后置钩子
router.afterEach(() => {
  window.scrollTo(0, 0)
})

export default router
