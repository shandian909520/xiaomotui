import { createRouter, createWebHistory } from 'vue-router'
import { getToken } from '@/utils/request'

// 路由配置
const routes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/login/index.vue'),
    meta: {
      title: '登录',
      requiresAuth: false
    }
  },
  {
    path: '/',
    redirect: '/dashboard'
  },
  {
    path: '/dashboard',
    name: 'Dashboard',
    component: () => import('@/views/dashboard/index.vue'),
    meta: {
      title: '仪表盘',
      requiresAuth: true
    }
  },
  {
    path: '/devices',
    name: 'DeviceManage',
    component: () => import('@/views/device/index.vue'),
    meta: {
      title: '设备管理',
      icon: 'Monitor',
      requiresAuth: true
    }
  },
  {
    path: '/statistics',
    name: 'Statistics',
    component: () => import('@/views/statistics/index.vue'),
    meta: {
      title: '数据统计',
      icon: 'DataAnalysis',
      requiresAuth: true
    }
  }
]

// 创建路由实例
const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes
})

// 全局前置守卫
router.beforeEach((to, from, next) => {
  // 设置页面标题
  document.title = to.meta.title ? `${to.meta.title} - 小魔推管理后台` : '小魔推管理后台'

  // 获取token
  const token = getToken()

  // 如果路由需要认证
  if (to.meta.requiresAuth) {
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
  // 可以在这里做一些页面切换后的操作，比如滚动到顶部
  window.scrollTo(0, 0)
})

export default router
