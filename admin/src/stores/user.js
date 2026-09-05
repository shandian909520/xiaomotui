import { defineStore } from 'pinia'
import { login, logout, getUserInfo, getUserPermissions } from '@/api/auth'
import { setToken, removeToken, getToken } from '@/utils/request'
import router from '@/router'

export const useUserStore = defineStore('user', {
  state: () => ({
    token: getToken() || '',
    user: JSON.parse(localStorage.getItem('user') || 'null'),
    roles: [],
    permissions: JSON.parse(localStorage.getItem('user_permissions') || '[]')
  }),

  getters: {
    // 是否已登录
    isLoggedIn: (state) => !!state.token,

    // 用户名
    username: (state) => state.user?.username || '',

    // 用户昵称
    nickname: (state) => state.user?.nickname || '',

    // 用户头像
    avatar: (state) => state.user?.avatar || '',

    // 用户角色
    userRole: (state) => state.user?.role || '',

    // 是否有指定权限
    hasPermission: (state) => (permission) => {
      if (state.user?.role === 'admin') return true
      if (state.permissions.includes('*')) return true
      if (!state.permissions || state.permissions.length === 0) return false
      return state.permissions.some(p => {
        if (p === permission) return true
        if (p.endsWith('/*') && permission.startsWith(p.slice(0, -1))) return true
        return false
      })
    },

    // 是否是连锁版
    isChainVersion: (state) => state.user?.version === 'chain'
  },

  actions: {
    setToken(token) {
        this.token = token
        setToken(token)
    },

    setUserInfo(user) {
        this.user = user
        localStorage.setItem('user', JSON.stringify(user))
        if (user && user.role) {
            this.roles = [user.role]
        }
    },

    setPermissions(permissions) {
      this.permissions = permissions || []
      localStorage.setItem('user_permissions', JSON.stringify(this.permissions))
    },

    /**
     * 登录
     */
    async login(loginForm) {
      try {
        const response = await login(loginForm)

        const resData = response.data || response
        const code = response.code !== undefined ? response.code : 200

        if (code === 200) {
          const { token, user } = resData

          if (token && typeof token === 'string') this.setToken(token)
          if (user) this.setUserInfo(user)

          return Promise.resolve(response)
        } else {
          return Promise.reject(new Error(response.msg || response.message || '登录失败'))
        }
      } catch (error) {
        return Promise.reject(error)
      }
    },

    /**
     * 获取用户信息
     */
    async getUserInfo() {
      try {
        const response = await getUserInfo()
        const resData = response.data || response
        const code = response.code !== undefined ? response.code : 200

        if (code === 200) {
          const user = resData
          this.setUserInfo(user)
          return Promise.resolve(user)
        } else {
          return Promise.reject(new Error(response.msg || '获取用户信息失败'))
        }
      } catch (error) {
        return Promise.reject(error)
      }
    },

    /**
     * 获取用户权限
     */
    async fetchPermissions() {
      try {
        const response = await getUserPermissions()
        const resData = response.data || response
        const permissions = Array.isArray(resData) ? resData : (resData?.permissions || [])
        this.setPermissions(permissions)
        return permissions
      } catch (error) {
        console.error('获取权限失败:', error)
        this.setPermissions([])
        return []
      }
    },

    /**
     * 退出登录
     */
    async logout() {
      try {
        await logout()
      } catch (error) {
        console.error('退出登录接口调用失败:', error)
      } finally {
        this.token = ''
        this.user = null
        this.roles = []
        this.permissions = []
        removeToken()
        localStorage.removeItem('user_permissions')
      }
    }
  }
})
