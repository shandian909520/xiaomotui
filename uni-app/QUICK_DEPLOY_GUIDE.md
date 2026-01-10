# 快速部署指南

快速参考：小魔推碰一碰多平台构建和部署

---

## 🚀 快速构建

### 构建所有平台

**Linux/Mac:**
```bash
cd uni-app
chmod +x scripts/build.sh
./scripts/build.sh all
```

**Windows:**
```cmd
cd uni-app
scripts\build.bat all
```

### 构建单个平台

```bash
# H5
./scripts/build.sh h5

# 微信小程序
./scripts/build.sh weixin

# 支付宝小程序
./scripts/build.sh alipay
```

---

## 📝 构建前准备

### 1. 更新版本号

编辑 `manifest.json`:
```json
{
  "versionName": "1.0.2",  // 更新版本
  "versionCode": "102"     // 递增版本号
}
```

### 2. 配置AppID

**微信小程序:**
```json
{
  "mp-weixin": {
    "appid": "wx1234567890"  // 填写你的AppID
  }
}
```

**支付宝小程序:**
```json
{
  "mp-alipay": {
    "appid": "2021001234567890"  // 填写你的AppID
  }
}
```

### 3. 切换生产环境

```bash
node scripts/env-config.js production
```

---

## 🌐 H5部署

### 构建
```bash
./scripts/build.sh h5
```

### 部署到服务器
```bash
scp -r dist/h5/* user@server:/var/www/xiaomotui/
```

### Nginx最小配置
```nginx
server {
    listen 80;
    server_name h5.xiaomotui.com;
    root /var/www/xiaomotui;
    index index.html;

    location / {
        try_files $uri $uri/ /index.html;
    }
}
```

---

## 📱 微信小程序部署

### 1. 构建
```bash
./scripts/build.sh weixin
```

### 2. 上传
- 打开微信开发者工具
- 导入项目：选择 `dist/mp-weixin` 目录
- 点击右上角"上传"
- 填写版本号和备注

### 3. 提交审核
1. 登录 [微信公众平台](https://mp.weixin.qq.com/)
2. 开发管理 -> 版本管理
3. 找到开发版本，点击"提交审核"
4. 填写审核信息
5. 提交

### 必须配置的域名
- request: `https://api.xiaomotui.com`
- uploadFile: `https://api.xiaomotui.com`
- downloadFile: `https://api.xiaomotui.com`

---

## 🔵 支付宝小程序部署

### 1. 构建
```bash
./scripts/build.sh alipay
```

### 2. 上传
- 打开支付宝小程序开发者工具
- 打开项目：选择 `dist/mp-alipay` 目录
- 点击右上角"上传"
- 填写版本号和描述

### 3. 提交审核
1. 登录 [支付宝开放平台](https://open.alipay.com/)
2. 开发管理 -> 版本管理
3. 找到开发版本，点击"提交审核"
4. 填写审核信息
5. 提交

### 必须配置的域名
- HTTP请求: `https://api.xiaomotui.com`
- 上传文件: `https://api.xiaomotui.com`

---

## ⚡ 一键发布流程

```bash
# 1. 更新版本号（手动编辑manifest.json）

# 2. 配置生产环境
node scripts/env-config.js production

# 3. 构建所有平台
./scripts/build.sh all

# 4. H5部署
scp -r dist/h5/* user@server:/var/www/xiaomotui/

# 5. 使用开发者工具上传微信和支付宝小程序

# 6. 在各平台后台提交审核
```

---

## 🔧 常见问题快速解决

### CLI未找到
```bash
# 配置HBuilderX CLI到PATH
export PATH=$PATH:/Applications/HBuilderX.app/Contents/MacOS
```

### 域名未配置
- 微信：公众平台 -> 开发 -> 开发管理 -> 开发设置 -> 服务器域名
- 支付宝：开放平台 -> 设置 -> 开发设置 -> 服务器域名白名单

### 版本号错误
确保 versionCode 每次递增，大于线上版本

### 构建失败
1. 检查 manifest.json 语法
2. 检查 pages.json 配置
3. 清除缓存重试

---

## 📞 快速联系

- 完整文档：[DEPLOYMENT.md](./DEPLOYMENT.md)
- 检查清单：[SUBMISSION_CHECKLIST.md](./SUBMISSION_CHECKLIST.md)
- 技术支持：开发团队

---

**最后更新:** 2025-10-01
