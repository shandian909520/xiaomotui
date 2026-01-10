# 实施计划

## 任务概述

小魔推碰一碰是一个基于NFC技术的智能营销内容生成平台。项目采用ThinkPHP 8.0框架，通过渐进式开发方式实现从NFC触发到AI内容生成再到全平台分发的完整链路。每个任务严格控制在15-30分钟内完成，确保开发进度可控。

## 技术规范遵循

严格遵循ThinkPHP 8.0开发规范，采用MVC架构模式，数据库操作使用Eloquent ORM，API接口遵循RESTful设计原则，响应格式统一使用JSON。

## 原子任务要求

**每个任务必须满足以下标准：**
- **文件范围**: 涉及1-3个相关文件
- **时间限制**: 15-30分钟内可完成
- **单一目标**: 一个可测试的输出结果
- **具体文件**: 必须指定确切的文件路径
- **清晰边界**: 明确的输入输出，最少的上下文切换

## 任务格式指南
- 使用复选框格式：`- [ ] 任务编号. 任务描述`
- **指定文件**：始终包含确切的文件路径
- **包含实施细节**：使用要点说明
- 使用以下格式引用需求：`_需求: X.Y_`
- 使用以下格式引用现有代码：`_复用: path/to/file_`
- 仅关注编码任务（无部署、用户测试等）

## 良好与不良任务示例

❌ **不良示例（过于宽泛）**：
- "实现认证系统"（影响多个文件，多个目的）
- "添加用户管理功能"（范围模糊，无文件规范）
- "构建完整仪表板"（过大，多个组件）

✅ **良好示例（原子化）**：
- "在app/model/User.php中创建User模型，包含openid/phone字段"
- "在app/common/JwtUtil.php中添加JWT生成方法，使用HS256算法"
- "在app/controller/AuthController.php中创建login方法，处理微信code换取token"

## 任务清单

### 阶段一：项目基础设施（8个任务）

- [x] 1. 初始化ThinkPHP 8.0项目
  - 文件：composer.json
  - 运行composer create-project topthink/think xiaomotui
  - 设置PHP版本要求为8.0+
  - 目的：创建项目基础结构
  - _需求: 基础设施_

- [x] 2. 配置数据库连接
  - 文件：.env, config/database.php
  - 设置MySQL连接参数（host、port、database、username、password）
  - 配置字符集为utf8mb4
  - 目的：建立数据库连接
  - _需求: 基础设施_

- [x] 3. 创建用户表迁移文件
  - 文件：database/migrations/20240101_create_users_table.php
  - 定义users表结构（id、openid、phone、nickname等字段）
  - 添加openid唯一索引
  - 目的：用户数据表结构
  - _需求: 3.1_

- [x] 4. 创建商家表迁移文件
  - 文件：database/migrations/20240101_create_merchants_table.php
  - 定义merchants表结构（id、user_id、name、category等字段）
  - 设置user_id外键关联
  - 目的：商家数据表结构
  - _需求: 5.1_

- [x] 5. 创建NFC设备表迁移文件
  - 文件：database/migrations/20240101_create_nfc_devices_table.php
  - 定义nfc_devices表结构（id、merchant_id、device_code等字段）
  - 设置device_code唯一索引
  - 目的：设备管理数据结构
  - _需求: 1.1_

- [x] 6. 创建内容任务表迁移文件
  - 文件：database/migrations/20240101_create_content_tasks_table.php
  - 定义content_tasks表结构（id、user_id、type、status等字段）
  - 添加status索引用于查询
  - 目的：内容生成任务管理
  - _需求: 2.1_

- [x] 7. 创建内容模板表迁移文件
  - 文件：database/migrations/20240101_create_content_templates_table.php
  - 定义content_templates表结构（id、name、type、content等字段）
  - 设置category索引
  - 目的：模板管理数据结构
  - _需求: 6.1_

- [x] 8. 执行数据库迁移
  - 文件：database/migrate_all.sh
  - 运行php think migrate:run
  - 验证所有表创建成功
  - 目的：创建数据库表结构
  - _需求: 基础设施_

### 阶段二：认证系统核心（8个任务）

- [x] 9. 创建JWT工具类
  - 文件：app/common/JwtUtil.php
  - 实现generate()方法生成token
  - 实现verify()方法验证token
  - 目的：JWT token管理
  - _需求: 认证系统_

