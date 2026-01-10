import { defineStore } from 'pinia'
import { login, logout, getUserInfo } from '@/api/auth'
import { setToken, removeToken, getToken } from '@/utils/request'
import router from '@/router'

export const useUserStore = defineStore('user', {
  state: () => ({
    token: getToken() || '',
    user: null,
    roles: []
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
    userRole: (state) => state.user?.role || ''
  },

  actions: {
    /**
     * 登录
     * @param {Object} loginForm - 登录表单数据
     * @returns {Promise}
     */
    async login(loginForm) {
      try {
        const response = await login(loginForm)
        
        if (response.code === 200 || response.data) {
          const { token, user } = response.data || response
          
          // 保存token
          this.token = token
          setToken(token)
          
          // 保存用户信息
          this.user = user
          localStorage.setItem('user', JSON.stringify(user))
          
          // 保存用户角色
          if (user.role) {
            this.roles = [user.role]
          }
          
          return Promise.resolve(response)
        } else {
          return Promise.reject(new Error(response.msg || '登录失败'))
        }
      } catch (error) {
        return Promise.reject(error)
      }
    },

    /**
     * 获取用户信息
     * @returns {Promise}
     */
    async getUserInfo() {
      try {
        const response = await getUserInfo()
        
        if (response.code === 200 || response.data) {
          const user = response.data || response
          
          this.user = user
          localStorage.setItem('user', JSON.stringify(user))
          
          if (user.role) {
            this.roles = [user.role]
          }
          
          return Promise.resolve(user)
        } else {
          return Promise.reject(new Error(response.msg || '获取用户信息失败'))
        }
      } catch (error) {
        return Promise.reject(error)
      }
    },

    /**
     * 退出登录
     * @returns {Promise}
     */
    async logout() {
      try {
        await logout()
      } catch (error) {
        console.error('退出登录接口调用失败:', error)
      } finally {
        // 无论接口是否成功，都清除本地数据
        this.token = ''
        this.user = null
        this.roles = []
        removeToken()
        router.push('/login')
      }
    },

    /**
     * 重置状态
     */
    resetState() {
      this.token = ''
      this.user = null
      this.roles = []
      removeToken()
    },

    /**
     * 从localStorage恢复用户信息
     */
    restoreUser() {
      const userStr = localStorage.getItem('user')
      if (userStr) {
        try {
          this.user = JSON.parse(userStr)
          if (this.user.role) {
            this.roles = [this.user.role]
          }
        } catch (error) {
          console.error('解析用户信息失败:', error)
        }
      }
    }
  }
})
