<template>
  <div class="change-password-container">
    <el-card class="form-card">
      <template #header>
        <span class="card-title">修改密码</span>
      </template>

      <el-form
        ref="formRef"
        :model="form"
        :rules="rules"
        label-width="120px"
        class="password-form"
      >
        <el-form-item label="商家名称">
          <el-input :model-value="merchantName" disabled />
        </el-form-item>

        <el-form-item label="登录账号">
          <el-input :model-value="loginAccount" disabled />
        </el-form-item>

        <el-form-item label="原密码" prop="oldPassword">
          <el-input
            v-model="form.oldPassword"
            type="password"
            placeholder="请输入原密码"
            show-password
          />
        </el-form-item>

        <el-form-item label="新密码" prop="newPassword">
          <el-input
            v-model="form.newPassword"
            type="password"
            placeholder="请输入新密码"
            show-password
          />
        </el-form-item>

        <el-form-item label="确认新密码" prop="confirmPassword">
          <el-input
            v-model="form.confirmPassword"
            type="password"
            placeholder="请再次输入新密码"
            show-password
          />
        </el-form-item>

        <el-form-item>
          <div class="password-tip">
            密码规则：6-16位字母+数字组合
          </div>
        </el-form-item>

        <el-form-item>
          <el-button type="primary" @click="handleSubmit" :loading="submitting">
            保存
          </el-button>
        </el-form-item>
      </el-form>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { accountApi } from '@/api/index.js'
import { removeToken } from '@/utils/request'

const formRef = ref(null)
const submitting = ref(false)

const merchantName = ref('')
const loginAccount = ref('')

const form = reactive({
  oldPassword: '',
  newPassword: '',
  confirmPassword: ''
})

const validateConfirmPassword = (rule, value, callback) => {
  if (value !== form.newPassword) {
    callback(new Error('两次输入的密码不一致'))
  } else {
    callback()
  }
}

const validatePassword = (rule, value, callback) => {
  if (!value) {
    callback(new Error('请输入密码'))
  } else if (value.length < 6 || value.length > 16) {
    callback(new Error('密码长度为6-16位'))
  } else if (!/[a-zA-Z]/.test(value) || !/[0-9]/.test(value)) {
    callback(new Error('密码需包含字母和数字'))
  } else {
    callback()
  }
}

const rules = {
  oldPassword: [
    { required: true, message: '请输入原密码', trigger: 'blur' }
  ],
  newPassword: [
    { required: true, message: '请输入新密码', trigger: 'blur' },
    { validator: validatePassword, trigger: 'blur' }
  ],
  confirmPassword: [
    { required: true, message: '请确认新密码', trigger: 'blur' },
    { validator: validateConfirmPassword, trigger: 'blur' }
  ]
}

const handleSubmit = async () => {
  if (!formRef.value) return
  await formRef.value.validate()

  submitting.value = true
  try {
    await accountApi.changePassword({
      old_password: form.oldPassword,
      new_password: form.newPassword,
      confirm_password: form.confirmPassword
    })
    ElMessage.success('密码修改成功，请重新登录')
    removeToken()
    localStorage.removeItem('username')
    setTimeout(() => {
      window.location.hash = '#/login'
    }, 1500)
  } catch (error) {
    ElMessage.error(error.message || '修改失败')
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  const userStr = localStorage.getItem('user')
  if (userStr) {
    try {
      const user = JSON.parse(userStr)
      merchantName.value = user.merchant_name || user.name || ''
      loginAccount.value = user.account || user.phone || ''
    } catch (e) {
      // ignore
    }
  }
  loginAccount.value = loginAccount.value || localStorage.getItem('username') || ''
})
</script>

<style lang="scss" scoped>
.change-password-container {
  padding: 20px;
  display: flex;
  justify-content: center;
}

.form-card {
  width: 600px;
  margin-top: 40px;

  .card-title {
    font-size: 16px;
    font-weight: 700;
    color: #211834;
  }
}

.password-form {
  padding-right: 40px;
}

.password-tip {
  font-size: 12px;
  color: #8f7d9e;
  line-height: 1.6;
}
</style>
