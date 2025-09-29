# 小魔推 uni-app 版本设计文档

## 概述

小魔推碰一碰采用 uni-app 框架开发前端，实现一套代码多端运行（H5、微信小程序、支付宝小程序、APP）。后端保持 ThinkPHP 8.0 不变，通过条件编译处理不同平台的特殊功能。

## 技术架构

### 技术栈选择
- **前端框架**: uni-app (Vue 3 版本)
- **UI框架**: uView Plus 3.0
- **状态管理**: Pinia
- **网络请求**: uni-request 封装
- **后端框架**: ThinkPHP 8.0
- **数据库**: MySQL 8.0
- **缓存**: Redis
- **文件存储**: 阿里云OSS

### 项目结构
```
xiaomotui/
├── api/                      # ThinkPHP后端（不变）
│   ├── app/
│   ├── config/
│   └── database/
├── uni-app/                  # uni-app前端
│   ├── pages/               # 页面文件
│   │   ├── index/          # 首页
│   │   ├── nfc/            # NFC功能
│   │   ├── content/        # 内容生成
│   │   ├── publish/        # 发布管理
│   │   ├── user/           # 用户中心
│   │   └── merchant/       # 商家管理
│   ├── components/          # 组件
│   │   ├── common/         # 通用组件
│   │   └── business/       # 业务组件
│   ├── api/                # API接口
│   │   ├── request.js      # 请求封装
│   │   └── modules/        # 模块接口
│   ├── store/              # Pinia状态管理
│   │   ├── user.js         # 用户状态
│   │   └── app.js          # 应用状态
│   ├── utils/              # 工具函数
│   │   ├── auth.js         # 认证相关
│   │   ├── platform.js     # 平台判断
│   │   └── nfc.js          # NFC处理
│   ├── static/             # 静态资源
│   ├── uni_modules/        # uni插件
│   ├── App.vue             # 应用入口
│   ├── main.js             # 主入口
│   ├── manifest.json       # 配置文件
│   └── pages.json          # 页面路由
└── admin/                   # Vue管理后台（不变）
```

## 核心功能设计

### 1. 多平台兼容处理

#### 条件编译示例
```javascript
// utils/platform.js
export const triggerNFC = (deviceCode) => {
  // #ifdef H5
  // H5平台：使用Web NFC API或二维码
  if ('NDEFReader' in window) {
    return useWebNFC(deviceCode);
  } else {
    return useQRCode(deviceCode);
  }
  // #endif

  // #ifdef MP-WEIXIN
  // 微信小程序：使用HCE能力
  return uni.startHCE({
    aid_list: [deviceCode]
  });
  // #endif

  // #ifdef APP-PLUS
  // APP：使用原生NFC插件
  return plus.nfc.read(deviceCode);
  // #endif
}
```

### 2. 统一API封装

#### 请求封装
```javascript
// api/request.js
import { useUserStore } from '@/store/user'

class Request {
  constructor() {
    this.baseURL = process.env.VUE_APP_BASE_URL
    this.timeout = 30000
  }

  request(options) {
    const userStore = useUserStore()

    return new Promise((resolve, reject) => {
      uni.request({
        url: this.baseURL + options.url,
        method: options.method || 'GET',
        data: options.data,
        header: {
          'Authorization': `Bearer ${userStore.token}`,
          'Content-Type': 'application/json',
          ...options.header
        },
        timeout: this.timeout,
        success: (res) => {
          if (res.data.code === 200) {
            resolve(res.data.data)
          } else if (res.data.code === 401) {
            // Token过期，跳转登录
            uni.navigateTo({ url: '/pages/auth/login' })
            reject(res.data)
          } else {
            uni.showToast({
              title: res.data.message,
              icon: 'none'
            })
            reject(res.data)
          }
        },
        fail: reject
      })
    })
  }

  get(url, data) {
    return this.request({ url, data, method: 'GET' })
  }

  post(url, data) {
    return this.request({ url, data, method: 'POST' })
  }
}

export default new Request()
```

### 3. NFC功能适配

