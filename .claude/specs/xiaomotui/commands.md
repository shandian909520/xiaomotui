# 小魔推碰一碰 - 任务执行命令

## 使用说明

本文档提供了所有84个开发任务的具体执行命令。每个命令都对应tasks.md中的一个原子任务。

## 命令格式说明

- `mkdir -p`: 创建目录（如不存在）
- `touch`: 创建空文件
- `composer`: PHP包管理器
- `php think`: ThinkPHP命令行工具
- `npm/yarn`: Node.js包管理器
- `uni`: uni-app命令行工具

---

## 阶段一：项目基础设施（8个任务）

### 任务1: 初始化ThinkPHP 8.0项目
```bash
# 创建项目根目录
mkdir -p xiaomotui
cd xiaomotui

# 使用Composer创建ThinkPHP项目
composer create-project topthink/think .

# 设置PHP版本要求
composer config platform.php 8.0
```

### 任务2: 配置数据库连接
```bash
# 复制环境配置文件
cp .example.env .env

# 编辑数据库配置（需手动修改）
echo "DATABASE_HOST=localhost" >> .env
echo "DATABASE_PORT=3306" >> .env
echo "DATABASE_NAME=xiaomotui" >> .env
echo "DATABASE_USERNAME=root" >> .env
echo "DATABASE_PASSWORD=" >> .env
echo "DATABASE_CHARSET=utf8mb4" >> .env
```

### 任务3: 创建用户表迁移文件
```bash
# 创建迁移目录
mkdir -p database/migrations

# 创建用户表迁移文件
php think make:migration create_users_table
```

### 任务4: 创建商家表迁移文件
```bash
php think make:migration create_merchants_table
```

### 任务5: 创建NFC设备表迁移文件
```bash
php think make:migration create_nfc_devices_table
```

### 任务6: 创建内容任务表迁移文件
```bash
php think make:migration create_content_tasks_table
```

### 任务7: 创建内容模板表迁移文件
```bash
php think make:migration create_content_templates_table
```

### 任务8: 执行数据库迁移
```bash
# 创建数据库 (MySQL 5.7兼容)
mysql -u root -p -e "CREATE DATABASE xiaomotui CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"

# 配置MySQL 5.7参数
mysql -u root -p xiaomotui -e "SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION';"

# 执行迁移
php think migrate:run

# 验证表创建
php think migrate:status
```

---

## 阶段二：认证系统核心（8个任务）

### 任务9: 创建JWT工具类
```bash
# 安装JWT库
composer require firebase/php-jwt

# 创建工具类目录
mkdir -p app/common

# 创建JWT工具类文件
touch app/common/JwtUtil.php
```

### 任务10: 创建响应格式化类
```bash
touch app/common/Response.php
```

### 任务11: 创建BaseController基类
```bash
# 创建控制器基类
touch app/controller/BaseController.php
```

### 任务12: 创建User模型
```bash
# 创建模型目录
mkdir -p app/model

# 创建User模型
php think make:model User
```

### 任务13: 创建AuthController控制器框架
```bash
php think make:controller Auth
```

### 任务14: 实现微信code换取openid
```bash
# 安装HTTP客户端
composer require guzzlehttp/guzzle

# 创建服务目录
mkdir -p app/service

# 创建微信服务类
touch app/service/WechatService.php
```

### 任务15: 实现登录接口逻辑
```bash
# 编辑AuthController文件（手动实现login方法）
echo "// 实现login方法" >> app/controller/AuthController.php
```

### 任务16: 创建认证中间件
```bash
# 创建中间件目录
mkdir -p app/middleware

# 创建认证中间件
php think make:middleware Auth
```

---

## 阶段三：NFC核心功能（7个任务）

### 任务17: 创建NfcDevice模型
```bash
php think make:model NfcDevice
```

### 任务18: 创建NfcController控制器框架
```bash
php think make:controller Nfc
```

### 任务19: 实现设备触发接口
```bash
# 编辑NfcController文件
echo "// 实现trigger方法" >> app/controller/NfcController.php
```

### 任务20: 创建触发记录模型
```bash
php think make:model DeviceTrigger
```

### 任务21: 实现设备状态上报
```bash
echo "// 实现deviceStatus方法" >> app/controller/NfcController.php
```

### 任务22: 实现设备配置获取
```bash
echo "// 实现getConfig方法" >> app/controller/NfcController.php
```

### 任务23: 创建设备异常告警服务
```bash
touch app/service/DeviceAlertService.php
```

---

## 阶段四：AI内容生成系统（9个任务）

### 任务24: 创建ContentTask模型
```bash
php think make:model ContentTask
```

### 任务25: 创建ContentTemplate模型
```bash
php think make:model ContentTemplate
```

### 任务26: 创建ContentController框架
```bash
php think make:controller Content
```

### 任务27: 实现内容生成任务创建
```bash
echo "// 实现generate方法" >> app/controller/ContentController.php
```

### 任务28: 创建AI服务接口类
```bash
mkdir -p app/service/ai
touch app/service/ai/AiServiceInterface.php
```