- [x] 10. 创建响应格式化类
  - 文件：app/common/Response.php
  - 定义success($data)方法返回成功响应
  - 定义error($message, $code)方法返回错误响应
  - 目的：统一API响应格式
  - _需求: API规范_

- [x] 11. 创建BaseController基类
  - 文件：app/controller/BaseController.php
  - 继承think\Controller
  - 集成Response类方法
  - 目的：控制器基础封装
  - _需求: 基础设施_

- [x] 12. 创建User模型
  - 文件：app/model/User.php
  - 继承think\Model
  - 定义findByOpenid($openid)方法
  - 目的：用户数据操作封装
  - _需求: 3.1_
  - _复用: app/controller/BaseController.php_

- [x] 13. 创建AuthController控制器框架
  - 文件：app/controller/AuthController.php
  - 继承BaseController
  - 定义login()、refresh()、logout()方法签名
  - 目的：认证控制器框架
  - _需求: 认证系统_
  - _复用: app/controller/BaseController.php_

- [x] 14. 实现微信code换取openid
  - 文件：app/service/WechatService.php
  - 创建code2Session($code)方法
  - 调用微信API获取openid和session_key
  - 目的：微信登录服务
  - _需求: 认证系统_

- [x] 15. 实现登录接口逻辑
  - 文件：app/controller/AuthController.php（login方法）
  - 调用WechatService获取openid
  - 创建或更新用户记录
  - 生成JWT token返回
  - 目的：用户登录功能
  - _需求: 认证系统_
  - _复用: app/service/WechatService.php, app/common/JwtUtil.php_

- [x] 16. 创建认证中间件
  - 文件：app/middleware/Auth.php
  - 从请求头获取Authorization
  - 验证JWT token有效性
  - 注入用户信息到请求
  - 目的：接口认证保护
  - _需求: 认证系统_
  - _复用: app/common/JwtUtil.php_

### 阶段三：NFC核心功能（7个任务）

- [x] 17. 创建NfcDevice模型
  - 文件：app/model/NfcDevice.php
  - 继承think\Model
  - 定义findByCode($code)方法
  - 定义updateHeartbeat()方法
  - 目的：设备数据操作
  - _需求: 1.1_

- [x] 18. 创建NfcController控制器框架
  - 文件：app/controller/NfcController.php
  - 继承BaseController
  - 定义trigger()、deviceStatus()、getConfig()方法签名
  - 目的：NFC控制器框架
  - _需求: 1.1_
  - _复用: app/controller/BaseController.php_

- [x] 19. 实现设备触发接口
  - 文件：app/controller/NfcController.php（trigger方法）
  - 验证device_code参数
  - 查询设备配置
  - 返回触发响应
  - 目的：处理NFC触发
  - _需求: 1.2, 1.3_
  - _复用: app/model/NfcDevice.php_

- [x] 20. 创建触发记录模型
  - 文件：app/model/DeviceTrigger.php
  - 继承think\Model
  - 定义record()方法记录触发事件
  - 目的：触发数据记录
  - _需求: 7.1_

- [x] 21. 实现设备状态上报
  - 文件：app/controller/NfcController.php（deviceStatus方法）
  - 接收电量、信号强度参数
  - 更新设备在线状态
  - 记录心跳时间
  - 目的：设备监控功能
  - _需求: 1.1_
  - _复用: app/model/NfcDevice.php_

- [x] 22. 实现设备配置获取
  - 文件：app/controller/NfcController.php（getConfig方法）
  - 根据device_code查询配置
  - 包含商家信息
  - 返回触发模式设置
  - 目的：设备配置查询
  - _需求: 1.1_
  - _复用: app/model/NfcDevice.php_

- [x] 23. 创建设备异常告警服务
  - 文件：app/service/DeviceAlertService.php
  - 定义checkOffline()方法检测离线设备
  - 定义sendAlert()方法发送告警
  - 目的：设备异常监控
  - _需求: 5.5_

### 阶段四：AI内容生成系统（9个任务）

- [x] 24. 创建ContentTask模型
  - 文件：app/model/ContentTask.php
  - 继承think\Model
  - 定义状态常量（PENDING、PROCESSING、COMPLETED、FAILED）
  - 定义updateStatus()方法
  - 目的：任务数据管理
  - _需求: 2.1_