#### 多端NFC处理
```javascript
// utils/nfc.js
export class NFCManager {
  constructor() {
    this.platform = uni.getSystemInfoSync().platform
  }

  async init() {
    // #ifdef MP-WEIXIN
    // 微信小程序初始化HCE
    try {
      await uni.startHCE({
        aid_list: ['F0010203040506']
      })
      this.listenNFC()
    } catch (e) {
      console.error('NFC初始化失败', e)
    }
    // #endif

    // #ifdef H5
    // H5检测Web NFC支持
    if ('NDEFReader' in window) {
      this.reader = new NDEFReader()
      await this.reader.scan()
    }
    // #endif

    // #ifdef APP-PLUS
    // APP使用原生插件
    this.nfcModule = uni.requireNativePlugin('xiaomotui-nfc')
    this.nfcModule.init()
    // #endif
  }

  listenNFC() {
    // #ifdef MP-WEIXIN
    uni.onHCEMessage((res) => {
      this.handleNFCData(res.messageType, res.data)
    })
    // #endif

    // #ifdef H5
    if (this.reader) {
      this.reader.addEventListener('reading', ({ message }) => {
        this.handleNFCData('ndef', message)
      })
    }
    // #endif
  }

  handleNFCData(type, data) {
    // 统一处理NFC数据
    uni.navigateTo({
      url: `/pages/nfc/trigger?data=${encodeURIComponent(data)}`
    })
  }

  // 降级方案：扫码
  async scanQRCode() {
    // #ifdef MP-WEIXIN || APP-PLUS
    const res = await uni.scanCode()
    return res.result
    // #endif

    // #ifdef H5
    // H5使用扫码组件
    return this.useH5Scanner()
    // #endif
  }
}
```

### 4. 页面设计

#### 首页 (pages/index/index.vue)
```vue
<template>
  <view class="index-page">
    <!-- 顶部轮播 -->
    <swiper class="banner">
      <swiper-item v-for="item in banners" :key="item.id">
        <image :src="item.image" mode="aspectFill" />
      </swiper-item>
    </swiper>

    <!-- 功能入口 -->
    <view class="features">
      <view class="feature-item" @click="goNFC">
        <u-icon name="scan" size="48"></u-icon>
        <text>碰一碰</text>
      </view>
      <view class="feature-item" @click="goContent">
        <u-icon name="play-circle" size="48"></u-icon>
        <text>内容生成</text>
      </view>
      <view class="feature-item" @click="goPublish">
        <u-icon name="share" size="48"></u-icon>
        <text>一键发布</text>
      </view>
      <view class="feature-item" @click="goCoupon">
        <u-icon name="coupon" size="48"></u-icon>
        <text>优惠券</text>
      </view>
    </view>

    <!-- 最近生成 -->
    <view class="recent">
      <view class="title">最近生成</view>
      <content-list :list="recentList" />
    </view>
  </view>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useUserStore } from '@/store/user'
import { getHomeData } from '@/api/home'
import ContentList from '@/components/business/ContentList.vue'

const userStore = useUserStore()
const banners = ref([])
const recentList = ref([])

onMounted(async () => {
  // 检查登录状态
  if (!userStore.isLogin) {
    uni.navigateTo({ url: '/pages/auth/login' })
    return
  }

  // 加载首页数据
  const data = await getHomeData()
  banners.value = data.banners
  recentList.value = data.recent
})

const goNFC = () => {
  // 检测平台能力
  // #ifdef MP-WEIXIN
  uni.navigateTo({ url: '/pages/nfc/index' })
  // #endif

  // #ifdef H5
  if (!('NDEFReader' in window)) {
    uni.navigateTo({ url: '/pages/nfc/qrcode' })
  } else {
    uni.navigateTo({ url: '/pages/nfc/index' })
  }
  // #endif
}
</script>
```

