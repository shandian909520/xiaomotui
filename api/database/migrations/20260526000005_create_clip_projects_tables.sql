-- 剪辑工程表
CREATE TABLE IF NOT EXISTS `xmt_clip_projects` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `merchant_id` int(11) unsigned NOT NULL COMMENT '商家ID',
  `user_id` int(11) unsigned DEFAULT NULL COMMENT '用户ID',
  `name` varchar(200) NOT NULL DEFAULT '未命名工程' COMMENT '工程名称',
  `mode` enum('auto','batch','storyboard') NOT NULL DEFAULT 'auto' COMMENT '模式:auto一键成片,batch批量混剪,storyboard分镜剪辑',
  `config` json DEFAULT NULL COMMENT '剪辑配置JSON',
  `status` enum('draft','completed','exporting','failed') NOT NULL DEFAULT 'draft' COMMENT '状态',
  `video_url` varchar(500) DEFAULT NULL COMMENT '导出视频URL',
  `duration` int DEFAULT 0 COMMENT '视频时长(秒)',
  `template_id` int(11) unsigned DEFAULT NULL COMMENT '关联模板ID',
  `is_template` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否为模板',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_merchant` (`merchant_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_mode` (`mode`),
  KEY `idx_status` (`status`),
  KEY `idx_template` (`template_id`),
  KEY `idx_create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='剪辑工程表';

-- 分镜表
CREATE TABLE IF NOT EXISTS `xmt_clip_shots` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` int(11) unsigned NOT NULL COMMENT '工程ID',
  `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `material_id` int(11) unsigned DEFAULT NULL COMMENT '素材ID',
  `material_type` enum('image','video') DEFAULT 'image' COMMENT '素材类型',
  `material_url` varchar(500) DEFAULT NULL COMMENT '素材URL(冗余)',
  `thumbnail_url` varchar(500) DEFAULT NULL COMMENT '缩略图URL',
  `duration` decimal(5,1) NOT NULL DEFAULT 3.0 COMMENT '时长(秒)',
  `subtitle` varchar(500) DEFAULT NULL COMMENT '分镜字幕',
  `voice_text` varchar(500) DEFAULT NULL COMMENT '配音文本',
  `voice_actor_id` int(11) unsigned DEFAULT NULL COMMENT '配音演员ID',
  `transition_type` varchar(20) DEFAULT 'none' COMMENT '转场:none/fade/slide/zoom/wipe/random',
  `filter_name` varchar(50) DEFAULT NULL COMMENT '滤镜名称',
  `mute_original` tinyint(1) NOT NULL DEFAULT 0 COMMENT '消除原声',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_project_sort` (`project_id`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='分镜表';
