<template>
	<view class="ai-progress-container">
		<!-- 主进度条 -->
		<view class="progress-bar-wrapper">
			<view class="progress-bar">
				<view class="progress-fill" :style="{ width: progress + '%' }"></view>
			</view>
			<text class="progress-text">{{ progress }}%</text>
		</view>

		<!-- 4步骤指示器 -->
		<view class="steps-wrapper">
			<view
				v-for="(step, index) in steps"
				:key="step.step"
				class="step-item"
				:class="getStepClass(step)"
			>
				<!-- 步骤图标 -->
				<view class="step-icon-wrapper">
					<view class="step-icon" :class="getStepClass(step)">
						<text class="step-emoji">{{ step.icon }}</text>
						<text v-if="step.status === 'processing'" class="step-loading">⏳</text>
						<text v-if="step.status === 'completed'" class="step-check">✓</text>
					</view>

					<!-- 连接线 -->
					<view
						v-if="index < steps.length - 1"
						class="step-line"
						:class="{ 'step-line-active': step.status === 'completed' }"
					></view>
				</view>

				<!-- 步骤名称 -->
				<text class="step-name" :class="{ 'step-name-active': step.status !== 'pending' }">
					{{ step.name }}
				</text>

				<!-- 权重提示 -->
				<text class="step-weight">{{ step.weight }}%</text>
			</view>
		</view>

		<!-- 时间信息 -->
		<view class="time-info">
			<view class="time-item">
				<text class="time-label">已用时间：</text>
				<text class="time-value">{{ formatTime(elapsedTime) }}</text>
			</view>
			<view class="time-item">
				<text class="time-label">预计剩余：</text>
				<text class="time-value time-remaining">{{ formatTime(remainingTime) }}</text>
			</view>
		</view>

		<!-- 当前状态描述 -->
		<view class="status-message">
			<text class="status-icon">{{ getStatusIcon() }}</text>
			<text class="status-text">{{ statusMessage }}</text>
		</view>
	</view>
</template>

<script>
export default {
	name: 'AiProgress',
	props: {
		// 进度百分比 0-100
		progress: {
			type: Number,
			default: 0
		},
		// 步骤详情数组
		steps: {
			type: Array,
			default: () => [
				{ step: 1, name: '分析需求', icon: '🔍', status: 'pending', weight: 10 },
				{ step: 2, name: '调用AI模型', icon: '🤖', status: 'pending', weight: 50 },
				{ step: 3, name: '生成内容', icon: '✨', status: 'pending', weight: 30 },
				{ step: 4, name: '质量检查', icon: '✅', status: 'pending', weight: 10 }
			]
		},
		// 已用时间（秒）
		elapsedTime: {
			type: Number,
			default: 0
		},
		// 预计剩余时间（秒）
		remainingTime: {
			type: Number,
			default: 0
		},
		// 当前步骤名称
		currentStepName: {
			type: String,
			default: '等待处理'
		},
		// 任务状态
		taskStatus: {
			type: String,
			default: 'pending' // pending, processing, completed, failed
		}
	},

	computed: {
		statusMessage() {
			if (this.taskStatus === 'pending') {
				return '任务排队中，请稍候...'
			} else if (this.taskStatus === 'processing') {
				return `正在${this.currentStepName}，请耐心等待...`
			} else if (this.taskStatus === 'completed') {
				return '内容生成完成！'
			} else if (this.taskStatus === 'failed') {
				return '生成失败，请重试'
			}
			return ''
		}
	},

	methods: {
		getStepClass(step) {
			return `step-${step.status}`
		},

		getStatusIcon() {
			const icons = {
				'pending': '⏸️',
				'processing': '⏳',
				'completed': '🎉',
				'failed': '❌'
			}
			return icons[this.taskStatus] || '⏸️'
		},

		formatTime(seconds) {
			if (seconds < 0) return '0秒'
			if (seconds < 60) {
				return `${Math.floor(seconds)}秒`
			} else if (seconds < 3600) {
				const minutes = Math.floor(seconds / 60)
				const secs = Math.floor(seconds % 60)
				return `${minutes}分${secs}秒`
			} else {
				const hours = Math.floor(seconds / 3600)
				const minutes = Math.floor((seconds % 3600) / 60)
				return `${hours}小时${minutes}分`
			}
		}
	}
}
</script>

<style scoped>
.ai-progress-container {
	padding: 30rpx;
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	border-radius: 20rpx;
	box-shadow: 0 10rpx 30rpx rgba(102, 126, 234, 0.3);
}

