<template>
  <view class="capture-page">
    <!-- 自定义导航栏 -->
    <view class="custom-navbar">
      <view class="navbar-content" :style="{ paddingTop: statusBarHeight + 'px' }">
        <view class="navbar-left" @tap="goBack">
          <text class="back-icon">←</text>
        </view>
        <text class="navbar-title">拍摄素材</text>
        <view class="navbar-right"></view>
      </view>
    </view>

    <!-- 相机取景区域 -->
    <view class="camera-area" :style="{ height: cameraHeight + 'px' }">
      <camera
        v-if="showCamera"
        class="camera"
        device-position="back"
        flash="auto"
        @error="onCameraError"
      />
      <view v-else class="camera-placeholder">
        <text class="placeholder-icon">📷</text>
        <text class="placeholder-text">相机加载中...</text>
      </view>

      <!-- 取景框提示 -->
      <view class="viewfinder-tips">
        <view class="tip-item">
          <text class="tip-dot"></text>
          <text class="tip-text">保持画面稳定</text>
        </view>
        <view class="tip-item">
          <text class="tip-dot"></text>
          <text class="tip-text">光线充足更清晰</text>
        </view>
      </view>
    </view>

    <!-- 拍摄提示 -->
    <view class="capture-tips">
      <text class="tips-title">拍摄建议</text>
      <view class="tips-content">
        <text class="tips-item">• 建议拍摄菜品、环境、服务等</text>
        <text class="tips-item">• 保持手机稳定，避免抖动</text>
        <text class="tips-item">• 选择光线充足的环境拍摄</text>
      </view>
    </view>

    <!-- 底部操作栏 -->
    <view class="bottom-actions">
      <!-- 相册 -->
      <view class="action-item" @tap="chooseFromAlbum">
        <view class="action-icon album-icon">
          <text class="icon-text">🖼️</text>
        </view>
        <text class="action-label">相册</text>
      </view>

      <!-- 拍照按钮 -->
      <view class="action-item capture-btn-wrapper">
        <view class="capture-btn" @tap="takePhoto">
          <view class="capture-inner"></view>
        </view>
        <text class="action-label">拍照</text>
      </view>

      <!-- 视频 -->
      <view class="action-item" @tap="recordVideo">
        <view class="action-icon video-icon">
          <text class="icon-text">🎬</text>
        </view>
        <text class="action-label">视频</text>
      </view>
    </view>

    <!-- 上传进度弹窗 -->
    <view class="upload-modal" v-if="uploading">
      <view class="upload-content">
        <view class="upload-progress">
          <view class="progress-ring" :style="{ '--progress': uploadProgress + '%' }"></view>
          <text class="progress-text">{{ uploadProgress }}%</text>
        </view>
        <text class="upload-title">正在上传</text>
        <text class="upload-filename">{{ uploadFileName }}</text>
      </view>
    </view>

    <!-- 预览弹窗 -->
    <view class="preview-modal" v-if="showPreview" @tap="closePreview">
      <view class="preview-content" @tap.stop>
        <image
          v-if="previewType === 'image'"
          class="preview-image"
          :src="previewUrl"
          mode="aspectFit"
        />
        <video
          v-else
          class="preview-video"
          :src="previewUrl"
          controls
          autoplay
        />
        <view class="preview-actions">
          <button class="preview-btn secondary" @tap="closePreview">重拍</button>
          <button class="preview-btn primary" @tap="confirmUpload">使用</button>
        </view>
      </view>
    </view>
  </view>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import api from '../../api/index.js'

