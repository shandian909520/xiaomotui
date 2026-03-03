<template>
  <div class="login-container">
    <div class="login-card">
      <div class="login-header">
        <h2>小魔推管理后台</h2>
        <p>NFC智能营销管理系统</p>
      </div>

      <el-tabs v-model="loginType" class="login-tabs" stretch>
        <el-tab-pane label="账号登录" name="account">
          <el-form ref="accountFormRef" :model="loginForm" :rules="accountRules" size="large">
            <el-form-item prop="username">
              <el-input 
                v-model="loginForm.username" 
                placeholder="用户名" 
                prefix-icon="User"
              />
            </el-form-item>
            <el-form-item prop="password">
              <el-input 
                v-model="loginForm.password" 
                placeholder="密码" 
                prefix-icon="Lock"
                type="password" 
                show-password
                @keyup.enter="handleLogin"
              />
            </el-form-item>
          </el-form>
        </el-tab-pane>

        <el-tab-pane label="手机登录" name="mobile">
          <el-form ref="mobileFormRef" :model="loginForm" :rules="mobileRules" size="large">
            <el-form-item prop="phone">
              <el-input 
                v-model="loginForm.phone" 
                placeholder="手机号" 
                prefix-icon="Iphone"
              />
            </el-form-item>
            <el-form-item prop="code">
              <div style="display: flex; gap: 10px;">
                <el-input 
                  v-model="loginForm.code" 
                  placeholder="验证码" 
                  prefix-icon="Message"
                  style="flex: 1;"
                  @keyup.enter="handleLogin"
                />
                <el-button @click="handleSendCode" :disabled="codeTimer > 0">
                  {{ codeTimer > 0 ? `${codeTimer}秒后重试` : '获取验证码' }}
                </el-button>
              </div>
            </el-form-item>
          </el-form>
        </el-tab-pane>
      </el-tabs>

      <div style="margin-bottom: 20px;">
        <el-checkbox v-model="rememberMe">记住我</el-checkbox>
      </div>
      
      <el-button type="primary" :loading="loading" @click="handleLogin" style="width:100%" size="large">登录</el-button>
    </div>
  </div>
</template>
<script setup>
import { ref, reactive } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import { useUserStore } from '@/stores/user'
import { authApi } from '@/api'
import { setToken } from '@/utils/request'
import { User, Lock, Iphone, Message } from '@element-plus/icons-vue'

const router = useRouter()
const route = useRoute()
const userStore = useUserStore()

const loginType = ref('account')
const accountFormRef = ref(null)
const mobileFormRef = ref(null)

const loginForm = reactive({ 
  username: '', 
  password: '',
  phone: '', 
  code: '' 
})
const rememberMe = ref(false)
const loading = ref(false)
const codeTimer = ref(0)

const accountRules = {
  username: [{ required: true, message: '请输入用户名', trigger: 'blur' }],
  password: [{ required: true, message: '请输入密码', trigger: 'blur' }]
}

const mobileRules = {
  phone: [
    { required: true, message: '请输入手机号', trigger: 'blur' },
    { pattern: /^1[3-9]\d{9}$/, message: '请输入正确的手机号', trigger: 'blur' }
  ],
  code: [
    { required: true, message: '请输入验证码', trigger: 'blur' },
    { len: 6, message: '验证码为6位数字', trigger: 'blur' }
  ]
}

const handleLogin = async () => {
  const formRef = loginType.value === 'account' ? accountFormRef.value : mobileFormRef.value
  if (!formRef) return

  try {
    await formRef.validate()
    loading.value = true
    
    const data = {}
    if (loginType.value === 'account') {
      data.username = loginForm.username
      data.password = loginForm.password
    } else {
      data.phone = loginForm.phone
      data.code = loginForm.code
    }

    console.log('Calling authApi.login with data:', data)
    const res = await authApi.login(data)
    console.log('Login Response Object:', JSON.stringify(res))
    console.log('Token to save:', res?.token)
    
    // Request interceptor returns res.data directly if successful
     // and rejects if code !== 200
     if (res && res.token) {
         // Update store state directly (Pinia allows this)
         userStore.token = res.token
         userStore.user = res.user
         
         // Persist token to localStorage
         console.log('Saving token to localStorage:', res.token)
         setToken(res.token)
         const savedToken = localStorage.getItem('token') // Verify save
         console.log('Token in localStorage after save:', savedToken)

         if (res.user) {
             localStorage.setItem('user', JSON.stringify(res.user))
         }

         ElMessage.success('登录成功')
         router.push(route.query.redirect || '/dashboard')
     } else {
         console.error('Token missing in response')
         throw new Error('登录失败：未获取到Token')
     }
  } catch (error) {
    ElMessage.error(error.message || '登录失败')
  } finally {
    loading.value = false
  }
}

const handleSendCode = async () => {
  if (!loginForm.phone) {
    ElMessage.warning('请先输入手机号')
    return
  }

  if (!/^1[3-9]\d{9}$/.test(loginForm.phone)) {
    ElMessage.warning('请输入正确的手机号')
    return
  }

  try {
    await authApi.sendCode({ phone: loginForm.phone })
    ElMessage.success('验证码已发送')

    codeTimer.value = 60
    const timer = setInterval(() => {
      codeTimer.value--
      if (codeTimer.value <= 0) {
        clearInterval(timer)
      }
    }, 1000)
  } catch (error) {
    ElMessage.error(error.message || '发送验证码失败')
  }
}
</script>
<style lang="scss" scoped>
.login-container {
  width: 100%;
  min-height: 100vh;
  display: flex;
  justify-content: center;
  align-items: center;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
.login-card {
  width: 400px;
  background: white;
  border-radius: 10px;
  padding: 40px;
  box-shadow: 0 10px 40px rgba(0,0,0,0.3);
}
.login-header {
  text-align: center;
  margin-bottom: 30px;
  h2 { font-size: 24px; margin: 0 0 10px; }
  p { font-size: 14px; color: #666; margin: 0; }
}
</style>
