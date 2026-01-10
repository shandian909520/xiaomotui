# 任务27完成总结：实现内容生成任务创建

## 任务概述
实现内容生成任务创建功能，支持VIDEO/TEXT/IMAGE三种内容类型，允许可选的模板和设备关联。

## 实现内容

### 1. 数据库设计
**表名**: `xmt_content_tasks`

**字段**:
- `id`: 任务ID（主键）
- `user_id`: 用户ID
- `merchant_id`: 商家ID
- `device_id`: 设备ID（可选）
- `template_id`: 模板ID（可选）
- `type`: 内容类型（VIDEO/TEXT/IMAGE）
- `status`: 任务状态（PENDING/PROCESSING/COMPLETED/FAILED）
- `input_data`: 输入数据（JSON）
- `output_data`: 输出数据（JSON）
- `ai_provider`: AI服务商
- `generation_time`: 生成耗时（秒）
- `error_message`: 错误信息
- `create_time`: 创建时间
- `update_time`: 更新时间
- `complete_time`: 完成时间

### 2. 核心组件

#### 2.1 ContentTask模型
**文件**: `D:\xiaomotui\api\app\model\ContentTask.php`

**主要功能**:
- 定义数据表结构和字段映射
- 提供任务状态常量（PENDING/PROCESSING/COMPLETED/FAILED）
- 提供内容类型常量（VIDEO/TEXT/IMAGE）
- 实现任务状态转换方法
- 提供数据获取器和统计方法

**关键方法**:
```php
// 开始处理任务
public function startProcessing(): bool

// 完成任务
public function complete(array $outputData, int $generationTime = 0): bool

// 标记任务失败
public function markAsFailed(string $errorMessage): bool

// 重置任务状态
public function reset(): bool
```

#### 2.2 ContentService服务类
**文件**: `D:\xiaomotui\api\app\service\ContentService.php`

**主要功能**:
- 处理内容生成任务创建逻辑
- 验证设备和模板权限
- 检查用户配额
- 分发任务到队列（预留接口）
- 获取任务状态和历史记录

**核心方法**:
```php
// 创建内容生成任务
public function createGenerationTask(int $userId, ?int $merchantId, array $data): array

// 获取任务状态
public function getTaskStatus(int $userId, string $taskId): array

// 批量获取任务状态
public function getBatchTaskStatus(int $userId, array $taskIds): array

// 获取任务历史
public function getTaskHistory(int $userId, ?int $merchantId, array $params): array
```

#### 2.3 Content验证器
**文件**: `D:\xiaomotui\api\app\validate\Content.php`

**验证规则**:
- `merchant_id`: 必填，整数，大于0
- `device_id`: 可选，整数，大于0
- `template_id`: 可选，整数，大于0
- `type`: 必填，枚举值（VIDEO/TEXT/IMAGE）
- `input_data`: 可选，数组格式

**验证场景**:
- `generate`: 内容生成任务创建
- `taskStatus`: 单个任务状态查询
- `batchTaskStatus`: 批量任务状态查询
- `taskHistory`: 任务历史查询

#### 2.4 Content控制器
**文件**: `D:\xiaomotui\api\app\controller\Content.php`

**API端点**: `POST /api/content/generate`

**功能**:
- 接收并验证请求参数
- 从JWT中获取用户ID和商家ID
- 调用ContentService创建任务
- 返回标准化响应
- 处理异常情况

### 3. API接口规范

#### 3.1 创建内容生成任务
**请求方法**: POST
**请求路径**: `/api/content/generate`
**认证要求**: 需要JWT Token

**请求参数**:
```json
{
    "type": "VIDEO",            // 必填: VIDEO/TEXT/IMAGE
    "merchant_id": 123,         // 必填: 商家ID
    "device_id": 456,           // 可选: 设备ID
    "template_id": 789,         // 可选: 模板ID
    "input_data": {             // 可选: 输入数据
        "scene": "咖啡店",
        "style": "温馨",
        "requirements": "突出环境氛围"
    }
}
```

**成功响应** (200):
```json
{
    "code": 200,
    "message": "success",
    "data": {
        "task_id": 123,
        "status": "PENDING",
        "type": "VIDEO",
        "estimated_time": 300,
        "create_time": "2025-09-30 12:00:00",
        "message": "任务已创建，预计300秒完成"
    },
    "timestamp": 1727654400
}
```

**错误响应**:
- `401`: 用户未登录
- `400`: 参数验证失败
- `404`: 设备或模板不存在
- `429`: 配额不足

#### 3.2 查询任务状态
**请求方法**: GET
**请求路径**: `/api/content/task-status?task_id={id}`
**认证要求**: 需要JWT Token

**成功响应**:
```json
{
    "code": 200,
    "message": "success",
    "data": {
        "task_id": 123,
        "status": "PENDING",
        "type": "VIDEO",
        "create_time": "2025-09-30 12:00:00",
        "update_time": "2025-09-30 12:00:00",
        "complete_time": null,
        "generation_time": null,
        "error_message": null
    }
}
```

### 4. 预估处理时间

根据内容类型返回不同的预估时间：
- **VIDEO**: 300秒（5分钟）
- **IMAGE**: 60秒（1分钟）
- **TEXT**: 30秒

### 5. 路由配置

**文件**: `D:\xiaomotui\api\route\app.php`

```php
// 内容相关路由（需要认证）
Route::group('content', function () {
    Route::post('generate', 'Content/generate');           // 创建任务
    Route::get('task/:task_id/status', 'Content/taskStatus'); // 查询状态
    Route::get('templates', 'Content/templates');          // 模板列表
    Route::get('my', 'Content/my');                        // 我的内容
})->middleware(['AllowCrossDomain', 'ApiThrottle', 'Auth']);
```