export default {
  name: 'MerchantCapture',
  setup() {
    // 系统信息
    const statusBarHeight = ref(20)
    const cameraHeight = ref(400)

    // 相机状态
    const showCamera = ref(false)

    // 上传状态
    const uploading = ref(false)
    const uploadProgress = ref(0)
    const uploadFileName = ref('')

    // 预览状态
    const showPreview = ref(false)
    const previewUrl = ref('')
    const previewType = ref('image')

    /**
     * 初始化相机
     */
    const initCamera = async () => {
      // #ifdef MP-WEIXIN || MP-ALIPAY
      try {
        const res = await uni.authorize({ scope: 'scope.camera' })
        console.log('相机权限授权成功', res)
      } catch (error) {
        console.log('相机权限授权失败', error)
        uni.showModal({
          title: '需要相机权限',
          content: '请在设置中开启相机权限',
          success: (res) => {
            if (res.confirm) {
              uni.openSetting()
            }
          }
        })
        return
      }
      // #endif

      showCamera.value = true
    }

    /**
     * 返回上一页
     */
    const goBack = () => {
      uni.navigateBack()
    }

    /**
     * 相机错误处理
     */
    const onCameraError = (e) => {
      console.error('相机错误:', e)
      uni.showToast({
        title: '相机启动失败',
        icon: 'none'
      })
      showCamera.value = false
    }

    /**
     * 拍照
     */
    const takePhoto = () => {
      // #ifdef MP-WEIXIN || MP-ALIPAY
      if (!showCamera.value) {
        uni.showToast({ title: '相机未就绪', icon: 'none' })
        return
      }

      const ctx = uni.createCameraContext()
      ctx.takePhoto({
        quality: 'high',
        success: (res) => {
          previewUrl.value = res.tempImagePath
          previewType.value = 'image'
          showPreview.value = true
        },
        fail: (err) => {
          console.error('拍照失败:', err)
          uni.showToast({ title: '拍照失败', icon: 'none' })
        }
      })
      // #endif

      // #ifdef H5
      // H5端使用chooseImage模拟
      uni.chooseImage({
        count: 1,
        sizeType: ['original'],
        sourceType: ['camera'],
        success: (res) => {
          previewUrl.value = res.tempFilePaths[0]
          previewType.value = 'image'
          showPreview.value = true
        }
      })
      // #endif
    }

    /**
     * 录制视频
     */
    const recordVideo = () => {
      // #ifdef MP-WEIXIN || MP-ALIPAY
      if (!showCamera.value) {
        uni.showToast({ title: '相机未就绪', icon: 'none' })
        return
      }

      const ctx = uni.createCameraContext()
      ctx.startRecord({
        success: () => {
          uni.showToast({ title: '开始录制...', icon: 'none' })

          // 15秒后自动停止
          setTimeout(() => {
            ctx.stopRecord({
              success: (res) => {
                previewUrl.value = res.tempVideoPath
                previewType.value = 'video'
                showPreview.value = true
              },
              fail: (err) => {
                console.error('停止录制失败:', err)
              }
            })
          }, 15000)
        },
        fail: (err) => {
          console.error('开始录制失败:', err)
          uni.showToast({ title: '录制失败', icon: 'none' })
        }
      })
      // #endif

      // #ifdef H5
      // H5端使用chooseVideo
      uni.chooseVideo({
        sourceType: ['camera'],
        maxDuration: 60,
        success: (res) => {
          previewUrl.value = res.tempFilePath
          previewType.value = 'video'
          showPreview.value = true
        }
      })
      // #endif
    }

    /**
     * 从相册选择
     */
    const chooseFromAlbum = () => {
      uni.chooseImage({
        count: 9,
        sizeType: ['compressed'],
        sourceType: ['album'],
        success: async (res) => {
          const files = res.tempFilePaths

          uploading.value = true
          uploadProgress.value = 0
          uploadFileName.value = '准备上传...'

          try {
            for (let i = 0; i < files.length; i++) {
              uploadFileName.value = `上传中 ${i + 1}/${files.length}`
              uploadProgress.value = Math.round((i / files.length) * 100)

              await uploadFile(files[i], 'image')
            }

            uploadProgress.value = 100
            uni.showToast({ title: '上传成功', icon: 'success' })

            setTimeout(() => {
              uploading.value = false
              uni.navigateBack()
            }, 1000)
          } catch (error) {
            console.error('上传失败:', error)
            uni.showToast({ title: '上传失败', icon: 'none' })
            uploading.value = false
          }
        }
      })
    }

    /**
     * 关闭预览
     */
    const closePreview = () => {
      showPreview.value = false
      previewUrl.value = ''
    }

    /**
     * 确认上传
     */
    const confirmUpload = async () => {
      uploading.value = true
      uploadProgress.value = 0
      uploadFileName.value = previewType.value === 'image' ? '图片上传中...' : '视频上传中...'

      try {
        await uploadFile(previewUrl.value, previewType.value)

        uploadProgress.value = 100
        uni.showToast({ title: '上传成功', icon: 'success' })

        setTimeout(() => {
          uploading.value = false
          closePreview()
          uni.navigateBack()
        }, 1000)
      } catch (error) {
        console.error('上传失败:', error)
        uni.showToast({ title: '上传失败', icon: 'none' })
        uploading.value = false
      }
    }

    /**
     * 上传文件
     */
    const uploadFile = async (filePath, type) => {
      return new Promise((resolve, reject) => {
        // 模拟上传进度
        let progress = 0
        const progressTimer = setInterval(() => {
          progress += 10
          if (progress > 90) {
            clearInterval(progressTimer)
          } else {
            uploadProgress.value = progress
          }
        }, 200)

        api.promoMaterial.upload(filePath, type, { showLoading: false })
          .then(res => {
            clearInterval(progressTimer)
            uploadProgress.value = 100
            resolve(res)
          })
          .catch(err => {
            clearInterval(progressTimer)
            reject(err)
          })
      })
    }

    onMounted(() => {
      // 获取系统信息
      const systemInfo = uni.getSystemInfoSync()
      statusBarHeight.value = systemInfo.statusBarHeight

      // 计算相机区域高度
      const windowHeight = systemInfo.windowHeight
      const navbarHeight = 44 + statusBarHeight.value
      const tipsHeight = 120
      const actionsHeight = 180
      cameraHeight.value = windowHeight - navbarHeight - tipsHeight - actionsHeight

      // 初始化相机
      initCamera()
    })

    return {
      statusBarHeight,
      cameraHeight,
      showCamera,
      uploading,
      uploadProgress,
      uploadFileName,
      showPreview,
      previewUrl,
      previewType,
      goBack,
      onCameraError,
      takePhoto,
      recordVideo,
      chooseFromAlbum,
      closePreview,
      confirmUpload
    }
  }
}
</script>

