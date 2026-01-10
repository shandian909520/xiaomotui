# 违规内容处理系统

## 概述

违规内容处理系统是小魔推平台的内容安全保障机制，用于自动检测、下架和处理违规素材，同时提供申诉和通知功能。

## 功能特性

### 1. 内容审核
- **文本审核**: 关键词检测、正则模式匹配、第三方API（百度/阿里云）
- **图片审核**: 支持百度/阿里云图片内容审核API
- **视频审核**: 支持百度/阿里云视频内容审核API
- **音频审核**: 语音转文字后进行文本审核

### 2. 违规检测
- **自动检测**: 素材上传时自动进行内容审核
- **手动检测**: 管理员手动触发检测
- **用户举报**: 用户可以举报违规素材

### 3. 自动处理
- **严重违规**: 自动下架并通知商家
- **中度违规**: 发出警告通知
- **轻微违规**: 记录但不处理

### 4. 违规分类
- SENSITIVE: 敏感内容
- ILLEGAL: 违法内容
- PORN: 色情内容
- VIOLENCE: 暴力内容
- AD: 广告内容
- FRAUD: 欺诈内容
- SPAM: 垃圾内容
- COPYRIGHT: 版权问题
- OTHER: 其他违规

### 5. 申诉机制
- 商家可在7天内提交申诉
- 提供申诉理由和证据
- 管理员审核后做出决定
- 申诉通过后恢复素材

### 6. 通知系统
- **系统通知**: 站内消息
- **邮件通知**: 重要违规发送邮件
- **短信通知**: 严重违规发送短信
- **微信通知**: 企业微信/公众号通知

### 7. 黑名单管理
- 违规次数达到阈值自动加入黑名单
- 黑名单商家受到功能限制
- 支持手动解除黑名单

## 数据库表

### 1. xmt_content_violations (违规记录表)
存储所有违规记录，包括违规类型、严重程度、处理动作等。

### 2. xmt_violation_appeals (申诉记录表)
存储商家提交的申诉记录和审核结果。

### 3. xmt_merchant_notifications (商家通知表)
存储发送给商家的各类通知。

### 4. xmt_violation_keywords (违规关键词库)
存储用于文本审核的违规关键词。

### 5. xmt_merchant_blacklist (商家黑名单)
存储被列入黑名单的商家信息。

## API接口

### 商家端接口

#### 1. 获取违规历史
```
GET /api/violation/history
参数:
  - material_id: 素材ID（可选）
  - status: 状态（可选）
  - violation_type: 违规类型（可选）
  - severity: 严重程度（可选）
  - start_date: 开始日期（可选）
  - end_date: 结束日期（可选）
  - page: 页码
  - limit: 每页数量

响应:
{
  "code": 200,
  "message": "success",
  "data": {
    "list": [...],
    "total": 10,
    "page": 1,
    "limit": 20,
    "pages": 1
  }
}
```

#### 2. 举报素材
```
POST /api/violation/report
参数:
  - material_id: 素材ID
  - violation_type: 违规类型
  - reason: 举报原因
  - evidence: 证据URL数组（可选）

响应:
{
  "code": 200,
  "message": "举报成功",
  "data": {
    "violation_id": 123
  }
}
```

#### 3. 提交申诉
```
POST /api/violation/appeal
参数:
  - violation_id: 违规记录ID
  - reason: 申诉理由
  - evidence: 申诉证据
  - contact_phone: 联系电话
  - contact_email: 联系邮箱

响应:
{
  "code": 200,
  "message": "申诉提交成功",
  "data": {
    "appeal_id": 456
  }
}
```

#### 4. 获取申诉列表
```
GET /api/violation/appeals
参数:
  - status: 状态（可选）
  - page: 页码
  - limit: 每页数量

响应:
{
  "code": 200,
  "message": "success",
  "data": {
    "list": [...],
    "total": 5,
    "page": 1,
    "limit": 20
  }
}
```

#### 5. 获取通知列表
```
GET /api/violation/notifications
参数:
  - type: 通知类型（可选）
  - status: 状态（可选）
  - unread_only: 只看未读（可选）
  - page: 页码
  - limit: 每页数量

响应:
{
  "code": 200,
  "message": "success",
  "data": {
    "list": [...],
    "total": 15,
    "unread_count": 5,
    "page": 1,
    "limit": 20
  }
}
```

#### 6. 标记通知已读
```
PUT /api/violation/notification/:id/read

响应:
{
  "code": 200,
  "message": "标记成功"
}
```

#### 7. 获取违规统计
```
GET /api/violation/statistics
参数:
  - start_date: 开始日期
  - end_date: 结束日期

响应:
{
  "code": 200,
  "message": "success",
  "data": {
    "total": 50,
    "status_stats": [...],
    "type_stats": [...],
    "severity_stats": [...],
    "trend_data": [...]
  }
}
```

### 管理员接口