- [x] 25. 创建ContentTemplate模型
  - 文件：app/model/ContentTemplate.php
  - 继承think\Model
  - 定义getByCategory()方法
  - 定义increaseUsage()方法
  - 目的：模板数据管理
  - _需求: 6.1_

- [x] 26. 创建ContentController框架
  - 文件：app/controller/ContentController.php
  - 继承BaseController
  - 定义generate()、taskStatus()、templates()方法签名
  - 目的：内容控制器框架
  - _需求: 2.1_
  - _复用: app/controller/BaseController.php_

- [x] 27. 实现内容生成任务创建
  - 文件：app/controller/ContentController.php（generate方法）
  - 验证type、template_id参数
  - 创建任务记录
  - 触发异步生成
  - 目的：创建生成任务
  - _需求: 2.1_
  - _复用: app/model/ContentTask.php_

- [x] 28. 创建AI服务接口类
  - 文件：app/service/ai/AiServiceInterface.php
  - 定义generateText()接口方法
  - 定义generateVideo()接口方法
  - 定义analyzeScene()接口方法
  - 目的：AI服务规范
  - _需求: 2.1_

- [x] 29. 实现百度文心一言服务
  - 文件：app/service/WenxinService.php, config/ai.php, app/controller/AiContent.php
  - 实现完整的文心一言服务类，包含Token管理、智能提示词构建
  - 创建AI服务配置文件，支持多模型、多平台、多风格
  - 创建AiContent控制器，提供8个API接口
  - 添加路由配置和完整的测试脚本
  - 处理多种风格（温馨、时尚、文艺、潮流、高端、亲民）
  - 支持多平台（抖音、小红书、微信）适配
  - 目的：文案生成服务
  - _需求: 2.2, 2.3, 2.4_
  - _完成时间: 2025-09-30_

- [x] 30. 实现剪映视频生成服务
  - 文件：app/service/ai/JianyingService.php
  - 实现AiServiceInterface接口
  - 调用剪映API生成视频
  - 支持30秒内完成生成
  - 目的：视频生成服务
  - _需求: 2.1, 2.2_

- [x] 31. 创建内容生成队列任务
  - 文件：app/command/ProcessContentTask.php
  - 继承think\console\Command
  - 查询PENDING状态任务
  - 调用AI服务生成内容
  - 目的：异步任务处理
  - _需求: 2.1_
  - _复用: app/service/ai/BaiduAiService.php_

- [x] 32. 实现任务状态查询
  - 文件：app/controller/ContentController.php（taskStatus方法）
  - 根据task_id查询状态
  - 返回进度百分比
  - 包含生成结果URL
  - 目的：任务进度查询
  - _需求: 2.1_
  - _复用: app/model/ContentTask.php_

### 阶段五：平台分发系统（8个任务）

- [x] 33. 创建发布任务表迁移文件
  - 文件：database/migrations/20240102_create_publish_tasks_table.php
  - 定义publish_tasks表结构
  - 添加scheduled_time索引
  - 目的：发布任务数据结构
  - _需求: 3.1_

- [x] 34. 创建平台账号表迁移文件
  - 文件：database/migrations/20240102_create_platform_accounts_table.php
  - 定义platform_accounts表结构
  - 设置user_id和platform复合索引
  - 目的：平台授权数据结构
  - _需求: 3.1_

- [x] 35. 创建PublishTask模型
  - 文件：app/model/PublishTask.php
  - 继承think\Model
  - 定义getPendingTasks()方法
  - 定义updateResult()方法
  - 目的：发布任务管理
  - _需求: 3.1_

- [x] 36. 创建PlatformAccount模型
  - 文件：app/model/PlatformAccount.php
  - 继承think\Model
  - 定义refreshToken()方法
  - 定义isTokenValid()方法
  - 目的：平台账号管理
  - _需求: 3.1_

- [x] 37. 创建PublishController框架
  - 文件：app/controller/PublishController.php
  - 继承BaseController
  - 定义publish()、platformAuth()、authCallback()方法签名
  - 目的：发布控制器框架
  - _需求: 3.1_
  - _复用: app/controller/BaseController.php_

