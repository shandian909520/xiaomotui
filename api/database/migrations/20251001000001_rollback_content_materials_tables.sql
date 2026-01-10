-- AI内容素材库管理系统表结构回滚脚本
-- 按照依赖关系逆序删除表

-- 删除审核记录表
DROP TABLE IF EXISTS `xmt_content_material_reviews`;

-- 删除使用记录表
DROP TABLE IF EXISTS `xmt_content_material_usage`;

-- 删除标签关联表
DROP TABLE IF EXISTS `xmt_content_material_tag_relations`;

-- 删除素材主表
DROP TABLE IF EXISTS `xmt_content_materials`;

-- 删除标签表
DROP TABLE IF EXISTS `xmt_content_material_tags`;

-- 删除分类表
DROP TABLE IF EXISTS `xmt_content_material_categories`;