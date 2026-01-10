<template>
	<view class="auth-container">
		<!-- 背景装饰 -->
		<view class="bg-decoration">
			<view class="circle circle-1"></view>
			<view class="circle circle-2"></view>
			<view class="circle circle-3"></view>
		</view>

		<!-- 主要内容 -->
		<view class="auth-content">
			<!-- Logo和标题 -->
			<view class="logo-section">
				<image class="logo" src="/static/logo.png" mode="aspectFit"></image>
				<text class="app-name">小魔推碰一碰</text>
				<text class="app-slogan">智能NFC营销平台</text>
			</view>

			<!-- 登录表单区域 -->
			<view class="login-section">
				<!-- 微信小程序登录 -->
				<!-- #ifdef MP-WEIXIN -->
				<button
					class="login-btn wechat-btn"
					:loading="loading"
					:disabled="loading"
					@tap="handleWechatLogin"
				>
					<text class="btn-icon">&#xe601;</text>
					<text class="btn-text">{{ loading ? '登录中...' : '微信一键登录' }}</text>
				</button>

				<!-- 获取用户信息按钮（可选） -->
				<button
					v-if="showGetUserInfo"
					class="login-btn info-btn"
					open-type="getUserInfo"
					@getuserinfo="handleGetUserInfo"
				>
					<text class="btn-text">授权用户信息</text>
				</button>

				<!-- 获取手机号按钮（可选） -->
				<button
					v-if="showGetPhone"
					class="login-btn phone-btn"
					open-type="getPhoneNumber"
					@getphonenumber="handleGetPhoneNumber"
				>
					<text class="btn-text">授权手机号</text>
				</button>
				<!-- #endif -->

				<!-- 支付宝小程序登录 -->
				<!-- #ifdef MP-ALIPAY -->
				<button
					class="login-btn alipay-btn"
					:loading="loading"
					:disabled="loading"
					@tap="handleAlipayLogin"
				>
					<text class="btn-icon">&#xe602;</text>
					<text class="btn-text">{{ loading ? '登录中...' : '支付宝一键登录' }}</text>
				</button>
				<!-- #endif -->

				<!-- H5手机号登录 -->
				<!-- #ifdef H5 -->
				<view class="h5-login-form">
					<view class="form-item">
						<input
							class="form-input"
							v-model="phone"
							type="number"
							maxlength="11"
							placeholder="请输入手机号"
						/>
					</view>

					<view class="form-item code-item">
						<input
							class="form-input"
							v-model="smsCode"
							type="number"
							maxlength="6"
							placeholder="请输入验证码"
						/>
						<button
							class="code-btn"
							:disabled="codeDisabled"
							@tap="handleSendCode"
						>
							{{ codeText }}
						</button>
					</view>

					<button
						class="login-btn primary-btn"
						:loading="loading"
						:disabled="loading"
						@tap="handlePhoneLogin"
					>
						{{ loading ? '登录中...' : '登录' }}
					</button>
				</view>
				<!-- #endif -->
			</view>

			<!-- 隐私协议 -->
			<view class="privacy-section">
				<checkbox-group @change="handlePrivacyChange">
					<label class="privacy-label">
						<checkbox :checked="agreePrivacy" color="#6366f1" />
						<text class="privacy-text">
							我已阅读并同意
							<text class="privacy-link" @tap.stop="handleShowPrivacy">《隐私政策》</text>
							和
							<text class="privacy-link" @tap.stop="handleShowUserAgreement">《用户协议》</text>
						</text>
					</label>
				</checkbox-group>
			</view>

			<!-- 错误提示 -->
			<view v-if="errorMsg" class="error-msg">
				<text>{{ errorMsg }}</text>
			</view>

			<!-- 平台信息 -->
			<view class="platform-info">
				<text>当前平台：{{ platformName }}</text>
			</view>
		</view>
	</view>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useUserStore } from '../../stores/user.js'
import { handleLoginSuccess, getCurrentPlatform, getPlatformName } from '../../utils/auth.js'