### 任务29: 实现百度文心一言服务
```bash
# 安装百度AI SDK
composer require baidu-aip/sdk

touch app/service/ai/BaiduAiService.php
```

### 任务30: 实现剪映视频生成服务
```bash
touch app/service/ai/JianyingService.php
```

### 任务31: 创建内容生成队列任务
```bash
# 创建命令目录
mkdir -p app/command

php think make:command ProcessContentTask
```

### 任务32: 实现任务状态查询
```bash
echo "// 实现taskStatus方法" >> app/controller/ContentController.php
```

---

## 阶段五：平台分发系统（8个任务）

### 任务33: 创建发布任务表迁移文件
```bash
php think make:migration create_publish_tasks_table
```

### 任务34: 创建平台账号表迁移文件
```bash
php think make:migration create_platform_accounts_table
```

### 任务35: 创建PublishTask模型
```bash
php think make:model PublishTask
```

### 任务36: 创建PlatformAccount模型
```bash
php think make:model PlatformAccount
```

### 任务37: 创建PublishController框架
```bash
php think make:controller Publish
```

### 任务38: 实现发布任务创建
```bash
echo "// 实现publish方法" >> app/controller/PublishController.php
```

### 任务39: 创建抖音发布服务
```bash
mkdir -p app/service/platform
touch app/service/platform/DouyinService.php
```

### 任务40: 创建定时发布命令
```bash
php think make:command ProcessPublishTask
```

---

## 阶段六：场景化营销功能（6个任务）

### 任务41: 创建优惠券表迁移文件
```bash
php think make:migration create_coupons_tables
```

### 任务42: 创建Coupon模型
```bash
php think make:model Coupon
```

### 任务43: 创建WiFi连接服务
```bash
touch app/service/WifiService.php
```

### 任务44: 实现团购跳转服务
```bash
touch app/service/GroupBuyService.php
```

### 任务45: 实现好友添加服务
```bash
touch app/service/ContactService.php
```

### 任务46: 实现桌号绑定服务
```bash
touch app/service/TableService.php
```

---

## 阶段七：AI素材库管理（5个任务）

### 任务47: 创建素材库表迁移文件
```bash
php think make:migration create_material_library_table
```

### 任务48: 创建素材导入服务
```bash
touch app/service/MaterialImportService.php
```

### 任务49: 创建内容审核服务
```bash
touch app/service/ContentAuditService.php
```

### 任务50: 创建素材推荐算法
```bash
touch app/service/MaterialRecommendService.php
```

### 任务51: 实现违规内容处理
```bash
touch app/service/ViolationService.php
```

---

## 阶段八：数据分析系统（6个任务）

### 任务52: 创建Statistics模型
```bash
php think make:model Statistics
```

### 任务53: 创建实时数据服务
```bash
touch app/service/RealtimeDataService.php
```

### 任务54: 创建用户行为分析服务
```bash
touch app/service/UserBehaviorService.php
```

### 任务55: 创建营销效果分析服务
```bash
touch app/service/MarketingAnalysisService.php
```

### 任务56: 创建智能建议服务
```bash
touch app/service/SmartSuggestionService.php
```

### 任务57: 创建异常预警服务
```bash
touch app/service/AnomalyAlertService.php
```

---

## 阶段九：商家管理功能（5个任务）

### 任务58: 创建Merchant模型
```bash
php think make:model Merchant
```

### 任务59: 创建MerchantController控制器
```bash
php think make:controller Merchant
```

### 任务60: 创建DeviceManageController
```bash
php think make:controller DeviceManage
```

### 任务61: 创建TemplateManageController
```bash
php think make:controller TemplateManage
```

### 任务62: 创建StatisticsController
```bash
php think make:controller Statistics
```

---

## 阶段十：uni-app跨平台前端（6个任务）

### 任务63: 初始化uni-app项目
```bash
# 全局安装HBuilderX或使用Vue CLI
npm install -g @vue/cli
vue create -p dcloudio/uni-preset-vue uni-app

# 或使用HBuilderX创建项目
# File -> New -> Project -> uni-app
```

### 任务64: 创建跨平台API请求封装
```bash
cd uni-app
mkdir -p api
touch api/request.js
```

### 任务65: 创建多平台授权登录页面
```bash
mkdir -p pages/auth
touch pages/auth/index.vue
```

### 任务66: 创建NFC触发页面
```bash
mkdir -p pages/nfc
touch pages/nfc/trigger.vue
```

### 任务67: 创建内容预览页面
```bash
mkdir -p pages/content
touch pages/content/preview.vue
```

### 任务68: 创建发布设置页面
```bash
mkdir -p pages/publish
touch pages/publish/setting.vue
```

---

## 阶段十一：管理后台核心（6个任务）

### 任务69: 初始化Vue管理后台
```bash
# 创建Vue 3项目
npm create vue@latest admin
cd admin
npm install

# 安装Element Plus
npm install element-plus
npm install @element-plus/icons-vue
```

### 任务70: 创建axios请求封装
```bash
# 安装axios
npm install axios

mkdir -p src/utils
touch src/utils/request.js
```

### 任务71: 创建登录页面组件
```bash
mkdir -p src/views/login
touch src/views/login/index.vue
```

