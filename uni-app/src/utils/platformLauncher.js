/**
 * 平台唤起工具模块
 * 封装抖音/快手等平台APP的唤起逻辑
 */

// 平台配置
const PLATFORM_CONFIG = {
	douyin: {
		name: '抖音',
		scheme: 'snssdk1128://',
		universalLink: 'https://www.douyin.com',
		androidPackage: 'com.ss.android.ugc.aweme',
		iosStoreUrl: 'https://apps.apple.com/cn/app/id1142110895',
		androidStoreUrl: 'https://www.douyin.com/download',
	},
	kuaishou: {
		name: '快手',
		scheme: 'kwai://',
		universalLink: 'https://www.kuaishou.com',
		androidPackage: 'com.smile.gifmaker',
		iosStoreUrl: 'https://apps.apple.com/cn/app/id440948110',
		androidStoreUrl: 'https://www.kuaishou.com/download',
	},
}

/**
 * 下载视频并保存到相册
 * @param {string} videoUrl 视频URL
 * @returns {Promise<{success: boolean, savedPath: string, error: string}>}
 */
export function downloadAndSaveVideo(videoUrl) {
	return new Promise((resolve) => {
		uni.showLoading({ title: '正在下载视频...', mask: true })

		uni.downloadFile({
			url: videoUrl,
			success: (downloadRes) => {
				if (downloadRes.statusCode !== 200) {
					uni.hideLoading()
					resolve({ success: false, savedPath: '', error: '视频下载失败' })
					return
				}

				uni.saveVideoToPhotosAlbum({
					filePath: downloadRes.tempFilePath,
					success: () => {
						uni.hideLoading()
						resolve({ success: true, savedPath: downloadRes.tempFilePath, error: '' })
					},
					fail: (err) => {
						uni.hideLoading()
						if (err.errMsg && err.errMsg.includes('auth deny')) {
							resolve({ success: false, savedPath: '', error: '请授权访问相册权限' })
						} else {
							resolve({ success: false, savedPath: '', error: '保存到相册失败' })
						}
					},
				})
			},
			fail: () => {
				uni.hideLoading()
				resolve({ success: false, savedPath: '', error: '视频下载失败，请检查网络' })
			},
		})
	})
}

/**
 * 复制文案到剪贴板
 * @param {string} text 文案内容
 * @returns {Promise<boolean>}
 */
export function copyToClipboard(text) {
	return new Promise((resolve) => {
		uni.setClipboardData({
			data: text,
			success: () => resolve(true),
			fail: () => resolve(false),
		})
	})
}

/**
 * 唤起APP
 * @param {string} platform 平台标识 douyin/kuaishou
 * @returns {Promise<{success: boolean, fallback: boolean}>}
 */
export function launchApp(platform) {
	const config = PLATFORM_CONFIG[platform]
	if (!config) {
		return Promise.resolve({ success: false, fallback: false })
	}

	return new Promise((resolve) => {
		// #ifdef APP-PLUS
		// APP端使用 plus.runtime
		try {
			plus.runtime.launchApplication(
				{
					pname: config.androidPackage, // Android
					action: config.scheme, // iOS fallback
				},
				(err) => {
					// 唤起失败，尝试用scheme
					plus.runtime.openURL(config.scheme, () => {
						resolve({ success: false, fallback: true })
					})
				}
			)
			// 如果没有报错，认为唤起成功
			setTimeout(() => resolve({ success: true, fallback: false }), 1000)
		} catch (e) {
			resolve({ success: false, fallback: true })
		}
		// #endif

		// #ifdef H5
		// H5端尝试用scheme唤起
		const startTime = Date.now()
		const iframe = document.createElement('iframe')
		iframe.style.display = 'none'
		iframe.src = config.scheme
		document.body.appendChild(iframe)

		setTimeout(() => {
			document.body.removeChild(iframe)
			const elapsed = Date.now() - startTime
			// 如果时间差较小，说明没有离开页面（APP未安装或唤起失败）
			if (elapsed < 2500) {
				resolve({ success: false, fallback: true })
			} else {
				resolve({ success: true, fallback: false })
			}
		}, 2000)
		// #endif

		// #ifdef MP-WEIXIN
		// 微信小程序内无法直接唤起其他APP，引导用户手动打开
		resolve({ success: false, fallback: true })
		// #endif
	})
}

/**
 * 一键操作：下载视频 + 复制文案 + 唤起APP
 * @param {string} platform 平台标识
 * @param {string} videoUrl 视频URL
 * @param {string} copyText 需要复制的文案（含话题标签）
 * @returns {Promise<{success: boolean, platform: string, error: string, steps: object}>}
 */
export async function saveVideoAndLaunch(platform, videoUrl, copyText) {
	const config = PLATFORM_CONFIG[platform]
	if (!config) {
		return { success: false, platform, error: '不支持的平台', steps: {} }
	}

	const steps = {
		download: false,
		copy: false,
		launch: false,
	}

	// H5环境下无法直接下载视频到相册，走降级流程
	// #ifdef H5
	steps.copy = await copyToClipboard(copyText)
	steps.download = true // H5标记为true，引导用户手动保存

	uni.showModal({
		title: `发布到${config.name}`,
		content: `文案已复制到剪贴板。\n\n请长按视频保存到相册，然后打开${config.name}APP，选择相册中的视频发布并粘贴文案。`,
		confirmText: '我知道了',
		showCancel: false,
	})

	return {
		success: true,
		platform,
		error: '',
		steps,
	}
	// #endif

	// 非H5环境：下载视频到相册
	// #ifndef H5
	const downloadResult = await downloadAndSaveVideo(videoUrl)
	steps.download = downloadResult.success

	if (!downloadResult.success) {
		return {
			success: false,
			platform,
			error: downloadResult.error,
			steps,
		}
	}

	// 复制文案到剪贴板
	steps.copy = await copyToClipboard(copyText)

	// 唤起APP
	const launchResult = await launchApp(platform)
	steps.launch = launchResult.success

	if (launchResult.fallback) {
		showManualGuide(platform, config)
	}

	return {
		success: steps.download,
		platform,
		error: '',
		steps,
	}
	// #endif
}

/**
 * 显示手动操作引导
 * @param {string} platform 平台标识
 * @param {object} config 平台配置
 */
function showManualGuide(platform, config) {
	uni.showModal({
		title: `打开${config.name}发布`,
		content: `视频已保存到相册，文案已复制到剪贴板。\n\n请打开${config.name}APP，选择相册中的视频发布，粘贴文案即可。`,
		confirmText: '我知道了',
		showCancel: false,
	})
}

/**
 * 获取平台配置信息
 * @param {string} platform
 * @returns {object|null}
 */
export function getPlatformConfig(platform) {
	return PLATFORM_CONFIG[platform] || null
}

/**
 * 获取所有支持的平台
 * @returns {Array<{key: string, name: string}>}
 */
export function getSupportedPlatforms() {
	return Object.entries(PLATFORM_CONFIG).map(([key, config]) => ({
		key,
		name: config.name,
	}))
}

export default {
	saveVideoAndLaunch,
	downloadAndSaveVideo,
	copyToClipboard,
	launchApp,
	getPlatformConfig,
	getSupportedPlatforms,
}
