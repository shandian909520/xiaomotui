# 定时发布任务 Cron 设置文档

## 概述

本文档描述如何设置定时任务（cron job）来自动执行定时发布任务。`publish:scheduled` 命令会查找所有已到达发布时间的待发布任务，并自动执行发布操作。

## 命令说明

### 基本命令

```bash
php think publish:scheduled
```

### 命令选项

| 选项 | 简写 | 说明 | 默认值 |
|------|------|------|--------|
| `--limit` | `-l` | 单次处理的最大任务数 | 50 |
| `--dry-run` | - | 试运行模式（不实际发布） | false |
| `--verbose` | `-v` | 显示详细输出 | false |

### 使用示例

```bash
# 基本使用
php think publish:scheduled

# 限制处理数量
php think publish:scheduled --limit=20

# 试运行模式（测试用）
php think publish:scheduled --dry-run

# 详细输出模式
php think publish:scheduled -v

# 组合使用
php think publish:scheduled --limit=10 --dry-run -v
```

## Cron 配置

### Linux/Unix 系统

#### 1. 编辑 crontab

```bash
crontab -e
```

#### 2. 添加定时任务

##### 每 5 分钟执行一次（推荐）

```bash
*/5 * * * * cd /path/to/project/api && php think publish:scheduled >> /var/log/scheduled-publish.log 2>&1
```

##### 每分钟执行一次（实时性要求高）

```bash
* * * * * cd /path/to/project/api && php think publish:scheduled >> /var/log/scheduled-publish.log 2>&1
```

##### 每 10 分钟执行一次

```bash
*/10 * * * * cd /path/to/project/api && php think publish:scheduled >> /var/log/scheduled-publish.log 2>&1
```

##### 工作时间内每 5 分钟执行一次（9:00-18:00）

```bash
*/5 9-18 * * * cd /path/to/project/api && php think publish:scheduled >> /var/log/scheduled-publish.log 2>&1
```

#### 3. 验证 cron 配置

```bash
# 查看当前用户的 crontab
crontab -l

# 查看 cron 服务状态
sudo systemctl status cron

# 查看 cron 日志
sudo tail -f /var/log/syslog | grep CRON
```

### Windows 系统

#### 使用任务计划程序

1. 打开"任务计划程序"（Task Scheduler）
2. 点击"创建基本任务"
3. 设置任务名称：`定时发布任务`
4. 触发器：选择"每天"或"重复"
5. 操作：启动程序
   - 程序/脚本：`D:\php\php.exe`（你的 PHP 路径）
   - 添加参数：`think publish:scheduled`
   - 起始于：`D:\xiaomotui\api`（项目路径）
6. 高级设置：勾选"每隔 5 分钟重复一次"

#### 使用批处理脚本

创建 `scheduled_publish.bat` 文件：

```batch
@echo off
cd /d D:\xiaomotui\api
php think publish:scheduled >> D:\logs\scheduled-publish.log 2>&1
```

然后在任务计划程序中执行此批处理文件。

## 日志管理

### 日志位置

- **应用日志**：`runtime/log/` 目录下的日志文件
- **Cron 日志**：根据配置输出到指定文件（如 `/var/log/scheduled-publish.log`）

### 日志轮转配置

创建 logrotate 配置文件：`/etc/logrotate.d/scheduled-publish`

```
/var/log/scheduled-publish.log {
    daily
    rotate 7
    compress
    delaycompress
    missingok
    notifempty
    create 644 www-data www-data
}
```

### 查看日志

```bash
# 查看最新日志
tail -f /var/log/scheduled-publish.log

# 查看最近 100 行
tail -n 100 /var/log/scheduled-publish.log

# 搜索错误
grep -i error /var/log/scheduled-publish.log

# 查看今天的日志
grep "$(date +%Y-%m-%d)" /var/log/scheduled-publish.log
```

## 监控和告警

### 1. 监控脚本

创建监控脚本 `monitor_scheduled_publish.sh`：

```bash
#!/bin/bash

LOG_FILE="/var/log/scheduled-publish.log"
ERROR_COUNT=$(grep -c "ERROR" "$LOG_FILE" | tail -n 100)

if [ "$ERROR_COUNT" -gt 5 ]; then
    echo "定时发布任务出现异常，错误数量：$ERROR_COUNT" | mail -s "告警：定时发布任务异常" admin@example.com
fi
```

### 2. 健康检查

添加健康检查 cron：

```bash
# 每小时检查一次
0 * * * * /path/to/monitor_scheduled_publish.sh
```

### 3. 死锁检测

如果任务可能运行时间较长，添加锁机制防止重复执行：

创建 `scheduled_publish_with_lock.sh`：

```bash
#!/bin/bash

LOCK_FILE="/tmp/scheduled_publish.lock"
PROJECT_PATH="/path/to/project/api"
LOG_FILE="/var/log/scheduled-publish.log"

# 检查锁文件
if [ -f "$LOCK_FILE" ]; then
    # 检查进程是否还在运行
    PID=$(cat "$LOCK_FILE")
    if ps -p "$PID" > /dev/null 2>&1; then
        echo "$(date): 任务正在运行中，跳过本次执行" >> "$LOG_FILE"
        exit 0
    else
        # 进程不存在，删除陈旧的锁文件
        rm -f "$LOCK_FILE"
    fi
fi

# 创建锁文件
echo $$ > "$LOCK_FILE"

# 执行命令
cd "$PROJECT_PATH" && php think publish:scheduled >> "$LOG_FILE" 2>&1

# 删除锁文件
rm -f "$LOCK_FILE"
```