/* 主进度条 */
.progress-bar-wrapper {
	display: flex;
	align-items: center;
	margin-bottom: 40rpx;
}

.progress-bar {
	flex: 1;
	height: 16rpx;
	background: rgba(255, 255, 255, 0.2);
	border-radius: 8rpx;
	overflow: hidden;
	margin-right: 20rpx;
}

.progress-fill {
	height: 100%;
	background: linear-gradient(90deg, #4facfe 0%, #00f2fe 100%);
	border-radius: 8rpx;
	transition: width 0.3s ease;
}

.progress-text {
	font-size: 32rpx;
	font-weight: bold;
	color: #fff;
	min-width: 80rpx;
	text-align: right;
}

/* 步骤指示器 */
.steps-wrapper {
	display: flex;
	justify-content: space-between;
	margin-bottom: 30rpx;
	padding: 0 10rpx;
}

.step-item {
	flex: 1;
	display: flex;
	flex-direction: column;
	align-items: center;
	position: relative;
}

.step-icon-wrapper {
	display: flex;
	align-items: center;
	width: 100%;
	margin-bottom: 10rpx;
	position: relative;
}

.step-icon {
	width: 80rpx;
	height: 80rpx;
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	position: relative;
	z-index: 2;
	background: rgba(255, 255, 255, 0.2);
	border: 4rpx solid rgba(255, 255, 255, 0.3);
	transition: all 0.3s ease;
}

.step-icon.step-pending {
	background: rgba(255, 255, 255, 0.1);
	border-color: rgba(255, 255, 255, 0.2);
}

.step-icon.step-processing {
	background: #4facfe;
	border-color: #00f2fe;
	animation: pulse 1.5s ease-in-out infinite;
}

.step-icon.step-completed {
	background: #00d084;
	border-color: #00ffa3;
}

@keyframes pulse {
	0%, 100% {
		transform: scale(1);
		box-shadow: 0 0 0 0 rgba(79, 172, 254, 0.7);
	}
	50% {
		transform: scale(1.05);
		box-shadow: 0 0 0 10rpx rgba(79, 172, 254, 0);
	}
}

.step-emoji {
	font-size: 36rpx;
}

.step-loading {
	position: absolute;
	top: -5rpx;
	right: -5rpx;
	font-size: 24rpx;
	animation: spin 2s linear infinite;
}

@keyframes spin {
	from { transform: rotate(0deg); }
	to { transform: rotate(360deg); }
}

.step-check {
	position: absolute;
	top: -8rpx;
	right: -8rpx;
	font-size: 28rpx;
	color: #fff;
	background: #00d084;
	border-radius: 50%;
	width: 32rpx;
	height: 32rpx;
	display: flex;
	align-items: center;
	justify-content: center;
}

.step-line {
	flex: 1;
	height: 4rpx;
	background: rgba(255, 255, 255, 0.2);
	margin: 0 10rpx;
	transition: background 0.3s ease;
}

.step-line-active {
	background: linear-gradient(90deg, #4facfe 0%, #00f2fe 100%);
}

.step-name {
	font-size: 24rpx;
	color: rgba(255, 255, 255, 0.6);
	text-align: center;
	margin-bottom: 6rpx;
	transition: color 0.3s ease;
}

.step-name-active {
	color: #fff;
	font-weight: bold;
}

.step-weight {
	font-size: 20rpx;
	color: rgba(255, 255, 255, 0.5);
}

/* 时间信息 */
.time-info {
	display: flex;
	justify-content: space-between;
	padding: 20rpx 30rpx;
	background: rgba(255, 255, 255, 0.1);
	border-radius: 12rpx;
	margin-bottom: 20rpx;
}

.time-item {
	display: flex;
	align-items: center;
}

.time-label {
	font-size: 26rpx;
	color: rgba(255, 255, 255, 0.8);
	margin-right: 10rpx;
}

.time-value {
	font-size: 28rpx;
	font-weight: bold;
	color: #fff;
}

.time-remaining {
	color: #4facfe;
}

/* 状态消息 */
.status-message {
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 15rpx 20rpx;
	background: rgba(255, 255, 255, 0.15);
	border-radius: 10rpx;
}

.status-icon {
	font-size: 32rpx;
	margin-right: 10rpx;
}

.status-text {
	font-size: 28rpx;
	color: #fff;
	font-weight: 500;
}
</style>
