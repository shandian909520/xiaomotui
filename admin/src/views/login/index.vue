<template>
  <div class="login-container">
    <div class="login-left"></div>
    <div class="login-right">
      <div class="login-box">
        <h2 class="login-title">登录</h2>
        <el-form :model="form" size="large" @submit.prevent>
          <el-form-item>
            <el-input v-model="form.username" placeholder="请输入账号" />
          </el-form-item>
          <el-form-item>
            <el-input v-model="form.password" placeholder="请输入密码" type="password" show-password @keyup.enter="login" />
          </el-form-item>
          <el-button class="login-btn" :loading="loading" @click="login">登 录</el-button>
        </el-form>
        <div class="login-footer">
          <span>还没有账号？</span>
          <a href="javascript:;">立即注册</a>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import { setToken } from '@/utils/request'
import { authApi } from '@/api/index'

const router = useRouter()
const route = useRoute()
const loading = ref(false)
const form = reactive({
  username: '',
  password: ''
})

const login = async () => {
  if (!form.username) {
    ElMessage.warning('请输入账号')
    return
  }
  if (!form.password) {
    ElMessage.warning('请输入密码')
    return
  }
  if (loading.value) return
  loading.value = true
  try {
    const data = await authApi.login({ username: form.username, password: form.password })
    const token = data?.token
    if (!token || typeof token !== 'string') {
      throw new Error('登录响应缺少有效的 token')
    }
    setToken(token)
    const userInfo = data.user || { username: form.username }
    localStorage.setItem('user', JSON.stringify(userInfo))
    localStorage.setItem('username', userInfo.username || form.username)
    ElMessage.success('登录成功')
    router.push(route.query.redirect || '/home')
  } catch (error) {
    ElMessage.error(error.message || '登录失败，请检查账号密码')
  } finally {
    loading.value = false
  }
}
</script>

<style lang="scss" scoped>
.login-container {
  width: 100%;
  height: 100vh;
  display: flex;
  background: url('https://pyp-xmt.oss-cn-beijing.aliyuncs.com/1/0/upload/20260114/png/1744.png') center / cover no-repeat;
}

.login-left {
  flex: 1;
}

.login-right {
  width: 420px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #fff;
}

.login-box {
  width: 300px;
}

.login-title {
  margin: 0 0 30px;
  font-size: 24px;
  font-weight: 700;
  color: #222;
}

:deep(.el-input__wrapper) {
  height: 38px;
  border-radius: 4px;
}

.login-btn {
  width: 100%;
  height: 50px;
  margin-top: 10px;
  border: none;
  border-radius: 4px;
  background: linear-gradient(134deg, #be5cff 0%, #8582ff 100%);
  color: #fff;
  font-size: 16px;
  font-weight: 700;
  letter-spacing: 4px;

  &:hover {
    opacity: 0.9;
  }
}

.login-footer {
  margin-top: 16px;
  text-align: center;
  font-size: 13px;
  color: #999;

  a {
    color: #834eff;
    text-decoration: none;

    &:hover {
      text-decoration: underline;
    }
  }
}
</style>