- [x] 38. 实现发布任务创建
  - 文件：app/controller/PublishController.php（publish方法）
  - 验证content_task_id存在
  - 支持选择多个平台
  - 支持定时发布设置
  - 目的：创建发布任务
  - _需求: 3.1, 3.3_
  - _复用: app/model/PublishTask.php_

- [x] 39. 创建抖音发布服务
  - 文件：app/service/platform/DouyinService.php
  - 实现uploadVideo()方法
  - 实现publish()方法
  - 适配抖音API规范
  - 目的：抖音发布功能
  - _需求: 3.1, 3.2_

- [x] 40. 创建定时发布命令
  - 文件：app/command/ProcessPublishTask.php
  - 继承think\console\Command
  - 查询到期的定时任务
  - 执行批量发布
  - 目的：定时发布处理
  - _需求: 3.3_
  - _复用: app/service/platform/DouyinService.php_

### 阶段六：场景化营销功能（6个任务）

- [x] 41. 创建优惠券表迁移文件
  - 文件：database/migrations/20240103_create_coupons_tables.php
  - 定义coupons和user_coupons表结构
  - 设置优惠券码唯一索引
  - 目的：优惠券数据结构
  - _需求: 4.4_

- [x] 42. 创建Coupon模型
  - 文件：app/model/Coupon.php
  - 继承think\Model
  - 定义checkAvailable()方法
  - 定义decrease()方法
  - 目的：优惠券数据管理
  - _需求: 4.4_

- [x] 43. 创建WiFi连接服务
  - 文件：app/service/WifiService.php
  - 定义generateConfig()方法生成WiFi配置
  - 支持免密连接协议
  - 目的：WiFi快速连接
  - _需求: 4.3_

- [x] 44. 实现团购跳转服务
  - 文件：app/service/GroupBuyService.php
  - 定义generateUrl()方法生成跳转链接
  - 支持美团、大众点评等平台
  - 目的：团购页面跳转
  - _需求: 4.1_

- [x] 45. 实现好友添加服务
  - 文件：app/service/ContactService.php
  - 定义getWechatQrcode()方法
  - 支持企业微信、个人微信
  - 目的：社交添加功能
  - _需求: 4.2_

- [x] 46. 实现桌号绑定服务
  - 文件：app/service/TableService.php
  - 定义bindTable()方法绑定桌号
  - 定义callService()方法呼叫服务
  - 目的：桌号服务功能
  - _需求: 4.5_

### 阶段七：AI素材库管理（5个任务）

- [x] 47. 创建素材库表迁移文件
  - 文件：database/migrations/20240104_create_material_library_table.php
  - 定义material_library表结构
  - 包含视频片段、音效、转场等字段
  - 目的：素材库数据结构
  - _需求: 6.1_

- [x] 48. 创建素材导入服务
  - 文件：app/service/MaterialImportService.php
  - 实现batchImport()方法批量导入
  - 支持视频、音频、图片格式
  - 目的：素材批量导入
  - _需求: 6.1_

- [x] 49. 创建内容审核服务
  - 文件：app/service/ContentAuditService.php
  - 实现autoAudit()自动审核
  - 实现manualAudit()人工审核
  - 目的：内容安全审核
  - _需求: 6.4_

- [x] 50. 创建素材推荐算法
  - 文件：app/service/MaterialRecommendService.php
  - 根据使用频率调整权重
  - 基于用户反馈优化推荐
  - 目的：智能素材推荐
  - _需求: 6.2_

- [x] 51. 实现违规内容处理
  - 文件：app/service/ViolationService.php
  - 定义blockContent()下架内容
  - 定义notifyMerchant()通知商家
  - 目的：违规内容管理
  - _需求: 6.5_

### 阶段八：数据分析系统（6个任务）

- [x] 52. 创建Statistics模型
  - 文件：app/model/Statistics.php
  - 继承think\Model
  - 定义aggregateByDate()方法
  - 定义getMetrics()方法
  - 目的：统计数据管理
  - _需求: 7.1_

- [x] 53. 创建实时数据服务
  - 文件：app/service/RealtimeDataService.php
  - 实现getTriggerCount()实时触发量
  - 实现getGenerationCount()生成量
  - 目的：实时数据统计
  - _需求: 7.1_

