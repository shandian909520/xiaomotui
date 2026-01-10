-- 创建敏感词表
CREATE TABLE IF NOT EXISTS `sensitive_words` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '词ID',
  `word` varchar(100) NOT NULL COMMENT '敏感词',
  `category` varchar(50) DEFAULT NULL COMMENT '分类',
  `level` tinyint(1) DEFAULT 1 COMMENT '等级 1-5',
  `action` enum('BLOCK','REVIEW','REPLACE') DEFAULT 'REVIEW' COMMENT '处理动作',
  `status` tinyint(1) DEFAULT 1 COMMENT '状态 0禁用 1启用',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_word` (`word`),
  KEY `idx_category` (`category`),
  KEY `idx_level` (`level`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='敏感词表';

-- 插入一些示例敏感词（实际使用时应该导入完整的敏感词库）
INSERT INTO `sensitive_words` (`word`, `category`, `level`, `action`, `status`, `create_time`, `update_time`) VALUES
('测试敏感词', 'OTHER', 1, 'REVIEW', 1, NOW(), NOW()),
('违规词汇', 'ILLEGAL', 3, 'BLOCK', 1, NOW(), NOW()),
('广告推广', 'SPAM', 2, 'REVIEW', 1, NOW(), NOW()),
('敏感内容', 'OTHER', 2, 'REVIEW', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `update_time` = NOW();