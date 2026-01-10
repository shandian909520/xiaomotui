# AI内容素材库管理系统 - 数据库设计文档

## 概述

素材库管理系统用于支持AI内容生成的素材管理，包括视频片段、音效、转场效果、文案模板等多种类型的素材。系统支持素材的分类、标签、审核、使用统计和智能推荐。

## 数据库表结构

### 1. 素材分类表 (xmt_content_material_categories)

**表名**: `xmt_content_material_categories`
**用途**: 管理素材的层级分类结构
**特点**: 支持多级分类、按类型分组

#### 字段说明

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | int unsigned | 是 | 分类ID，主键 |
| parent_id | int unsigned | 否 | 父分类ID，支持多级分类 |
| name | varchar(100) | 是 | 分类名称 |
| type | enum | 是 | 素材类型：VIDEO(视频)、AUDIO(音频)、IMAGE(图片)、TEXT(文本)、TRANSITION(转场) |
| description | varchar(500) | 否 | 分类描述 |
| icon | varchar(255) | 否 | 分类图标URL |
| sort_order | int | 否 | 排序值，默认0 |
| material_count | int unsigned | 否 | 该分类下的素材数量，默认0 |
| status | tinyint(1) | 否 | 状态：0禁用 1启用，默认1 |
| create_time | datetime | 是 | 创建时间 |
| update_time | datetime | 是 | 更新时间 |

#### 索引

- PRIMARY KEY: `id`
- KEY: `idx_parent` (parent_id)
- KEY: `idx_type` (type)
- KEY: `idx_status` (status)
- KEY: `idx_sort` (sort_order)

#### 使用场景

- 素材按类型和用途分类管理
- 支持层级分类浏览
- 按分类统计素材数量

---

### 2. 素材标签表 (xmt_content_material_tags)

**表名**: `xmt_content_material_tags`
**用途**: 管理素材的标签系统，支持灵活的素材组织
**特点**: 标签可限定类型或全局通用

#### 字段说明

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | int unsigned | 是 | 标签ID，主键 |
| name | varchar(50) | 是 | 标签名称 |
| type | enum | 否 | 适用类型：VIDEO、AUDIO、IMAGE、TEXT、TRANSITION、ALL，默认ALL |
| usage_count | int unsigned | 否 | 使用次数统计，默认0 |
| create_time | datetime | 是 | 创建时间 |

#### 索引

- PRIMARY KEY: `id`
- UNIQUE KEY: `uk_name_type` (name, type) - 同一类型下标签名称唯一
- KEY: `idx_type` (type)
- KEY: `idx_usage_count` (usage_count)

#### 使用场景

- 素材多维度标签
- 标签搜索和筛选
- 热门标签统计

---

### 3. 内容素材主表 (xmt_content_materials)

**表名**: `xmt_content_materials`
**用途**: 存储所有类型的素材信息
**特点**: 支持多种素材类型、质量评分、智能推荐权重

#### 字段说明

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | int unsigned | 是 | 素材ID，主键 |
| name | varchar(200) | 是 | 素材名称 |
| type | enum | 是 | 素材类型：VIDEO、AUDIO、IMAGE、TEXT、TRANSITION |
| category_id | int unsigned | 否 | 分类ID，关联分类表 |
| file_url | varchar(500) | 否 | 文件URL（非文本素材） |
| file_size | int unsigned | 否 | 文件大小（字节） |
| duration | int unsigned | 否 | 时长（秒，视频/音频） |
| width | int unsigned | 否 | 宽度（像素，图片/视频） |
| height | int unsigned | 否 | 高度（像素，图片/视频） |
| thumbnail_url | varchar(500) | 否 | 缩略图URL |
| content | text | 否 | 文本内容（文案模板） |
| metadata | json | 否 | 元数据（标签、属性等） |
| style | varchar(50) | 否 | 风格标签（如：现代、复古、简约） |
| scene | varchar(50) | 否 | 适用场景（如：餐厅、促销、新品） |
| quality_score | decimal(3,2) | 否 | 质量评分（0-10），默认0.00 |
| usage_count | int unsigned | 否 | 使用次数，默认0 |
| success_count | int unsigned | 否 | 成功使用次数，默认0 |
| recommendation_weight | decimal(5,2) | 否 | 推荐权重，默认1.00 |
| review_status | enum | 否 | 审核状态：PENDING、APPROVED、REJECTED，默认PENDING |
| review_time | datetime | 否 | 审核时间 |
| reviewer_id | int unsigned | 否 | 审核人ID |
| rejection_reason | varchar(500) | 否 | 拒绝原因 |
| is_public | tinyint(1) | 否 | 是否公开：0私有 1公开，默认1 |
| status | tinyint(1) | 否 | 状态：0禁用 1启用，默认1 |
| creator_id | int unsigned | 否 | 创建者ID |
| create_time | datetime | 是 | 创建时间 |
| update_time | datetime | 是 | 更新时间 |