<style lang="scss" scoped>
.capture-page {
  min-height: 100vh;
  background: #000000;
  display: flex;
  flex-direction: column;
}

/* 自定义导航栏 */
.custom-navbar {
  background: rgba(0, 0, 0, 0.8);
}

.navbar-content {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 44px;
  padding: 0 30rpx;
}

.navbar-left {
  width: 60rpx;
}

.back-icon {
  font-size: 20px;
  color: #ffffff;
}

.navbar-title {
  font-size: 18px;
  font-weight: 600;
  color: #ffffff;
}

.navbar-right {
  width: 60rpx;
}

/* 相机区域 */
.camera-area {
  position: relative;
  background: #1f2937;
}

.camera {
  width: 100%;
  height: 100%;
}

.camera-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.placeholder-icon {
  font-size: 80rpx;
  margin-bottom: 20rpx;
}

.placeholder-text {
  font-size: 14px;
  color: #6b7280;
}

.viewfinder-tips {
  position: absolute;
  bottom: 20rpx;
  left: 0;
  right: 0;
  display: flex;
  justify-content: center;
  gap: 40rpx;
}

.tip-item {
  display: flex;
  align-items: center;
  gap: 8rpx;
}

.tip-dot {
  width: 12rpx;
  height: 12rpx;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.6);
}

.tip-text {
  font-size: 12px;
  color: rgba(255, 255, 255, 0.8);
}

/* 拍摄提示 */
.capture-tips {
  background: rgba(0, 0, 0, 0.6);
  padding: 30rpx;
}

.tips-title {
  display: block;
  font-size: 14px;
  font-weight: 600;
  color: #ffffff;
  margin-bottom: 16rpx;
}

.tips-content {
  display: flex;
  flex-direction: column;
  gap: 8rpx;
}

.tips-item {
  font-size: 12px;
  color: rgba(255, 255, 255, 0.7);
  line-height: 1.6;
}

/* 底部操作栏 */
.bottom-actions {
  background: rgba(0, 0, 0, 0.9);
  padding: 30rpx 60rpx;
  padding-bottom: calc(30rpx + env(safe-area-inset-bottom));
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
}

.action-item {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.action-icon {
  width: 100rpx;
  height: 100rpx;
  border-radius: 16rpx;
  background: rgba(255, 255, 255, 0.1);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 12rpx;
}

.icon-text {
  font-size: 40rpx;
}

.action-label {
  font-size: 12px;
  color: rgba(255, 255, 255, 0.8);
}

/* 拍照按钮 */
.capture-btn-wrapper {
  margin-top: -20rpx;
}

.capture-btn {
  width: 140rpx;
  height: 140rpx;
  border-radius: 50%;
  background: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 12rpx;
}

.capture-inner {
  width: 100%;
  height: 100%;
  border-radius: 50%;
  background: #ffffff;
  border: 8rpx solid #1f2937;
  transition: all 0.2s;
}

.capture-btn:active .capture-inner {
  background: #6366f1;
}

/* 上传进度弹窗 */
.upload-modal {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.8);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}

.upload-content {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.upload-progress {
  position: relative;
  width: 160rpx;
  height: 160rpx;
  margin-bottom: 40rpx;
}

.progress-ring {
  width: 100%;
  height: 100%;
  border-radius: 50%;
  background: conic-gradient(
    #6366f1 var(--progress),
    rgba(255, 255, 255, 0.2) var(--progress)
  );
  display: flex;
  align-items: center;
  justify-content: center;

  &::before {
    content: '';
    position: absolute;
    width: 120rpx;
    height: 120rpx;
    border-radius: 50%;
    background: #000000;
  }
}

.progress-text {
  position: absolute;
  font-size: 16px;
  font-weight: bold;
  color: #ffffff;
}

.upload-title {
  font-size: 18px;
  font-weight: 600;
  color: #ffffff;
  margin-bottom: 16rpx;
}

.upload-filename {
  font-size: 14px;
  color: rgba(255, 255, 255, 0.6);
}

/* 预览弹窗 */
.preview-modal {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.9);
  display: flex;
  flex-direction: column;
  z-index: 9999;
}

.preview-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60rpx;
}

.preview-image {
  max-width: 100%;
  max-height: 60vh;
}

.preview-video {
  width: 100%;
  max-height: 60vh;
}

.preview-actions {
  display: flex;
  gap: 40rpx;
  margin-top: 60rpx;
}

.preview-btn {
  width: 200rpx;
  height: 88rpx;
  border-radius: 44rpx;
  font-size: 16px;
  font-weight: 500;
  border: none;

  &.secondary {
    background: rgba(255, 255, 255, 0.2);
    color: #ffffff;
  }

  &.primary {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: #ffffff;
  }
}
</style>
