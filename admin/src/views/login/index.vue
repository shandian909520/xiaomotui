<template>
  <div class="login-container">
    <div class="login-card">
      <div class="login-header">
        <h2>小魔推管理后台</h2>
        <p>NFC智能营销管理系统</p>
        <div style="margin-top: 10px; font-size: 12px; color: #666;">
          测试账号：13800138000，验证码：123456
        </div>
      </div>
      <el-form ref="loginFormRef" :model="loginForm" :rules="loginRules">
        <el-form-item prop="phone">
          <el-input v-model="loginForm.phone" placeholder="手机号" size="large" />
        </el-form-item>
        <el-form-item prop="code">
          <div style="display: flex; gap: 10px;">
            <el-input v-model="loginForm.code" placeholder="验证码" size="large" style="flex: 1;" />
            <el-button @click="handleSendCode" :disabled="codeTimer > 0" size="large">
              {{ codeTimer > 0 ? `${codeTimer}秒后重试` : '获取验证码' }}
            </el-button>
          </div>
        </el-form-item>
        <el-form-item>
          <el-checkbox v-model="rememberMe">记住我</el-checkbox>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :loading="loading" @click="handleLogin" style="width:100%">登录</el-button>
        </el-form-item>
      </el-form>
    </div>
  </div>
</template>
<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import { useUserStore } from '@/store/modules/user'
import { authApi } from '@/api'
const router = useRouter()
const route = useRoute()
const userStore = useUserStore()
const loginFormRef = ref(null)
const loginForm = reactive({ phone: '', code: '' })
const rememberMe = ref(false)
const loading = ref(false)
const codeTimer = ref(0)
const loginRules = {
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
  if (!loginFormRef.value) return
  try {
    await loginFormRef.value.validate()
    loading.value = true
    // 使用手机号登录
    await authApi.login({ phone: loginForm.phone, code: loginForm.code })
    ElMessage.success('登录成功')
    router.push(route.query.redirect || '/dashboard')
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
    // 发送验证码
    await authApi.sendCode({ phone: loginForm.phone })
    ElMessage.success('验证码已发送')

    // 开始倒计时
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

onMounted(() => {
  if (userStore.isLoggedIn) router.push('/dashboard')
})
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