#### 索引

- PRIMARY KEY: `id`
- KEY: `idx_type` (type)
- KEY: `idx_category` (category_id)
- KEY: `idx_style` (style)
- KEY: `idx_scene` (scene)
- KEY: `idx_review_status` (review_status)
- KEY: `idx_status` (status)
- KEY: `idx_create_time` (create_time)
- KEY: `idx_quality_score` (quality_score)
- KEY: `idx_usage_count` (usage_count)
- KEY: `idx_recommendation_weight` (recommendation_weight)
- KEY: `idx_creator` (creator_id)

#### 使用场景

- 素材存储和管理
- 按类型、分类、场景筛选
- 质量评分和推荐权重计算
- 素材审核流程

---

### 4. 素材标签关联表 (xmt_content_material_tag_relations)

**表名**: `xmt_content_material_tag_relations`
**用途**: 管理素材和标签的多对多关系
**特点**: 唯一约束防止重复关联

#### 字段说明

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | int unsigned | 是 | 关联ID，主键 |
| material_id | int unsigned | 是 | 素材ID |
| tag_id | int unsigned | 是 | 标签ID |
| create_time | datetime | 是 | 创建时间 |

#### 索引

- PRIMARY KEY: `id`
- UNIQUE KEY: `uk_material_tag` (material_id, tag_id) - 防止重复关联
- KEY: `idx_material` (material_id)
- KEY: `idx_tag` (tag_id)

#### 使用场景

- 素材多标签关联
- 按标签查询素材
- 标签使用统计

---

### 5. 素材使用记录表 (xmt_content_material_usage)

**表名**: `xmt_content_material_usage`
**用途**: 记录素材的使用情况，用于统计和优化推荐
**特点**: 支持用户反馈和性能评分

#### 字段说明

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | int unsigned | 是 | 记录ID，主键 |
| material_id | int unsigned | 是 | 素材ID |
| template_id | int unsigned | 否 | 模板ID |
| content_task_id | int unsigned | 否 | 内容任务ID |
| user_id | int unsigned | 否 | 用户ID |
| merchant_id | int unsigned | 否 | 商家ID |
| usage_context | json | 否 | 使用上下文（场景、参数等） |
| performance_score | decimal(3,2) | 否 | 表现评分（0-10） |
| user_feedback | tinyint(1) | 否 | 用户反馈：1好评 0差评 |
| create_time | datetime | 是 | 创建时间 |

#### 索引

- PRIMARY KEY: `id`
- KEY: `idx_material` (material_id)
- KEY: `idx_template` (template_id)
- KEY: `idx_task` (content_task_id)
- KEY: `idx_user` (user_id)
- KEY: `idx_merchant` (merchant_id)
- KEY: `idx_create_time` (create_time)
- KEY: `idx_performance_score` (performance_score)

#### 使用场景

- 素材使用统计
- 性能分析和优化
- 用户反馈收集
- 推荐权重调整

---

### 6. 素材审核记录表 (xmt_content_material_reviews)

**表名**: `xmt_content_material_reviews`
**用途**: 记录素材的审核历史
**特点**: 支持自动审核和人工审核

#### 字段说明

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| id | int unsigned | 是 | 记录ID，主键 |
| material_id | int unsigned | 是 | 素材ID |
| reviewer_id | int unsigned | 是 | 审核人ID（自动审核时为系统ID） |
| review_type | enum | 是 | 审核类型：AUTO(自动)、MANUAL(人工) |
| result | enum | 是 | 审核结果：APPROVED(通过)、REJECTED(拒绝)、FLAGGED(标记) |
| score | decimal(3,2) | 否 | 评分（0-10） |
| issues | json | 否 | 问题列表（违规类型、位置等） |
| comments | text | 否 | 审核意见 |
| review_time | datetime | 是 | 审核时间 |

#### 索引

- PRIMARY KEY: `id`
- KEY: `idx_material` (material_id)
- KEY: `idx_reviewer` (reviewer_id)
- KEY: `idx_review_time` (review_time)
- KEY: `idx_review_type` (review_type)
- KEY: `idx_result` (result)

#### 使用场景