- [x] 54. 创建用户行为分析服务
  - 文件：app/service/UserBehaviorService.php
  - 实现getUserProfile()用户画像
  - 实现getUsagePattern()使用模式
  - 目的：用户行为分析
  - _需求: 7.2_

- [x] 55. 创建营销效果分析服务
  - 文件：app/service/MarketingAnalysisService.php
  - 计算内容传播指数
  - 计算转化率和ROI
  - 目的：营销效果评估
  - _需求: 7.3_

- [x] 56. 创建智能建议服务
  - 文件：app/service/SmartSuggestionService.php
  - 基于数据生成优化建议
  - 个性化营销策略推荐
  - 目的：智能营销建议
  - _需求: 7.4_

- [x] 57. 创建异常预警服务
  - 文件：app/service/AnomalyAlertService.php
  - 检测异常数据波动
  - 分析可能原因
  - 发送预警通知
  - 目的：数据异常监控
  - _需求: 7.5_

### 阶段九：商家管理功能（5个任务）

- [x] 58. 创建Merchant模型
  - 文件：app/model/Merchant.php
  - 继承think\Model
  - 定义审核状态管理方法
  - 定义关联用户关系
  - 目的：商家数据管理
  - _需求: 5.1_

- [x] 59. 创建MerchantController控制器
  - 文件：app/controller/MerchantController.php
  - 实现register()商家注册
  - 实现audit()商家审核
  - 实现profile()信息管理
  - 目的：商家管理接口
  - _需求: 5.1_
  - _复用: app/controller/BaseController.php_

- [x] 60. 创建DeviceManageController
  - 文件：app/controller/DeviceManageController.php
  - 实现list()设备列表
  - 实现bind()设备绑定
  - 实现config()设备配置
  - 目的：设备管理接口
  - _需求: 5.2_
  - _复用: app/model/NfcDevice.php_

- [x] 61. 创建TemplateManageController
  - 文件：app/controller/TemplateManageController.php
  - 实现create()创建模板
  - 实现edit()编辑模板
  - 实现preview()预览模板
  - 目的：模板管理接口
  - _需求: 5.3_
  - _复用: app/model/ContentTemplate.php_

- [x] 62. 创建StatisticsController
  - 文件：app/controller/StatisticsController.php
  - 实现overview()数据概览
  - 实现deviceStats()设备统计
  - 实现contentStats()内容统计
  - 目的：数据统计接口
  - _需求: 5.5_
  - _复用: app/model/Statistics.php_

### 阶段十：uni-app跨平台前端（6个任务）

- [x] 63. 初始化uni-app项目
  - 文件：uni-app/manifest.json, uni-app/pages.json
  - 配置应用信息和多平台设置
  - 定义页面路由结构
  - 设置tabBar导航和全局样式
  - 目的：uni-app基础配置
  - _需求: 前端基础_

- [x] 64. 创建跨平台API请求封装
  - 文件：uni-app/api/request.js
  - 封装uni.request统一接口
  - 条件编译处理不同平台差异
  - 自动携带token和错误处理
  - 目的：统一网络请求封装
  - _需求: 前端基础_

- [x] 65. 创建多平台授权登录页面
  - 文件：uni-app/pages/auth/index.vue
  - 使用条件编译处理微信/支付宝登录
  - 调用uni.login获取授权码
  - 集成Pinia状态管理存储用户信息
  - 目的：多平台登录功能
  - _需求: 认证系统_
  - _复用: uni-app/api/request.js_

- [x] 66. 创建NFC触发页面
  - 文件：uni-app/pages/nfc/trigger.vue
  - 处理NFC扫码和二维码扫描
  - 使用uView Plus组件展示进度
  - 实时查询任务状态更新
  - 目的：NFC触发功能界面
  - _需求: 1.1_
  - _复用: uni-app/api/request.js_

- [x] 67. 创建内容预览页面
  - 文件：uni-app/pages/content/preview.vue
  - 集成uView Plus视频播放组件
  - 支持H5/APP/小程序多端播放
  - 提供下载保存和平台分享功能
  - 目的：跨平台内容展示
  - _需求: 2.1_

- [x] 68. 创建发布设置页面
  - 文件：uni-app/pages/publish/setting.vue
  - 使用uView Plus表单组件
  - 动态显示可用平台选项
  - 集成日期时间选择器
  - 目的：发布配置界面
  - _需求: 3.1_

