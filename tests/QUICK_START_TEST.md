# 商家管理API测试快速指南

## 快速开始

### 1. 运行完整测试

```bash
# 进入项目根目录
cd D:\xiaomotui

# 运行测试脚本
php tests/merchant_api_test.php
```

### 2. 测试前准备

#### 2.1 确保API服务运行

```bash
# 启动ThinkPHP内置服务器
cd api
php think run -H localhost -p 8001
```

#### 2.2 检查数据库连接

确保`.env`文件中数据库配置正确：
```env
DATABASE_TYPE=mysql
DATABASE_HOSTNAME=127.0.0.1
DATABASE_DATABASE=xiaomotui
DATABASE_USERNAME=root
DATABASE_PASSWORD=your_password
```

#### 2.3 准备测试数据

确保数据库中有测试用户：
- 手机号: 13800138000
- 验证码: 123456 (测试环境固定)

### 3. 测试覆盖范围

#### 3.1 必测接口 (核心功能)
- ✅ 用户登录 (phone-login)
- ✅ 获取商家信息
- ✅ 更新商家信息
- ✅ 商家统计
- ✅ NFC设备列表
- ✅ 团购配置

#### 3.2 可选接口 (扩展功能)
- 模板管理 (CRUD)
- 优惠券管理 (CRUD)
- 设备统计
- 触发记录

### 4. 快速验证单个接口

使用curl快速测试：

```bash
# 1. 登录获取token
curl -X POST http://localhost:8001/api/auth/phone-login \
  -H "Content-Type: application/json" \
  -d '{"phone":"13800138000","code":"123456"}'

# 2. 获取商家信息 (替换YOUR_TOKEN)
curl -X GET http://localhost:8001/api/merchant/info \
  -H "Authorization: Bearer YOUR_TOKEN"

# 3. 获取设备列表
curl -X GET http://localhost:8001/api/nfc/devices \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### 5. Postman集合

导入Postman集合进行可视化测试：

1. 打开Postman
2. 导入 `tests/postman_collection.json` (待创建)
3. 设置环境变量：
   - `base_url`: http://localhost:8001
   - `token`: (登录后自动设置)

### 6. 常见问题排查

#### 问题1: 连接失败
```
错误: Failed to connect to localhost port 8001
解决: 确保API服务正在运行
```

#### 问题2: 401未授权
```
错误: 401 Unauthorized
解决: 检查token是否正确，是否过期
```

#### 问题3: 数据库连接失败
```
错误: SQLSTATE[HY000] [2002] Connection refused
解决: 检查MySQL服务是否启动，数据库配置是否正确
```

#### 问题4: 验证码错误
```
错误: 验证码不正确
解决: 确认测试环境验证码为 123456，或修改测试脚本
```

### 7. 测试报告解读

测试完成后会显示：

```
============================================================
  测试总结
============================================================

  总测试数: 35
  通过: 30
  失败: 5
  通过率: 85.71%

  ✓ 测试结果良好  (通过率 >= 80%)
  ⚠ 测试结果一般  (60% <= 通过率 < 80%)
  ✗ 测试结果较差  (通过率 < 60%)
```

### 8. 下一步行动

1. **查看测试结果**
   - 识别失败的测试
   - 分析失败原因

2. **修复问题**
   - 优先修复阻塞性问题
   - 优化性能问题

3. **重新测试**
   - 运行完整测试验证修复
   - 记录测试结果

4. **持续改进**
   - 添加更多测试用例
   - 完善错误处理
   - 优化性能

## 自动化测试集成

### CI/CD集成示例

```yaml
# .github/workflows/api-test.yml
name: API Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest

    steps:
    - uses: actions/checkout@v2

    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.1'

    - name: Start MySQL
      run: |
        sudo systemctl start mysql.service
        mysql -e 'CREATE DATABASE IF NOT EXISTS xiaomotui;' -uroot -proot

    - name: Run API Tests
      run: php tests/merchant_api_test.php

    - name: Upload Test Results
      if: always()
      uses: actions/upload-artifact@v2
      with:
        name: test-results
        path: tests/test-results.json
```

## 性能基准

### 响应时间要求

| 接口类型 | 响应时间要求 | 实际表现 |
|---------|-------------|----------|
| 简单查询 | < 200ms | ⏳ 待测试 |
| 列表查询 | < 500ms | ⏳ 待测试 |
| 数据更新 | < 300ms | ⏳ 待测试 |
| 统计查询 | < 1000ms | ⏳ 待测试 |
| 复杂操作 | < 2000ms | ⏳ 待测试 |

### 并发测试

```bash
# 使用Apache Bench进行并发测试
ab -n 1000 -c 10 -H "Authorization: Bearer YOUR_TOKEN" \
   http://localhost:8001/api/merchant/info
```

---

**文档版本**: v1.0
**最后更新**: 2026-01-25