#### NFC触发页 (pages/nfc/trigger.vue)
```vue
<template>
  <view class="nfc-trigger">
    <view class="status-card">
      <!-- 动画效果 -->
      <view class="animation" v-if="status === 'processing'">
        <u-loading-icon size="60" />
      </view>

      <!-- 成功提示 -->
      <view class="success" v-else-if="status === 'success'">
        <u-icon name="checkmark-circle-fill" size="80" color="#52c41a" />
        <text>生成成功！</text>
      </view>

      <!-- 进度显示 -->
      <view class="progress">
        <u-line-progress :percentage="progress" />
        <text>{{ statusText }}</text>
      </view>
    </view>

    <!-- 结果预览 -->
    <view class="result" v-if="result">
      <video v-if="result.type === 'VIDEO'"
             :src="result.url"
             controls />
      <view v-else class="text-content">
        {{ result.text }}
      </view>

      <!-- 操作按钮 -->
      <view class="actions">
        <u-button type="primary" @click="goPublish">
          一键发布
        </u-button>
        <u-button @click="regenerate">
          重新生成
        </u-button>
      </view>
    </view>
  </view>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { createContentTask, getTaskStatus } from '@/api/content'

const props = defineProps({
  deviceCode: String
})

const status = ref('processing')
const progress = ref(0)
const statusText = ref('正在生成中...')
const result = ref(null)
const taskId = ref(null)

onMounted(async () => {
  // 创建生成任务
  const task = await createContentTask({
    device_code: props.deviceCode,
    type: 'VIDEO'
  })
  taskId.value = task.id

  // 轮询状态
  pollStatus()
})

const pollStatus = () => {
  const timer = setInterval(async () => {
    const data = await getTaskStatus(taskId.value)
    progress.value = data.progress
    statusText.value = data.message

    if (data.status === 'COMPLETED') {
      clearInterval(timer)
      status.value = 'success'
      result.value = data.result
    } else if (data.status === 'FAILED') {
      clearInterval(timer)
      status.value = 'failed'
      uni.showToast({
        title: '生成失败',
        icon: 'error'
      })
    }
  }, 2000)
}
</script>
```

### 5. 商家管理端适配

#### 商家后台入口 (pages/merchant/index.vue)
```vue
<template>
  <view class="merchant-page">
    <!-- 数据概览 -->
    <view class="overview">
      <view class="stat-item">
        <text class="number">{{ stats.triggerCount }}</text>
        <text class="label">今日触发</text>
      </view>
      <view class="stat-item">
        <text class="number">{{ stats.contentCount }}</text>
        <text class="label">内容生成</text>
      </view>
      <view class="stat-item">
        <text class="number">{{ stats.publishCount }}</text>
        <text class="label">发布次数</text>
      </view>
    </view>

    <!-- 功能菜单 -->
    <u-cell-group>
      <u-cell title="设备管理" isLink
              @click="goPage('/pages/merchant/device')" />
      <u-cell title="模板管理" isLink
              @click="goPage('/pages/merchant/template')" />
      <u-cell title="优惠券管理" isLink
              @click="goPage('/pages/merchant/coupon')" />
      <u-cell title="数据统计" isLink
              @click="goPage('/pages/merchant/statistics')" />
    </u-cell-group>

    <!-- 在不同平台显示不同提示 -->
    <!-- #ifdef H5 -->
    <view class="tips">
      <text>建议使用电脑访问管理后台获得更好体验</text>
      <u-button size="small" @click="openAdminUrl">
        打开管理后台
      </u-button>
    </view>
    <!-- #endif -->
  </view>
</template>
```

## 状态管理设计

### 用户状态 (store/user.js)
```javascript
import { defineStore } from 'pinia'
import { login, getUserInfo } from '@/api/auth'

export const useUserStore = defineStore('user', {
  state: () => ({
    token: uni.getStorageSync('token') || '',
    userInfo: {},
    isLogin: false,
    isMerchant: false
  }),

  actions: {
    async login(code) {
      try {
        // 微信登录
        // #ifdef MP-WEIXIN
        const res = await login({ code })
        // #endif

        // H5手机号登录
        // #ifdef H5
        const res = await login({ phone: code })
        // #endif

        this.token = res.token
        this.userInfo = res.user
        this.isLogin = true
        this.isMerchant = res.user.role === 'merchant'

        // 持久化
        uni.setStorageSync('token', res.token)

        return res
      } catch (e) {
        console.error('登录失败', e)
        throw e
      }
    },

    logout() {
      this.token = ''
      this.userInfo = {}
      this.isLogin = false
      uni.removeStorageSync('token')
      uni.reLaunch({ url: '/pages/index/index' })
    }
  }
})
```

## 部署配置