// 获取页面参数
const pages = getCurrentPages()
const currentPage = pages[pages.length - 1]
const options = currentPage?.$page?.options || {}
const redirect = ref(options.redirect || '')

// Store
const userStore = useUserStore()

// 响应式数据
const loading = ref(false)
const agreePrivacy = ref(false)
const errorMsg = ref('')

// 微信特有
const showGetUserInfo = ref(false)
const showGetPhone = ref(false)

// H5手机号登录
const phone = ref('')
const smsCode = ref('')
const codeDisabled = ref(false)
const codeCountdown = ref(60)
const codeText = ref('获取验证码')

// 平台信息
const platformName = computed(() => getPlatformName())

/**
 * 处理隐私协议勾选
 */
function handlePrivacyChange(e) {
	agreePrivacy.value = e.detail.value.length > 0
}

/**
 * 显示隐私政策
 */
function handleShowPrivacy() {
	uni.navigateTo({
		url: '/pages/user/privacy'
	})
}

/**
 * 显示用户协议
 */
function handleShowUserAgreement() {
	uni.navigateTo({
		url: '/pages/user/agreement'
	})
}

/**
 * 检查隐私协议
 */
function checkPrivacy() {
	if (!agreePrivacy.value) {
		uni.showToast({
			title: '请先阅读并同意隐私政策和用户协议',
			icon: 'none',
			duration: 2000
		})
		return false
	}
	return true
}

/**
 * 微信小程序登录
 */
async function handleWechatLogin() {
	// #ifdef MP-WEIXIN
	if (!checkPrivacy()) return

	errorMsg.value = ''
	loading.value = true

	try {
		// 调用store中的登录方法
		const result = await userStore.wechatLogin()

		// 登录成功提示
		uni.showToast({
			title: '登录成功',
			icon: 'success',
			duration: 1500
		})

		// 延迟跳转，让用户看到成功提示
		setTimeout(() => {
			handleLoginSuccess(redirect.value)
		}, 1500)
	} catch (error) {
		console.error('微信登录失败', error)
		errorMsg.value = error.message || '登录失败，请重试'

		uni.showToast({
			title: errorMsg.value,
			icon: 'none',
			duration: 2000
		})
	} finally {
		loading.value = false
	}
	// #endif
}

/**
 * 获取微信用户信息
 */
function handleGetUserInfo(e) {
	// #ifdef MP-WEIXIN
	if (e.detail.errMsg === 'getUserInfo:ok') {
		console.log('用户信息', e.detail)

		// 可以将用户信息保存到store或上传到服务器
		const userInfo = e.detail.userInfo
		if (userInfo) {
			userStore.setUserInfo({
				nickname: userInfo.nickName,
				avatar: userInfo.avatarUrl
			})
		}

		uni.showToast({
			title: '授权成功',
			icon: 'success'
		})

		showGetUserInfo.value = false
	} else {
		uni.showToast({
			title: '您拒绝了授权',
			icon: 'none'
		})
	}
	// #endif
}

/**
 * 获取微信手机号
 */
async function handleGetPhoneNumber(e) {
	// #ifdef MP-WEIXIN
	if (e.detail.errMsg === 'getPhoneNumber:ok') {
		try {
			loading.value = true
			await userStore.getWechatPhone(e)

			uni.showToast({
				title: '手机号获取成功',
				icon: 'success'
			})

			showGetPhone.value = false
		} catch (error) {
			console.error('获取手机号失败', error)
			uni.showToast({
				title: '手机号获取失败',
				icon: 'none'
			})
		} finally {
			loading.value = false
		}
	} else {
		uni.showToast({
			title: '您拒绝了授权',
			icon: 'none'
		})
	}
	// #endif
}

/**
 * 支付宝小程序登录
 */
async function handleAlipayLogin() {
	// #ifdef MP-ALIPAY
	if (!checkPrivacy()) return

	errorMsg.value = ''
	loading.value = true

	try {
		// 调用store中的登录方法
		const result = await userStore.alipayLogin()

		// 登录成功提示
		uni.showToast({
			title: '登录成功',
			icon: 'success',
			duration: 1500
		})

		// 延迟跳转
		setTimeout(() => {
			handleLoginSuccess(redirect.value)
		}, 1500)
	} catch (error) {
		console.error('支付宝登录失败', error)
		errorMsg.value = error.message || '登录失败，请重试'

		uni.showToast({
			title: errorMsg.value,
			icon: 'none',
			duration: 2000
		})
	} finally {
		loading.value = false
	}
	// #endif
}