- 素材审核流程
- 审核历史查询
- 违规内容统计
- 审核效率分析

---

## 表关系图

```
xmt_content_material_categories (分类表)
    │
    └─[1:N]─> xmt_content_materials (素材主表)
                    │
                    ├─[N:M]─> xmt_content_material_tags (标签表)
                    │           (通过 xmt_content_material_tag_relations)
                    │
                    ├─[1:N]─> xmt_content_material_usage (使用记录表)
                    │
                    └─[1:N]─> xmt_content_material_reviews (审核记录表)
```

## 业务流程

### 1. 素材上传流程

1. 用户上传素材文件
2. 系统创建素材记录（status=PENDING）
3. 触发自动审核（xmt_content_material_reviews）
4. 如需人工审核，通知审核人员
5. 审核通过后更新素材状态（status=APPROVED）

### 2. 素材使用流程

1. AI生成任务选择素材
2. 记录使用情况（xmt_content_material_usage）
3. 更新素材使用次数（usage_count++）
4. 收集用户反馈
5. 计算性能评分
6. 更新推荐权重

### 3. 素材推荐流程

1. 根据场景、风格、标签筛选候选素材
2. 按推荐权重排序
3. 过滤已禁用和未审核素材
4. 返回推荐结果

### 4. 素材优化流程

1. 定期分析使用记录
2. 计算成功率（success_count / usage_count）
3. 根据用户反馈调整推荐权重
4. 质量评分低的素材降权或下架

## 性能优化建议

### 1. 索引优化

- 已为常用查询字段添加索引
- 复合索引用于多条件查询
- 注意索引维护成本

### 2. 分区策略

大数据量时可考虑分区：
- 按时间分区（create_time）
- 按类型分区（type）

### 3. 缓存策略

- 热门素材缓存
- 分类树缓存
- 标签列表缓存
- 推荐结果缓存

### 4. 查询优化

- 使用覆盖索引
- 避免全表扫描
- 合理使用JOIN
- 分页查询限制

## 数据迁移

### 执行迁移

```bash
# 测试迁移（包含数据验证）
php database/test_content_materials_migration.php

# 正式执行迁移
php database/migrate.php
```

### 回滚迁移

```bash
# 执行回滚脚本
mysql -u用户名 -p数据库名 < database/migrations/20251001000001_rollback_content_materials_tables.sql
```

## 初始化数据建议

### 1. 默认分类

```sql
INSERT INTO xmt_content_material_categories (name, type, description, sort_order, status, create_time, update_time) VALUES
('美食视频', 'VIDEO', '美食相关视频素材', 1, 1, NOW(), NOW()),
('餐厅场景', 'VIDEO', '餐厅环境视频素材', 2, 1, NOW(), NOW()),
('背景音乐', 'AUDIO', '各类背景音乐', 1, 1, NOW(), NOW()),
('音效', 'AUDIO', '特效音效素材', 2, 1, NOW(), NOW()),
('转场特效', 'TRANSITION', '视频转场效果', 1, 1, NOW(), NOW()),
('文案模板', 'TEXT', '各类文案模板', 1, 1, NOW(), NOW());
```

### 2. 常用标签

```sql
INSERT INTO xmt_content_material_tags (name, type, create_time) VALUES
('美食', 'ALL', NOW()),
('餐厅', 'ALL', NOW()),
('促销', 'ALL', NOW()),
('新品', 'ALL', NOW()),
('欢快', 'AUDIO', NOW()),
('温馨', 'ALL', NOW()),
('现代', 'ALL', NOW()),
('传统', 'ALL', NOW());
```

## 维护建议

### 1. 定期清理

- 清理无效素材文件
- 删除过期使用记录
- 归档历史审核记录

### 2. 数据监控

- 监控表大小增长
- 监控索引效率
- 监控慢查询

### 3. 备份策略

- 每日增量备份
- 每周全量备份
- 重要操作前备份

## 安全注意事项

1. **文件上传**：验证文件类型和大小
2. **内容审核**：严格执行审核流程
3. **访问控制**：私有素材权限管理
4. **数据加密**：敏感信息加密存储
5. **SQL注入**：使用参数化查询

## 未来扩展

### 可能的扩展功能

1. **版本控制**：素材版本管理
2. **协作编辑**：多人协作编辑素材
3. **AI标签**：自动识别素材标签
4. **智能剪辑**：AI辅助素材剪辑
5. **CDN集成**：素材CDN加速

### 预留字段

- metadata：可存储扩展属性
- usage_context：可存储复杂使用场景
- issues：可存储详细问题信息