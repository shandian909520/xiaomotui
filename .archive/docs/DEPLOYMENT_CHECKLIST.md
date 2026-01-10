# 安全修复部署验证清单

## 概述
本文档提供4个关键安全修复的部署步骤和验证方法。

---

## 一、部署前准备

### 1.1 环境检查
- [ ] PHP版本 >= 8.0
- [ ] MySQL版本 >= 5.7
- [ ] Redis已安装并运行
- [ ] ThinkPHP 8.0框架正常运行
- [ ] 备份当前数据库（重要！）

### 1.2 依赖检查
```bash
# 检查Redis是否运行
redis-cli ping
# 应返回: PONG

# 检查PHP encrypt/decrypt函数
php -r "echo function_exists('encrypt') ? 'OK' : 'Missing';"
```

---

## 二、WiFi密码加密部署（修复1）

### 2.1 代码已修改文件
- ✅ `api/app/model/NfcDevice.php` (加密存储访问器/修改器)
- ✅ `api/app/controller/Nfc.php` (加密传输handleWifiMode方法)

### 2.2 数据迁移（加密现有WiFi密码）

**步骤1：创建迁移脚本**
```php
<?php
// api/database/encrypt_wifi_passwords.php

require __DIR__ . '/../vendor/autoload.php';

use app\model\NfcDevice;

// 初始化框架
$app = require __DIR__ . '/../bootstrap.php';
$app->initialize();

echo "开始加密WiFi密码...\n";

$devices = NfcDevice::where('wifi_password', '<>', '')
    ->where('wifi_password', 'not null')
    ->select();

$count = 0;

foreach ($devices as $device) {
    try {
        // 检查是否已加密（尝试解密）
        $decrypted = decrypt($device->wifi_password);
        if ($decrypted) {
            echo "设备 {$device->device_code} 密码已加密，跳过\n";
            continue;
        }
    } catch (\Exception $e) {
        // 解密失败，说明是明文，需要加密
        $plainPassword = $device->getData('wifi_password'); // 获取原始值

        // 直接更新数据库（绕过模型访问器）
        \think\facade\Db::name('nfc_devices')
            ->where('id', $device->id)
            ->update(['wifi_password' => encrypt($plainPassword)]);

        $count++;
        echo "已加密设备 {$device->device_code} 的WiFi密码\n";
    }
}

echo "完成！共加密 {$count} 个设备的WiFi密码\n";
```

**步骤2：执行迁移**
```bash
cd D:\xiaomotui\api
php database/encrypt_wifi_passwords.php
```

### 2.3 验证WiFi加密

**测试1：数据库存储验证**
```sql
-- 查看加密后的数据（应该是乱码）
SELECT device_code, wifi_ssid, wifi_password
FROM nfc_devices
WHERE wifi_password IS NOT NULL
LIMIT 3;
```

**测试2：API传输验证**
```bash
# 触发WiFi模式的NFC设备
curl -X POST http://localhost/api/nfc/trigger \
  -H "Content-Type: application/json" \
  -d '{
    "device_code": "NFC001",
    "user_location": "{"latitude": 31.2304, "longitude": 121.4737}"
  }'

# 预期响应应包含加密的wifi_config字段：
# {
#   "code": 200,
#   "data": {
#     "action": "show_wifi",
#     "wifi_ssid": "Guest-WiFi",
#     "wifi_config": "eyJ...base64编码的加密数据...",
#     "expires_at": 1696123456,
#     "message": "WiFi连接信息（已加密传输）"
#   }
# }
```

**测试3：前端解密验证**
```javascript
// 前端解密示例（需要实现）
function decryptWifiConfig(encryptedConfig) {
    // 1. Base64解码
    const encrypted = atob(encryptedConfig);

    // 2. AES解密（需要与后端密钥一致）
    const decrypted = CryptoJS.AES.decrypt(encrypted, SECRET_KEY).toString(CryptoJS.enc.Utf8);

    // 3. 解析JSON
    const wifiConfig = JSON.parse(decrypted);

    // 4. 检查过期时间
    if (Date.now() / 1000 > wifiConfig.expires_at) {
        throw new Error('WiFi配置已过期');
    }

    return wifiConfig;
}
```

---

## 三、NFC频率限制部署（修复2）

### 3.1 代码已修改文件
- ✅ `api/app/controller/Nfc.php` (添加checkRateLimit方法和调用)

### 3.2 Redis配置检查
```bash
# 检查Redis连接配置
cat api/config/cache.php | grep redis

# 测试Redis写入
redis-cli set test_key "test_value"
redis-cli get test_key
redis-cli del test_key
```