/**
 * 发送验证码
 */
async function handleSendCode() {
	// #ifdef H5
	if (!phone.value) {
		uni.showToast({
			title: '请输入手机号',
			icon: 'none'
		})
		return
	}

	if (!/^1[3-9]\d{9}$/.test(phone.value)) {
		uni.showToast({
			title: '手机号格式不正确',
			icon: 'none'
		})
		return
	}

	try {
		// 调用发送验证码接口
		const authApi = (await import('../../api/modules/auth.js')).default
		await authApi.sendSmsCode(phone.value)

		uni.showToast({
			title: '验证码已发送',
			icon: 'success'
		})

		// 开始倒计时
		codeDisabled.value = true
		const timer = setInterval(() => {
			codeCountdown.value--
			codeText.value = `${codeCountdown.value}秒后重试`

			if (codeCountdown.value <= 0) {
				clearInterval(timer)
				codeDisabled.value = false
				codeCountdown.value = 60
				codeText.value = '获取验证码'
			}
		}, 1000)
	} catch (error) {
		console.error('发送验证码失败', error)
		uni.showToast({
			title: error.message || '发送失败，请重试',
			icon: 'none'
		})
	}
	// #endif
}

/**
 * 手机号登录
 */
async function handlePhoneLogin() {
	// #ifdef H5
	if (!checkPrivacy()) return

	if (!phone.value) {
		uni.showToast({
			title: '请输入手机号',
			icon: 'none'
		})
		return
	}

	if (!smsCode.value) {
		uni.showToast({
			title: '请输入验证码',
			icon: 'none'
		})
		return
	}

	errorMsg.value = ''
	loading.value = true

	try {
		// 调用store中的登录方法
		await userStore.phoneLogin(phone.value, smsCode.value)

		// 登录成功提示
		uni.showToast({
			title: '登录成功',
			icon: 'success',
			duration: 1500
		})

		// 延迟跳转
		setTimeout(() => {
			handleLoginSuccess(redirect.value)
		}, 1500)
	} catch (error) {
		console.error('手机号登录失败', error)
		errorMsg.value = error.message || '登录失败，请重试'

		uni.showToast({
			title: errorMsg.value,
			icon: 'none',
			duration: 2000
		})
	} finally {
		loading.value = false
	}
	// #endif
}

/**
 * 页面加载时检查是否已登录
 */
onMounted(() => {
	// 检查是否已登录
	if (userStore.checkLoginStatus()) {
		// 已登录，直接跳转
		handleLoginSuccess(redirect.value)
	}
})
</script>

<style lang="scss" scoped>
.auth-container {
	min-height: 100vh;
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	position: relative;
	overflow: hidden;
}

/* 背景装饰 */
.bg-decoration {
	position: absolute;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	z-index: 0;

	.circle {
		position: absolute;
		border-radius: 50%;
		background: rgba(255, 255, 255, 0.1);
		animation: float 6s ease-in-out infinite;

		&.circle-1 {
			width: 200rpx;
			height: 200rpx;
			top: 10%;
			left: 10%;
			animation-delay: 0s;
		}

		&.circle-2 {
			width: 150rpx;
			height: 150rpx;
			top: 60%;
			right: 10%;
			animation-delay: 2s;
		}

		&.circle-3 {
			width: 100rpx;
			height: 100rpx;
			bottom: 20%;
			left: 20%;
			animation-delay: 4s;
		}
	}
}

@keyframes float {
	0%,
	100% {
		transform: translateY(0) scale(1);
		opacity: 0.6;
	}
	50% {
		transform: translateY(-30rpx) scale(1.1);
		opacity: 0.8;
	}
}

