<template>
	<view class="container">
		<view class="title">API使用示例</view>

		<!-- 认证API示例 -->
		<view class="section">
			<view class="section-title">1. 认证API</view>
			<button @click="handleLogin" class="btn">登录</button>
			<button @click="handleGetUserInfo" class="btn">获取用户信息</button>
			<button @click="handleLogout" class="btn">退出登录</button>
		</view>

		<!-- NFC API示例 -->
		<view class="section">
			<view class="section-title">2. NFC API</view>
			<button @click="handleNFCTrigger" class="btn">NFC触发</button>
			<button @click="handleScanQRCode" class="btn">扫码</button>
			<button @click="handleGetDeviceList" class="btn">获取设备列表</button>
		</view>

		<!-- 内容生成API示例 -->
		<view class="section">
			<view class="section-title">3. 内容生成API</view>
			<button @click="handleCreateTask" class="btn">创建生成任务</button>
			<button @click="handleGetTaskStatus" class="btn">查询任务状态</button>
			<button @click="handleGetTemplates" class="btn">获取模板列表</button>
		</view>

		<!-- 发布API示例 -->
		<view class="section">
			<view class="section-title">4. 发布API</view>
			<button @click="handlePublishNow" class="btn">立即发布</button>
			<button @click="handleGetAccounts" class="btn">获取平台账号</button>
			<button @click="handleBatchPublish" class="btn">批量发布</button>
		</view>

		<!-- 结果显示 -->
		<view class="result" v-if="result">
			<view class="result-title">返回结果：</view>
			<text class="result-text">{{ result }}</text>
		</view>
	</view>
</template>

<script>
import api from '@/api'

