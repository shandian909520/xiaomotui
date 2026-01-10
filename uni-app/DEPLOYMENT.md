# 小魔推碰一碰 - 部署文档

本文档详细说明了小魔推碰一碰应用在H5、微信小程序、支付宝小程序三个平台的构建和发布流程。

## 目录

- [前置准备](#前置准备)
- [环境配置](#环境配置)
- [H5平台部署](#h5平台部署)
- [微信小程序部署](#微信小程序部署)
- [支付宝小程序部署](#支付宝小程序部署)
- [常见问题](#常见问题)

---

## 前置准备

### 1. 开发工具

- **HBuilderX**: 最新版本（推荐3.0+）
- **HBuilderX CLI**: 命令行构建工具
- **微信开发者工具**: 用于微信小程序上传
- **支付宝小程序开发者工具**: 用于支付宝小程序上传
- **Node.js**: v16+

### 2. 平台账号准备

#### 微信小程序
- 已注册的小程序账号
- 小程序AppID
- 管理员权限账号

#### 支付宝小程序
- 已注册的支付宝小程序账号
- 小程序AppID
- 管理员权限账号

#### H5部署
- 服务器或云存储（阿里云OSS、腾讯云COS等）
- 域名（已备案）
- SSL证书（HTTPS）

### 3. 代码准备

```bash
# 克隆代码仓库
git clone https://your-repo/xiaomotui.git
cd xiaomotui/uni-app

# 安装依赖（如果需要）
npm install

# 确保代码是最新的
git pull origin main
```

---

## 环境配置

### 1. 配置manifest.json

在构建前，需要在`manifest.json`中配置各平台的AppID：

**微信小程序AppID配置:**
```json
{
  "mp-weixin": {
    "appid": "你的微信小程序AppID"
  }
}
```

**支付宝小程序AppID配置:**
```json
{
  "mp-alipay": {
    "appid": "你的支付宝小程序AppID"
  }
}
```

### 2. 配置API接口地址

编辑 `config/env.js` 或使用环境配置脚本：

```bash
# 生成开发环境配置
node scripts/env-config.js development

# 生成预发布环境配置
node scripts/env-config.js staging

# 生成生产环境配置
node scripts/env-config.js production
```

手动配置示例：
```javascript
// config/env.js
export default {
  env: 'production',
  apiBaseUrl: 'https://api.xiaomotui.com',
  uploadUrl: 'https://api.xiaomotui.com/api/upload',
  wsUrl: 'wss://api.xiaomotui.com',
  h5Domain: 'https://h5.xiaomotui.com',
  enableDebug: false,
  enableVConsole: false
};
```

### 3. 版本号更新

在 `manifest.json` 中更新版本号：

```json
{
  "versionName": "1.0.1",
  "versionCode": "101"
}
```

**版本号规则：**
- `versionName`: 语义化版本（如：1.0.1）
- `versionCode`: 纯数字递增（如：101、102）

---

## H5平台部署

### 构建步骤

#### 方法1：使用构建脚本（推荐）

**Linux/Mac:**
```bash
cd uni-app
chmod +x scripts/build.sh
./scripts/build.sh h5
```

**Windows:**
```cmd
cd uni-app
scripts\build.bat h5
```

#### 方法2：使用HBuilderX

1. 在HBuilderX中打开uni-app项目
2. 点击菜单：`发行` -> `网站-H5手机版`
3. 选择发行目录，点击`发行`
4. 等待构建完成

### 部署步骤

构建完成后，`dist/h5` 目录包含所有静态文件。

#### 部署到Nginx服务器

```bash
# 1. 上传文件到服务器
scp -r dist/h5/* user@your-server:/var/www/xiaomotui/

# 2. 配置Nginx
# 编辑 /etc/nginx/sites-available/xiaomotui
server {
    listen 80;
    listen 443 ssl http2;
    server_name h5.xiaomotui.com;

    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;

    root /var/www/xiaomotui;
    index index.html;

    # 单页应用路由支持
    location / {
        try_files $uri $uri/ /index.html;
    }

    # API代理（可选）
    location /api/ {
        proxy_pass https://api.xiaomotui.com/api/;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }

    # 静态资源缓存
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}

# 3. 重启Nginx
sudo nginx -t
sudo systemctl reload nginx
```

#### 部署到阿里云OSS

```bash
# 安装ossutil
wget http://gosspublic.alicdn.com/ossutil/1.7.15/ossutil64
chmod 755 ossutil64

# 配置OSS
./ossutil64 config

# 上传文件
./ossutil64 cp -r dist/h5/ oss://your-bucket/h5/ --update

# 配置静态网站托管
# 在OSS控制台 -> 基础设置 -> 静态网站 中配置
# 默认首页: index.html
# 默认404页: index.html
```

### H5部署检查清单

- [ ] manifest.json中H5配置已更新
- [ ] API接口地址已配置为生产环境
- [ ] 构建成功，dist/h5目录生成
- [ ] 文件已上传到服务器/CDN
- [ ] Nginx配置正确，路由支持SPA
- [ ] HTTPS证书配置完成
- [ ] 域名DNS解析正确
- [ ] 浏览器访问测试通过
- [ ] 移动端适配测试通过
- [ ] 微信内置浏览器测试通过

---

## 微信小程序部署

### 构建步骤

#### 方法1：使用构建脚本（推荐）

**Linux/Mac:**
```bash
cd uni-app
./scripts/build.sh weixin
```

**Windows:**
```cmd
cd uni-app
scripts\build.bat weixin
```

#### 方法2：使用HBuilderX

1. 在HBuilderX中打开uni-app项目
2. 点击菜单：`发行` -> `小程序-微信`
3. 填写小程序名称和AppID
4. 点击`发行`，等待构建完成

### 上传审核步骤

#### 1. 使用微信开发者工具上传

```bash
# 1. 打开微信开发者工具
# 2. 导入项目，选择 dist/mp-weixin 目录
# 3. 项目类型选择"小程序"
# 4. 填入AppID

# 5. 上传代码
# 点击右上角"上传"按钮
# 填写版本号和项目备注
# 确认上传
```

#### 2. 在微信公众平台提交审核

1. 登录 [微信公众平台](https://mp.weixin.qq.com/)
2. 进入`开发管理` -> `版本管理`
3. 在"开发版本"中找到刚上传的版本
4. 点击`提交审核`

#### 3. 填写审核信息

**基本信息:**
- 版本号：与上传时一致
- 版本描述：本次更新的功能说明

**功能页面:**
- 添加首页：pages/index/index
- 添加NFC触发页：pages/nfc/trigger
- 添加内容生成页：pages/content/generate
- 添加用户中心：pages/user/profile

**配置项:**
- 服务类目：`工具 -> 生活工具`
- 标签：营销工具、NFC、内容生成

**测试账号:**
提供2-3个测试账号用于审核

#### 4. 等待审核

- 审核时间：一般1-3个工作日
- 可在`版本管理`中查看审核进度
- 审核通过后点击`发布`即可上线

### 微信小程序配置要点

#### 1. 服务器域名配置

在`开发` -> `开发管理` -> `开发设置` -> `服务器域名`中配置：

**request合法域名:**
```
https://api.xiaomotui.com
```

**uploadFile合法域名:**
```
https://api.xiaomotui.com
```

**downloadFile合法域名:**
```
https://api.xiaomotui.com
https://cdn.xiaomotui.com
```

**socket合法域名:**
```
wss://api.xiaomotui.com
```

#### 2. 业务域名配置

如果需要使用web-view嵌入H5页面：

```
https://h5.xiaomotui.com
```

#### 3. 隐私设置

在`设置` -> `隐私设置`中配置用户信息收集说明：

- 位置信息：用于查找附近商户
- 相册：用于上传图片素材
- 摄像头：用于拍摄照片和视频

### 微信小程序提交检查清单

- [ ] manifest.json中微信AppID已配置
- [ ] API接口地址已配置为生产环境
- [ ] 服务器域名已在微信公众平台配置
- [ ] 构建成功，dist/mp-weixin目录生成
- [ ] 使用开发者工具上传成功
- [ ] 版本号正确递增
- [ ] 隐私政策已配置
- [ ] 服务类目正确
- [ ] 功能页面配置完整
- [ ] 测试账号已提供
- [ ] 本地测试通过
- [ ] 体验版测试通过

---

## 支付宝小程序部署

### 构建步骤

#### 方法1：使用构建脚本（推荐）

**Linux/Mac:**
```bash
cd uni-app
./scripts/build.sh alipay
```

**Windows:**
```cmd
cd uni-app
scripts\build.bat alipay
```

#### 方法2：使用HBuilderX

1. 在HBuilderX中打开uni-app项目
2. 点击菜单：`发行` -> `小程序-支付宝`
3. 填写小程序名称和AppID
4. 点击`发行`，等待构建完成

### 上传审核步骤

#### 1. 使用支付宝小程序开发者工具上传

```bash
# 1. 打开支付宝小程序开发者工具
# 2. 选择"小程序"
# 3. 点击"打开"，选择 dist/mp-alipay 目录
# 4. 填入AppID

# 5. 上传代码
# 点击右上角"上传"按钮
# 填写版本号和版本描述
# 确认上传
```

#### 2. 在支付宝开放平台提交审核

1. 登录 [支付宝开放平台](https://open.alipay.com/)
2. 进入`开发管理` -> `版本管理`
3. 在"开发版本"中找到刚上传的版本
4. 点击`提交审核`

#### 3. 填写审核信息

**基本信息:**
- 版本号：与上传时一致
- 更新说明：本次更新的功能说明

**应用类目:**
- 一级类目：工具
- 二级类目：生活工具

**应用标签:**
营销、NFC、内容生成

**应用简介:**
小魔推碰一碰是基于NFC技术的智能营销内容生成平台，支持一碰即可生成专业探店视频和营销文案。

**应用截图:**
- 首页截图
- NFC触发截图
- 内容生成截图
- 用户中心截图

至少4张，每张不超过2MB

**测试账号:**
提供2-3个测试账号

#### 4. 等待审核

- 审核时间：一般1-3个工作日
- 可在`版本管理`中查看审核进度
- 审核通过后点击`上架`即可上线

### 支付宝小程序配置要点

#### 1. 服务器域名白名单

在`设置` -> `开发设置` -> `服务器域名白名单`中配置：

**HTTP请求域名:**
```
https://api.xiaomotui.com
```

**上传文件域名:**
```
https://api.xiaomotui.com
```

**下载文件域名:**
```
https://api.xiaomotui.com
https://cdn.xiaomotui.com
```

#### 2. 接口权限申请

在`设置` -> `功能管理`中申请以下权限：

- 获取会员信息
- 获取位置信息
- 选择图片
- 保存图片
- 拍照或从手机相册选择

#### 3. 隐私政策

配置用户信息使用说明：
- 地理位置：用于查找附近商户
- 相册：用于上传和保存图片素材

### 支付宝小程序提交检查清单

- [ ] manifest.json中支付宝AppID已配置
- [ ] API接口地址已配置为生产环境
- [ ] 服务器域名白名单已配置
- [ ] 构建成功，dist/mp-alipay目录生成
- [ ] 使用开发者工具上传成功
- [ ] 版本号正确递增
- [ ] 隐私政策已配置
- [ ] 应用类目正确
- [ ] 应用截图已上传
- [ ] 测试账号已提供
- [ ] 本地测试通过
- [ ] 体验版测试通过
- [ ] 接口权限已申请

---

## 全平台构建

如需一次性构建所有平台：

**Linux/Mac:**
```bash
./scripts/build.sh all
```

**Windows:**
```cmd
scripts\build.bat all
```

构建完成后，各平台产物位于：
- H5: `dist/h5/`
- 微信小程序: `dist/mp-weixin/`
- 支付宝小程序: `dist/mp-alipay/`

---

## 常见问题

### 1. HBuilderX CLI未找到

**问题:** 运行构建脚本提示"cli命令未找到"

**解决方案:**
```bash
# 方法1: 配置PATH环境变量
export PATH=$PATH:/Applications/HBuilderX.app/Contents/MacOS

# 方法2: 使用HBuilderX图形界面构建
```

### 2. 微信小程序域名未配置

**问题:** 小程序调用API时提示"不在以下 request 合法域名列表中"

**解决方案:**
1. 登录微信公众平台
2. 进入`开发` -> `开发管理` -> `开发设置`
3. 在"服务器域名"中添加API域名
4. 等待配置生效（约5分钟）

### 3. 支付宝小程序白名单问题

**问题:** 支付宝小程序网络请求失败

**解决方案:**
1. 登录支付宝开放平台
2. 进入`设置` -> `开发设置`
3. 在"服务器域名白名单"中添加API域名
4. 保存后重新构建上传

### 4. H5跨域问题

**问题:** H5版本调用API出现CORS错误

**解决方案:**
```nginx
# 在Nginx配置中添加CORS头
add_header Access-Control-Allow-Origin *;
add_header Access-Control-Allow-Methods 'GET, POST, PUT, DELETE, OPTIONS';
add_header Access-Control-Allow-Headers 'DNT,X-CustomHeader,Keep-Alive,User-Agent,X-Requested-With,If-Modified-Since,Cache-Control,Content-Type,Authorization';

if ($request_method = 'OPTIONS') {
    return 204;
}
```

### 5. 版本号不递增

**问题:** 小程序上传时提示"版本号必须大于线上版本"

**解决方案:**
1. 在manifest.json中递增versionCode
2. 重新构建并上传

### 6. 审核被拒

**常见被拒原因:**
- 服务类目选择不当
- 功能描述不清晰
- 缺少隐私政策
- 测试账号无法登录
- 功能与描述不符

**解决方案:**
1. 查看审核反馈
2. 根据反馈修改
3. 重新提交审核

### 7. 构建失败

**问题:** 构建过程中出现错误

**排查步骤:**
1. 检查manifest.json配置是否正确
2. 检查pages.json路由配置
3. 检查依赖是否完整
4. 清除缓存重新构建
5. 查看HBuilderX控制台错误信息

---

## 发布流程总结

### 完整发布步骤

```bash
# 1. 更新代码
git pull origin main

# 2. 更新版本号（manifest.json）
# versionName: "1.0.1" -> "1.0.2"
# versionCode: "101" -> "102"

# 3. 配置生产环境
node scripts/env-config.js production

# 4. 检查配置
# - manifest.json中的AppID
# - API接口地址
# - 域名白名单

# 5. 构建所有平台
./scripts/build.sh all

# 6. 部署H5
scp -r dist/h5/* user@server:/var/www/xiaomotui/

# 7. 上传微信小程序
# 使用微信开发者工具打开 dist/mp-weixin 并上传

# 8. 上传支付宝小程序
# 使用支付宝开发者工具打开 dist/mp-alipay 并上传

# 9. 提交审核
# 在各平台后台提交审核

# 10. 测试验证
# 体验版测试 -> 审核通过 -> 发布上线

# 11. 监控
# 关注用户反馈和错误日志
```

### 发布前最终检查清单

**代码层面:**
- [ ] 所有代码已提交到版本控制系统
- [ ] 版本号已正确更新
- [ ] 无console.log等调试代码
- [ ] 所有功能本地测试通过

**配置层面:**
- [ ] manifest.json各平台AppID已配置
- [ ] API接口地址为生产环境
- [ ] 域名白名单已配置
- [ ] 环境变量配置正确

**平台层面:**
- [ ] 服务器域名已备案
- [ ] HTTPS证书有效
- [ ] 小程序权限已申请
- [ ] 隐私政策已配置

**文档层面:**
- [ ] 更新日志已记录
- [ ] 用户手册已更新
- [ ] API文档已同步

---

## 相关资源

### 官方文档

- [uni-app官方文档](https://uniapp.dcloud.io/)
- [HBuilderX文档](https://hx.dcloud.net.cn/)
- [微信小程序文档](https://developers.weixin.qq.com/miniprogram/dev/framework/)
- [支付宝小程序文档](https://opendocs.alipay.com/mini)

### 开发工具下载

- [HBuilderX下载](https://www.dcloud.io/hbuilderx.html)
- [微信开发者工具](https://developers.weixin.qq.com/miniprogram/dev/devtools/download.html)
- [支付宝小程序开发者工具](https://opendocs.alipay.com/mini/ide/download)

### 平台入口

- [微信公众平台](https://mp.weixin.qq.com/)
- [支付宝开放平台](https://open.alipay.com/)

---

**文档版本:** v1.0.0
**最后更新:** 2025-10-01
**维护人员:** 开发团队