### 3.3 验证频率限制

**测试1：IP限流测试（每分钟10次）**
```bash
# 使用脚本快速请求11次
for i in {1..11}; do
    echo "请求 $i:"
    curl -X POST http://localhost/api/nfc/trigger \
      -H "Content-Type: application/json" \
      -d '{"device_code": "NFC001"}' \
      -w "\nStatus: %{http_code}\n"
    sleep 1
done

# 预期：
# - 前10次请求成功（200）
# - 第11次请求被拦截（400或429）
# - 错误消息："触发过于频繁，请稍后再试（每分钟最多10次）"
```

**测试2：用户限流测试（每分钟30次）**
```bash
# 使用已登录用户Token快速请求31次
TOKEN="your_jwt_token_here"

for i in {1..31}; do
    echo "用户请求 $i:"
    curl -X POST http://localhost/api/nfc/trigger \
      -H "Content-Type: application/json" \
      -H "Authorization: Bearer $TOKEN" \
      -d '{"device_code": "NFC001"}' \
      -w "\nStatus: %{http_code}\n"
    sleep 0.5
done

# 预期：
# - 前30次成功
# - 第31次被拦截："触发过于频繁，请稍后再试（每分钟最多30次）"
```

**测试3：设备限流测试（每分钟100次）**
```bash
# 模拟多个用户触发同一设备
for i in {1..101}; do
    echo "设备请求 $i:"
    # 每次使用不同IP（需要代理或多台机器）
    curl -X POST http://localhost/api/nfc/trigger \
      -H "Content-Type: application/json" \
      -H "X-Forwarded-For: 192.168.1.$((i % 255))" \
      -d '{"device_code": "NFC001"}' \
      -w "\nStatus: %{http_code}\n"
    sleep 0.2
done

# 预期：
# - 前100次成功
# - 第101次被拦截："设备触发过于频繁，请稍后再试"
```

**测试4：Redis键检查**
```bash
# 查看限流记录
redis-cli keys "nfc_rate:*"

# 查看具体值
redis-cli get "nfc_rate:ip:127.0.0.1"
redis-cli get "nfc_rate:user:1"
redis-cli get "nfc_rate:device:NFC001"

# 检查过期时间（应该是60秒）
redis-cli ttl "nfc_rate:ip:127.0.0.1"
```

---

## 四、优惠券并发控制部署（修复3）

### 4.1 代码已修改文件
- ✅ `api/app/service/NfcService.php` (重写handleCouponTrigger方法)

### 4.2 验证并发安全

**测试1：创建测试优惠券**
```sql
-- 创建只有10张的优惠券
INSERT INTO coupons (
    merchant_id, title, description,
    discount_type, discount_value, min_amount,
    total_count, status,
    start_time, end_time,
    create_time, update_time
) VALUES (
    1, '并发测试券', '用于测试并发控制',
    'amount', 10.00, 50.00,
    10, 1,
    NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY),
    NOW(), NOW()
);
```

**测试2：并发抢券测试（关键！）**
```bash
# 创建并发测试脚本
cat > api/test_concurrent_coupon.php << 'EOF'
<?php
require __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Promise;

$client = new Client(['base_uri' => 'http://localhost']);

// 创建20个用户的Token（只有10张券）
$tokens = [
    'user1_token', 'user2_token', 'user3_token', 'user4_token', 'user5_token',
    'user6_token', 'user7_token', 'user8_token', 'user9_token', 'user10_token',
    'user11_token', 'user12_token', 'user13_token', 'user14_token', 'user15_token',
    'user16_token', 'user17_token', 'user18_token', 'user19_token', 'user20_token',
];

$promises = [];

// 同时发起20个请求
foreach ($tokens as $index => $token) {
    $promises[] = $client->postAsync('/api/nfc/trigger', [
        'headers' => [
            'Authorization' => "Bearer $token",
            'Content-Type' => 'application/json'
        ],
        'json' => [
            'device_code' => 'NFC_COUPON_001'
        ]
    ]);
}

// 等待所有请求完成
$results = Promise\Utils::settle($promises)->wait();

$success = 0;
$failed = 0;

foreach ($results as $index => $result) {
    if ($result['state'] === 'fulfilled') {
        $response = json_decode($result['value']->getBody(), true);

        if (isset($response['data']['status']) && $response['data']['status'] === 'new_received') {
            $success++;
            echo "用户" . ($index + 1) . " 抢券成功\n";
        } else {
            $failed++;
            echo "用户" . ($index + 1) . " 抢券失败: {$response['message']}\n";
        }
    } else {
        $failed++;
        echo "用户" . ($index + 1) . " 请求异常\n";
    }
}

echo "\n总结：成功 $success 次，失败 $failed 次\n";
echo "预期：成功应该正好10次（券数量），失败10次\n";

// 验证数据库库存
require __DIR__ . '/bootstrap.php';
\think\facade\Db::connect();

$coupon = \think\facade\Db::name('coupons')
    ->where('title', '并发测试券')
    ->find();

echo "数据库剩余库存：{$coupon['total_count']}（应该为0）\n";

$receivedCount = \think\facade\Db::name('coupon_users')
    ->where('coupon_id', $coupon['id'])
    ->count();

echo "实际发放数量：$receivedCount（应该正好为10）\n";
EOF

# 运行并发测试
php api/test_concurrent_coupon.php
```

