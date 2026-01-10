# 小魔推碰一碰 - uni-app前端

## 项目说明

这是小魔推碰一碰项目的uni-app跨平台前端部分，支持微信小程序、支付宝小程序、H5等多个平台。

## 项目结构

```
uni-app/
├── pages/                  # 主包页面
│   ├── index/             # 首页
│   ├── nfc/               # NFC相关页面
│   ├── content/           # 内容相关页面
│   ├── publish/           # 发布相关页面
│   ├── material/          # 素材相关页面
│   ├── merchant/          # 商户相关页面
│   ├── statistics/        # 统计相关页面
│   └── user/              # 用户相关页面
├── pages-sub/             # 分包页面
│   ├── marketing/         # 营销功能分包
│   ├── dining/            # 餐饮功能分包
│   └── alert/             # 告警功能分包
├── components/            # 组件目录
├── static/                # 静态资源
│   ├── images/           # 图片资源
│   ├── tabbar/           # tabBar图标
│   └── styles/           # 全局样式
├── store/                 # 状态管理
├── utils/                 # 工具函数
├── api/                   # API接口
├── config/                # 配置文件
├── App.vue               # 应用入口
├── main.js               # 主入口文件
├── manifest.json         # 应用配置
└── pages.json            # 页面路由配置
```

## 核心配置说明

### manifest.json

应用配置文件，包含：
- 应用基本信息（appid、name、version等）
- 多平台编译配置（微信小程序、支付宝小程序、H5等）
- 权限配置
- SDK配置

### pages.json

页面路由配置文件，包含：
- 页面路径和样式配置
- 分包配置（subPackages）
- tabBar配置
- 全局样式配置
- 条件编译配置（condition）

### 页面结构

#### 主包页面
- **首页** (`pages/index/index`): 数据概览和快捷入口
- **NFC触发** (`pages/nfc/trigger`): NFC碰一碰触发页面
- **内容预览** (`pages/content/preview`): 生成内容预览
- **AI生成** (`pages/content/generate`): AI内容生成
- **发布设置** (`pages/publish/settings`): 发布平台设置
- **定时发布** (`pages/publish/schedule`): 定时发布任务管理
- **素材库** (`pages/material/list`): 素材列表
- **素材详情** (`pages/material/detail`): 素材详情
- **商户信息** (`pages/merchant/info`): 商户信息管理
- **设备管理** (`pages/merchant/devices`): NFC设备管理
- **数据统计** (`pages/statistics/overview`): 数据统计概览
- **数据分析** (`pages/statistics/analysis`): 数据分析详情
- **登录** (`pages/user/login`): 用户登录
- **个人中心** (`pages/user/profile`): 个人中心
- **系统设置** (`pages/user/settings`): 系统设置

#### 分包页面

**营销功能分包** (`pages-sub/marketing`)
- 优惠券管理
- 优惠券创建
- 团购活动
- 团购创建

**餐饮功能分包** (`pages-sub/dining`)
- 餐桌管理
- 就餐记录
- 就餐详情

**告警功能分包** (`pages-sub/alert`)
- 告警列表
- 告警详情
- 告警规则

### tabBar配置

底部导航栏包含4个tab：
1. **首页**: 数据概览和快捷功能
2. **素材**: 素材库管理
3. **数据**: 数据统计分析
4. **我的**: 个人中心

### 全局样式

全局样式定义在 `App.vue` 和 `static/styles/common.scss` 中，包含：
- 通用容器样式
- 通用按钮样式
- 通用卡片样式
- 通用列表样式
- Flex布局工具类
- 文本样式工具类
- 间距工具类

## 开发说明

### 环境要求
- HBuilderX 3.0+
- Node.js 12+
- 微信开发者工具（开发微信小程序）
- 支付宝开发者工具（开发支付宝小程序）

### 开发流程

1. **安装依赖**
```bash
# 如果使用npm
npm install

# 如果使用yarn
yarn install
```

2. **配置小程序appid**
在 `manifest.json` 中配置对应平台的appid：
```json
"mp-weixin": {
  "appid": "你的微信小程序appid"
}
```

3. **配置API地址**
在 `config/` 目录下配置API接口地址

4. **运行项目**
- 在HBuilderX中点击运行到对应平台
- 或使用命令行工具编译

### 注意事项

1. **平台差异**: 不同平台可能存在API差异，需要使用条件编译处理
2. **分包加载**: 分包页面会在需要时才加载，可以优化首次启动速度
3. **图标资源**: tabBar图标需要准备对应的图片资源
4. **权限申请**: 使用定位等功能需要申请用户授权

## API对接

API接口基础地址配置在 `config/` 目录，后端API地址：
- 开发环境: `http://localhost:8000`
- 生产环境: 需要配置实际域名

## 下一步工作

1. 创建各个页面的具体实现
2. 实现API接口调用封装
3. 实现状态管理（Vuex/Pinia）
4. 创建通用组件
5. 实现各个业务功能
6. 准备tabBar图标资源

## 联系方式

如有问题，请联系项目负责人。