### 任务72: 创建设备管理页面
```bash
mkdir -p src/views/device
touch src/views/device/index.vue
```

### 任务73: 创建数据统计页面
```bash
# 安装ECharts
npm install echarts

mkdir -p src/views/statistics
touch src/views/statistics/index.vue
```

### 任务74: 创建商家审核页面
```bash
mkdir -p src/views/merchant
touch src/views/merchant/audit.vue
```

---

## 阶段十二：系统集成测试（5个任务）

### 任务75: 创建API接口测试
```bash
# 安装PHPUnit
composer require --dev phpunit/phpunit

# 创建测试目录
mkdir -p tests/api
touch tests/api/AuthTest.php
```

### 任务76: 创建NFC功能测试
```bash
touch tests/api/NfcTest.php
```

### 任务77: 创建内容生成测试
```bash
touch tests/api/ContentTest.php
```

### 任务78: 创建性能基准测试
```bash
mkdir -p tests/benchmark
touch tests/benchmark/performance.php
```

### 任务79: 创建端到端测试
```bash
mkdir -p tests/e2e
touch tests/e2e/full_flow.php
```

---

## 阶段十三：部署与上线（5个任务）

### 任务80: 配置生产环境变量
```bash
# 创建生产环境配置
cp .env .env.production

# 创建部署目录
mkdir -p deploy
```

### 任务81: 创建数据库部署脚本
```bash
touch deploy/database.sh
chmod +x deploy/database.sh
```

### 任务82: 配置Nginx服务器
```bash
touch deploy/nginx.conf
```

### 任务83: 部署后端API服务
```bash
touch deploy/api_deploy.sh
chmod +x deploy/api_deploy.sh
```

### 任务84: 发布uni-app到各平台
```bash
# 在uni-app目录中构建
cd uni-app

# 构建H5版本
npm run build:h5

# 构建微信小程序版本
npm run build:mp-weixin

# 构建支付宝小程序版本
npm run build:mp-alipay

# 构建APP版本
npm run build:app
```

---

## 快速开始脚本

### 创建项目结构脚本
```bash
#!/bin/bash
# 文件名：setup_project.sh

echo "正在创建小魔推碰一碰项目结构..."

# 创建主目录
mkdir -p xiaomotui
cd xiaomotui

# 创建后端ThinkPHP项目
composer create-project topthink/think api
cd api
composer config platform.php 8.0

# 创建uni-app前端项目
cd ..
vue create -p dcloudio/uni-preset-vue uni-app

# 创建Vue管理后台
npm create vue@latest admin
cd admin
npm install
npm install element-plus axios echarts

# 返回主目录
cd ..

echo "项目结构创建完成！"
echo "后端API: ./api/"
echo "uni-app前端: ./uni-app/"
echo "管理后台: ./admin/"
```

### 安装依赖脚本
```bash
#!/bin/bash
# 文件名：install_dependencies.sh

echo "正在安装项目依赖..."

# 安装后端依赖
cd api
composer install
composer require firebase/php-jwt
composer require guzzlehttp/guzzle
composer require baidu-aip/sdk
composer require --dev phpunit/phpunit

# 安装前端依赖
cd ../uni-app
npm install

# 安装管理后台依赖
cd ../admin
npm install

echo "依赖安装完成！"
```

---

## 注意事项

1. **执行顺序**: 严格按照阶段顺序执行任务
2. **依赖检查**: 每个任务执行前检查前置依赖
3. **环境配置**: 确保PHP 8.0+、Node.js 16+、MySQL 5.7+已安装
4. **权限设置**: 部署脚本需要执行权限
5. **配置文件**: 手动编辑.env等配置文件中的敏感信息
6. **测试验证**: 每完成一个阶段进行功能测试

## MySQL 5.7 配置优化

### my.cnf 配置示例
```ini
[mysqld]
# 基础配置
character-set-server = utf8mb4
collation-server = utf8mb4_general_ci
default-time-zone = '+8:00'

# SQL模式配置
sql_mode = STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION

# InnoDB配置
innodb_file_per_table = 1
innodb_buffer_pool_size = 128M
innodb_log_file_size = 64M

# 连接配置
max_connections = 200
max_connect_errors = 1000

[client]
default-character-set = utf8mb4
```

### 数据库初始化脚本
```bash
#!/bin/bash
# 文件名：init_mysql57.sh

echo "正在初始化MySQL 5.7数据库..."

# 创建数据库
mysql -u root -p << EOF
CREATE DATABASE xiaomotui CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

-- 创建专用用户
CREATE USER 'xiaomotui'@'localhost' IDENTIFIED BY 'xiaomotui_2024';
GRANT ALL PRIVILEGES ON xiaomotui.* TO 'xiaomotui'@'localhost';

-- 设置数据库参数
USE xiaomotui;
SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION';

FLUSH PRIVILEGES;
EOF

echo "MySQL 5.7 数据库初始化完成！"
echo "数据库名: xiaomotui"
echo "用户名: xiaomotui"
echo "密码: xiaomotui_2024"
```

使用这些命令可以快速搭建小魔推碰一碰项目的完整架构。