**预期结果：**
- 成功领取：正好10次
- 失败领取：10次（"优惠券已抢完"）
- 数据库剩余库存：0
- 实际发放数量：10
- **不会出现超发**

**测试3：分布式锁验证**
```bash
# 查看锁记录（请求进行中时）
redis-cli keys "coupon_lock:*"

# 查看锁的值和过期时间
redis-cli get "coupon_lock:merchant:1"
redis-cli ttl "coupon_lock:merchant:1"
```

**测试4：数据库行锁验证**
```sql
-- 在并发测试期间，查看锁等待情况
SHOW ENGINE INNODB STATUS\G

-- 查看事务信息（应该看到FOR UPDATE锁）
SELECT * FROM information_schema.INNODB_TRX;
```

---

## 五、AI任务超时处理部署（修复4）

### 5.1 代码已修改文件
- ✅ `api/app/command/CheckTimeoutTask.php` (定时检查命令)
- ✅ `api/app/service/ContentService.php` (查询时检查)

### 5.2 注册定时命令

**步骤1：在ThinkPHP中注册命令**
```php
// api/config/console.php
<?php
return [
    'commands' => [
        'check:timeout-task' => 'app\\command\\CheckTimeoutTask',
    ],
];
```

**步骤2：测试命令执行**
```bash
cd D:\xiaomotui\api

# 手动执行一次
php think check:timeout-task

# 预期输出：
# 开始检查超时任务...
# 检查完成：共检查 X 个任务，发现 Y 个超时任务
```

### 5.3 配置Crontab定时任务

**Linux/macOS系统：**
```bash
# 编辑crontab
crontab -e

# 添加以下行（每5分钟执行一次）
*/5 * * * * cd /path/to/xiaomotui/api && php think check:timeout-task >> /var/log/timeout_task.log 2>&1
```

**Windows系统（任务计划程序）：**
```powershell
# 创建计划任务（每5分钟）
$action = New-ScheduledTaskAction -Execute "php.exe" `
    -Argument "think check:timeout-task" `
    -WorkingDirectory "D:\xiaomotui\api"

$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) `
    -RepetitionInterval (New-TimeSpan -Minutes 5)

Register-ScheduledTask -TaskName "NFC_CheckTimeoutTask" `
    -Action $action -Trigger $trigger `
    -Description "检查AI内容生成任务超时"
```

### 5.4 验证超时检测

**测试1：创建超时任务**
```sql
-- 手动创建一个超时的processing任务（15分钟前）
INSERT INTO content_tasks (
    user_id, device_id, type, status,
    prompt, create_time, update_time
) VALUES (
    1, 1, 'video', 'processing',
    '测试超时任务',
    DATE_SUB(NOW(), INTERVAL 15 MINUTE),
    DATE_SUB(NOW(), INTERVAL 15 MINUTE)
);

-- 记录任务ID
SELECT LAST_INSERT_ID();
```

**测试2：执行定时任务**
```bash
# 手动执行检查
php think check:timeout-task

# 检查日志
tail -f runtime/log/202410/03.log | grep "内容生成任务超时"
```

**测试3：验证任务状态更新**
```sql
-- 查询刚才创建的任务（应该已被标记为failed）
SELECT id, status, error_message, update_time
FROM content_tasks
WHERE id = <刚才的任务ID>;

-- 预期：
-- status = 'failed'
-- error_message = '任务处理超时（900秒），已自动标记为失败'
```

**测试4：查询时实时检测**
```bash
# 创建一个processing任务（11分钟前）
mysql> INSERT INTO content_tasks (user_id, device_id, type, status, prompt, create_time, update_time)
       VALUES (1, 1, 'video', 'processing', '查询时检测',
       DATE_SUB(NOW(), INTERVAL 11 MINUTE), DATE_SUB(NOW(), INTERVAL 11 MINUTE));

