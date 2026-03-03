# P2功能快速启动指南

## 一、快速配置(5分钟)

### 1.1 环境变量配置

```bash
# 进入API目录
cd api

# 复制环境变量模板
cp .env.example .env

# 编辑配置
vi .env
```

**必填配置项**:
```env
# 应用配置
APP_DEBUG=true
APP_TRACE=false

# 数据库配置
DATABASE_TYPE=mysql
DATABASE_HOSTNAME=127.0.0.1
DATABASE_DATABASE=xiaomotui
DATABASE_USERNAME=root
DATABASE_PASSWORD=your_password
DATABASE_PREFIX=xmt_
DATABASE_CHARSET=utf8mb4

# Redis配置
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=
REDIS_SELECT=0

# 告警配置(快速测试使用默认值)
DEVICE_ALERT_OFFLINE_THRESHOLD=5
DEVICE_ALERT_BATTERY_LOW=20
DEVICE_ALERT_BATTERY_CRITICAL=10
DEVICE_ALERT_WECHAT_ENABLED=false
DEVICE_ALERT_SMS_ENABLED=false
DEVICE_ALERT_EMAIL_ENABLED=false

# WebSocket配置
WEBSOCKET_ENABLED=true
WEBSOCKET_HOST=0.0.0.0
WEBSOCKET_PORT=9501
```

### 1.2 前端配置

```bash
# 进入前端目录
cd admin

# 复制环境变量
cp .env.example .env

# 编辑配置
vi .env
```

**必填配置项**:
```env
# API地址
VITE_API_BASE_URL=http://localhost/api

# WebSocket地址
VITE_WS_HOST=localhost
VITE_WS_PORT=9501
```

## 二、启动服务(3分钟)

### 2.1 启动后端

```bash
# 1. 启动Redis
redis-server

# 2. 启动WebSocket服务器(新建终端窗口)
cd api/public
php -t ws_server.php start

# 看到以下输出表示启动成功:
# WebSocket Server started on ws://0.0.0.0:9501
```

### 2.2 配置定时任务

```bash
# 编辑crontab
crontab -e

# 添加设备监控任务(每分钟执行)
* * * * * cd /www/xiaomotui/api && php think device:monitor:check >> /tmp/device_monitor.log 2>&1

# 保存并退出
```

### 2.3 启动前端

```bash
# 进入前端目录
cd admin

# 安装依赖(首次运行)
npm install

# 启动开发服务器
npm run dev

# 看到以下输出表示启动成功:
# VITE v4.x ready in xxx ms
# ➜ Local: http://localhost:5173/
```

## 三、快速验证(2分钟)

### 3.1 测试设备监控

```bash
# 触发设备监控检查
cd api
php think device:monitor:check

# 预期输出:
# ====================================
# 设备监控检查任务开始
# 时间: 2025-02-12 15:30:00
# ====================================
#
# 检查完成:
#   - 总设备数: 10
#   - 在线设备: 8
#   - 离线设备: 2
#   - 触发告警: 2
#   - 错误数量: 0
#   - 执行耗时: 45.32ms
#
# ✓ 任务执行成功
```

### 3.2 测试告警创建

```bash
# 模拟创建告警(使用ThinkPHP命令)
php think test:create-alert --level=critical --type=offline

# 或使用curl调用API
curl -X POST http://localhost/api/alerts/create \
  -H "Content-Type: application/json" \
  -d '{
    "device_id": 1,
    "merchant_id": 1,
    "alert_type": "offline",
    "alert_level": "critical",
    "alert_title": "测试告警",
    "alert_message": "这是一个测试告警"
  }'
```

### 3.3 测试WebSocket

