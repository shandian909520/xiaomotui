-- 迁移记录表
-- 此表用于跟踪已执行的数据库迁移
DROP TABLE IF EXISTS `xmt_migration_log`;

-- 创建迁移记录表
CREATE TABLE `xmt_migration_log` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `migration_name` varchar(255) NOT NULL COMMENT '迁移文件名',
  `batch` int(11) NOT NULL COMMENT '批次号',
  `executed_at` datetime NOT NULL COMMENT '执行时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `migration_name` (`migration_name`),
  KEY `batch` (`batch`),
  KEY `executed_at` (`executed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='数据库迁移记录表';