-- Agent C 业务闭环:AI 评价灵感草稿模板表
-- 用于商家自定义"AI 生成草稿"时的输入模板,缺失时 ReviewService 回退到内置/兜底模板
CREATE TABLE IF NOT EXISTS `xmt_review_draft_templates` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `merchant_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '商家ID(0=平台共享)',
  `platform` varchar(20) NOT NULL DEFAULT 'DIANPING' COMMENT 'DIANPING/MEITUAN/GAODE/BAIDU/DOUYIN',
  `scene_key` varchar(50) NOT NULL DEFAULT 'default' COMMENT '业务场景, default/custom',
  `title` varchar(100) NOT NULL DEFAULT '' COMMENT '模板标题(展示用)',
  `prompt` text NOT NULL COMMENT '提示词(支持占位符 {merchant_name})',
  `style` varchar(50) NOT NULL DEFAULT '亲切自然' COMMENT 'AI 风格',
  `weight` int(11) unsigned NOT NULL DEFAULT 10 COMMENT '权重(越大越靠前)',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0 停用 1 启用',
  `sort` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '人工排序',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_merchant_platform` (`merchant_id`, `platform`, `status`),
  KEY `idx_scene_key` (`scene_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='AI 评价灵感草稿模板(商家自定义)';

-- 默认兜底模板(DIANPING)
INSERT IGNORE INTO `xmt_review_draft_templates`
  (`merchant_id`, `platform`, `scene_key`, `title`, `prompt`, `style`, `weight`, `status`, `sort`)
VALUES
  (0, 'DIANPING', 'default', '真实体验分享', '请生成 {count} 条候选评价文案,围绕【{merchant_name}】展开,要求第一人称、口语化、30-80字,带具体细节但不得编造菜品或价格。每条用换行分隔。', '亲切自然', 10, 1, 0),
  (0, 'MEITUAN',  'default', '真实体验分享', '请生成 {count} 条候选评价文案,围绕【{merchant_name}】展开,要求第一人称、口语化、30-80字,带具体细节但不得编造菜品或价格。每条用换行分隔。', '亲切自然', 10, 1, 0),
  (0, 'DOUYIN',   'default', '真实体验分享', '请生成 {count} 条候选评价文案,围绕【{merchant_name}】展开,要求第一人称、口语化、30-80字,带具体细节但不得编造菜品或价格。每条用换行分隔。', '亲切自然', 10, 1, 0);
