# 端到端测试快速入门

## 5分钟快速开始

### 第1步: 准备环境

确保已安装：
- ✅ PHP 8.0+
- ✅ MySQL数据库
- ✅ Composer依赖

### 第2步: 配置数据库

编辑 `config.php`，确保数据库配置正确：

```php
'database' => [
    'hostname' => '127.0.0.1',
    'database' => 'xiaomotui_test',  // 使用测试数据库
    'username' => 'root',
    'password' => '',
],
```

### 第3步: 运行测试

**Windows用户:**
```bash
cd D:\xiaomotui\api\tests\e2e
run_e2e.bat
```

**Linux/Mac用户:**
```bash
cd /path/to/xiaomotui/api/tests/e2e
chmod +x run_e2e.sh
./run_e2e.sh
```

### 第4步: 查看报告

测试完成后，报告会自动保存在 `reports/` 目录。

选择查看报告时，会自动显示最新的测试结果。

## 测试内容

✅ **场景1**: 完整NFC到发布流程
✅ **场景2**: 多设备并发流程
✅ **场景3**: 错误处理流程
✅ **场景4**: 完整商家工作流
✅ **数据验证**: 5项一致性检查

## 预期结果

成功执行后，你将看到：

```
[测试场景汇总]
场景1: ✓ 通过
场景2: ✓ 通过
场景3: ✓ 通过
场景4: ✓ 通过

[总体结果]
成功率: 100%
状态: ✅ 所有测试通过
```

## 常见问题

**Q: 数据库连接失败？**
A: 检查config.php中的数据库配置

**Q: 权限错误？**
A: 确保reports目录可写

**Q: 测试数据会影响生产环境吗？**
A: 不会，测试数据有特殊前缀，且会自动清理

**Q: 如何保留测试数据？**
A: 在config.php中设置 `'cleanup' => ['enabled' => false]`

## 详细文档

查看 [README.md](README.md) 获取完整文档。

## 需要帮助？

- 查看 [README.md](README.md) 的故障排查部分
- 查看 [TASK_79_COMPLETION_SUMMARY.md](TASK_79_COMPLETION_SUMMARY.md) 了解技术细节
- 检查 `reports/` 目录中的测试报告
