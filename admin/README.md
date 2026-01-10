# 小魔推管理后台

基于 Vue 3 + Vite + Element Plus 的 NFC 智能营销管理系统后台。

## 技术栈

- **框架**: Vue 3.4+ (Composition API)
- **构建工具**: Vite 5.0+
- **路由**: Vue Router 4.2+
- **状态管理**: Pinia 2.1+
- **UI 框架**: Element Plus 2.5+
- **HTTP 客户端**: Axios 1.6+
- **图表库**: ECharts 5.4+ & Vue-ECharts 6.6+
- **样式**: Sass 1.69+

## 功能模块

### 核心功能
- 用户登录/登出
- 权限管理和路由守卫
- 仪表盘数据展示
- 响应式布局

### 业务模块
- **NFC 设备管理**: 设备列表、触发记录
- **内容管理**: AI 内容生成、模板管理
- **券码管理**: 券码发放、用户领取记录
- **商户管理**: 商户信息维护
- **系统管理**: 用户管理、系统设置

## 快速开始

### 环境要求
- Node.js >= 16
- npm >= 8 或 pnpm >= 8

### 安装依赖
```bash
npm install
# 或
pnpm install
```

### 开发模式
```bash
npm run dev
```
访问: http://localhost:3000

### 构建生产版本
```bash
npm run build
```

### 预览构建结果
```bash
npm run preview
```

## 项目结构

```
admin/
├── public/              # 静态资源
│   └── favicon.ico
├── src/
│   ├── api/            # API 接口定义
│   │   ├── index.js    # API 统一管理
│   │   └── modules/    # 模块化接口
│   ├── assets/         # 资源文件
│   │   ├── images/     # 图片资源
│   │   └── styles/     # 全局样式
│   │       └── main.scss
│   ├── components/     # 全局组件
│   │   └── index.js
│   ├── layout/         # 布局组件
│   │   ├── index.vue   # 主布局
│   │   ├── Header.vue  # 头部
│   │   ├── Sidebar.vue # 侧边栏
│   │   └── Main.vue    # 内容区
│   ├── router/         # 路由配置
│   │   └── index.js
│   ├── stores/         # Pinia 状态管理
│   │   ├── index.js    # Store 入口
│   │   ├── user.js     # 用户状态
│   │   └── app.js      # 应用状态
│   ├── utils/          # 工具函数
│   │   ├── request.js  # Axios 封装
│   │   ├── storage.js  # 本地存储
│   │   └── index.js    # 通用工具
│   ├── views/          # 页面组件
│   │   ├── login/      # 登录页
│   │   │   └── index.vue
│   │   └── dashboard/  # 仪表盘
│   │       └── index.vue
│   ├── App.vue         # 根组件
│   └── main.js         # 应用入口
├── .env.development    # 开发环境配置
├── .env.production     # 生产环境配置
├── .gitignore
├── index.html          # HTML 入口
├── package.json        # 项目配置
├── vite.config.js      # Vite 配置
└── README.md
```

## 环境变量

### 开发环境 (.env.development)
```env
VITE_APP_TITLE=小魔推管理后台
VITE_APP_BASE_API=http://localhost:8000
VITE_APP_PORT=3000
```

### 生产环境 (.env.production)
```env
VITE_APP_TITLE=小魔推管理后台
VITE_APP_BASE_API=https://api.xiaomotui.com
```

## 开发规范

### 代码风格
- 使用 Vue 3 Composition API
- 使用 `<script setup>` 语法糖
- 组件命名使用 PascalCase
- 文件命名使用 kebab-case 或 index.vue

### 路由配置
- 使用路由懒加载
- 配置 meta 信息（title、requiresAuth 等）
- 统一在 router/index.js 管理

### 状态管理
- 使用 Pinia 进行状态管理
- 按功能模块拆分 Store
- 使用 Composition API 风格

### API 调用
- 统一在 api/ 目录管理
- 使用封装的 request 实例
- 统一错误处理和 token 管理

## API 接口

后端 API 基础地址:
- 开发环境: `http://localhost:8000/api`
- 生产环境: `https://api.xiaomotui.com/api`

### 主要接口
- `POST /auth/login` - 用户登录
- `POST /auth/logout` - 用户登出
- `GET /auth/userinfo` - 获取用户信息
- `GET /nfc/devices` - NFC 设备列表
- `GET /content/tasks` - 内容任务列表
- `GET /coupons` - 券码列表
- `GET /stats/dashboard` - 仪表盘统计

详细 API 文档请参考后端项目文档。

## 部署

### Nginx 配置示例
```nginx
server {
    listen 80;
    server_name admin.xiaomotui.com;

    root /var/www/xiaomotui-admin;
    index index.html;

    location / {
        try_files $uri $uri/ /index.html;
    }

    location /api {
        proxy_pass http://localhost:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}
```

## 常见问题

### 1. 开发服务器无法启动
- 检查 Node.js 版本是否 >= 16
- 删除 node_modules 后重新安装依赖
- 检查端口 3000 是否被占用

### 2. API 请求失败
- 检查后端服务是否启动
- 检查 .env 文件中的 API 地址配置
- 查看浏览器控制台的网络请求详情

### 3. 路由跳转白屏
- 检查路由配置是否正确
- 确认组件路径是否存在
- 查看浏览器控制台的错误信息

## 贡献指南

欢迎提交 Issue 和 Pull Request！

## 许可证

MIT License

## 联系方式

- 项目地址: [GitHub](https://github.com/yourusername/xiaomotui)
- 问题反馈: [Issues](https://github.com/yourusername/xiaomotui/issues)
