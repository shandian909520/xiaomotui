# 素材库表迁移文件说明

## 文件说明

### 主迁移文件
**文件名**: `20251001000001_create_content_materials_tables.sql`

此文件创建AI内容素材库管理系统的6个核心表：

1. **xmt_content_material_categories** - 素材分类表
2. **xmt_content_material_tags** - 素材标签表
3. **xmt_content_materials** - 内容素材主表
4. **xmt_content_material_tag_relations** - 素材标签关联表
5. **xmt_content_material_usage** - 素材使用记录表
6. **xmt_content_material_reviews** - 素材审核记录表

### 回滚文件
**文件名**: `20251001000001_rollback_content_materials_tables.sql`

用于回滚迁移，按依赖关系逆序删除所有素材库相关表。

## 使用方法

### 执行迁移

```bash
# 方法1: 使用项目迁移工具
php database/migrate.php

# 方法2: 直接执行SQL文件
mysql -u用户名 -p数据库名 < database/migrations/20251001000001_create_content_materials_tables.sql
```

### 回滚迁移

```bash
mysql -u用户名 -p数据库名 < database/migrations/20251001000001_rollback_content_materials_tables.sql
```

### 测试迁移

```bash
# 完整测试（需要数据库连接）
php database/test_content_materials_migration.php

# 语法验证（无需数据库）
php database/validate_materials_migration_syntax.php
```

## 表结构概览

### 1. 素材分类表 (xmt_content_material_categories)
- 支持多级分类
- 按素材类型分组
- 11个字段，4个索引

### 2. 素材标签表 (xmt_content_material_tags)
- 灵活的标签系统
- 支持类型限定
- 5个字段，3个索引

### 3. 内容素材主表 (xmt_content_materials)
- 支持5种素材类型
- 质量评分和推荐权重
- 完整的审核状态
- 27个字段，11个索引

### 4. 素材标签关联表 (xmt_content_material_tag_relations)
- 多对多关系
- 防止重复关联
- 4个字段，3个索引

### 5. 素材使用记录表 (xmt_content_material_usage)
- 使用统计
- 性能评分
- 用户反馈
- 10个字段，7个索引

### 6. 素材审核记录表 (xmt_content_material_reviews)
- 审核历史
- 自动/人工审核
- 问题记录
- 9个字段，5个索引

## 素材类型

系统支持以下5种素材类型：

- **VIDEO** - 视频片段
- **AUDIO** - 音效、背景音乐
- **IMAGE** - 图片素材
- **TEXT** - 文案模板
- **TRANSITION** - 转场效果

## 审核流程

1. 素材上传后状态为 PENDING
2. 触发自动审核 (review_type=AUTO)
3. 如需人工审核，通知审核人员 (review_type=MANUAL)
4. 审核结果：APPROVED（通过）/ REJECTED（拒绝）/ FLAGGED（标记）
5. 通过审核后状态变为 APPROVED，可以正常使用

## 推荐优化机制

系统会根据以下数据自动优化素材推荐权重：

- 使用次数 (usage_count)
- 成功使用次数 (success_count)
- 性能评分 (performance_score)
- 用户反馈 (user_feedback)
- 质量评分 (quality_score)

## 性能优化

### 已实施的优化
- 为常用查询字段添加索引
- 使用合适的数据类型
- JSON字段用于灵活扩展
- 唯一约束防止重复数据

### 建议的优化
- 大数据量时考虑分区
- 热门素材使用缓存
- 定期归档历史数据
- 监控慢查询并优化

## 初始化数据

### 默认分类
```sql
INSERT INTO xmt_content_material_categories (name, type, description, sort_order, status, create_time, update_time) VALUES
('美食视频', 'VIDEO', '美食相关视频素材', 1, 1, NOW(), NOW()),
('餐厅场景', 'VIDEO', '餐厅环境视频素材', 2, 1, NOW(), NOW()),
('背景音乐', 'AUDIO', '各类背景音乐', 1, 1, NOW(), NOW()),
('音效', 'AUDIO', '特效音效素材', 2, 1, NOW(), NOW()),
('转场特效', 'TRANSITION', '视频转场效果', 1, 1, NOW(), NOW()),
('文案模板', 'TEXT', '各类文案模板', 1, 1, NOW(), NOW());
```

### 常用标签
```sql
INSERT INTO xmt_content_material_tags (name, type, create_time) VALUES
('美食', 'ALL', NOW()),
('餐厅', 'ALL', NOW()),
('促销', 'ALL', NOW()),
('新品', 'ALL', NOW()),
('欢快', 'AUDIO', NOW()),
('温馨', 'ALL', NOW());
```

## 相关文档

- **设计文档**: `database/CONTENT_MATERIALS_SCHEMA.md`
- **完成总结**: `database/TASK_47_COMPLETION_SUMMARY.md`

## 注意事项

1. **字符集**: 必须使用 utf8mb4
2. **存储引擎**: 必须使用 InnoDB
3. **表前缀**: 所有表使用 xmt_ 前缀
4. **备份**: 执行迁移前请备份数据库
5. **测试**: 建议先在开发环境测试

## 依赖关系

此迁移文件不依赖其他表，可以独立执行。但建议在以下表已创建后执行：
- xmt_users（用户表）
- xmt_merchants（商家表）
- xmt_content_templates（内容模板表）

## 版本信息

- **创建日期**: 2025-10-01
- **版本**: 1.0.0
- **作者**: Claude Code
- **任务编号**: Task 47

## 技术支持

如有问题，请参考：
1. 设计文档：`database/CONTENT_MATERIALS_SCHEMA.md`
2. 测试脚本：`database/test_content_materials_migration.php`
3. 验证脚本：`database/validate_materials_migration_syntax.php`