### 阶段十一：管理后台核心（6个任务）

- [x] 69. 初始化Vue管理后台
  - 文件：admin/package.json
  - 安装Vue 3.0和Element Plus
  - 配置vite构建工具
  - 设置代理到后端API
  - 目的：管理后台框架
  - _需求: 5.1_

- [x] 70. 创建axios请求封装
  - 文件：admin/src/utils/request.js
  - 配置axios拦截器
  - 自动添加token头
  - 处理401跳转登录
  - 目的：HTTP请求封装
  - _需求: 前端基础_

- [x] 71. 创建登录页面组件
  - 文件：admin/src/views/login/index.vue
  - 实现管理员登录表单
  - 调用登录接口
  - 存储token到localStorage
  - 目的：后台登录功能
  - _需求: 认证系统_
  - _复用: admin/src/utils/request.js_

- [x] 72. 创建设备管理页面
  - 文件：admin/src/views/device/index.vue
  - 展示设备表格列表
  - 显示在线状态指示
  - 提供配置编辑对话框
  - 目的：设备管理界面
  - _需求: 5.2_

- [x] 73. 创建数据统计页面
  - 文件：admin/src/views/statistics/index.vue
  - 集成ECharts图表库
  - 展示触发量趋势图
  - 显示转化率饼图
  - 目的：数据可视化
  - _需求: 5.5_

- [x] 74. 创建商家审核页面
  - 文件：admin/src/views/merchant/audit.vue
  - 展示待审核列表
  - 查看商家详情
  - 执行通过/拒绝操作
  - 目的：商家审核功能
  - _需求: 5.1_

### 阶段十二：系统集成测试（5个任务）

- [x] 75. 创建API接口测试
  - 文件：tests/api/AuthTest.php
  - 测试登录接口响应
  - 验证token生成正确
  - 测试token刷新功能
  - 目的：认证接口测试
  - _需求: 测试覆盖_

- [x] 76. 创建NFC功能测试
  - 文件：tests/api/NfcTest.php
  - 模拟设备触发请求
  - 验证任务创建成功
  - 测试设备状态更新
  - 目的：核心功能测试
  - _需求: 测试覆盖_

- [x] 77. 创建内容生成测试
  - 文件：tests/api/ContentTest.php
  - 测试任务创建接口
  - 模拟AI服务响应
  - 验证状态更新正确
  - 目的：生成功能测试
  - _需求: 测试覆盖_

- [x] 78. 创建性能基准测试
  - 文件：tests/benchmark/performance.php
  - 测试API响应时间
  - 验证并发处理能力
  - 检查内存使用情况
  - 目的：性能验证
  - _需求: 性能要求_

- [x] 79. 创建端到端测试
  - 文件：tests/e2e/full_flow.php
  - 模拟完整用户流程
  - 从NFC触发到内容发布
  - 验证数据一致性
  - 目的：流程完整性测试
  - _需求: 测试覆盖_

### 阶段十三：部署与上线（5个任务）

- [x] 80. 配置生产环境变量
  - 文件：.env.production
  - 设置生产数据库连接
  - 配置云服务密钥
  - 设置域名和CDN
  - 目的：生产环境配置
  - _需求: 部署准备_

- [x] 81. 创建数据库部署脚本
  - 文件：deploy/database.sh
  - 执行生产环境迁移
  - 初始化基础数据
  - 创建必要索引
  - 目的：数据库部署
  - _需求: 部署准备_

- [x] 82. 配置Nginx服务器
  - 文件：deploy/nginx.conf
  - 配置API反向代理
  - 设置静态文件服务
  - 配置SSL证书
  - 目的：Web服务器配置
  - _需求: 部署准备_

- [x] 83. 部署后端API服务
  - 文件：deploy/api_deploy.sh
  - 上传代码到服务器
  - 安装composer依赖
  - 配置定时任务
  - 目的：API服务部署
  - _需求: 部署准备_

- [x] 84. 发布uni-app到各平台
  - 文件：uni-app/manifest.json, uni-app/dist/
  - 构建H5、微信小程序、支付宝小程序
  - 配置各平台发布参数
  - 提交平台审核和上线
  - 目的：多平台应用发布
  - _需求: 前端发布_