export default {
	data() {
		return {
			result: '',
			currentTaskId: '',
			currentDeviceCode: 'TEST_DEVICE_001'
		}
	},

	methods: {
		// ========== 认证API示例 ==========

		/**
		 * 登录示例
		 */
		async handleLogin() {
			try {
				// #ifdef MP-WEIXIN
				// 微信小程序登录
				const res = await api.auth.wechatLogin()
				this.result = JSON.stringify(res, null, 2)
				uni.showToast({
					title: '登录成功',
					icon: 'success'
				})
				// #endif

				// #ifdef H5
				// H5手机号登录
				const res = await api.auth.phoneLogin('13800138000', '123456')
				this.result = JSON.stringify(res, null, 2)
				uni.showToast({
					title: '登录成功',
					icon: 'success'
				})
				// #endif
			} catch (e) {
				console.error('登录失败', e)
				this.result = '登录失败: ' + JSON.stringify(e)
			}
		},

		/**
		 * 获取用户信息
		 */
		async handleGetUserInfo() {
			try {
				const res = await api.auth.getUserInfo()
				this.result = JSON.stringify(res, null, 2)
			} catch (e) {
				console.error('获取用户信息失败', e)
				this.result = '获取失败: ' + JSON.stringify(e)
			}
		},

		/**
		 * 退出登录
		 */
		async handleLogout() {
			try {
				await api.auth.logout()
				this.result = '已退出登录'
				uni.showToast({
					title: '已退出',
					icon: 'success'
				})
			} catch (e) {
				console.error('退出失败', e)
			}
		},

		// ========== NFC API示例 ==========

		/**
		 * NFC触发示例
		 */
		async handleNFCTrigger() {
			try {
				const res = await api.nfc.trigger(this.currentDeviceCode, {
					scene: 'restaurant',
					location: '北京市朝阳区'
				})
				this.result = JSON.stringify(res, null, 2)

				// 如果返回了任务ID，保存起来
				if (res.task_id) {
					this.currentTaskId = res.task_id
				}
			} catch (e) {
				console.error('触发失败', e)
				this.result = '触发失败: ' + JSON.stringify(e)
			}
		},

		/**
		 * 扫码示例
		 */
		async handleScanQRCode() {
			try {
				const deviceCode = await api.nfc.scanQRCode()
				this.currentDeviceCode = deviceCode
				this.result = `扫码成功，设备码: ${deviceCode}`

				// 扫码后自动触发
				await this.handleNFCTrigger()
			} catch (e) {
				console.error('扫码失败', e)
				this.result = '扫码失败: ' + JSON.stringify(e)
			}
		},

		/**
		 * 获取设备列表
		 */
		async handleGetDeviceList() {
			try {
				const res = await api.nfc.getDeviceList({
					page: 1,
					page_size: 10
				})
				this.result = JSON.stringify(res, null, 2)
			} catch (e) {
				console.error('获取设备列表失败', e)
				this.result = '获取失败: ' + JSON.stringify(e)
			}
		},

		// ========== 内容生成API示例 ==========

		/**
		 * 创建生成任务
		 */
		async handleCreateTask() {
			try {
				const res = await api.content.createTask({
					type: 'VIDEO',
					templateId: 1,
					deviceCode: this.currentDeviceCode,
					scene: {
						type: 'restaurant',
						name: '美味餐厅',
						description: '一家精致的中餐厅'
					},
					style: 'modern',
					platform: 'douyin'
				})
				this.result = JSON.stringify(res, null, 2)

				// 保存任务ID
				if (res.task_id) {
					this.currentTaskId = res.task_id
				}

				uni.showToast({
					title: '任务创建成功',
					icon: 'success'
				})
			} catch (e) {
				console.error('创建任务失败', e)
				this.result = '创建失败: ' + JSON.stringify(e)
			}
		},

		/**
		 * 查询任务状态
		 */
		async handleGetTaskStatus() {
			if (!this.currentTaskId) {
				uni.showToast({
					title: '请先创建任务',
					icon: 'none'
				})
				return
			}

			try {
				const res = await api.content.getTaskStatus(this.currentTaskId)
				this.result = JSON.stringify(res, null, 2)

				// 如果任务完成，显示提示
				if (res.status === 'COMPLETED') {
					uni.showToast({
						title: '任务已完成',
						icon: 'success'
					})
				} else if (res.status === 'PROCESSING') {
					uni.showToast({
						title: `生成中 ${res.progress}%`,
						icon: 'loading'
					})
				}
			} catch (e) {
				console.error('查询失败', e)
				this.result = '查询失败: ' + JSON.stringify(e)
			}
		},

		/**
		 * 获取模板列表
		 */
		async handleGetTemplates() {
			try {
				const res = await api.content.getTemplateList({
					category: 'restaurant',
					type: 'VIDEO',
					page: 1
				})
				this.result = JSON.stringify(res, null, 2)
			} catch (e) {
				console.error('获取模板失败', e)
				this.result = '获取失败: ' + JSON.stringify(e)
			}
		},

		// ========== 发布API示例 ==========

		/**
		 * 立即发布
		 */
		async handlePublishNow() {
			if (!this.currentTaskId) {
				uni.showToast({
					title: '请先创建内容任务',
					icon: 'none'
				})
				return
			}

			try {
				const res = await api.publish.publishNow(
					this.currentTaskId,
					['douyin', 'xiaohongshu'],
					{
						title: '精彩视频分享',
						description: '这是一个很棒的视频',
						tags: ['美食', '推荐', '探店']
					}
				)
				this.result = JSON.stringify(res, null, 2)

				uni.showToast({
					title: '发布成功',
					icon: 'success'
				})
			} catch (e) {
				console.error('发布失败', e)
				this.result = '发布失败: ' + JSON.stringify(e)
			}
		},

		/**
		 * 获取平台账号
		 */
		async handleGetAccounts() {
			try {
				const res = await api.publish.getPlatformAccounts()
				this.result = JSON.stringify(res, null, 2)
			} catch (e) {
				console.error('获取账号失败', e)
				this.result = '获取失败: ' + JSON.stringify(e)
			}
		},

		/**
		 * 批量发布
		 */
		async handleBatchPublish() {
			try {
				const res = await api.publish.batchPublish([
					{
						content_task_id: this.currentTaskId,
						platforms: ['douyin'],
						title: '视频1'
					},
					{
						content_task_id: this.currentTaskId,
						platforms: ['xiaohongshu'],
						title: '视频2'
					}
				])
				this.result = JSON.stringify(res, null, 2)

				uni.showToast({
					title: '批量发布成功',
					icon: 'success'
				})
			} catch (e) {
				console.error('批量发布失败', e)
				this.result = '发布失败: ' + JSON.stringify(e)
			}
		}
	}
}
</script>

<style scoped>
.container {
	padding: 30rpx;
}

.title {
	font-size: 36rpx;
	font-weight: bold;
	text-align: center;
	margin-bottom: 40rpx;
	color: #333;
}

.section {
	margin-bottom: 40rpx;
	padding: 30rpx;
	background: #fff;
	border-radius: 16rpx;
	box-shadow: 0 2rpx 10rpx rgba(0, 0, 0, 0.05);
}

.section-title {
	font-size: 32rpx;
	font-weight: bold;
	margin-bottom: 20rpx;
	color: #333;
}

.btn {
	margin: 10rpx 0;
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	color: #fff;
	border: none;
	border-radius: 8rpx;
	font-size: 28rpx;
}

.result {
	margin-top: 40rpx;
	padding: 30rpx;
	background: #f5f5f5;
	border-radius: 16rpx;
}

.result-title {
	font-size: 28rpx;
	font-weight: bold;
	margin-bottom: 20rpx;
	color: #333;
}

.result-text {
	font-size: 24rpx;
	color: #666;
	line-height: 1.6;
	word-wrap: break-word;
}
</style>