### manifest.json 配置
```json
{
  "name": "小魔推",
  "appid": "__UNI__XIAOMOTUI",
  "versionName": "1.0.0",
  "versionCode": 100,

  // 小程序配置
  "mp-weixin": {
    "appid": "wx1234567890",
    "setting": {
      "urlCheck": false,
      "es6": true,
      "postcss": true
    },
    "permission": {
      "scope.userLocation": {
        "desc": "获取位置信息"
      }
    },
    "requiredPrivateInfos": ["getNFCAdapter"]
  },

  // H5配置
  "h5": {
    "publicPath": "/",
    "router": {
      "mode": "history",
      "base": "/"
    },
    "devServer": {
      "port": 8080,
      "proxy": {
        "/api": {
          "target": "http://localhost:8000",
          "changeOrigin": true
        }
      }
    }
  },

  // App配置
  "app-plus": {
    "modules": {
      "NFC": {}
    },
    "distribute": {
      "android": {
        "permissions": [
          "android.permission.NFC"
        ]
      }
    }
  }
}
```

### pages.json 路由配置
```json
{
  "pages": [
    {
      "path": "pages/index/index",
      "style": {
        "navigationBarTitleText": "小魔推"
      }
    },
    {
      "path": "pages/nfc/index",
      "style": {
        "navigationBarTitleText": "碰一碰"
      }
    },
    {
      "path": "pages/nfc/trigger",
      "style": {
        "navigationBarTitleText": "内容生成中"
      }
    },
    {
      "path": "pages/content/preview",
      "style": {
        "navigationBarTitleText": "内容预览"
      }
    },
    {
      "path": "pages/publish/index",
      "style": {
        "navigationBarTitleText": "发布管理"
      }
    },
    {
      "path": "pages/user/index",
      "style": {
        "navigationBarTitleText": "个人中心"
      }
    }
  ],

  "tabBar": {
    "color": "#666",
    "selectedColor": "#FF6B6B",
    "list": [
      {
        "pagePath": "pages/index/index",
        "text": "首页",
        "iconPath": "static/tabs/home.png",
        "selectedIconPath": "static/tabs/home-active.png"
      },
      {
        "pagePath": "pages/nfc/index",
        "text": "碰一碰",
        "iconPath": "static/tabs/nfc.png",
        "selectedIconPath": "static/tabs/nfc-active.png"
      },
      {
        "pagePath": "pages/publish/index",
        "text": "发布",
        "iconPath": "static/tabs/publish.png",
        "selectedIconPath": "static/tabs/publish-active.png"
      },
      {
        "pagePath": "pages/user/index",
        "text": "我的",
        "iconPath": "static/tabs/user.png",
        "selectedIconPath": "static/tabs/user-active.png"
      }
    ]
  },

  "globalStyle": {
    "navigationBarTextStyle": "black",
    "navigationBarTitleText": "小魔推",
    "navigationBarBackgroundColor": "#FFFFFF",
    "backgroundColor": "#F8F8F8"
  }
}
```

## 调试优化

### 1. 多端调试方案
```javascript
// utils/debug.js
const isDebug = process.env.NODE_ENV === 'development'

export const log = (...args) => {
  if (isDebug) {
    console.log('[XiaoMoTui]', ...args)
  }
}

export const showDebugPanel = () => {
  // #ifdef H5
  if (isDebug) {
    import('vconsole').then(({ default: VConsole }) => {
      new VConsole()
    })
  }
  // #endif
}

// 模拟NFC触发（开发调试用）
export const mockNFCTrigger = () => {
  // #ifdef H5
  window.mockNFC = (deviceCode) => {
    uni.navigateTo({
      url: `/pages/nfc/trigger?deviceCode=${deviceCode}`
    })
  }
  console.log('调试模式：使用 mockNFC("设备码") 模拟NFC触发')
  // #endif
}
```

### 2. 热重载配置
```javascript
// vite.config.js
export default {
  server: {
    hmr: true,
    port: 3000,
    proxy: {
      '/api': {
        target: 'http://localhost:8000',
        changeOrigin: true
      }
    }
  }
}
```

## 优势总结

1. **开发效率高** - 一套代码维护，减少开发成本
2. **调试方便** - HBuilderX提供完整调试工具，支持热重载
3. **平台兼容好** - 条件编译完美解决平台差异
4. **生态丰富** - 大量uni-app插件可用
5. **性能优秀** - 编译后性能接近原生

这个uni-app版本既解决了调试问题，又保证了多端兼容性，是小魔推项目的最佳选择！