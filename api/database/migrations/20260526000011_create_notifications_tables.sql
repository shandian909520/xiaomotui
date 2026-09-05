-- 功能通知表
CREATE TABLE IF NOT EXISTS `xmt_notifications` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `merchant_id` int(11) unsigned DEFAULT NULL COMMENT '商家ID(NULL=全局通知)',
  `title` varchar(200) NOT NULL COMMENT '通知标题',
  `content` text DEFAULT NULL COMMENT '通知内容',
  `type` enum('feature_update','system','activity') NOT NULL DEFAULT 'feature_update' COMMENT '通知类型',
  `is_read` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否已读',
  `extra_data` json DEFAULT NULL COMMENT '扩展数据(链接/图片等)',
  `publish_time` datetime DEFAULT NULL COMMENT '发布时间',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_merchant` (`merchant_id`),
  KEY `idx_type` (`type`),
  KEY `idx_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='功能通知表';

-- 任务中心表
CREATE TABLE IF NOT EXISTS `xmt_user_tasks` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `merchant_id` int(11) unsigned NOT NULL COMMENT '商家ID',
  `user_id` int(11) unsigned DEFAULT NULL COMMENT '操作用户ID',
  `task_type` varchar(50) NOT NULL COMMENT '任务类型(video_export/ai_generate/clip_export等)',
  `task_name` varchar(200) NOT NULL COMMENT '任务名称',
  `status` enum('pending','processing','completed','failed') NOT NULL DEFAULT 'pending' COMMENT '状态',
  `progress` tinyint(4) NOT NULL DEFAULT 0 COMMENT '进度百分比(0-100)',
  `result_data` json DEFAULT NULL COMMENT '结果数据',
  `error_msg` varchar(500) DEFAULT NULL COMMENT '失败原因',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_merchant` (`merchant_id`),
  KEY `idx_status` (`status`),
  KEY `idx_type` (`task_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='任务中心表';

-- 物料设计场景表
CREATE TABLE IF NOT EXISTS `xmt_design_scenes` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `scene_key` varchar(50) NOT NULL COMMENT '场景标识(table_sticker/badge/banner等)',
  `scene_name` varchar(100) NOT NULL COMMENT '场景名称',
  `icon` varchar(200) DEFAULT NULL COMMENT '图标',
  `description` varchar(500) DEFAULT NULL COMMENT '场景描述',
  `template_count` int(11) NOT NULL DEFAULT 0 COMMENT '模板数量',
  `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态(0=禁用 1=启用)',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_scene_key` (`scene_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='物料设计场景表';

-- 初始化物料设计场景数据
INSERT INTO `xmt_design_scenes` (`scene_key`, `scene_name`, `icon`, `description`, `sort_order`) VALUES
('table_sticker', '桌贴', 'Grid', '桌贴二维码激活物料', 1),
('badge', '店员工牌', 'User', '员工佩戴二维码工牌', 2),
('roll_up', '易拉宝', 'PictureFilled', '门店易拉宝展示物料', 3),
('product_pack', '产品包装', 'Box', '产品外包装二维码', 4),
('takeout_pack', '外卖包装', 'ShoppingBag', '外卖包装贴纸物料', 5),
('window', '门店橱窗', 'Monitor', '橱窗展示贴纸', 6),
('display_stand', '活动展架', 'SetUp', '活动促销展架物料', 7),
('receipt', '小票', 'Tickets', '小票打印二维码', 8),
('member_card', '会员卡/礼品卡', 'Postcard', '会员卡礼品卡设计', 9),
('fitting_room', '试衣间', 'House', '试衣间引导物料', 10),
('poster', '海报定制', 'PictureFilled', '自定义海报设计', 11);