### 6. 业务逻辑流程

1. **请求接收**
   - 接收POST请求
   - 从JWT中解析user_id和merchant_id

2. **数据验证**
   - 验证必填字段（type, merchant_id）
   - 验证枚举值（type必须是VIDEO/TEXT/IMAGE）
   - 验证可选字段（device_id, template_id）

3. **权限检查**
   - 验证设备是否存在且可用
   - 验证模板是否存在且启用
   - 检查用户是否有权使用设备/模板

4. **配额检查**
   - 检查用户今日生成次数
   - 检查是否超过配额限制

5. **任务创建**
   - 构建任务数据
   - 插入数据库
   - 初始状态为PENDING

6. **队列分发**
   - 将任务加入处理队列（预留接口）
   - 记录操作日志

7. **响应返回**
   - 返回task_id
   - 返回预估处理时间
   - 返回创建时间

### 7. 测试用例

创建了API测试脚本：`D:\xiaomotui\api\test_content_api.php`

**测试场景**:
1. 创建VIDEO类型任务（带模板和设备）
2. 创建TEXT类型任务（不带模板）
3. 创建IMAGE类型任务（带设备）
4. 查询任务状态

**CURL测试示例**:
```bash
# 1. 登录获取token
curl -X POST http://localhost/xiaomotui/api/public/index.php/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"phone": "13800138000", "password": "123456"}'

# 2. 创建内容生成任务
curl -X POST http://localhost/xiaomotui/api/public/index.php/api/content/generate \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <token>" \
  -d '{
    "merchant_id": 1,
    "type": "VIDEO",
    "input_data": {
      "scene": "咖啡店",
      "style": "温馨"
    }
  }'
```

### 8. 文件清单

| 文件 | 说明 |
|------|------|
| `app/controller/Content.php` | 内容控制器 |
| `app/service/ContentService.php` | 内容服务类 |
| `app/model/ContentTask.php` | 内容任务模型 |
| `app/model/ContentTemplate.php` | 内容模板模型 |
| `app/validate/Content.php` | 内容验证器 |
| `route/app.php` | 路由配置 |
| `database/migrations/20250929222838_create_content_tasks_table.sql` | 数据表迁移文件 |
| `test_content_api.php` | API测试脚本 |
| `TASK_27_COMPLETION_SUMMARY.md` | 任务完成总结 |

### 9. 关键特性

✅ **类型安全**: 使用大写枚举值（VIDEO/TEXT/IMAGE）匹配数据库设计
✅ **权限控制**: 验证设备和模板访问权限
✅ **配额管理**: 检查用户生成配额限制
✅ **JSON存储**: input_data和output_data使用JSON格式存储
✅ **状态追踪**: 完整的任务状态流转（PENDING→PROCESSING→COMPLETED/FAILED）
✅ **时间预估**: 根据内容类型返回预估处理时间
✅ **错误处理**: 完善的异常处理和错误响应
✅ **日志记录**: 记录关键操作日志
✅ **队列支持**: 预留队列处理接口
✅ **扩展性**: 支持未来添加更多内容类型和AI服务商

### 10. 使用说明

#### 10.1 前置条件
- 用户需要先登录获取JWT token
- 商家ID必须存在且有效
- 如果指定device_id，设备必须存在且状态为启用
- 如果指定template_id，模板必须存在且状态为启用

#### 10.2 调用示例

**JavaScript (Axios)**:
```javascript
// 创建内容生成任务
const response = await axios.post('/api/content/generate', {
  merchant_id: 1,
  device_id: 1,
  template_id: 1,
  type: 'VIDEO',
  input_data: {
    scene: '咖啡店',
    style: '温馨',
    requirements: '突出环境氛围'
  }
}, {
  headers: {
    'Authorization': `Bearer ${token}`
  }
});

console.log('任务ID:', response.data.data.task_id);
console.log('预估时间:', response.data.data.estimated_time, '秒');
```

**PHP**:
```php
// 使用GuzzleHttp
$client = new \GuzzleHttp\Client();
$response = $client->post('/api/content/generate', [
    'headers' => [
        'Authorization' => 'Bearer ' . $token,
        'Content-Type' => 'application/json',
    ],
    'json' => [
        'merchant_id' => 1,
        'type' => 'VIDEO',
        'input_data' => [
            'scene' => '咖啡店',
            'style' => '温馨',
        ]
    ]
]);

$result = json_decode($response->getBody(), true);
echo "任务ID: " . $result['data']['task_id'];
```

### 11. 后续优化建议

1. **队列实现**: 实现真实的队列处理逻辑
2. **进度追踪**: 实现任务处理进度实时更新
3. **通知机制**: 任务完成后通知用户
4. **缓存优化**: 对频繁查询的任务状态进行缓存
5. **批量创建**: 支持批量创建多个任务
6. **模板推荐**: 根据商家类型推荐合适的模板
7. **统计分析**: 添加更详细的生成统计和分析
8. **错误重试**: 失败任务自动重试机制

### 12. 注意事项

⚠️ **重要提示**:
1. 所有API调用都需要JWT认证
2. merchant_id是必填字段，从JWT中获取
3. 内容类型必须大写（VIDEO/TEXT/IMAGE）
4. input_data为JSON对象，可包含任意结构
5. 任务创建后默认状态为PENDING
6. 预估时间仅供参考，实际处理时间可能不同
7. 配额检查默认为每日100次，可配置

## 任务状态

✅ **已完成**

## 完成时间

2025-09-30

## 测试状态

✅ 代码实现完成
✅ API测试脚本创建
✅ 文档编写完成
✅ 符合规范要求