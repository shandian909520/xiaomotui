# 任务状态查询API文档

## 概述

查询内容生成任务的状态和结果。

## 端点

```
GET /api/content/task/{task_id}/status
```

## 认证

需要JWT Token:
```
Authorization: Bearer {your_jwt_token}
```

## 请求参数

### 路径参数

| 参数 | 类型 | 必需 | 说明 |
|------|------|------|------|
| task_id | 整数 | 是 | 任务ID |

## 响应格式

### 成功响应 (200)

```json
{
    "code": 200,
    "message": "success",
    "data": {
        "task_id": 123,
        "type": "TEXT",
        "status": "COMPLETED",
        "progress": 100,
        "result": {
            "text": "生成的文案内容",
            "word_count": 150
        },
        "generation_time": 25,
        "create_time": "2025-09-30 10:00:00",
        "update_time": "2025-09-30 10:00:30",
        "complete_time": "2025-09-30 10:00:30",
        "merchant_id": 1,
        "device_id": 5,
        "template_id": 10,
        "ai_provider": "openai"
    },
    "timestamp": 1727673600
}
```

## 任务状态

| 状态 | 说明 | 进度 |
|------|------|------|
| PENDING | 等待处理 | 0% |
| PROCESSING | 处理中 | 50% |
| COMPLETED | 已完成 | 100% |
| FAILED | 失败 | 0% |

## 响应字段

### 基础字段（所有状态）

- `task_id`: 任务ID
- `type`: 内容类型（VIDEO/TEXT/IMAGE）
- `status`: 任务状态
- `progress`: 进度百分比（0-100）
- `create_time`: 创建时间
- `update_time`: 更新时间

### 条件字段

**PROCESSING状态:**
- `estimated_remaining_time`: 预估剩余时间（秒）

**COMPLETED状态:**
- `result`: 生成结果
- `generation_time`: 生成耗时（秒）
- `complete_time`: 完成时间

**FAILED状态:**
- `error_message`: 错误信息
- `complete_time`: 失败时间

## 错误响应

### 401 - 未授权
用户未登录或token无效

### 403 - 禁止访问
用户无权查看该任务（只能查看自己的任务）

### 404 - 任务不存在
指定的任务ID不存在

### 400 - 参数错误
task_id参数格式错误

## 使用示例

### JavaScript

```javascript
async function checkTaskStatus(taskId) {
    const response = await fetch(`/api/content/task/${taskId}/status`, {
        headers: {
            'Authorization': `Bearer ${token}`
        }
    });

    const result = await response.json();

    if (result.code === 200) {
        const task = result.data;
        console.log(`状态: ${task.status}, 进度: ${task.progress}%`);

        if (task.status === 'COMPLETED') {
            console.log('结果:', task.result);
        }
    }

    return result;
}
```

### 轮询示例

```javascript
async function waitForTaskCompletion(taskId, maxAttempts = 60) {
    for (let i = 0; i < maxAttempts; i++) {
        const result = await checkTaskStatus(taskId);

        if (result.data.status === 'COMPLETED') {
            return result.data;
        }

        if (result.data.status === 'FAILED') {
            throw new Error(result.data.error_message);
        }

        // 等待2秒后重试
        await new Promise(resolve => setTimeout(resolve, 2000));
    }

    throw new Error('任务超时');
}
```

## 最佳实践

1. **轮询间隔**: 建议2-5秒查询一次
2. **超时处理**: 设置最大轮询次数，避免无限等待
3. **错误处理**: 妥善处理FAILED状态和网络错误
4. **缓存**: 已完成的任务结果可以缓存
5. **用户体验**: 显示进度条和预估时间

## 相关API

- [创建内容生成任务](./content-generate.md)
- [获取任务历史](./task-history.md)
- [取消任务](./cancel-task.md)