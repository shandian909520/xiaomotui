# 小魔推系统快速验证指南

## 测试准备

### 环境检查
```bash
# 1. 检查 API 服务
curl http://127.0.0.1:28080/api/health

# 2. 检查数据库连接
cd D:\xiaomotui\api
php think config

# 3. 检查 Redis 连接
redis-cli ping
```

---

## 核心功能测试

### 1. NFC 触发测试
```bash
# 测试设备触发
curl -X POST http://127.0.0.1:28080/api/nfc/trigger \
  -H "Content-Type: application/json" \
  -d '{
    "device_id": "TEST_DEVICE_001",
    "user_id": 1001,
    "trigger_time": "2026-02-12 10:30:00"
  }'
```

**预期结果**：返回优惠券或配置的内容

---

### 2. 管理员登录测试
```bash
# 测试管理员密码哈希验证
curl -X POST http://127.0.0.1:28080/api/auth/admin-login \
  -H "Content-Type: application/json" \
  -d '{
    "username": "admin",
    "password": "你的密码"
  }'
```

**检查点**：
- ✅ 不应再支持明文密码
- ✅ 未配置 ADMIN_PASSWORD_HASH 时应返回错误

---

### 3. 优惠券并发测试

使用 Apache Bench 或 JMeter 模拟并发：

```bash
# 10个并发用户同时领取优惠券
ab -n 10 -c 10 -p application/json \
  -T 'application/json' \
  -H "Authorization: Bearer YOUR_TOKEN" \
  http://127.0.0.1:28080/api/coupon/receive
```

**检查点**：
- ✅ 只有1个请求成功（库存为1时）
- ✅ 其他返回"优惠券已领完"

---

### 4. 性能测试

```bash
# 测试统计接口响应时间
curl -w "@curl-format.txt" -o /dev/null -s \
  "http://127.0.0.1:28080/api/statistics/overview?type=today"
```

**检查点**：
- ✅ 响应时间 < 500ms
- ✅ 不应出现 N+1 查询
```

curl-format.txt 内容：
time_namelookup:  %{time_namelookup}\n
time_connect:  %{time_connect}\n
time_appconnect:  %{time_appconnect}\n
time_pretransfer:  %{time_pretransfer}\n
time_starttransfer:  %{time_starttransfer}\n
time_total:    %{time_total}\n
```

---

## P0 修复验证

### 验证管理员密码修复
访问 `api/app/service/AuthService.php:88-99`，确认：
- [ ] 移除了 `$configPassword`
- [ ] 强制要求 `$passwordHash`
- [ ] 未配置时抛出异常

### 验证 WiFi 密码修复
访问 `api/app/model/NfcDevice.php:75-100`，确认：
- [ ] `getWifiPasswordAttr` 不再自动解密
- [ ] 添加了 `getDecryptedWifiPassword()` 方法
- [ ] API 响应中不返回 WiFi 密码

### 验证优惠券并发修复
检查是否有以下文件：
- [ ] `P0_FIX_CONCURRENCY_FIX.md` - 修复说明
- [ ] `NfcService_coupon_fix.php` - 修复代码示例

---

## 数据库性能检查

### 检查索引
```sql
-- 检查是否添加了建议的索引
SHOW INDEX FROM xmt_device_triggers;
SHOW INDEX FROM xmt_content_tasks;
SHOW INDEX FROM xmt_nfc_devices;
```

### 检查慢查询
```bash
# 查看 ThinkPHP 慢查询日志
tail -f D:\xiaomotui\api\runtime\log\sql.log

# 或启用 MySQL 慢查询日志
# 在 my.cnf 中设置：
# slow_query_log = /var/log/mysql/slow.log
# long_query_time = 1
```

---

## 前端测试

### 小程序测试
1. [ ] 扫描 NFC 设备
2. [ ] 领取优惠券
3. [ ] 查看统计数据
4. [ ] 测试微信登录

### 管理后台测试
1. [ ] 登录（验证密码哈希）
2. [ ] 查看设备列表
3. [ ] 查看统计数据（检查响应时间）
4. [ ] 创建优惠券

---

## 代码质量检查

### 运行 PHPStan（可选）
```bash
cd D:\xiaomotui\api
vendor/bin/phpstan analyse app
```

### 检查代码规范
```bash
# 检查 PSR-12 规范
vendor/bin/phpcs --standard=PSR12 app/
```

---

## 安全检查清单

- [ ] JWT 密钥长度 >= 32 位
- [ ] 所有用户输入都经过验证
- [ ] 敏感信息不在日志中
- [ ] WiFi 密码不在 API 响应中
- [ ] 优惠券领取有并发保护

---

## 测试结果记录

| 测试项 | 状态 | 问题 | 备注 |
|-------|------|------|------|
| NFC 触发 | ⏳ | | |
| 管理员登录 | ⏳ | | |
| 优惠券并发 | ⏳ | | |
| 性能测试 | ⏳ | | |
| 密码哈希 | ⏳ | | |
| WiFi 密码保护 | ⏳ | | |

---

**下次测试时间**: ___________
**测试人员**: ___________