#### 1. 检测素材内容
```
POST /api/violation/check
参数:
  - material_id: 素材ID
  - check_type: 检测类型（MANUAL）

响应:
{
  "code": 200,
  "message": "检测完成",
  "data": {
    "has_violation": true,
    "violations": [...],
    "severity": "HIGH",
    "confidence": 0.95,
    "auto_action": "DISABLED"
  }
}
```

#### 2. 审核违规举报
```
PUT /api/admin/violation/:id/review
参数:
  - confirmed: 是否确认违规
  - comment: 审核意见

响应:
{
  "code": 200,
  "message": "审核完成"
}
```

#### 3. 处理申诉
```
PUT /api/admin/appeal/:id/process
参数:
  - approved: 是否批准
  - comment: 审核意见

响应:
{
  "code": 200,
  "message": "申诉处理完成"
}
```

#### 4. 获取待处理申诉
```
GET /api/admin/appeals/pending
参数:
  - page: 页码
  - limit: 每页数量

响应:
{
  "code": 200,
  "message": "success",
  "data": {
    "list": [...],
    "total": 10
  }
}
```

#### 5. 批量下架素材
```
POST /api/admin/violation/batch-disable
参数:
  - material_ids: 素材ID数组
  - reason: 下架原因

响应:
{
  "code": 200,
  "message": "批量处理完成",
  "data": {
    "success_count": 8,
    "fail_count": 2,
    "total": 10
  }
}
```

## 配置说明

### 审核配置 (config/moderation.php)

```php
return [
    // 文本审核
    'text' => [
        'enabled' => true,
        'third_party_enabled' => false,
    ],

    // 图片审核
    'image' => [
        'enabled' => true,
        'provider' => 'baidu', // baidu|aliyun
        'baidu' => [
            'app_id' => '',
            'api_key' => '',
            'secret_key' => '',
        ],
    ],

    // 黑名单阈值
    'blacklist_thresholds' => [
        'total_violations' => 10,
        'high_severity_violations' => 3,
    ],

    // 通知配置
    'notification' => [
        'severity_channels' => [
            'HIGH' => ['system', 'email', 'sms'],
            'MEDIUM' => ['system', 'email'],
            'LOW' => ['system'],
        ],
    ],
];
```

## 工作流程

### 1. 自动检测流程
```
素材上传 → 内容审核 → 检测违规 → 确定动作 → 自动下架（如需要） → 通知商家
```

### 2. 举报处理流程
```
用户举报 → 创建违规记录 → 管理员审核 → 确认违规/驳回 → 处理素材 → 通知商家
```

### 3. 申诉处理流程
```
商家申诉 → 提交证据 → 管理员审核 → 批准/驳回 → 恢复素材（如批准） → 通知商家
```

### 4. 黑名单触发流程
```
违规累积 → 达到阈值 → 自动加入黑名单 → 限制功能 → 通知商家
```

## 关键词管理

### 添加违规关键词

```sql
INSERT INTO xmt_violation_keywords (keyword, category, severity, match_type, enabled, create_time, update_time)
VALUES ('赌博', 'ILLEGAL', 'HIGH', 'EXACT', 1, NOW(), NOW());
```

### 关键词匹配类型
- **EXACT**: 精确匹配
- **FUZZY**: 模糊匹配（忽略空格和特殊字符）
- **REGEX**: 正则表达式匹配

## 通知模板

### 违规通知
```
尊敬的商家：

您的素材「{素材名称}」(ID:{素材ID})因{违规类型}被检测为违规内容。

违规详情：
- 违规类型：{违规类型}
- 严重程度：{严重程度}
- 处理结果：{处理动作}

该素材已被自动下架，暂时无法使用。

如对此处理有异议，您可以在7天内提交申诉。

小魔推团队
{时间}
```

### 申诉结果通知
```
尊敬的商家：

您针对素材「{素材名称}」提交的申诉已审核完成。

审核结果：{通过/驳回}
审核意见：{审核意见}

您的素材已恢复正常使用。

感谢您的理解与配合。

小魔推团队
{时间}
```

## 测试

运行测试脚本：
```bash
php test_violation_system.php
```

测试内容包括：
1. 内容审核服务测试
2. 违规关键词插入
3. 违规处理流程测试
4. 申诉功能测试
5. 通知服务测试
6. 违规统计测试

## 注意事项

1. **隐私保护**: 违规详情中不要暴露用户敏感信息
2. **误判处理**: 建立完善的申诉机制，及时处理误判
3. **性能优化**: 图片/视频审核使用缓存，避免重复检测
4. **合规性**: 遵守当地法律法规，定期更新违规关键词
5. **审计日志**: 保留完整的违规处理记录用于审计
6. **API限流**: 第三方审核API需要做好限流和容错

## 扩展建议

1. **AI模型**: 集成自训练的内容审核模型
2. **实时监控**: 建立违规内容监控大屏
3. **自动学习**: 根据申诉结果优化审核规则
4. **多语言支持**: 支持多语言内容审核
5. **风险评分**: 为商家建立信用评分系统

## 联系支持

如有问题，请联系技术支持团队。
