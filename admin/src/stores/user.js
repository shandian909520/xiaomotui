import { defineStore } from 'pinia'
import { login, logout, getUserInfo } from '@/api/auth'
import { setToken, removeToken, getToken } from '@/utils/request'
import router from '@/router'

export const useUserStore = defineStore('user', {
  state: () => ({
    token: getToken() || '',
    user: JSON.parse(localStorage.getItem('user') || 'null'),
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

    /**
     * 登录
     * @param {Object} loginForm - 登录表单数据
     * @returns {Promise}
     */
    async login(loginForm) {
      try {
        const response = await login(loginForm)
        
        // Handle response format
        const resData = response.data || response
        const code = response.code !== undefined ? response.code : 200

        if (code === 200) {
          const { token, user } = resData
          
          if (token) this.setToken(token)
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
     * @returns {Promise}
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
     * 退出登录
     * @returns {Promise}
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
        removeToken()
      }
    }
  }
})