使用带锁的脚本：

```bash
*/5 * * * * /path/to/scheduled_publish_with_lock.sh
```

## 性能优化建议

### 1. 合理设置执行频率

- **高实时性要求**：每 1-2 分钟执行一次
- **正常需求**：每 5 分钟执行一次（推荐）
- **低频需求**：每 10-15 分钟执行一次

### 2. 限制单次处理数量

根据服务器性能调整 `--limit` 参数：

```bash
# 性能较弱的服务器
php think publish:scheduled --limit=10

# 正常服务器
php think publish:scheduled --limit=50

# 高性能服务器
php think publish:scheduled --limit=100
```

### 3. 避免高峰期执行

如果系统有明显的高峰期，可以在低峰期增加执行频率：

```bash
# 低峰期（0-6点）每分钟执行
* 0-6 * * * cd /path/to/project/api && php think publish:scheduled

# 高峰期（9-18点）每 5 分钟执行
*/5 9-18 * * * cd /path/to/project/api && php think publish:scheduled

# 其他时段每 3 分钟执行
*/3 7-8,19-23 * * * cd /path/to/project/api && php think publish:scheduled
```

## 故障排查

### 1. 命令未执行

**检查项：**
- cron 服务是否运行：`sudo systemctl status cron`
- crontab 是否正确配置：`crontab -l`
- PHP 路径是否正确：`which php`
- 项目路径是否正确
- 文件权限是否正确

### 2. 命令执行失败

**检查项：**
- 查看日志文件是否有错误信息
- 手动执行命令测试：`cd /path/to/project/api && php think publish:scheduled -v`
- 检查数据库连接是否正常
- 检查 Redis 连接是否正常
- 检查磁盘空间是否充足

### 3. 任务未被处理

**可能原因：**
- 任务状态不是 PENDING
- scheduled_time 未设置或未到达
- 数据库查询条件不匹配
- 任务被锁定（PUBLISHING 状态）

**排查方法：**

```sql
-- 查看待处理的定时任务
SELECT id, content_task_id, status, scheduled_time, create_time
FROM publish_tasks
WHERE status = 'PENDING'
  AND scheduled_time IS NOT NULL
  AND scheduled_time <= NOW()
ORDER BY scheduled_time ASC;

-- 查看正在发布的任务（可能卡住）
SELECT id, content_task_id, status, update_time
FROM publish_tasks
WHERE status = 'PUBLISHING'
  AND update_time < DATE_SUB(NOW(), INTERVAL 30 MINUTE);
```

### 4. 内存或性能问题

**解决方案：**
- 减少 `--limit` 参数值
- 增加 cron 执行间隔
- 优化数据库查询
- 检查是否有内存泄漏

## 测试和验证

### 1. 手动测试

```bash
# 试运行测试
php think publish:scheduled --dry-run -v

# 限制处理数量测试
php think publish:scheduled --limit=1 -v

# 正常执行测试
php think publish:scheduled -v
```

### 2. 创建测试任务

```sql
-- 创建一个立即可执行的测试任务
INSERT INTO publish_tasks (content_task_id, user_id, platforms, status, scheduled_time, create_time, update_time)
VALUES (
    1,
    1,
    '[{"platform":"DOUYIN","account_id":1,"config":{}}]',
    'PENDING',
    DATE_SUB(NOW(), INTERVAL 1 MINUTE),
    NOW(),
    NOW()
);
```

### 3. 验证执行结果

```sql
-- 查看任务执行状态
SELECT id, status, scheduled_time, publish_time, results
FROM publish_tasks
WHERE id = [测试任务ID];
```

## 最佳实践

1. **使用锁机制**：防止任务重复执行
2. **合理设置限制**：根据服务器性能调整 `--limit` 参数
3. **日志管理**：定期清理日志文件，避免占用过多磁盘空间
4. **监控告警**：设置异常监控和告警机制
5. **错误重试**：对失败的任务实施重试策略
6. **性能监控**：记录执行时间，及时发现性能问题
7. **定期维护**：定期检查超时任务，重置状态或清理数据

## 推荐配置

### 生产环境

```bash
# 每 5 分钟执行，处理最多 50 个任务，输出到日志
*/5 * * * * cd /path/to/project/api && /usr/bin/php think publish:scheduled --limit=50 >> /var/log/scheduled-publish.log 2>&1
```

### 测试环境

```bash
# 每分钟执行，详细输出
* * * * * cd /path/to/project/api && php think publish:scheduled -v >> /var/log/scheduled-publish.log 2>&1
```

### 开发环境

```bash
# 每 10 分钟执行，小批量处理
*/10 * * * * cd /path/to/project/api && php think publish:scheduled --limit=10 -v >> /tmp/scheduled-publish.log 2>&1
```

## 相关命令

- 查看所有可用命令：`php think`
- 查看命令帮助：`php think publish:scheduled --help`
- 数据库健康检查：`php think health:database`
- 内容生成队列：`php think content:generate`

## 联系支持

如有问题，请查看：
- 应用日志：`runtime/log/`
- Cron 日志：配置的日志文件路径
- 系统日志：`/var/log/syslog` 或 `/var/log/cron`