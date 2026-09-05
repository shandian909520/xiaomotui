-- 智能员工角色表
CREATE TABLE IF NOT EXISTS `xmt_ai_staff_roles` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `group_name` varchar(50) NOT NULL COMMENT '分组:内容文案组/视觉设计组/门店运营组/口碑管理组',
  `role_name` varchar(50) NOT NULL COMMENT '角色名称',
  `nickname` varchar(30) NOT NULL COMMENT '昵称(如郑编导)',
  `avatar_url` varchar(500) DEFAULT NULL COMMENT '头像URL',
  `description` varchar(200) DEFAULT NULL COMMENT '岗位职能描述',
  `task_types` json DEFAULT NULL COMMENT '支持的任务类型列表',
  `prompt_template` text DEFAULT NULL COMMENT '提示词模板',
  `is_hot` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否HOT标识',
  `free_count` int(11) NOT NULL DEFAULT 10 COMMENT '免费次数',
  `used_count` int(11) NOT NULL DEFAULT 0 COMMENT '已使用次数',
  `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态 0禁用 1启用',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_group` (`group_name`),
  KEY `idx_sort` (`sort_order`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='智能员工角色表';

-- 配音演员表
CREATE TABLE IF NOT EXISTS `xmt_voice_actors` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL COMMENT '演员名称',
  `gender` enum('male','female') NOT NULL DEFAULT 'female' COMMENT '性别',
  `style` varchar(100) DEFAULT NULL COMMENT '风格(如温柔/磁性/活泼/正式)',
  `sample_url` varchar(500) DEFAULT NULL COMMENT '试听音频URL',
  `voice_id` varchar(100) DEFAULT NULL COMMENT 'TTS引擎中的声音ID',
  `language` varchar(20) DEFAULT 'zh-CN' COMMENT '语言',
  `is_default` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否默认',
  `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态 0禁用 1启用',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_sort` (`sort_order`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='配音演员表';

-- 初始化智能员工角色数据
INSERT INTO `xmt_ai_staff_roles` (`group_name`, `role_name`, `nickname`, `description`, `task_types`, `is_hot`, `free_count`, `sort_order`) VALUES
('内容文案组', '视频口播文案', '郑编导', '擅长撰写短视频口播脚本，语言精炼有感染力', '["video_script"]', 1, 10, 1),
('内容文案组', '种草笔记', '红小编', '专注小红书种草笔记创作，图文并茂吸粉利器', '["notes"]', 1, 10, 2),
('内容文案组', '素人笔记', '褚策划', '模拟真实用户体验撰写素人种草内容', '["notes"]', 0, 10, 3),
('内容文案组', '文案改写', '卫文案', '对现有文案进行改写优化，提升传播效果', '["rewrite"]', 0, 10, 4),
('视觉设计组', '活动海报设计', '王设计', '为门店活动设计吸睛海报', '["poster_design"]', 0, 5, 5),
('视觉设计组', '商品美图', '李设计', '商品图片美化处理，提升视觉吸引力', '["image_design"]', 0, 5, 6),
('视觉设计组', '图生视频', '陈剪辑', '将图片素材转化为动态视频内容', '["image_to_video"]', 0, 3, 7),
('门店运营组', '套餐创意起名', '张起名', '为团购套餐起有创意的名字', '["naming"]', 0, 10, 8),
('门店运营组', '招牌产品起名', '刘起名', '为招牌产品设计响亮的名称', '["naming"]', 0, 10, 9),
('门店运营组', '团购SKU策划', '赵策划', '策划团购套餐SKU组合方案', '["sku_plan"]', 0, 5, 10),
('门店运营组', '拍菜单SKU规划', '孙策划', '规划菜单拍摄和SKU展示方案', '["menu_plan"]', 0, 5, 11),
('门店运营组', '榜单冲榜', '周运营', '制定平台榜单冲榜运营策略', '["ranking"]', 0, 5, 12),
('口碑管理组', '差评处理', '蒋公关', '专业回复差评，化解危机挽回客户', '["review_reply"]', 0, 10, 13),
('口碑管理组', '好评创作', '沈创意', '创作优质好评文案提升口碑', '["review_create"]', 0, 10, 14);

-- 初始化配音演员数据
INSERT INTO `xmt_voice_actors` (`name`, `gender`, `style`, `is_default`, `sort_order`) VALUES
('小云-温柔女声', 'female', '温柔', 1, 1),
('小明-磁性男声', 'male', '磁性', 1, 2),
('小雪-活泼女声', 'female', '活泼', 0, 3),
('小刚-正式男声', 'male', '正式', 0, 4),
('小梅-甜美女声', 'female', '甜美', 0, 5),
('小林-沉稳男声', 'male', '沉稳', 0, 6);