# 通过API查询任务状态
curl -X GET http://localhost/api/content/task-status/<task_id> \
  -H "Authorization: Bearer <user_token>"

# 预期响应：
# {
#   "code": 200,
#   "data": {
#     "task_id": "xxx",
#     "status": "failed",
#     "error_message": "任务处理超时（660秒），已自动标记为失败",
#     ...
#   }
# }
```

---

## 六、综合验证

### 6.1 日志检查
```bash
# 查看应用日志
tail -f api/runtime/log/202410/03.log

# 应该看到以下日志：
# - [warning] NFC触发IP频率超限
# - [info] 用户领取优惠券成功
# - [warning] 内容生成任务超时
# - [info] 超时任务检查完成
```

### 6.2 性能监控
```bash
# 查看Redis内存使用
redis-cli info memory

# 查看Redis键数量
redis-cli dbsize

# 查看MySQL慢查询
mysql> SHOW VARIABLES LIKE 'slow_query_log';
mysql> SHOW VARIABLES LIKE 'long_query_time';
```

### 6.3 安全审计
```bash
# 检查WiFi密码加密情况
mysql> SELECT COUNT(*) as total,
       SUM(CASE WHEN wifi_password LIKE '{%' THEN 1 ELSE 0 END) as encrypted
       FROM nfc_devices
       WHERE wifi_password IS NOT NULL;

# 预期：encrypted = total（全部加密）
```

---

## 七、回滚方案

### 7.1 代码回滚
```bash
# 使用git回滚到修复前的版本
git log --oneline -10
git revert <commit_hash>
```

### 7.2 数据回滚
```bash
# 恢复数据库备份
mysql -u root -p xiaomotui < backup_before_security_fix.sql

# 清理Redis缓存
redis-cli FLUSHDB
```

### 7.3 关闭定时任务
```bash
# Linux/macOS
crontab -e
# 注释或删除对应行

# Windows
Unregister-ScheduledTask -TaskName "NFC_CheckTimeoutTask" -Confirm:$false
```

---

## 八、上线检查清单

### 部署前
- [ ] 所有测试通过
- [ ] 数据库已备份
- [ ] Redis正常运行
- [ ] 代码已通过Code Review

### 部署中
- [ ] WiFi密码迁移脚本执行成功
- [ ] Crontab定时任务已配置
- [ ] Redis限流键正常写入
- [ ] 日志记录正常

### 部署后
- [ ] API响应正常
- [ ] 无错误日志
- [ ] 性能无明显下降
- [ ] 用户体验正常
- [ ] 监控告警正常

---

## 九、监控指标

### 关键指标
1. **WiFi加密覆盖率**：100%
2. **NFC触发频率**：IP < 10/min，User < 30/min，Device < 100/min
3. **优惠券超发率**：0%
4. **任务超时检测率**：100%（所有超时任务被检测）

### 告警阈值
- 频率限制触发次数 > 100次/小时 → 疑似攻击
- 优惠券库存异常（< 0） → 立即告警
- 超时任务 > 10个 → AI服务异常
- Redis连接失败 → 立即告警

---

## 十、常见问题

### Q1: WiFi密码迁移失败怎么办？
**A:** 检查encrypt函数是否可用，确保ThinkPHP配置文件中有加密密钥。

### Q2: Redis连接失败导致限流失效？
**A:** 修复方案：
1. 立即重启Redis
2. 或临时降级为Session限流
3. 监控Redis可用性

### Q3: 优惠券并发测试失败（超发）？
**A:** 检查：
1. Redis分布式锁是否生效
2. MySQL事务隔离级别（应为REPEATABLE-READ）
3. 是否正确使用了`lock(true)`和`dec()`

### Q4: 定时任务未执行？
**A:** 检查：
1. Crontab配置是否正确
2. PHP路径是否正确
3. 工作目录是否正确
4. 查看系统日志：`/var/log/syslog`或`/var/log/cron`

---

## 联系方式
如有问题，请联系技术支持或查看详细文档：
- 技术文档：`CRITICAL_FIXES_SUMMARY.md`
- 优化建议：`OPTIMIZATION_RECOMMENDATIONS.md`
- 项目手册：`PROJECT_USAGE_GUIDE.md`

---

**最后更新时间：** 2025-10-03
**文档版本：** v1.0