/* 主要内容 */
.auth-content {
	position: relative;
	z-index: 1;
	padding: 80rpx 60rpx;
	min-height: 100vh;
	display: flex;
	flex-direction: column;
	align-items: center;
}

/* Logo区域 */
.logo-section {
	text-align: center;
	margin-bottom: 120rpx;

	.logo {
		width: 160rpx;
		height: 160rpx;
		margin-bottom: 30rpx;
	}

	.app-name {
		display: block;
		font-size: 48rpx;
		font-weight: bold;
		color: #ffffff;
		margin-bottom: 20rpx;
		text-shadow: 0 2rpx 10rpx rgba(0, 0, 0, 0.1);
	}

	.app-slogan {
		display: block;
		font-size: 28rpx;
		color: rgba(255, 255, 255, 0.9);
	}
}

/* 登录区域 */
.login-section {
	width: 100%;
	max-width: 600rpx;
}

/* 登录按钮通用样式 */
.login-btn {
	width: 100%;
	height: 90rpx;
	line-height: 90rpx;
	border-radius: 45rpx;
	border: none;
	font-size: 32rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	margin-bottom: 30rpx;
	box-shadow: 0 8rpx 20rpx rgba(0, 0, 0, 0.15);
	transition: all 0.3s ease;

	&:active {
		transform: scale(0.98);
		box-shadow: 0 4rpx 10rpx rgba(0, 0, 0, 0.15);
	}

	.btn-icon {
		margin-right: 10rpx;
		font-size: 36rpx;
	}

	.btn-text {
		font-weight: 500;
	}
}

/* 微信登录按钮 */
.wechat-btn {
	background: #07c160;
	color: #ffffff;

	&:disabled {
		background: #9ed99e;
	}
}

/* 支付宝登录按钮 */
.alipay-btn {
	background: #1677ff;
	color: #ffffff;

	&:disabled {
		background: #91caff;
	}
}

/* 信息授权按钮 */
.info-btn,
.phone-btn {
	background: #ffffff;
	color: #6366f1;
	font-size: 28rpx;
	height: 80rpx;
	line-height: 80rpx;
}

/* 主按钮 */
.primary-btn {
	background: #6366f1;
	color: #ffffff;

	&:disabled {
		background: #9ca3af;
	}
}

/* H5登录表单 */
.h5-login-form {
	.form-item {
		margin-bottom: 30rpx;

		&.code-item {
			display: flex;
			align-items: center;
		}
	}

	.form-input {
		width: 100%;
		height: 90rpx;
		padding: 0 30rpx;
		background: rgba(255, 255, 255, 0.95);
		border-radius: 45rpx;
		font-size: 30rpx;
		color: #333333;

		&::placeholder {
			color: #999999;
		}
	}

	.code-item .form-input {
		flex: 1;
		margin-right: 20rpx;
	}

	.code-btn {
		width: 200rpx;
		height: 90rpx;
		line-height: 90rpx;
		background: rgba(255, 255, 255, 0.95);
		color: #6366f1;
		border-radius: 45rpx;
		font-size: 26rpx;
		text-align: center;
		border: none;

		&:disabled {
			color: #999999;
		}
	}
}

/* 隐私协议 */
.privacy-section {
	margin-top: 60rpx;
	width: 100%;
	max-width: 600rpx;

	.privacy-label {
		display: flex;
		align-items: center;
		color: rgba(255, 255, 255, 0.9);
		font-size: 24rpx;
	}

	checkbox {
		margin-right: 10rpx;
	}

	.privacy-text {
		flex: 1;
		line-height: 1.6;
	}

	.privacy-link {
		color: #ffffff;
		text-decoration: underline;
	}
}

/* 错误提示 */
.error-msg {
	margin-top: 30rpx;
	padding: 20rpx 30rpx;
	background: rgba(248, 113, 113, 0.9);
	color: #ffffff;
	border-radius: 10rpx;
	font-size: 26rpx;
	text-align: center;
}

/* 平台信息 */
.platform-info {
	margin-top: auto;
	padding-top: 60rpx;
	color: rgba(255, 255, 255, 0.7);
	font-size: 24rpx;
	text-align: center;
}
</style>