**打开浏览器控制台(F12)**:
```javascript
// 1. 连接WebSocket
const ws = new WebSocket('ws://localhost:9501')

// 2. 监听事件
ws.onopen = () => console.log('✓ WebSocket已连接')
ws.onmessage = (e) => console.log('✓ 收到消息:', e.data)
ws.onerror = (e) => console.error('✗ 连接错误:', e)
ws.onclose = () => console.log('✗ 连接关闭')

// 3. 发送测试消息
ws.send(JSON.stringify({ type: 'ping' }))
```

**预期输出**:
```
✓ WebSocket已连接
✓ 收到消息: {"type":"pong","timestamp":1234567890}
```

### 3.4 测试数据分析API

```bash
# 测试用户画像
curl -H "Authorization: Bearer YOUR_TOKEN" \
  http://localhost/api/analysis/user-profile?id=1

# 测试活跃时段分析
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://localhost/api/analysis/active-hours?start_date=2025-01-01&end_date=2025-01-07"

# 测试转化漏斗
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "http://localhost/api/analysis/conversion-funnel?start_date=2025-01-01&end_date=2025-01-07"
```

### 3.5 测试前端页面

**1. 访问管理后台**:
```
http://localhost:5173
```

**2. 登录系统**:
- 账号: admin
- 密码: admin123

**3. 查看告警管理**:
- 点击左侧菜单 "设备管理" → "告警管理"
- 查看告警列表
- 测试筛选、查看详情、标记解决

**4. 查看数据分析**:
- 点击左侧菜单 "数据统计"
- 查看核心指标卡片
- 查看各种图表
- 测试日期范围筛选

## 四、验证检查清单

### 4.1 后端功能验证

- [ ] 设备监控任务正常运行(crontab -l查看)
- [ ] WebSocket服务器已启动(ps aux | grep ws_server)
- [ ] Redis连接正常(redis-cli ping)
- [ ] 告警记录正常写入数据库(select count(*) from xmt_device_alerts)
- [ ] 日志正常输出(tail -f api/runtime/log/$(date +%Y%m%d).log)

### 4.2 前端功能验证

- [ ] 页面正常加载,无404错误
- [ ] 告警列表显示正常数据
- [ ] WebSocket连接成功(查看Network→WS)
- [ ] 统计图表正常渲染
- [ ] 筛选和分页功能正常

### 4.3 实时推送验证

- [ ] 打开告警管理页面
- [ ] 触发一个新告警(通过API或命令)
- [ ] 页面自动刷新显示新告警
- [ ] 右上角弹出通知提示

## 五、常见问题排查

### 5.1 WebSocket连接失败

**问题**: WebSocket connection failed

**排查步骤**:
```bash
# 1. 检查WebSocket服务是否启动
ps aux | grep ws_server

# 2. 检查端口是否被占用
netstat -anp | grep 9501

# 3. 查看WebSocket日志
tail -f api/runtime/log/websocket.log

# 4. 检查防火墙
telnet localhost 9501
```

**解决方案**:
```bash
# 停止占用端口的进程
kill -9 $(lsof -ti:9501)

# 重新启动WebSocket服务
cd api/public && php ws_server.php start
```

### 5.2 告警通知未收到

**问题**: 触发告警但通知未送达

**排查步骤**:
```bash
# 1. 检查告警记录
mysql -uroot -p -e "SELECT * FROM xmt_device_alerts ORDER BY id DESC LIMIT 5"

# 2. 检查通知日志
tail -f api/runtime/log/$(date +%Y%m%d).log | grep "通知"

# 3. 检查Redis缓存
redis-cli
> KEYS notification:*
> GET notification_channels:merchant:1
```

**解决方案**:
```bash
# 确保环境变量配置正确
grep DEVICE_ALERT .env

# 清除频率控制缓存
redis-cli FLUSHDB

# 手动触发通知
php think notification:send --alert_id=123
```

### 5.3 数据分析页面无数据

**问题**: 统计图表显示为空

**排查步骤**:
```bash
# 1. 检查是否有测试数据
mysql -uroot -p -e "SELECT COUNT(*) FROM device_triggers"

# 2. 检查API返回
curl -H "Authorization: Bearer YOUR_TOKEN" \
  http://localhost/api/statistics/overview

# 3. 检查浏览器控制台
# F12 → Console → 查看是否有错误
```

**解决方案**:
```bash
# 创建测试数据
php think create:test-data --statistics=30days

# 清除分析缓存
redis-cli DEL user_behavior:*

# 重启前端开发服务器
cd admin && npm run dev
```

### 5.4 定时任务未执行

**问题**: 设备长时间离线但无告警

**排查步骤**:
```bash
# 1. 查看crontab
crontab -l

# 2. 检查日志
tail -f /tmp/device_monitor.log

# 3. 手动执行命令
php think device:monitor:check
```

**解决方案**:
```bash
# 添加crontab任务
crontab -e

# 输入以下内容:
* * * * * cd /www/xiaomotui/api && php think device:monitor:check >> /tmp/device_monitor.log 2>&1

# 保存后查看服务状态
service cron status
```

## 六、性能优化建议

### 6.1 数据库优化

```sql
-- 添加告警表索引
ALTER TABLE xmt_device_alerts ADD INDEX idx_merchant_status (merchant_id, status);
ALTER TABLE xmt_device_alerts ADD INDEX idx_create_time (create_time);
ALTER TABLE xmt_device_alerts ADD INDEX idx_level_status (alert_level, status);

-- 清理过期告警(30天前)
DELETE FROM xmt_device_alerts
WHERE status IN ('resolved', 'ignored')
AND resolve_time < DATE_SUB(NOW(), INTERVAL 30 DAY);
```

### 6.2 Redis优化

```bash
# 设置最大内存
redis-cli CONFIG SET maxmemory 2gb

# 设置淘汰策略
redis-cli CONFIG SET maxmemory-policy allkeys-lru

# 查看内存使用
redis-cli INFO memory
```

### 6.3 PHP-FPM优化

```ini
; /etc/php-fpm.d/www.conf

pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500

; 长时间执行限制
request_timeout = 60
```

## 七、监控和维护

### 7.1 日志监控

```bash
# 实时查看错误日志
tail -f api/runtime/log/$(date +%Y%m%d).log | grep ERROR

# 统计今日告警数量
grep "设备告警" api/runtime/log/$(date +%Y%m%d).log | wc -l

# 查看WebSocket连接日志
tail -f api/runtime/log/websocket.log
```

### 7.2 性能监控

```bash
# 查看PHP-FPM状态
curl http://localhost/status?json | jq

# 查看Redis信息
redis-cli INFO stats

# 查看MySQL状态
mysqladmin -uroot -p processlist
```

### 7.3 定期维护

**每日**:
- 检查告警处理情况
- 验证WebSocket连接数
- 查看错误日志

**每周**:
- 清理已解决告警(30天前)
- 分析告警趋势
- 优化数据库表

**每月**:
- 归档历史数据
- 性能评估和调优
- 容量规划

## 八、下一步

### 8.1 完善通知渠道

1. **微信通知**
   - 注册企业微信机器人
   - 获取webhook地址
   - 配置到.env

2. **短信通知**
   - 申请阿里云/腾讯云短信
   - 配置AccessKey和模板
   - 测试短信发送

3. **邮件通知**
   - 配置SMTP服务器
   - 设置发件人信息
   - 测试邮件发送

### 8.2 数据采集

1. **设备数据**
   - 部署更多测试设备
   - 模拟真实使用场景
   - 持续采集7天以上

2. **用户数据**
   - 模拟用户行为
   - 生成触发记录
   - 生成内容数据

### 8.3 功能迭代

1. **P3优先级**
   - 智能推荐系统
   - AI预测分析
   - 自动化运营

2. **性能优化**
   - 引入Elasticsearch
   - 使用ClickHouse分析
   - 实现消息队列

---

**文档版本**: v1.0
**最后更新**: 2025-02-12
**维护者**: 开发团队
