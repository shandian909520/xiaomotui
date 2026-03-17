-- MySQL dump 10.13  Distrib 5.7.26, for Win64 (x86_64)
--
-- Host: localhost    Database: xiaomotui_dev
-- ------------------------------------------------------
-- Server version	5.7.26

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `anomaly_alerts`
--

DROP TABLE IF EXISTS `anomaly_alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `anomaly_alerts` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '异常ID',
  `merchant_id` int(11) unsigned DEFAULT NULL COMMENT '商家ID',
  `type` varchar(50) NOT NULL COMMENT '异常类型',
  `severity` enum('CRITICAL','HIGH','MEDIUM','LOW') DEFAULT 'MEDIUM' COMMENT '严重等级',
  `metric_name` varchar(100) DEFAULT NULL COMMENT '指标名称',
  `current_value` decimal(15,2) DEFAULT NULL COMMENT '当前值',
  `expected_value` decimal(15,2) DEFAULT NULL COMMENT '期望值',
  `deviation` decimal(10,2) DEFAULT NULL COMMENT '偏差百分比',
  `possible_causes` json DEFAULT NULL COMMENT '可能原因',
  `status` enum('DETECTED','NOTIFIED','HANDLING','RESOLVED','IGNORED') DEFAULT 'DETECTED' COMMENT '处理状态',
  `notified_at` datetime DEFAULT NULL COMMENT '通知时间',
  `resolved_at` datetime DEFAULT NULL COMMENT '解决时间',
  `handle_notes` text COMMENT '处理备注',
  `extra_data` json DEFAULT NULL COMMENT '额外数据',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_merchant_id` (`merchant_id`),
  KEY `idx_type` (`type`),
  KEY `idx_severity` (`severity`),
  KEY `idx_status` (`status`),
  KEY `idx_create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='异常预警表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `anomaly_alerts`
--

LOCK TABLES `anomaly_alerts` WRITE;
/*!40000 ALTER TABLE `anomaly_alerts` DISABLE KEYS */;
/*!40000 ALTER TABLE `anomaly_alerts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `coupons`
--

DROP TABLE IF EXISTS `coupons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `coupons` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '优惠券ID',
  `merchant_id` int(11) unsigned NOT NULL COMMENT '商家ID',
  `name` varchar(100) NOT NULL COMMENT '优惠券名称',
  `type` enum('DISCOUNT','FULL_REDUCE','FREE_SHIPPING') NOT NULL COMMENT '优惠券类型',
  `value` decimal(10,2) NOT NULL COMMENT '优惠金额',
  `min_amount` decimal(10,2) DEFAULT '0.00' COMMENT '最低消费金额',
  `total_count` int(11) NOT NULL COMMENT '总发放数量',
  `used_count` int(11) DEFAULT '0' COMMENT '已使用数量',
  `per_user_limit` int(11) DEFAULT '1' COMMENT '每人限领数量',
  `valid_days` int(11) DEFAULT '30' COMMENT '有效天数',
  `start_time` datetime NOT NULL COMMENT '开始时间',
  `end_time` datetime NOT NULL COMMENT '结束时间',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态 0禁用 1启用',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `merchant_id` (`merchant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COMMENT='优惠券表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `coupons`
--

LOCK TABLES `coupons` WRITE;
/*!40000 ALTER TABLE `coupons` DISABLE KEYS */;
INSERT INTO `coupons` VALUES (1,1,'TestCoupon_1770647994063','FULL_REDUCE',100.00,0.00,50,0,1,30,'2026-02-01 00:00:00','2026-02-06 00:00:00',1,'2026-02-09 22:39:55','2026-02-09 22:39:55');
/*!40000 ALTER TABLE `coupons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `device_triggers`
--

DROP TABLE IF EXISTS `device_triggers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `device_triggers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `device_id` int(10) unsigned NOT NULL COMMENT '设备ID',
  `device_code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '设备编码',
  `user_id` int(10) unsigned DEFAULT NULL COMMENT '用户ID',
  `user_openid` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '用户OpenID',
  `trigger_mode` enum('VIDEO','COUPON','WIFI','CONTACT','MENU') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '触发模式',
  `response_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '响应类型',
  `response_data` json DEFAULT NULL COMMENT '响应数据',
  `response_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '响应时间(毫秒)',
  `client_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT '客户端IP',
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT '用户代理',
  `success` tinyint(3) unsigned NOT NULL DEFAULT '1' COMMENT '是否成功 1成功 0失败',
  `error_message` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT '错误信息',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_device_id` (`device_id`),
  KEY `idx_device_code` (`device_code`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_user_openid` (`user_openid`),
  KEY `idx_trigger_mode` (`trigger_mode`),
  KEY `idx_success` (`success`),
  KEY `idx_create_time` (`create_time`),
  KEY `idx_response_time` (`response_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='设备触发记录表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `device_triggers`
--

LOCK TABLES `device_triggers` WRITE;
/*!40000 ALTER TABLE `device_triggers` DISABLE KEYS */;
/*!40000 ALTER TABLE `device_triggers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_admin`
--

DROP TABLE IF EXISTS `xmt_admin`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_admin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL COMMENT '用户名',
  `password` varchar(255) NOT NULL COMMENT '密码',
  `nickname` varchar(50) DEFAULT NULL COMMENT '昵称',
  `avatar` varchar(255) DEFAULT NULL COMMENT '头像',
  `email` varchar(100) DEFAULT NULL COMMENT '邮箱',
  `mobile` varchar(20) DEFAULT NULL COMMENT '手机号',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态: 0=禁用, 1=启用',
  `login_time` int(10) unsigned DEFAULT NULL COMMENT '最后登录时间',
  `login_ip` varchar(50) DEFAULT NULL COMMENT '最后登录IP',
  `create_time` int(10) unsigned NOT NULL COMMENT '创建时间',
  `update_time` int(10) unsigned NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COMMENT='管理员表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_admin`
--

LOCK TABLES `xmt_admin` WRITE;
/*!40000 ALTER TABLE `xmt_admin` DISABLE KEYS */;
INSERT INTO `xmt_admin` VALUES (1,'admin','$2y$10$geHMa2kWGhI3xeVhaxRTDuranFNiCsVo9pCtw.oamyGOBRN/NQe6i','超级管理员',NULL,NULL,NULL,1,NULL,NULL,1770450869,1770450869);
/*!40000 ALTER TABLE `xmt_admin` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_contact_actions`
--

DROP TABLE IF EXISTS `xmt_contact_actions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_contact_actions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `device_id` int(11) unsigned NOT NULL COMMENT '设备ID',
  `merchant_id` int(11) unsigned NOT NULL COMMENT '商家ID',
  `user_id` int(11) unsigned DEFAULT NULL COMMENT '用户ID（游客为NULL）',
  `contact_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '联系方式类型 wework/wechat/phone',
  `trigger_time` datetime NOT NULL COMMENT '触发时间',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'IP地址',
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '用户代理',
  `extra_data` json DEFAULT NULL COMMENT '额外数据JSON',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_device_id` (`device_id`),
  KEY `idx_merchant_id` (`merchant_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_contact_type` (`contact_type`),
  KEY `idx_trigger_time` (`trigger_time`),
  KEY `idx_create_time` (`create_time`),
  KEY `idx_merchant_time` (`merchant_id`,`trigger_time`),
  KEY `idx_device_time` (`device_id`,`trigger_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='联系行为记录表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_contact_actions`
--

LOCK TABLES `xmt_contact_actions` WRITE;
/*!40000 ALTER TABLE `xmt_contact_actions` DISABLE KEYS */;
/*!40000 ALTER TABLE `xmt_contact_actions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_content_audits`
--

DROP TABLE IF EXISTS `xmt_content_audits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_content_audits` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '审核ID',
  `content_id` int(11) unsigned NOT NULL COMMENT '内容ID',
  `content_type` enum('MATERIAL','CONTENT_TASK','COMMENT','USER_CONTENT') NOT NULL COMMENT '内容类型',
  `audit_type` enum('TEXT','IMAGE','VIDEO','AUDIO') NOT NULL COMMENT '审核类型',
  `audit_method` enum('AUTO','MANUAL','MIXED') DEFAULT 'AUTO' COMMENT '审核方式',
  `status` tinyint(1) DEFAULT '0' COMMENT '审核状态 0待审核 1通过 2拒绝 3审核中',
  `auto_result` json DEFAULT NULL COMMENT '自动审核结果',
  `manual_result` json DEFAULT NULL COMMENT '人工审核结果',
  `risk_level` enum('LOW','MEDIUM','HIGH','CRITICAL') DEFAULT 'LOW' COMMENT '风险等级',
  `violation_types` json DEFAULT NULL COMMENT '违规类型',
  `audit_message` text COMMENT '审核信息',
  `auditor_id` int(11) DEFAULT NULL COMMENT '审核员ID',
  `submit_time` datetime NOT NULL COMMENT '提交时间',
  `audit_time` datetime DEFAULT NULL COMMENT '审核完成时间',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_content` (`content_id`,`content_type`),
  KEY `idx_status` (`status`),
  KEY `idx_risk_level` (`risk_level`),
  KEY `idx_submit_time` (`submit_time`),
  KEY `idx_auditor_id` (`auditor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='内容审核记录表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_content_audits`
--

LOCK TABLES `xmt_content_audits` WRITE;
/*!40000 ALTER TABLE `xmt_content_audits` DISABLE KEYS */;
/*!40000 ALTER TABLE `xmt_content_audits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_content_feedbacks`
--

DROP TABLE IF EXISTS `xmt_content_feedbacks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_content_feedbacks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '反馈ID',
  `task_id` bigint(20) unsigned NOT NULL COMMENT '任务ID',
  `user_id` int(10) unsigned NOT NULL COMMENT '用户ID',
  `merchant_id` int(10) unsigned DEFAULT NULL COMMENT '商家ID',
  `feedback_type` enum('like','dislike') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '反馈类型',
  `reasons` json DEFAULT NULL COMMENT '不满意原因列表',
  `other_reason` text COLLATE utf8mb4_unicode_ci COMMENT '其他原因描述',
  `submit_time` datetime NOT NULL COMMENT '提交时间',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_task_id` (`task_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_merchant_id` (`merchant_id`),
  KEY `idx_feedback_type` (`feedback_type`),
  KEY `idx_create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='内容反馈表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_content_feedbacks`
--

LOCK TABLES `xmt_content_feedbacks` WRITE;
/*!40000 ALTER TABLE `xmt_content_feedbacks` DISABLE KEYS */;
/*!40000 ALTER TABLE `xmt_content_feedbacks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_content_moderation_blacklist`
--

DROP TABLE IF EXISTS `xmt_content_moderation_blacklist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_content_moderation_blacklist` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `blacklist_type` varchar(50) NOT NULL COMMENT '黑名单类型: user/content/ip',
  `reason` text COMMENT '加入黑名单原因',
  `violation_count` int(11) NOT NULL DEFAULT '1' COMMENT '违规次数',
  `severity` varchar(20) NOT NULL DEFAULT 'MEDIUM' COMMENT '严重程度',
  `auto_add` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否自动添加: 0-手动,1-自动',
  `created_by` int(11) DEFAULT NULL COMMENT '创建人ID',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `expires_at` datetime DEFAULT NULL COMMENT '过期时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_type` (`blacklist_type`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='内容审核黑名单表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_content_moderation_blacklist`
--

LOCK TABLES `xmt_content_moderation_blacklist` WRITE;
/*!40000 ALTER TABLE `xmt_content_moderation_blacklist` DISABLE KEYS */;
/*!40000 ALTER TABLE `xmt_content_moderation_blacklist` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_content_moderation_logs`
--

DROP TABLE IF EXISTS `xmt_content_moderation_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_content_moderation_logs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '日志ID',
  `material_id` int(11) DEFAULT NULL COMMENT '素材ID',
  `content_type` varchar(20) NOT NULL COMMENT '内容类型',
  `provider` varchar(20) DEFAULT NULL COMMENT '服务商',
  `action` varchar(50) NOT NULL COMMENT '操作: check/async/cached/error',
  `request_data` text COMMENT '请求数据JSON',
  `response_data` text COMMENT '响应数据JSON',
  `execution_time` int(11) DEFAULT NULL COMMENT '执行时间(毫秒)',
  `error_message` text COMMENT '错误信息',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_material_id` (`material_id`),
  KEY `idx_provider` (`provider`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='内容审核日志表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_content_moderation_logs`
--

LOCK TABLES `xmt_content_moderation_logs` WRITE;
/*!40000 ALTER TABLE `xmt_content_moderation_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `xmt_content_moderation_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_content_moderation_results`
--

DROP TABLE IF EXISTS `xmt_content_moderation_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_content_moderation_results` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '结果ID',
  `task_id` varchar(64) DEFAULT NULL COMMENT '任务ID',
  `material_id` int(11) DEFAULT NULL COMMENT '素材ID',
  `provider` varchar(20) NOT NULL COMMENT '服务商',
  `pass` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否通过: 0-不通过,1-通过',
  `score` int(11) NOT NULL DEFAULT '100' COMMENT '评分(0-100)',
  `confidence` decimal(3,2) NOT NULL DEFAULT '1.00' COMMENT '置信度(0-1)',
  `suggestion` varchar(20) NOT NULL DEFAULT 'pass' COMMENT '审核建议: pass/review/reject',
  `violations` text COMMENT '违规详情JSON',
  `check_time` datetime NOT NULL COMMENT '检查时间',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_task_id` (`task_id`),
  KEY `idx_material_id` (`material_id`),
  KEY `idx_provider` (`provider`),
  KEY `idx_check_time` (`check_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='内容审核结果表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_content_moderation_results`
--

LOCK TABLES `xmt_content_moderation_results` WRITE;
/*!40000 ALTER TABLE `xmt_content_moderation_results` DISABLE KEYS */;
/*!40000 ALTER TABLE `xmt_content_moderation_results` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_content_moderation_tasks`
--

DROP TABLE IF EXISTS `xmt_content_moderation_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_content_moderation_tasks` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '任务ID',
  `task_id` varchar(64) NOT NULL COMMENT '任务唯一标识',
  `material_id` int(11) DEFAULT NULL COMMENT '素材ID',
  `content_type` varchar(20) NOT NULL COMMENT '内容类型: text/image/video/audio',
  `provider` varchar(20) NOT NULL COMMENT '服务商: baidu/aliyun/tencent',
  `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT '任务状态: pending/processing/completed/failed',
  `error_message` text COMMENT '错误信息',
  `result` text COMMENT '审核结果JSON',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `started_at` datetime DEFAULT NULL COMMENT '开始处理时间',
  `completed_at` datetime DEFAULT NULL COMMENT '完成时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_task_id` (`task_id`),
  KEY `idx_material_id` (`material_id`),
  KEY `idx_status` (`status`),
  KEY `idx_provider` (`provider`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='内容审核任务表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_content_moderation_tasks`
--

LOCK TABLES `xmt_content_moderation_tasks` WRITE;
/*!40000 ALTER TABLE `xmt_content_moderation_tasks` DISABLE KEYS */;
/*!40000 ALTER TABLE `xmt_content_moderation_tasks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_content_tasks`
--

DROP TABLE IF EXISTS `xmt_content_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_content_tasks` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '任务ID',
  `user_id` int(11) unsigned NOT NULL COMMENT '用户ID',
  `merchant_id` int(11) unsigned NOT NULL COMMENT '商家ID',
  `device_id` int(11) unsigned DEFAULT NULL COMMENT '设备ID',
  `template_id` int(11) unsigned DEFAULT NULL COMMENT '模板ID',
  `type` enum('VIDEO','TEXT','IMAGE') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '内容类型',
  `status` enum('PENDING','PROCESSING','COMPLETED','FAILED') COLLATE utf8mb4_unicode_ci DEFAULT 'PENDING' COMMENT '任务状态',
  `input_data` json DEFAULT NULL COMMENT '输入数据',
  `output_data` json DEFAULT NULL COMMENT '输出数据',
  `ai_provider` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'AI服务商',
  `generation_time` int(11) DEFAULT NULL COMMENT '生成耗时(秒)',
  `error_message` text COLLATE utf8mb4_unicode_ci COMMENT '错误信息',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  `complete_time` datetime DEFAULT NULL COMMENT '完成时间',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `merchant_id` (`merchant_id`),
  KEY `device_id` (`device_id`),
  KEY `template_id` (`template_id`),
  KEY `status` (`status`),
  KEY `type` (`type`),
  KEY `ai_provider` (`ai_provider`),
  KEY `idx_create_time` (`create_time`),
  KEY `idx_complete_time` (`complete_time`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='内容生成任务表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_content_tasks`
--

LOCK TABLES `xmt_content_tasks` WRITE;
/*!40000 ALTER TABLE `xmt_content_tasks` DISABLE KEYS */;
INSERT INTO `xmt_content_tasks` VALUES (1,0,0,NULL,NULL,'TEXT','COMPLETED','{\"scene\": \"探店推广\", \"style\": \"亲切\", \"category\": \"餐饮美食\", \"platform\": \"douyin\", \"requirements\": \"环境优美\"}','{\"text\": \"?【藏在城市里的绿野仙踪！】?\\n推门就是森系花园，光影洒满餐桌✨\\n闺蜜聚餐/约会圣地，随手拍都是ins风大片?\\n#网红餐厅打卡 #高颜值环境 #美食与美景并存\\n?定位戳这里，速来解锁治愈系用餐体验！\", \"model\": \"ernie-3.5-8k\", \"tokens\": 168}','wenxin',4,NULL,'2026-02-09 19:45:13','2026-02-09 19:45:13',NULL),(2,0,0,NULL,NULL,'TEXT','COMPLETED','{\"scene\": \"探店推广\", \"style\": \"亲切\", \"category\": \"餐饮美食\", \"platform\": \"douyin\", \"requirements\": \"全场8折 味美价廉\"}','{\"text\": \"?【味美价廉狂欢来袭！】?\\n这家宝藏餐厅全场8折！烟火气里藏美味，人均30吃到撑！快约饭搭子冲，错过拍大腿！#探店打卡 #味美价廉 #吃货福利\", \"model\": \"ernie-3.5-8k\", \"tokens\": 153}','wenxin',3,NULL,'2026-02-09 21:17:22','2026-02-09 21:17:22',NULL),(3,0,0,NULL,NULL,'TEXT','COMPLETED','{\"scene\": \"新品上市\", \"style\": \"幽默\", \"category\": \"餐饮美食\", \"platform\": \"douyin\", \"requirements\": \"测试核心卖点：美味好吃，价格实惠\"}','{\"text\": \"家人们谁懂啊！这新品美食一口入魂，好吃到跺jiojio，关键价格还超实惠，冲就完事儿！#新品美食 #美味实惠\", \"model\": \"ernie-3.5-8k\", \"tokens\": 139}','wenxin',2,NULL,'2026-02-09 22:45:13','2026-02-09 22:45:13',NULL),(4,0,1,1,1,'VIDEO','COMPLETED','{\"scene\": \"餐饮促销\", \"style\": \"现代\", \"platform\": \"douyin\"}','{\"title\": \"老王火锅春节特惠\", \"duration\": 15, \"thumbnail\": \"https://cdn.example.com/thumb/promo001.jpg\", \"video_url\": \"https://cdn.example.com/video/promo001.mp4\"}','wenxin',12,NULL,'2026-02-10 10:00:00','2026-02-10 10:00:12','2026-02-10 10:00:12'),(5,0,1,1,3,'VIDEO','COMPLETED','{\"scene\": \"节日促销\", \"style\": \"喜庆\", \"platform\": \"douyin\"}','{\"title\": \"春节大促销\", \"duration\": 8, \"thumbnail\": \"https://cdn.example.com/thumb/spring001.jpg\", \"video_url\": \"https://cdn.example.com/video/spring001.mp4\"}','wenxin',8,NULL,'2026-02-12 09:30:00','2026-02-12 09:30:08','2026-02-12 09:30:08'),(6,3,3,NULL,4,'TEXT','COMPLETED','{\"scene\": \"新品推广\", \"style\": \"文艺\", \"platform\": \"wechat\"}','{\"title\": \"燕麦拿铁上市\", \"content\": \"新品上市\"}','wenxin',5,NULL,'2026-02-13 14:00:00','2026-02-13 14:00:05','2026-02-13 14:00:05'),(7,4,4,NULL,1,'VIDEO','COMPLETED','{\"scene\": \"餐饮促销\", \"style\": \"热闹\", \"platform\": \"douyin\"}','{\"title\": \"小李川菜今日特价\", \"duration\": 10, \"video_url\": \"https://cdn.example.com/video/sichuan001.mp4\"}','wenxin',10,NULL,'2026-02-14 11:00:00','2026-02-14 11:00:10','2026-02-14 11:00:10'),(8,5,5,NULL,6,'VIDEO','COMPLETED','{\"scene\": \"服务推广\", \"style\": \"优雅\", \"platform\": \"xiaohongshu\"}','{\"title\": \"丽人美容水光针特惠\", \"duration\": 12, \"video_url\": \"https://cdn.example.com/video/beauty001.mp4\"}','wenxin',11,NULL,'2026-02-14 15:30:00','2026-02-14 15:30:11','2026-02-14 15:30:11'),(9,0,1,NULL,2,'TEXT','COMPLETED','{\"scene\": \"产品介绍\", \"style\": \"温馨\", \"platform\": \"wechat\"}','{\"title\": \"每日特价推荐\", \"content\": \"今日推荐菜品水煮鱼\"}','wenxin',4,NULL,'2026-02-15 08:00:00','2026-02-15 08:00:04','2026-02-15 08:00:04'),(10,0,3,NULL,4,'TEXT','PROCESSING','{\"scene\": \"新品推广\", \"style\": \"简约\", \"platform\": \"wechat\"}',NULL,'wenxin',NULL,NULL,'2026-02-16 09:00:00','2026-02-16 09:00:00',NULL),(11,0,4,NULL,1,'VIDEO','PENDING','{\"scene\": \"餐饮促销\", \"style\": \"热闹\", \"platform\": \"douyin\"}',NULL,NULL,NULL,NULL,'2026-02-16 10:00:00','2026-02-16 10:00:00',NULL),(12,0,1,1,1,'VIDEO','FAILED','{\"scene\": \"品牌宣传\", \"style\": \"高端\", \"platform\": \"douyin\"}',NULL,'wenxin',NULL,NULL,'2026-02-11 16:00:00','2026-02-11 16:01:00',NULL);
/*!40000 ALTER TABLE `xmt_content_tasks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_content_templates`
--

DROP TABLE IF EXISTS `xmt_content_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_content_templates` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '模板ID',
  `merchant_id` int(11) unsigned DEFAULT NULL COMMENT '商家ID 为空表示系统模板',
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '模板名称',
  `type` enum('VIDEO','TEXT','IMAGE') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '模板类型',
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '模板分类',
  `style` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '风格标签',
  `content` json NOT NULL COMMENT '模板内容配置',
  `preview_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '预览图',
  `video_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '视频文件URL',
  `video_duration` int(11) DEFAULT NULL COMMENT '视频时长(秒)',
  `video_resolution` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '视频分辨率 如1920x1080',
  `video_size` bigint(20) DEFAULT NULL COMMENT '视频文件大小(字节)',
  `video_format` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '视频格式 mp4/avi/mov等',
  `thumbnail_time` int(11) DEFAULT NULL COMMENT '缩略图截取时间点(秒)',
  `aspect_ratio` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT '16:9' COMMENT '宽高比 16:9/9:16/1:1等',
  `is_template` tinyint(1) DEFAULT '1' COMMENT '是否作为模板 0否 1是',
  `template_tags` json DEFAULT NULL COMMENT '模板标签 用于分类和搜索',
  `difficulty` enum('easy','medium','hard') COLLATE utf8mb4_unicode_ci DEFAULT 'easy' COMMENT '制作难度 简单/中等/困难',
  `industry` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '适用行业 餐饮/零售/教育等',
  `usage_count` int(11) DEFAULT '0' COMMENT '使用次数',
  `is_public` tinyint(1) DEFAULT '0' COMMENT '是否公开 0私有 1公开',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态 0禁用 1启用',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `merchant_id` (`merchant_id`),
  KEY `category` (`category`),
  KEY `type` (`type`),
  KEY `style` (`style`),
  KEY `is_public` (`is_public`),
  KEY `status` (`status`),
  KEY `idx_usage` (`usage_count`),
  KEY `idx_video_type` (`type`,`is_template`),
  KEY `idx_industry` (`industry`),
  KEY `idx_difficulty` (`difficulty`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='内容模板表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_content_templates`
--

LOCK TABLES `xmt_content_templates` WRITE;
/*!40000 ALTER TABLE `xmt_content_templates` DISABLE KEYS */;
INSERT INTO `xmt_content_templates` VALUES (1,NULL,'餐饮促销视频模板','VIDEO','促销','现代','{\"scenes\": [{\"text\": \"欢迎光临\", \"duration\": 3}, {\"text\": \"特价优惠\", \"duration\": 2}, {\"text\": \"欢迎下次光临\", \"duration\": 3}]}','/uploads/templates/promotion_preview.jpg',NULL,8,'1920x1080',NULL,'mp4',NULL,'16:9',1,'[\"餐饮\", \"促销\", \"特价\"]','easy','餐饮',35,1,1,'2026-02-07 12:49:02','2026-02-07 12:49:02'),(2,NULL,'产品介绍视频模板','VIDEO','自定义','简约','{\"scenes\": [{\"text\": \"产品展示\", \"duration\": 5}, {\"text\": \"核心优势\", \"duration\": 3}, {\"text\": \"联系我们\", \"duration\": 2}]}','/uploads/templates/product_preview.jpg',NULL,10,'1920x1080',NULL,'mp4',NULL,'16:9',1,'[\"产品\", \"介绍\", \"营销\"]','medium','零售',18,1,1,'2026-02-07 12:49:02','2026-02-07 12:49:02'),(3,NULL,'春节促销视频模板','VIDEO','节日促销','喜庆','{\"scenes\": [{\"text\": \"? 新春特惠\", \"color\": \"#FF0000\", \"duration\": 2, \"animation\": \"bounceIn\"}, {\"text\": \"{{店铺名}}\", \"color\": \"#FFD700\", \"duration\": 1.5}, {\"text\": \"全场菜品{{折扣}}折\", \"color\": \"#FFFFFF\", \"duration\": 3}, {\"text\": \"{{活动时间}}\", \"color\": \"#FFFF00\", \"duration\": 2}, {\"text\": \"祝您新春快乐！\", \"color\": \"#FF6B6B\", \"duration\": 2}], \"background\": {\"color\": \"#D32F2F\", \"music\": \"festive\"}}',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'16:9',1,'{\"0\": \"春节\", \"1\": \"促销\", \"2\": \"餐饮\"}','easy','餐饮',43,1,1,'2026-02-13 19:08:07','2026-02-16 17:06:19'),(4,NULL,'新品推广文案模板','TEXT','新品推广','文艺','{\"structure\": [{\"text\": \"☕ 新品上市 | {{产品名称}}\", \"type\": \"title\", \"style\": \"bold\"}, {\"text\": \"采用{{原料描述}}，{{口感描述}}\", \"type\": \"body\", \"style\": \"normal\"}, {\"text\": \"? 限时尝鲜价：¥{{价格}}\", \"type\": \"highlight\", \"style\": \"emphasis\"}, {\"text\": \"? {{店铺地址}}\", \"type\": \"cta\", \"style\": \"normal\"}]}',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'16:9',1,'[\"新品\", \"推广\", \"咖啡\"]','easy','餐饮',28,1,1,'2026-02-13 19:08:07','2026-02-13 19:08:07'),(5,NULL,'会员专属优惠海报','IMAGE','会员营销','温馨','{\"layout\": \"poster\", \"elements\": [{\"type\": \"background\", \"color\": \"#FFE4E1\"}, {\"text\": \"亲爱的{{会员姓名}}\", \"type\": \"text\", \"color\": \"#333333\", \"position\": {\"x\": 50, \"y\": 50}, \"font_size\": 24}, {\"text\": \"您有{{积分}}积分待使用\", \"type\": \"text\", \"color\": \"#666666\", \"position\": {\"x\": 50, \"y\": 100}, \"font_size\": 18}, {\"text\": \"会员专享{{折扣}}折\", \"type\": \"highlight\", \"color\": \"#FF0000\", \"position\": {\"x\": 50, \"y\": 150}, \"font_size\": 28}, {\"data\": \"{{会员二维码}}\", \"type\": \"qrcode\", \"position\": {\"x\": 50, \"y\": 200}}]}',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'16:9',1,'[\"会员\", \"优惠\", \"积分\"]','easy','零售',15,1,1,'2026-02-13 19:08:07','2026-02-13 19:08:07'),(6,NULL,'美容服务推广模板','VIDEO','服务推广','优雅','{\"scenes\": [{\"text\": \"✨ 发现更美的自己\", \"color\": \"#E91E63\", \"duration\": 2}, {\"text\": \"{{服务项目}}\", \"color\": \"#9C27B0\", \"duration\": 2.5}, {\"text\": \"专业技师 · 品质保障\", \"color\": \"#673AB7\", \"duration\": 2}, {\"text\": \"原价¥{{原价}} → 现价¥{{现价}}\", \"color\": \"#FF5722\", \"duration\": 2}, {\"text\": \"立即预约\", \"color\": \"#FF9800\", \"duration\": 1.5}]}',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'16:9',1,'[\"美容\", \"服务\", \"优惠\"]','easy','美容美发',22,1,1,'2026-02-13 19:08:07','2026-02-13 19:08:07'),(7,NULL,'活动邀请函模板','TEXT','活动邀请','正式','{\"structure\": [{\"text\": \"诚挚邀请\", \"type\": \"header\", \"style\": \"center\"}, {\"text\": \"{{活动名称}}\", \"type\": \"title\", \"style\": \"bold\"}, {\"text\": \"尊敬的{{客户姓名}}：\", \"type\": \"body\", \"style\": \"normal\"}, {\"text\": \"我们诚挚地邀请您参加{{活动描述}}\", \"type\": \"body\", \"style\": \"normal\"}, {\"text\": \"? 时间：{{活动时间}}\", \"type\": \"info\", \"style\": \"normal\"}, {\"text\": \"? 地点：{{活动地点}}\", \"type\": \"info\", \"style\": \"normal\"}, {\"text\": \"期待您的光临！\", \"type\": \"cta\", \"style\": \"emphasis\"}]}',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'16:9',1,'[\"活动\", \"邀请\", \"通用\"]','easy','通用',8,1,1,'2026-02-13 19:08:07','2026-02-13 19:08:07'),(8,NULL,'春节促销视频模板_副本','VIDEO','节日促销','喜庆','{\"scenes\": [{\"text\": \"? 新春特惠\", \"color\": \"#FF0000\", \"duration\": 2, \"animation\": \"bounceIn\"}, {\"text\": \"{{店铺名}}\", \"color\": \"#FFD700\", \"duration\": 1.5}, {\"text\": \"全场菜品{{折扣}}折\", \"color\": \"#FFFFFF\", \"duration\": 3}, {\"text\": \"{{活动时间}}\", \"color\": \"#FFFF00\", \"duration\": 2}, {\"text\": \"祝您新春快乐！\", \"color\": \"#FF6B6B\", \"duration\": 2}], \"background\": {\"color\": \"#D32F2F\", \"music\": \"festive\"}}','',NULL,NULL,NULL,NULL,NULL,NULL,'16:9',1,'{\"0\": \"春节\", \"1\": \"促销\", \"2\": \"餐饮\"}','easy','餐饮',0,1,1,'2026-02-16 17:06:19','2026-02-16 17:06:19');
/*!40000 ALTER TABLE `xmt_content_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_content_violations`
--

DROP TABLE IF EXISTS `xmt_content_violations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_content_violations` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '违规记录ID',
  `material_id` int(11) unsigned NOT NULL COMMENT '素材ID',
  `merchant_id` int(11) unsigned NOT NULL COMMENT '商家ID',
  `violation_type` enum('SENSITIVE','ILLEGAL','PORN','VIOLENCE','AD','FRAUD','SPAM','COPYRIGHT','OTHER') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '违规类型',
  `severity` enum('HIGH','MEDIUM','LOW') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '严重程度',
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '违规标题',
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '违规描述',
  `details` json DEFAULT NULL COMMENT '违规详情(关键词、检测结果等)',
  `detection_method` enum('AUTO','MANUAL','REPORT') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '检测方式',
  `detector_id` int(11) unsigned DEFAULT NULL COMMENT '检测人ID(手动检测时)',
  `reporter_id` int(11) unsigned DEFAULT NULL COMMENT '举报人ID(举报时)',
  `report_reason` text COLLATE utf8mb4_unicode_ci COMMENT '举报原因',
  `action_taken` enum('DISABLED','WARNING','DELETED','NONE') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DISABLED' COMMENT '处理动作',
  `status` enum('PENDING','CONFIRMED','APPEALED','RESOLVED','DISMISSED') COLLATE utf8mb4_unicode_ci DEFAULT 'PENDING' COMMENT '状态',
  `appeal_id` int(11) unsigned DEFAULT NULL COMMENT '申诉ID',
  `evidence_urls` json DEFAULT NULL COMMENT '证据截图URL列表',
  `auto_disable` tinyint(1) DEFAULT '1' COMMENT '是否自动下架 0否 1是',
  `notification_sent` tinyint(1) DEFAULT '0' COMMENT '是否已通知 0否 1是',
  `notification_time` datetime DEFAULT NULL COMMENT '通知时间',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `resolve_time` datetime DEFAULT NULL COMMENT '处理完成时间',
  `resolver_id` int(11) unsigned DEFAULT NULL COMMENT '处理人ID',
  `resolve_comment` text COLLATE utf8mb4_unicode_ci COMMENT '处理备注',
  PRIMARY KEY (`id`),
  KEY `idx_material` (`material_id`),
  KEY `idx_merchant` (`merchant_id`),
  KEY `idx_status` (`status`),
  KEY `idx_violation_type` (`violation_type`),
  KEY `idx_severity` (`severity`),
  KEY `idx_detection_method` (`detection_method`),
  KEY `idx_create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='内容违规记录表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_content_violations`
--

LOCK TABLES `xmt_content_violations` WRITE;
/*!40000 ALTER TABLE `xmt_content_violations` DISABLE KEYS */;
/*!40000 ALTER TABLE `xmt_content_violations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_coupon_users`
--

DROP TABLE IF EXISTS `xmt_coupon_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_coupon_users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `coupon_id` int(10) unsigned NOT NULL COMMENT '优惠券ID',
  `user_id` int(10) unsigned NOT NULL COMMENT '用户ID',
  `coupon_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '优惠券代码',
  `use_status` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '使用状态 0未使用 1已使用 2已过期',
  `used_time` timestamp NULL DEFAULT NULL COMMENT '使用时间',
  `order_id` bigint(20) unsigned DEFAULT NULL COMMENT '关联订单ID',
  `received_source` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT '领取来源',
  `device_id` int(10) unsigned DEFAULT NULL COMMENT '关联设备ID（NFC设备领取时）',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_coupon_code` (`coupon_code`),
  KEY `idx_coupon_id` (`coupon_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_use_status` (`use_status`),
  KEY `idx_used_time` (`used_time`),
  KEY `idx_device_id` (`device_id`),
  KEY `idx_received_source` (`received_source`),
  KEY `idx_create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户优惠券表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_coupon_users`
--

LOCK TABLES `xmt_coupon_users` WRITE;
/*!40000 ALTER TABLE `xmt_coupon_users` DISABLE KEYS */;
/*!40000 ALTER TABLE `xmt_coupon_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_device_alerts`
--

DROP TABLE IF EXISTS `xmt_device_alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_device_alerts` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `device_id` int(11) NOT NULL COMMENT '设备ID',
  `device_code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '设备编码',
  `merchant_id` int(11) NOT NULL COMMENT '商家ID',
  `alert_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '告警类型',
  `alert_level` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '告警级别',
  `alert_title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '告警标题',
  `alert_message` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '告警内容',
  `alert_data` json DEFAULT NULL COMMENT '告警数据',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT '告警状态',
  `trigger_time` datetime NOT NULL COMMENT '触发时间',
  `resolve_time` datetime DEFAULT NULL COMMENT '解决时间',
  `resolve_user_id` int(11) DEFAULT NULL COMMENT '解决者ID',
  `resolve_note` text COLLATE utf8mb4_unicode_ci COMMENT '解决备注',
  `notification_sent` int(1) NOT NULL DEFAULT '0' COMMENT '是否已发送通知',
  `notification_channels` json DEFAULT NULL COMMENT '通知渠道',
  `notification_logs` json DEFAULT NULL COMMENT '通知日志',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_device_id` (`device_id`),
  KEY `idx_merchant_id` (`merchant_id`),
  KEY `idx_alert_type` (`alert_type`),
  KEY `idx_alert_level` (`alert_level`),
  KEY `idx_status` (`status`),
  KEY `idx_trigger_time` (`trigger_time`),
  KEY `idx_create_time` (`create_time`),
  KEY `idx_device_type_status` (`device_id`,`alert_type`,`status`),
  KEY `idx_merchant_status` (`merchant_id`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='设备告警记录表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_device_alerts`
--

LOCK TABLES `xmt_device_alerts` WRITE;
/*!40000 ALTER TABLE `xmt_device_alerts` DISABLE KEYS */;
INSERT INTO `xmt_device_alerts` VALUES (1,1,'NFC001',1,'LOW_BATTERY','warning','设备电量低','NFC001 电量低于30%','{\"battery_level\": 25}','resolved','2026-02-14 08:00:00','2026-02-14 10:00:00',0,NULL,1,NULL,NULL,'2026-02-14 08:00:00','2026-02-14 10:00:00'),(2,6,'NFC_TABLE_201',4,'LOW_BATTERY','warning','设备电量低','NFC_TABLE_201 电量低于30%','{\"battery_level\": 20}','pending','2026-02-16 06:00:00',NULL,NULL,NULL,1,NULL,NULL,'2026-02-16 06:00:00','2026-02-16 06:00:00'),(3,4,'NFC_TABLE_101',3,'OFFLINE','critical','设备离线','NFC_TABLE_101 超过30分钟未上报心跳','{\"last_heartbeat\": \"2026-02-16 07:45:00\"}','pending','2026-02-16 08:20:00',NULL,NULL,NULL,1,NULL,NULL,'2026-02-16 08:20:00','2026-02-16 08:20:00'),(4,2,'NFC_TABLE_002',1,'TRIGGER_FAILURE','warning','NFC触发失败率过高','最近1小时触发失败率达到15%','{\"failure_rate\": 0.15}','acknowledged','2026-02-15 14:00:00',NULL,0,NULL,1,NULL,NULL,'2026-02-15 14:00:00','2026-02-15 16:00:00'),(5,8,'NFC_COUNTER_301',5,'ABNORMAL_TRAFFIC','critical','异常流量告警','设备10分钟内触发次数超过阈值50次','{\"trigger_count\": 68}','pending','2026-02-16 09:30:00',NULL,NULL,NULL,0,NULL,NULL,'2026-02-16 09:30:00','2026-02-16 09:30:00');
/*!40000 ALTER TABLE `xmt_device_alerts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_device_triggers`
--

DROP TABLE IF EXISTS `xmt_device_triggers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_device_triggers` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `device_id` int(11) NOT NULL COMMENT '设备ID',
  `device_code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '设备编码',
  `merchant_id` int(11) NOT NULL COMMENT '商家ID',
  `user_id` int(11) DEFAULT NULL COMMENT '用户ID',
  `user_openid` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '用户OpenID',
  `trigger_mode` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'nfc' COMMENT '触发模式',
  `response_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '响应类型',
  `response_data` json DEFAULT NULL COMMENT '响应数据',
  `response_time` int(11) DEFAULT NULL COMMENT '响应时间(毫秒)',
  `client_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '客户端IP',
  `user_agent` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '用户代理',
  `success` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否成功 1成功 0失败',
  `error_message` text COLLATE utf8mb4_unicode_ci COMMENT '错误信息',
  `trigger_time` datetime NOT NULL COMMENT '触发时间',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_device_id` (`device_id`),
  KEY `idx_merchant_id` (`merchant_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_trigger_time` (`trigger_time`),
  KEY `idx_create_time` (`create_time`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='设备触发记录表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_device_triggers`
--

LOCK TABLES `xmt_device_triggers` WRITE;
/*!40000 ALTER TABLE `xmt_device_triggers` DISABLE KEYS */;
INSERT INTO `xmt_device_triggers` VALUES (1,1,'NFC001',1,1,'oXXXX_user001','nfc','video','{\"video_url\": \"https://cdn.example.com/video/promo001.mp4\"}',120,'192.168.1.100',NULL,1,NULL,'2026-02-16 07:30:00','2026-02-16 07:30:00'),(2,1,'NFC001',1,2,'oXXXX_user002','nfc','video','{\"video_url\": \"https://cdn.example.com/video/promo001.mp4\"}',95,'192.168.1.101',NULL,1,NULL,'2026-02-16 08:15:00','2026-02-16 08:15:00'),(3,2,'NFC_TABLE_002',1,3,'oXXXX_user003','nfc','video','{\"video_url\": \"https://cdn.example.com/video/spring001.mp4\"}',110,'192.168.1.102',NULL,1,NULL,'2026-02-16 09:00:00','2026-02-16 09:00:00'),(4,3,'NFC_COUNTER_001',1,1,'oXXXX_user001','nfc','coupon','{\"discount\": \"8折\", \"coupon_id\": 1}',80,'192.168.1.100',NULL,1,NULL,'2026-02-15 12:30:00','2026-02-15 12:30:00'),(5,4,'NFC_TABLE_101',3,4,'oXXXX_user004','nfc','video','{\"video_url\": \"https://cdn.example.com/video/coffee001.mp4\"}',105,'192.168.2.50',NULL,1,NULL,'2026-02-16 08:30:00','2026-02-16 08:30:00'),(6,4,'NFC_TABLE_101',3,5,'oXXXX_user005','nfc','video',NULL,3000,'192.168.2.51',NULL,0,NULL,'2026-02-16 09:10:00','2026-02-16 09:10:00'),(7,6,'NFC_TABLE_201',4,1,'oXXXX_user001','nfc','video','{\"video_url\": \"https://cdn.example.com/video/sichuan001.mp4\"}',130,'192.168.3.10',NULL,1,NULL,'2026-02-15 18:00:00','2026-02-15 18:00:00'),(8,7,'NFC_ENTRANCE_201',4,2,'oXXXX_user002','nfc','group_buy','{\"price\": \"99\", \"group_buy_id\": 1}',90,'192.168.3.11',NULL,1,NULL,'2026-02-15 18:30:00','2026-02-15 18:30:00'),(9,8,'NFC_COUNTER_301',5,3,'oXXXX_user003','nfc','coupon','{\"discount\": \"满200减50\", \"coupon_id\": 2}',75,'192.168.4.20',NULL,1,NULL,'2026-02-16 10:00:00','2026-02-16 10:00:00'),(10,1,'NFC001',1,4,'oXXXX_user004','nfc','video','{\"video_url\": \"https://cdn.example.com/video/promo001.mp4\"}',100,'192.168.1.103',NULL,1,NULL,'2026-02-14 19:20:00','2026-02-14 19:20:00'),(11,1,'NFC001',1,5,'oXXXX_user005','nfc','video','{\"video_url\": \"https://cdn.example.com/video/promo001.mp4\"}',88,'192.168.1.104',NULL,1,NULL,'2026-02-13 20:10:00','2026-02-13 20:10:00'),(12,4,'NFC_TABLE_101',3,1,'oXXXX_user001','nfc','video','{\"video_url\": \"https://cdn.example.com/video/coffee001.mp4\"}',115,'192.168.2.52',NULL,1,NULL,'2026-02-15 15:00:00','2026-02-15 15:00:00'),(13,9,'PROMO_TEST_001',1,NULL,'anonymous_ec350abea3b7efb5769eab5ce9c6d7bf','PROMO','show_promo','{\"tags\": [\"#美食推荐\", \"#打卡探店\"], \"type\": \"promo\", \"video\": {\"url\": \"https://www.w3schools.com/html/mov_bbb.mp4\", \"title\": \"测试推广视频\", \"duration\": 10, \"thumbnail\": \"https://via.placeholder.com/320x180?text=Promo+Video\"}, \"action\": \"show_promo\", \"reward\": null, \"message\": \"推广素材加载成功\", \"merchant\": {\"logo\": \"\", \"name\": \"系统管理员\", \"description\": \"\"}, \"platforms\": [\"douyin\", \"kuaishou\"], \"copywriting\": \"这家店太好吃了，强烈推荐给大家！环境好服务棒，下次还来\"}',46,'127.0.0.1','curl/8.14.1',1,'','2026-02-18 08:45:22','2026-02-18 08:45:23'),(14,9,'PROMO_TEST_001',1,NULL,'anonymous_ec350abea3b7efb5769eab5ce9c6d7bf','PROMO','show_promo','{\"tags\": [\"#美食推荐\", \"#打卡探店\"], \"type\": \"promo\", \"video\": {\"url\": \"https://www.w3schools.com/html/mov_bbb.mp4\", \"title\": \"测试推广视频\", \"duration\": 10, \"thumbnail\": \"https://via.placeholder.com/320x180?text=Promo+Video\"}, \"action\": \"show_promo\", \"reward\": null, \"message\": \"推广素材加载成功\", \"merchant\": {\"logo\": \"\", \"name\": \"系统管理员\", \"description\": \"\"}, \"platforms\": [\"douyin\", \"kuaishou\"], \"copywriting\": \"这家店太好吃了，强烈推荐给大家！环境好服务棒，下次还来\"}',44,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36',1,'','2026-02-18 08:48:36','2026-02-18 08:48:37'),(15,9,'PROMO_TEST_001',1,NULL,'anonymous_ec350abea3b7efb5769eab5ce9c6d7bf','PROMO','show_promo','{\"tags\": [\"#美食推荐\", \"#打卡探店\"], \"type\": \"promo\", \"video\": {\"url\": \"https://www.w3schools.com/html/mov_bbb.mp4\", \"title\": \"测试推广视频\", \"duration\": 10, \"thumbnail\": \"https://via.placeholder.com/320x180?text=Promo+Video\"}, \"action\": \"show_promo\", \"reward\": null, \"message\": \"推广素材加载成功\", \"merchant\": {\"logo\": \"\", \"name\": \"系统管理员\", \"description\": \"\"}, \"platforms\": [\"douyin\", \"kuaishou\"], \"copywriting\": \"这家店太好吃了，强烈推荐给大家！环境好服务棒，下次还来\"}',58,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36',1,'','2026-02-18 08:50:43','2026-02-18 08:50:43'),(16,9,'PROMO_TEST_001',1,NULL,'anonymous_ec350abea3b7efb5769eab5ce9c6d7bf','PROMO','show_promo','{\"tags\": [\"#美食推荐\", \"#打卡探店\"], \"type\": \"promo\", \"video\": {\"url\": \"https://www.w3schools.com/html/mov_bbb.mp4\", \"title\": \"测试推广视频\", \"duration\": 10, \"thumbnail\": \"https://via.placeholder.com/320x180?text=Promo+Video\"}, \"action\": \"show_promo\", \"reward\": null, \"message\": \"推广素材加载成功\", \"merchant\": {\"logo\": \"\", \"name\": \"系统管理员\", \"description\": \"\"}, \"platforms\": [\"douyin\", \"kuaishou\"], \"copywriting\": \"这家店太好吃了，强烈推荐给大家！环境好服务棒，下次还来\"}',44,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36',1,'','2026-02-18 16:15:40','2026-02-18 16:15:40'),(17,9,'PROMO_TEST_001',1,NULL,'anonymous_ec350abea3b7efb5769eab5ce9c6d7bf','PROMO','show_promo','{\"tags\": [\"#美食推荐\", \"#打卡探店\"], \"type\": \"promo\", \"video\": {\"url\": \"https://www.w3schools.com/html/mov_bbb.mp4\", \"title\": \"测试推广视频\", \"duration\": 10, \"thumbnail\": \"https://via.placeholder.com/320x180?text=Promo+Video\"}, \"action\": \"show_promo\", \"reward\": null, \"message\": \"推广素材加载成功\", \"merchant\": {\"logo\": \"\", \"name\": \"系统管理员\", \"description\": \"\"}, \"platforms\": [\"douyin\", \"kuaishou\"], \"copywriting\": \"这家店太好吃了，强烈推荐给大家！环境好服务棒，下次还来\"}',54,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36',1,'','2026-02-18 20:11:32','2026-02-18 20:11:32'),(18,9,'PROMO_TEST_001',1,NULL,'anonymous_ec350abea3b7efb5769eab5ce9c6d7bf','PROMO','show_promo','{\"tags\": [\"#美食推荐\", \"#打卡探店\"], \"type\": \"promo\", \"video\": {\"url\": \"https://www.w3schools.com/html/mov_bbb.mp4\", \"title\": \"测试推广视频\", \"duration\": 10, \"thumbnail\": \"https://via.placeholder.com/320x180?text=Promo+Video\"}, \"action\": \"show_promo\", \"reward\": null, \"message\": \"推广素材加载成功\", \"merchant\": {\"logo\": \"\", \"name\": \"系统管理员\", \"description\": \"\"}, \"platforms\": [\"douyin\", \"kuaishou\"], \"copywriting\": \"这家店太好吃了，强烈推荐给大家！环境好服务棒，下次还来\"}',47,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36',1,'','2026-02-18 20:12:09','2026-02-18 20:12:10'),(19,9,'PROMO_TEST_001',1,NULL,'anonymous_ec350abea3b7efb5769eab5ce9c6d7bf','PROMO','show_promo','{\"tags\": [\"#美食推荐\", \"#打卡探店\"], \"type\": \"promo\", \"video\": {\"url\": \"https://www.w3schools.com/html/mov_bbb.mp4\", \"title\": \"测试推广视频\", \"duration\": 10, \"thumbnail\": \"https://via.placeholder.com/320x180?text=Promo+Video\"}, \"action\": \"show_promo\", \"reward\": null, \"message\": \"推广素材加载成功\", \"merchant\": {\"logo\": \"\", \"name\": \"系统管理员\", \"description\": \"\"}, \"platforms\": [\"douyin\", \"kuaishou\"], \"copywriting\": \"这家店太好吃了，强烈推荐给大家！环境好服务棒，下次还来\"}',51,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36',1,'','2026-02-18 20:24:49','2026-02-18 20:24:50');
/*!40000 ALTER TABLE `xmt_device_triggers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_dining_sessions`
--

DROP TABLE IF EXISTS `xmt_dining_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_dining_sessions` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '会话ID',
  `merchant_id` int(11) unsigned NOT NULL COMMENT '所属商家ID',
  `table_id` int(11) unsigned NOT NULL COMMENT '桌台ID',
  `device_id` int(11) unsigned DEFAULT NULL COMMENT 'NFC设备ID',
  `session_code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '会话编码',
  `status` enum('ACTIVE','COMPLETED','CANCELLED') COLLATE utf8mb4_unicode_ci DEFAULT 'ACTIVE' COMMENT '会话状态',
  `guest_count` tinyint(3) DEFAULT '1' COMMENT '用餐人数',
  `start_time` datetime NOT NULL COMMENT '开始时间',
  `end_time` datetime DEFAULT NULL COMMENT '结束时间',
  `duration` int(11) DEFAULT NULL COMMENT '用餐时长(分钟)',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_session_code` (`session_code`),
  KEY `idx_merchant_table` (`merchant_id`,`table_id`),
  KEY `idx_table_status` (`table_id`,`status`),
  KEY `idx_status` (`status`),
  KEY `idx_start_time` (`start_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用餐会话表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_dining_sessions`
--

LOCK TABLES `xmt_dining_sessions` WRITE;
/*!40000 ALTER TABLE `xmt_dining_sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `xmt_dining_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_email_failures`
--

DROP TABLE IF EXISTS `xmt_email_failures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_email_failures` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `to` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '收件人邮箱',
  `subject` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '邮件主题',
  `error_message` text COLLATE utf8mb4_unicode_ci COMMENT '错误信息',
  `attempts` int(11) DEFAULT '0' COMMENT '重试次数',
  `failed_time` datetime DEFAULT NULL COMMENT '最终失败时间',
  `email_data` text COLLATE utf8mb4_unicode_ci COMMENT '邮件数据（JSON）',
  `create_time` datetime DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `to` (`to`),
  KEY `failed_time` (`failed_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_email_failures`
--

LOCK TABLES `xmt_email_failures` WRITE;
/*!40000 ALTER TABLE `xmt_email_failures` DISABLE KEYS */;
/*!40000 ALTER TABLE `xmt_email_failures` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_email_logs`
--

DROP TABLE IF EXISTS `xmt_email_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_email_logs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `from` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '发件人邮箱',
  `to` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '收件人邮箱（多个用逗号分隔）',
  `cc` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '抄送邮箱',
  `bcc` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '密送邮箱',
  `subject` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '邮件主题',
  `body` text COLLATE utf8mb4_unicode_ci COMMENT '邮件正文（HTML）',
  `alt_body` text COLLATE utf8mb4_unicode_ci COMMENT '邮件正文（纯文本）',
  `is_html` tinyint(1) DEFAULT '1' COMMENT '是否为HTML邮件',
  `success` tinyint(1) DEFAULT '0' COMMENT '是否发送成功',
  `error_message` text COLLATE utf8mb4_unicode_ci COMMENT '错误信息',
  `has_attachment` tinyint(1) DEFAULT '0' COMMENT '是否有附件',
  `attachment_count` int(11) DEFAULT '0' COMMENT '附件数量',
  `attachments` text COLLATE utf8mb4_unicode_ci COMMENT '附件信息（JSON）',
  `template` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '使用的模板',
  `send_time` datetime DEFAULT NULL COMMENT '发送时间',
  `duration` int(11) DEFAULT '0' COMMENT '发送耗时（毫秒）',
  `create_time` datetime DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `to` (`to`),
  KEY `success` (`success`),
  KEY `send_time` (`send_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_email_logs`
--

LOCK TABLES `xmt_email_logs` WRITE;
/*!40000 ALTER TABLE `xmt_email_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `xmt_email_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_group_buy_redirects`
--

DROP TABLE IF EXISTS `xmt_group_buy_redirects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_group_buy_redirects` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `device_id` int(11) unsigned NOT NULL COMMENT '设备ID',
  `merchant_id` int(11) unsigned NOT NULL COMMENT '商家ID',
  `user_id` int(11) unsigned DEFAULT NULL COMMENT '用户ID',
  `platform` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '平台类型 MEITUAN/DOUYIN/ELEME/CUSTOM',
  `deal_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '团购ID',
  `redirect_url` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '完整跳转链接',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'IP地址',
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '用户代理',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `device_id` (`device_id`),
  KEY `merchant_id` (`merchant_id`),
  KEY `user_id` (`user_id`),
  KEY `platform` (`platform`),
  KEY `create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='团购跳转记录表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_group_buy_redirects`
--

LOCK TABLES `xmt_group_buy_redirects` WRITE;
/*!40000 ALTER TABLE `xmt_group_buy_redirects` DISABLE KEYS */;
/*!40000 ALTER TABLE `xmt_group_buy_redirects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_material_categories`
--

DROP TABLE IF EXISTS `xmt_material_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_material_categories` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '分类ID',
  `parent_id` int(11) DEFAULT '0' COMMENT '父分类ID',
  `name` varchar(100) NOT NULL COMMENT '分类名称',
  `type` enum('VIDEO','AUDIO','TRANSITION','TEXT_TEMPLATE','IMAGE','MUSIC') NOT NULL COMMENT '素材类型',
  `description` text COMMENT '分类描述',
  `sort` int(11) DEFAULT '0' COMMENT '排序',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态 0禁用 1启用',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_parent_id` (`parent_id`),
  KEY `idx_type` (`type`),
  KEY `idx_status` (`status`),
  KEY `idx_sort` (`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='素材分类表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_material_categories`
--

LOCK TABLES `xmt_material_categories` WRITE;
/*!40000 ALTER TABLE `xmt_material_categories` DISABLE KEYS */;
/*!40000 ALTER TABLE `xmt_material_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_material_performance`
--

DROP TABLE IF EXISTS `xmt_material_performance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_material_performance` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '统计ID',
  `template_id` int(11) unsigned NOT NULL COMMENT '模板ID',
  `date` date NOT NULL COMMENT '统计日期',
  `usage_count` int(11) DEFAULT '0' COMMENT '使用次数',
  `success_count` int(11) DEFAULT '0' COMMENT '成功次数',
  `avg_rating` decimal(3,2) DEFAULT '0.00' COMMENT '平均评分',
  `view_count` int(11) DEFAULT '0' COMMENT '浏览量',
  `share_count` int(11) DEFAULT '0' COMMENT '分享量',
  `conversion_rate` decimal(5,2) DEFAULT '0.00' COMMENT '转化率',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_template_date` (`template_id`,`date`),
  KEY `idx_template` (`template_id`),
  KEY `idx_date` (`date`),
  KEY `idx_usage_count` (`usage_count`),
  KEY `idx_avg_rating` (`avg_rating`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='素材效果统计表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_material_performance`
--

LOCK TABLES `xmt_material_performance` WRITE;
/*!40000 ALTER TABLE `xmt_material_performance` DISABLE KEYS */;
/*!40000 ALTER TABLE `xmt_material_performance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_material_ratings`
--

DROP TABLE IF EXISTS `xmt_material_ratings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_material_ratings` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '评分ID',
  `user_id` int(11) unsigned NOT NULL COMMENT '用户ID',
  `template_id` int(11) unsigned NOT NULL COMMENT '模板ID',
  `content_task_id` int(11) unsigned DEFAULT NULL COMMENT '内容任务ID',
  `rating` tinyint(1) NOT NULL COMMENT '评分 1-5',
  `feedback` text COLLATE utf8mb4_unicode_ci COMMENT '反馈内容',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_template` (`user_id`,`template_id`),
  KEY `idx_template` (`template_id`),
  KEY `idx_content_task` (`content_task_id`),
  KEY `idx_rating` (`rating`),
  KEY `idx_create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='素材评分表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_material_ratings`
--

LOCK TABLES `xmt_material_ratings` WRITE;
/*!40000 ALTER TABLE `xmt_material_ratings` DISABLE KEYS */;
/*!40000 ALTER TABLE `xmt_material_ratings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_material_usage_logs`
--

DROP TABLE IF EXISTS `xmt_material_usage_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_material_usage_logs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `user_id` int(11) unsigned NOT NULL COMMENT '用户ID',
  `merchant_id` int(11) unsigned NOT NULL COMMENT '商家ID',
  `template_id` int(11) unsigned NOT NULL COMMENT '模板ID',
  `content_task_id` int(11) unsigned DEFAULT NULL COMMENT '内容任务ID',
  `usage_context` json DEFAULT NULL COMMENT '使用上下文',
  `result` enum('SUCCESS','FAILED') COLLATE utf8mb4_unicode_ci DEFAULT 'SUCCESS' COMMENT '使用结果',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_merchant` (`user_id`,`merchant_id`),
  KEY `idx_template` (`template_id`),
  KEY `idx_content_task` (`content_task_id`),
  KEY `idx_result` (`result`),
  KEY `idx_create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='素材使用记录表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_material_usage_logs`
--

LOCK TABLES `xmt_material_usage_logs` WRITE;
/*!40000 ALTER TABLE `xmt_material_usage_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `xmt_material_usage_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_materials`
--

DROP TABLE IF EXISTS `xmt_materials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_materials` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '素材ID',
  `type` enum('VIDEO','AUDIO','TRANSITION','TEXT_TEMPLATE','IMAGE','MUSIC') NOT NULL COMMENT '素材类型',
  `name` varchar(200) NOT NULL COMMENT '素材名称',
  `category_id` int(11) DEFAULT NULL COMMENT '分类ID',
  `file_url` varchar(500) NOT NULL COMMENT '文件URL',
  `thumbnail_url` varchar(500) DEFAULT NULL COMMENT '缩略图URL',
  `file_size` bigint(20) DEFAULT NULL COMMENT '文件大小(字节)',
  `duration` int(11) DEFAULT NULL COMMENT '时长(秒)',
  `metadata` json DEFAULT NULL COMMENT '元数据',
  `tags` json DEFAULT NULL COMMENT '标签',
  `usage_count` int(11) DEFAULT '0' COMMENT '使用次数',
  `weight` int(11) DEFAULT '100' COMMENT '推荐权重',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态 0禁用 1启用 2审核中',
  `audit_status` tinyint(1) DEFAULT '0' COMMENT '审核状态 0待审核 1通过 2拒绝',
  `audit_message` text COMMENT '审核信息',
  `creator_id` int(11) DEFAULT NULL COMMENT '创建者ID',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  `moderation_status` varchar(20) DEFAULT 'PENDING' COMMENT '审核状态: PENDING/APPROVED/REJECTED',
  `moderation_score` int(11) DEFAULT '100' COMMENT '审核评分',
  `moderation_time` datetime DEFAULT NULL COMMENT '审核时间',
  PRIMARY KEY (`id`),
  KEY `idx_type` (`type`),
  KEY `idx_category_id` (`category_id`),
  KEY `idx_status` (`status`),
  KEY `idx_audit_status` (`audit_status`),
  KEY `idx_usage_count` (`usage_count`),
  KEY `idx_weight` (`weight`),
  KEY `idx_create_time` (`create_time`),
  KEY `idx_moderation_status` (`moderation_status`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COMMENT='素材表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_materials`
--

LOCK TABLES `xmt_materials` WRITE;
/*!40000 ALTER TABLE `xmt_materials` DISABLE KEYS */;
INSERT INTO `xmt_materials` VALUES (1,'VIDEO','测试推广视频',NULL,'https://www.w3schools.com/html/mov_bbb.mp4','https://via.placeholder.com/320x180?text=Promo+Video',1024000,10,NULL,NULL,0,100,1,1,NULL,1,'2026-02-18 08:35:46','2026-02-18 08:35:46','PENDING',100,NULL);
/*!40000 ALTER TABLE `xmt_materials` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_merchant_blacklist`
--

DROP TABLE IF EXISTS `xmt_merchant_blacklist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_merchant_blacklist` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `merchant_id` int(11) unsigned NOT NULL COMMENT '商家ID',
  `reason` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '加入黑名单原因',
  `violation_count` int(11) unsigned DEFAULT '0' COMMENT '违规次数',
  `severity_level` enum('HIGH','MEDIUM','LOW') COLLATE utf8mb4_unicode_ci DEFAULT 'MEDIUM' COMMENT '严重程度',
  `restrictions` json DEFAULT NULL COMMENT '限制措施',
  `status` enum('ACTIVE','LIFTED','EXPIRED') COLLATE utf8mb4_unicode_ci DEFAULT 'ACTIVE' COMMENT '状态',
  `start_time` datetime NOT NULL COMMENT '开始时间',
  `expire_time` datetime DEFAULT NULL COMMENT '到期时间(NULL为永久)',
  `operator_id` int(11) unsigned NOT NULL COMMENT '操作人ID',
  `lift_time` datetime DEFAULT NULL COMMENT '解除时间',
  `lift_reason` text COLLATE utf8mb4_unicode_ci COMMENT '解除原因',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_merchant` (`merchant_id`),
  KEY `idx_status` (`status`),
  KEY `idx_start_time` (`start_time`),
  KEY `idx_expire_time` (`expire_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商家黑名单表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_merchant_blacklist`
--

LOCK TABLES `xmt_merchant_blacklist` WRITE;
/*!40000 ALTER TABLE `xmt_merchant_blacklist` DISABLE KEYS */;
/*!40000 ALTER TABLE `xmt_merchant_blacklist` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_merchant_notifications`
--

DROP TABLE IF EXISTS `xmt_merchant_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_merchant_notifications` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '通知ID',
  `merchant_id` int(11) unsigned NOT NULL COMMENT '商家ID',
  `type` enum('VIOLATION','APPEAL_RESULT','WARNING','MATERIAL_DISABLED','INFO','SYSTEM') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '通知类型',
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '通知标题',
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '通知内容',
  `content_html` text COLLATE utf8mb4_unicode_ci COMMENT '通知内容(HTML)',
  `related_id` int(11) unsigned DEFAULT NULL COMMENT '关联ID',
  `related_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '关联类型(violation/appeal/material)',
  `related_data` json DEFAULT NULL COMMENT '关联数据',
  `channels` json NOT NULL COMMENT '通知渠道["system","email","sms","wechat"]',
  `priority` enum('HIGH','NORMAL','LOW') COLLATE utf8mb4_unicode_ci DEFAULT 'NORMAL' COMMENT '优先级',
  `status` enum('PENDING','SENDING','SENT','FAILED','READ') COLLATE utf8mb4_unicode_ci DEFAULT 'PENDING' COMMENT '状态',
  `send_result` json DEFAULT NULL COMMENT '发送结果详情',
  `send_time` datetime DEFAULT NULL COMMENT '发送时间',
  `read_time` datetime DEFAULT NULL COMMENT '阅读时间',
  `expire_time` datetime DEFAULT NULL COMMENT '过期时间',
  `retry_count` tinyint(2) DEFAULT '0' COMMENT '重试次数',
  `max_retry` tinyint(2) DEFAULT '3' COMMENT '最大重试次数',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_merchant` (`merchant_id`),
  KEY `idx_type` (`type`),
  KEY `idx_status` (`status`),
  KEY `idx_priority` (`priority`),
  KEY `idx_create_time` (`create_time`),
  KEY `idx_related` (`related_type`,`related_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商家通知表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_merchant_notifications`
--

LOCK TABLES `xmt_merchant_notifications` WRITE;
/*!40000 ALTER TABLE `xmt_merchant_notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `xmt_merchant_notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_merchants`
--

DROP TABLE IF EXISTS `xmt_merchants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_merchants` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '商家ID',
  `user_id` int(11) unsigned NOT NULL COMMENT '关联用户ID',
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '商家名称',
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '商家类别',
  `address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '地址',
  `longitude` decimal(10,7) DEFAULT NULL COMMENT '经度',
  `latitude` decimal(10,7) DEFAULT NULL COMMENT '纬度',
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '联系电话',
  `wechat_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '微信号',
  `weibo_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '微博号',
  `douyin_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '抖音号',
  `description` text COLLATE utf8mb4_unicode_ci COMMENT '商家描述',
  `logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '商家logo',
  `business_hours` json DEFAULT NULL COMMENT '营业时间',
  `contact_config` json DEFAULT NULL COMMENT '联系方式配置JSON',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态 0禁用 1正常 2审核中',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  `reject_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '拒绝原因',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `category` (`category`),
  KEY `status` (`status`),
  KEY `idx_location` (`longitude`,`latitude`),
  KEY `idx_wechat_id` (`wechat_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1000 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商家表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_merchants`
--

LOCK TABLES `xmt_merchants` WRITE;
/*!40000 ALTER TABLE `xmt_merchants` DISABLE KEYS */;
INSERT INTO `xmt_merchants` VALUES (1,0,'系统管理员','system','System Address',0.0000000,0.0000000,'13800000000',NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-02-08 16:03:08','2026-02-08 16:03:08',NULL),(3,3,'星巴克万达店','餐饮','北京市朝阳区万达广场1楼',116.4736690,39.9086460,'010-88886666',NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2025-12-01 08:00:00','2026-02-16 09:00:00',NULL),(4,4,'小李川菜馆','餐饮','上海市浦东新区世纪大道100号',121.5254290,31.2328700,'021-66668888',NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-01-05 09:00:00','2026-02-15 11:00:00',NULL),(5,5,'丽人美容SPA','美容','广州市天河区天河路228号',113.3312000,23.1375790,'020-33334444',NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2025-11-20 10:30:00','2026-02-16 08:00:00',NULL),(999,99999,'测试商家','餐饮','测试地址',NULL,NULL,NULL,NULL,NULL,NULL,'用于API测试的商家',NULL,NULL,NULL,1,'2026-02-12 15:23:57','2026-02-12 15:23:57',NULL);
/*!40000 ALTER TABLE `xmt_merchants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_migration_log`
--

DROP TABLE IF EXISTS `xmt_migration_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_migration_log` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `migration_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '迁移文件名',
  `batch` int(11) NOT NULL COMMENT '批次号',
  `executed_at` datetime NOT NULL COMMENT '执行时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `migration_name` (`migration_name`),
  KEY `batch` (`batch`),
  KEY `executed_at` (`executed_at`)
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='数据库迁移记录表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_migration_log`
--

LOCK TABLES `xmt_migration_log` WRITE;
/*!40000 ALTER TABLE `xmt_migration_log` DISABLE KEYS */;
INSERT INTO `xmt_migration_log` VALUES (1,'20250929000000_create_migration_log_table.sql',1,'2026-02-07 11:15:49'),(2,'20250929215341_create_users_table.sql',2,'2026-02-07 11:15:49'),(3,'20250929220835_create_merchants_table.sql',3,'2026-02-07 11:15:49'),(4,'20250929221354_create_nfc_devices_table.sql',4,'2026-02-07 11:15:49'),(5,'20250929222838_create_content_tasks_table.sql',5,'2026-02-07 11:15:49'),(6,'20250929223848_create_content_templates_table.sql',6,'2026-02-07 11:15:49'),(7,'20250930000001_create_device_triggers_table.sql',7,'2026-02-07 11:15:49'),(8,'20250930000002_create_coupons_table.sql',8,'2026-02-07 11:15:49'),(9,'20250930000003_create_coupon_users_table.sql',9,'2026-02-07 11:15:49'),(10,'20250930000004_add_group_buy_support.sql',10,'2026-02-07 11:15:49'),(11,'20250930000004_create_publish_tasks_table.sql',11,'2026-02-07 11:15:49'),(12,'20250930000004_create_tables_table.sql',12,'2026-02-07 11:15:49'),(13,'20250930000005_create_dining_sessions_table.sql',13,'2026-02-07 11:15:49'),(14,'20250930000005_create_platform_accounts_table.sql',14,'2026-02-07 11:15:49'),(15,'20250930000006_create_materials_table.sql',15,'2026-02-07 11:15:49'),(16,'20250930000006_create_session_users_table.sql',16,'2026-02-07 11:15:49'),(17,'20250930000007_create_material_categories_table.sql',17,'2026-02-07 11:15:49'),(18,'20250930000007_create_service_calls_table.sql',18,'2026-02-07 11:15:49'),(19,'20250930000008_add_table_id_to_nfc_devices.sql',19,'2026-02-07 11:15:49'),(20,'20250930000009_create_device_alerts_table.sql',20,'2026-02-07 11:15:49'),(21,'20250930000010_add_contact_config_to_merchants.sql',21,'2026-02-07 11:15:49'),(22,'20250930000010_create_statistics_table.sql',22,'2026-02-07 11:15:49'),(23,'20250930000011_create_contact_actions_table.sql',23,'2026-02-07 11:15:49'),(24,'20251001000001_create_content_audits_table.sql',24,'2026-02-07 11:15:49'),(25,'20251001000001_create_content_materials_tables.sql',25,'2026-02-07 11:15:49'),(26,'20251001000001_rollback_content_materials_tables.sql',26,'2026-02-07 11:15:49'),(27,'20251001000002_create_recommendation_tables.sql',27,'2026-02-07 11:15:49'),(28,'20251001000002_create_sensitive_words_table.sql',28,'2026-02-07 11:15:49'),(29,'20251001000002_create_violation_tables.sql',29,'2026-02-07 11:15:49'),(30,'20251001000003_create_anomaly_alerts_table.sql',30,'2026-02-07 11:15:49'),(31,'20251004000001_create_content_feedbacks_table.sql',31,'2026-02-07 11:15:49'),(32,'20250111000002_create_email_tables.sql',32,'2026-02-07 11:21:13'),(33,'20250111000003_create_sms_tables.sql',33,'2026-02-07 11:21:13'),(34,'20250111_create_wechat_template_tables.sql',34,'2026-02-07 11:21:48'),(35,'20260111_create_content_moderation_tables.sql',35,'2026-02-07 12:49:02'),(36,'20260125000001_add_video_library_fields.sql',36,'2026-02-07 12:49:02'),(37,'20250929210000_create_admin_table.sql',37,'2026-02-07 15:54:29');
/*!40000 ALTER TABLE `xmt_migration_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_migrations`
--

DROP TABLE IF EXISTS `xmt_migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_migrations` (
  `version` bigint(20) NOT NULL,
  `migration_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_time` timestamp NULL DEFAULT NULL,
  `end_time` timestamp NULL DEFAULT NULL,
  `breakpoint` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_migrations`
--

LOCK TABLES `xmt_migrations` WRITE;
/*!40000 ALTER TABLE `xmt_migrations` DISABLE KEYS */;
INSERT INTO `xmt_migrations` VALUES (20241230000001,'CreateDeviceAlertsTable','2026-02-07 03:13:30','2026-02-07 03:13:30',0),(20250111000001,'CreateEmailTables','2026-02-07 03:13:30','2026-02-07 03:13:30',0);
/*!40000 ALTER TABLE `xmt_migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_nfc_devices`
--

DROP TABLE IF EXISTS `xmt_nfc_devices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_nfc_devices` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '设备ID',
  `merchant_id` int(11) unsigned NOT NULL COMMENT '所属商家ID',
  `table_id` int(11) unsigned DEFAULT NULL COMMENT '绑定的桌台ID',
  `device_code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '设备编码',
  `device_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '设备名称',
  `location` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '设备位置',
  `type` enum('TABLE','WALL','COUNTER','ENTRANCE') COLLATE utf8mb4_unicode_ci DEFAULT 'TABLE' COMMENT '设备类型',
  `trigger_mode` enum('VIDEO','COUPON','WIFI','CONTACT','MENU','GROUP_BUY','PROMO') COLLATE utf8mb4_unicode_ci DEFAULT 'VIDEO',
  `template_id` int(11) DEFAULT NULL COMMENT '内容模板ID',
  `redirect_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '跳转链接',
  `group_buy_config` json DEFAULT NULL COMMENT '团购配置',
  `wifi_ssid` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'WiFi名称',
  `wifi_password` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'WiFi密码',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态 0离线 1在线 2维护',
  `battery_level` tinyint(3) DEFAULT NULL COMMENT '电池电量',
  `last_heartbeat` datetime DEFAULT NULL COMMENT '最后心跳时间',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  `promo_video_id` int(10) unsigned DEFAULT NULL COMMENT '推广视频ID',
  `promo_copywriting` text COLLATE utf8mb4_unicode_ci COMMENT '推广文案',
  `promo_tags` json DEFAULT NULL COMMENT '推广话题标签',
  `promo_reward_coupon_id` int(10) unsigned DEFAULT NULL COMMENT '推广奖励优惠券ID',
  PRIMARY KEY (`id`),
  UNIQUE KEY `device_code` (`device_code`),
  KEY `merchant_id` (`merchant_id`),
  KEY `status` (`status`),
  KEY `type` (`type`),
  KEY `trigger_mode` (`trigger_mode`),
  KEY `idx_heartbeat` (`last_heartbeat`),
  KEY `idx_table_id` (`table_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='NFC设备表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_nfc_devices`
--

LOCK TABLES `xmt_nfc_devices` WRITE;
/*!40000 ALTER TABLE `xmt_nfc_devices` DISABLE KEYS */;
INSERT INTO `xmt_nfc_devices` VALUES (1,1,NULL,'NFC001','Test Device 1',NULL,'TABLE','VIDEO',NULL,NULL,NULL,NULL,NULL,1,NULL,NULL,'2026-02-09 14:42:09','2026-02-09 14:42:09',NULL,NULL,NULL,NULL),(2,1,NULL,'NFC_TABLE_002','2号桌NFC','大厅2号桌','TABLE','VIDEO',NULL,NULL,NULL,NULL,NULL,1,85,'2026-02-16 07:50:00','2026-01-15 10:00:00','2026-02-16 07:50:00',NULL,NULL,NULL,NULL),(3,1,NULL,'NFC_COUNTER_001','收银台NFC','收银台','COUNTER','COUPON',NULL,NULL,NULL,NULL,NULL,1,92,'2026-02-16 07:55:00','2026-01-20 14:00:00','2026-02-16 07:55:00',NULL,NULL,NULL,NULL),(4,3,NULL,'NFC_TABLE_101','星巴克1号桌','靠窗位置','TABLE','VIDEO',NULL,NULL,NULL,NULL,NULL,1,78,'2026-02-16 07:45:00','2025-12-15 09:00:00','2026-02-16 07:45:00',NULL,NULL,NULL,NULL),(5,3,NULL,'NFC_WALL_101','星巴克入口展示','店铺入口','WALL','MENU',NULL,NULL,NULL,NULL,NULL,1,95,'2026-02-16 08:00:00','2025-12-15 09:30:00','2026-02-16 08:00:00',NULL,NULL,NULL,NULL),(6,4,NULL,'NFC_TABLE_201','川菜馆1号桌','包间A','TABLE','VIDEO',NULL,NULL,NULL,NULL,NULL,1,60,'2026-02-15 22:00:00','2026-01-10 08:00:00','2026-02-15 22:00:00',NULL,NULL,NULL,NULL),(7,4,NULL,'NFC_ENTRANCE_201','川菜馆入口','大门口','ENTRANCE','GROUP_BUY',NULL,NULL,NULL,NULL,NULL,1,88,'2026-02-16 07:30:00','2026-01-10 08:30:00','2026-02-16 07:30:00',NULL,NULL,NULL,NULL),(8,5,NULL,'NFC_COUNTER_301','美容院前台','前台接待','COUNTER','COUPON',NULL,NULL,NULL,NULL,NULL,1,99,'2026-02-16 08:05:00','2025-12-01 10:00:00','2026-02-16 08:05:00',NULL,NULL,NULL,NULL),(9,1,NULL,'PROMO_TEST_001','推广测试设备','一楼大厅入口','TABLE','PROMO',0,'',NULL,'','',1,NULL,'2026-02-18 20:24:49','2026-02-18 08:21:30','2026-02-18 20:24:50',1,'这家店太好吃了，强烈推荐给大家！环境好服务棒，下次还来','[\"#美食推荐\", \"#打卡探店\"]',0);
/*!40000 ALTER TABLE `xmt_nfc_devices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_operation_logs`
--

DROP TABLE IF EXISTS `xmt_operation_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_operation_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '日志ID',
  `user_id` int(11) DEFAULT NULL COMMENT '用户ID',
  `username` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '用户名',
  `role` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '用户角色',
  `module` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '模块名称',
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '操作类型',
  `description` text COLLATE utf8mb4_unicode_ci COMMENT '操作描述',
  `request_method` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '请求方法',
  `request_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '请求URL',
  `request_params` json DEFAULT NULL COMMENT '请求参数',
  `response_code` int(11) DEFAULT NULL COMMENT '响应状态码',
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'IP地址',
  `user_agent` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '用户代理',
  `execution_time` int(11) DEFAULT NULL COMMENT '执行时间(毫秒)',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_module` (`module`),
  KEY `idx_action` (`action`),
  KEY `idx_create_time` (`create_time`),
  KEY `idx_ip` (`ip`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='操作日志表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_operation_logs`
--

LOCK TABLES `xmt_operation_logs` WRITE;
/*!40000 ALTER TABLE `xmt_operation_logs` DISABLE KEYS */;
INSERT INTO `xmt_operation_logs` VALUES (1,0,'admin','admin','auth','login','管理员登录系统','POST','/api/auth/login','{\"username\": \"admin\"}',200,'127.0.0.1','Chrome/121',NULL,'2026-02-16 08:00:00'),(2,0,'admin','admin','template','create','创建春节促销视频模板','POST','/api/template/create','{\"name\": \"春节促销视频模板\"}',200,'127.0.0.1','Chrome/121',NULL,'2026-02-13 10:00:00'),(3,0,'admin','admin','content','generate','生成内容任务','POST','/api/content/generate','{\"template_id\": 1}',200,'127.0.0.1','Chrome/121',NULL,'2026-02-14 11:00:00'),(4,0,'admin','admin','statistics','dashboard','查看仪表盘','GET','/api/statistics/dashboard','{}',200,'127.0.0.1','Chrome/121',NULL,'2026-02-16 08:05:00'),(5,3,'user_3','merchant','content','generate','星巴克生成文案','POST','/api/content/generate','{\"template_id\": 4}',200,'192.168.2.50','WeChat',NULL,'2026-02-13 14:00:00'),(6,0,'admin','admin','device','list','查看设备列表','GET','/api/merchant/device/list','{}',200,'127.0.0.1','Chrome/121',NULL,'2026-02-16 08:10:00'),(7,0,'admin','admin','alert','resolve','解决告警','POST','/api/alert/1/resolve','{}',200,'127.0.0.1','Chrome/121',NULL,'2026-02-14 10:00:00'),(8,4,'user_4','merchant','publish','create','川菜馆发布抖音','POST','/api/publish/create','{\"content_task_id\": 4}',200,'192.168.3.10','WeChat',NULL,'2026-02-14 11:10:00');
/*!40000 ALTER TABLE `xmt_operation_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_platform_accounts`
--

DROP TABLE IF EXISTS `xmt_platform_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_platform_accounts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '账号ID',
  `user_id` int(10) unsigned NOT NULL COMMENT '用户ID',
  `platform` enum('DOUYIN','XIAOHONGSHU','WECHAT','WEIBO') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '平台类型',
  `platform_uid` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '平台用户ID',
  `platform_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '平台昵称',
  `access_token` text COLLATE utf8mb4_unicode_ci COMMENT '访问令牌',
  `refresh_token` text COLLATE utf8mb4_unicode_ci COMMENT '刷新令牌',
  `expires_time` datetime DEFAULT NULL COMMENT '令牌过期时间',
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '头像',
  `follower_count` int(11) DEFAULT '0' COMMENT '粉丝数',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态 0失效 1正常',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_platform` (`user_id`,`platform`,`platform_uid`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_platform` (`platform`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='平台账号表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_platform_accounts`
--

LOCK TABLES `xmt_platform_accounts` WRITE;
/*!40000 ALTER TABLE `xmt_platform_accounts` DISABLE KEYS */;
/*!40000 ALTER TABLE `xmt_platform_accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_promo_campaign_devices`
--

DROP TABLE IF EXISTS `xmt_promo_campaign_devices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_promo_campaign_devices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `campaign_id` int(10) unsigned NOT NULL COMMENT '活动ID',
  `device_id` int(10) unsigned NOT NULL COMMENT '设备ID',
  `create_time` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_device` (`device_id`),
  KEY `idx_campaign` (`campaign_id`),
  KEY `idx_device` (`device_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='活动设备关联表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_promo_campaign_devices`
--

LOCK TABLES `xmt_promo_campaign_devices` WRITE;
/*!40000 ALTER TABLE `xmt_promo_campaign_devices` DISABLE KEYS */;
/*!40000 ALTER TABLE `xmt_promo_campaign_devices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_promo_campaigns`
--

DROP TABLE IF EXISTS `xmt_promo_campaigns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_promo_campaigns` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `merchant_id` int(10) unsigned NOT NULL COMMENT '商家ID',
  `name` varchar(100) NOT NULL COMMENT '活动名称',
  `description` text COMMENT '活动描述',
  `variant_ids` json DEFAULT NULL COMMENT '关联的变体ID列表',
  `copywriting` text COMMENT '推广文案',
  `tags` json DEFAULT NULL COMMENT '话题标签',
  `reward_coupon_id` int(10) unsigned DEFAULT NULL COMMENT '奖励优惠券ID',
  `platforms` json DEFAULT NULL COMMENT '目标平台 ["douyin","kuaishou"]',
  `status` tinyint(4) DEFAULT '1' COMMENT '状态 1启用 0禁用',
  `start_time` datetime DEFAULT NULL COMMENT '开始时间',
  `end_time` datetime DEFAULT NULL COMMENT '结束时间',
  `create_time` datetime DEFAULT CURRENT_TIMESTAMP,
  `update_time` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_merchant` (`merchant_id`),
  KEY `idx_status` (`status`),
  KEY `idx_time` (`start_time`,`end_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='推广活动表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_promo_campaigns`
--

LOCK TABLES `xmt_promo_campaigns` WRITE;
/*!40000 ALTER TABLE `xmt_promo_campaigns` DISABLE KEYS */;
/*!40000 ALTER TABLE `xmt_promo_campaigns` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_promo_distributions`
--

DROP TABLE IF EXISTS `xmt_promo_distributions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_promo_distributions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `campaign_id` int(10) unsigned NOT NULL COMMENT '活动ID',
  `device_id` int(10) unsigned NOT NULL COMMENT '设备ID',
  `variant_id` int(10) unsigned NOT NULL COMMENT '变体ID',
  `user_openid` varchar(64) DEFAULT NULL COMMENT '用户OpenID',
  `platform` varchar(20) DEFAULT NULL COMMENT '发布平台',
  `status` enum('pending','downloaded','published','rewarded') DEFAULT 'pending',
  `reward_coupon_user_id` int(10) unsigned DEFAULT NULL COMMENT '发放的优惠券记录ID',
  `client_ip` varchar(45) DEFAULT NULL,
  `create_time` datetime DEFAULT CURRENT_TIMESTAMP,
  `update_time` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_campaign` (`campaign_id`),
  KEY `idx_device` (`device_id`),
  KEY `idx_user` (`user_openid`),
  KEY `idx_status` (`status`),
  KEY `idx_create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='推广分发记录表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_promo_distributions`
--

LOCK TABLES `xmt_promo_distributions` WRITE;
/*!40000 ALTER TABLE `xmt_promo_distributions` DISABLE KEYS */;
/*!40000 ALTER TABLE `xmt_promo_distributions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_promo_materials`
--

DROP TABLE IF EXISTS `xmt_promo_materials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_promo_materials` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `merchant_id` int(10) unsigned NOT NULL COMMENT '商家ID',
  `type` enum('image','video','music') NOT NULL COMMENT '素材类型',
  `name` varchar(200) NOT NULL COMMENT '素材名称',
  `file_url` varchar(500) NOT NULL COMMENT '文件URL',
  `thumbnail_url` varchar(500) DEFAULT NULL COMMENT '缩略图URL',
  `duration` decimal(6,2) DEFAULT NULL COMMENT '时长(秒)',
  `file_size` int(10) unsigned DEFAULT NULL COMMENT '文件大小(字节)',
  `width` int(10) unsigned DEFAULT NULL COMMENT '宽度(像素)',
  `height` int(10) unsigned DEFAULT NULL COMMENT '高度(像素)',
  `sort_order` int(11) DEFAULT '0' COMMENT '排序',
  `status` tinyint(4) DEFAULT '1' COMMENT '状态 1正常 0禁用',
  `create_time` datetime DEFAULT CURRENT_TIMESTAMP,
  `update_time` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_merchant_type` (`merchant_id`,`type`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='推广素材表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_promo_materials`
--

LOCK TABLES `xmt_promo_materials` WRITE;
/*!40000 ALTER TABLE `xmt_promo_materials` DISABLE KEYS */;
/*!40000 ALTER TABLE `xmt_promo_materials` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_promo_publishes`
--

DROP TABLE IF EXISTS `xmt_promo_publishes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_promo_publishes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `trigger_id` int(10) unsigned NOT NULL COMMENT '触发记录ID',
  `device_id` int(10) unsigned NOT NULL COMMENT '设备ID',
  `merchant_id` int(10) unsigned NOT NULL COMMENT '商家ID',
  `user_id` int(10) unsigned DEFAULT NULL COMMENT '用户ID',
  `user_openid` varchar(64) DEFAULT NULL COMMENT '用户OpenID',
  `platform` varchar(20) NOT NULL COMMENT '发布平台 douyin/kuaishou',
  `status` enum('claimed','verified','expired') DEFAULT 'claimed' COMMENT '状态',
  `coupon_user_id` int(10) unsigned DEFAULT NULL COMMENT '发放的优惠券记录ID',
  `client_ip` varchar(45) DEFAULT NULL,
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_trigger_platform` (`trigger_id`,`platform`),
  KEY `idx_device` (`device_id`),
  KEY `idx_merchant` (`merchant_id`),
  KEY `idx_user` (`user_openid`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COMMENT='推广发布记录表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_promo_publishes`
--

LOCK TABLES `xmt_promo_publishes` WRITE;
/*!40000 ALTER TABLE `xmt_promo_publishes` DISABLE KEYS */;
INSERT INTO `xmt_promo_publishes` VALUES (1,15,9,1,NULL,'ip_f528764d624db129b32c21fbca0cb8d6','kuaishou','claimed',NULL,'127.0.0.1','2026-02-18 02:33:00','2026-02-18 02:33:00'),(2,16,9,1,NULL,'ip_f528764d624db129b32c21fbca0cb8d6','douyin','claimed',NULL,'127.0.0.1','2026-02-18 08:19:00','2026-02-18 08:19:00');
/*!40000 ALTER TABLE `xmt_promo_publishes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_promo_templates`
--

DROP TABLE IF EXISTS `xmt_promo_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_promo_templates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `merchant_id` int(10) unsigned NOT NULL COMMENT '商家ID',
  `name` varchar(100) NOT NULL COMMENT '模板名称',
  `description` text COMMENT '模板描述',
  `material_ids` json DEFAULT NULL COMMENT '素材ID列表(有序)',
  `config` json DEFAULT NULL COMMENT '合成配置(时长/转场/音乐等)',
  `status` tinyint(4) DEFAULT '1' COMMENT '状态 1正常 0禁用',
  `create_time` datetime DEFAULT CURRENT_TIMESTAMP,
  `update_time` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_merchant` (`merchant_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='视频模板表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_promo_templates`
--

LOCK TABLES `xmt_promo_templates` WRITE;
/*!40000 ALTER TABLE `xmt_promo_templates` DISABLE KEYS */;
/*!40000 ALTER TABLE `xmt_promo_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_promo_variants`
--

DROP TABLE IF EXISTS `xmt_promo_variants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_promo_variants` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `template_id` int(10) unsigned NOT NULL COMMENT '模板ID',
  `merchant_id` int(10) unsigned NOT NULL COMMENT '商家ID',
  `file_url` varchar(500) NOT NULL COMMENT '视频文件URL',
  `file_size` int(10) unsigned DEFAULT NULL COMMENT '文件大小',
  `duration` decimal(6,2) DEFAULT NULL COMMENT '时长(秒)',
  `md5` varchar(32) DEFAULT NULL COMMENT '文件MD5',
  `params_json` json DEFAULT NULL COMMENT '去重参数',
  `use_count` int(11) DEFAULT '0' COMMENT '使用次数',
  `status` tinyint(4) DEFAULT '1' COMMENT '状态 1可用 0禁用',
  `create_time` datetime DEFAULT CURRENT_TIMESTAMP,
  `update_time` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_md5` (`md5`),
  KEY `idx_template` (`template_id`),
  KEY `idx_merchant` (`merchant_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='视频变体表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_promo_variants`
--

LOCK TABLES `xmt_promo_variants` WRITE;
/*!40000 ALTER TABLE `xmt_promo_variants` DISABLE KEYS */;
/*!40000 ALTER TABLE `xmt_promo_variants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_publish_tasks`
--

DROP TABLE IF EXISTS `xmt_publish_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_publish_tasks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '发布任务ID',
  `content_task_id` int(10) unsigned NOT NULL COMMENT '内容任务ID',
  `user_id` int(10) unsigned NOT NULL COMMENT '用户ID',
  `platforms` json NOT NULL COMMENT '发布平台配置',
  `status` enum('PENDING','PUBLISHING','COMPLETED','PARTIAL','FAILED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDING' COMMENT '发布状态',
  `results` json DEFAULT NULL COMMENT '发布结果',
  `scheduled_time` timestamp NULL DEFAULT NULL COMMENT '定时发布时间',
  `publish_time` timestamp NULL DEFAULT NULL COMMENT '实际发布时间',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_content_task_id` (`content_task_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_scheduled_time` (`scheduled_time`),
  KEY `idx_create_time` (`create_time`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='发布任务表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_publish_tasks`
--

LOCK TABLES `xmt_publish_tasks` WRITE;
/*!40000 ALTER TABLE `xmt_publish_tasks` DISABLE KEYS */;
INSERT INTO `xmt_publish_tasks` VALUES (1,4,0,'[{\"platform\": \"DOUYIN\", \"account_id\": 0}]','COMPLETED','[{\"post_id\": \"dy_001\", \"success\": true, \"platform\": \"DOUYIN\"}]',NULL,'2026-02-10 02:05:00','2026-02-10 02:01:00','2026-02-10 02:05:00'),(2,5,0,'[{\"platform\": \"DOUYIN\", \"account_id\": 0}, {\"platform\": \"XIAOHONGSHU\", \"account_id\": 0}]','COMPLETED','[{\"success\": true, \"platform\": \"DOUYIN\"}, {\"success\": true, \"platform\": \"XIAOHONGSHU\"}]',NULL,'2026-02-12 01:35:00','2026-02-12 01:31:00','2026-02-12 01:35:00'),(3,6,3,'[{\"platform\": \"XIAOHONGSHU\", \"account_id\": 0}]','COMPLETED','[{\"success\": true, \"platform\": \"XIAOHONGSHU\"}]',NULL,'2026-02-13 06:10:00','2026-02-13 06:05:00','2026-02-13 06:10:00'),(4,7,4,'[{\"platform\": \"DOUYIN\", \"account_id\": 0}]','COMPLETED','[{\"success\": true, \"platform\": \"DOUYIN\"}]',NULL,'2026-02-14 03:15:00','2026-02-14 03:10:00','2026-02-14 03:15:00'),(5,8,5,'[{\"platform\": \"XIAOHONGSHU\", \"account_id\": 0}, {\"platform\": \"DOUYIN\", \"account_id\": 0}]','PARTIAL','[{\"success\": true, \"platform\": \"XIAOHONGSHU\"}, {\"error\": \"auth expired\", \"success\": false, \"platform\": \"DOUYIN\"}]',NULL,'2026-02-14 07:40:00','2026-02-14 07:35:00','2026-02-14 07:40:00'),(6,9,0,'[{\"platform\": \"DOUYIN\", \"account_id\": 0}]','PENDING',NULL,'2026-02-17 04:00:00',NULL,'2026-02-15 00:10:00','2026-02-15 00:10:00'),(7,10,0,'[{\"platform\": \"XIAOHONGSHU\", \"account_id\": 0}]','PUBLISHING',NULL,NULL,NULL,'2026-02-16 01:05:00','2026-02-16 01:05:00'),(8,12,0,'[{\"platform\": \"DOUYIN\", \"account_id\": 0}]','FAILED','[{\"error\": \"content generation failed\", \"success\": false, \"platform\": \"DOUYIN\"}]',NULL,'2026-02-11 08:05:00','2026-02-11 08:02:00','2026-02-11 08:05:00');
/*!40000 ALTER TABLE `xmt_publish_tasks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_recommendation_cache`
--

DROP TABLE IF EXISTS `xmt_recommendation_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_recommendation_cache` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '缓存ID',
  `cache_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '缓存键',
  `merchant_id` int(11) unsigned DEFAULT NULL COMMENT '商家ID',
  `user_id` int(11) unsigned DEFAULT NULL COMMENT '用户ID',
  `context` json DEFAULT NULL COMMENT '推荐上下文',
  `recommendations` json NOT NULL COMMENT '推荐结果',
  `algorithm` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '推荐算法',
  `expire_time` datetime NOT NULL COMMENT '过期时间',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_cache_key` (`cache_key`),
  KEY `idx_merchant` (`merchant_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_expire` (`expire_time`),
  KEY `idx_algorithm` (`algorithm`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='推荐结果缓存表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_recommendation_cache`
--

LOCK TABLES `xmt_recommendation_cache` WRITE;
/*!40000 ALTER TABLE `xmt_recommendation_cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `xmt_recommendation_cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_sensitive_words`
--

DROP TABLE IF EXISTS `xmt_sensitive_words`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_sensitive_words` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '词ID',
  `word` varchar(100) NOT NULL COMMENT '敏感词',
  `category` varchar(50) DEFAULT NULL COMMENT '分类',
  `level` tinyint(1) DEFAULT '1' COMMENT '等级 1-5',
  `action` enum('BLOCK','REVIEW','REPLACE') DEFAULT 'REVIEW' COMMENT '处理动作',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态 0禁用 1启用',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_word` (`word`),
  KEY `idx_category` (`category`),
  KEY `idx_level` (`level`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COMMENT='敏感词表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_sensitive_words`
--

LOCK TABLES `xmt_sensitive_words` WRITE;
/*!40000 ALTER TABLE `xmt_sensitive_words` DISABLE KEYS */;
INSERT INTO `xmt_sensitive_words` VALUES (1,'测试敏感词','OTHER',1,'REVIEW',1,'2026-02-07 11:15:49','2026-02-07 11:15:49'),(2,'违规词汇','ILLEGAL',3,'BLOCK',1,'2026-02-07 11:15:49','2026-02-07 11:15:49'),(3,'广告推广','SPAM',2,'REVIEW',1,'2026-02-07 11:15:49','2026-02-07 11:15:49'),(4,'敏感内容','OTHER',2,'REVIEW',1,'2026-02-07 11:15:49','2026-02-07 11:15:49');
/*!40000 ALTER TABLE `xmt_sensitive_words` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_service_calls`
--

DROP TABLE IF EXISTS `xmt_service_calls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_service_calls` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '呼叫ID',
  `session_id` int(11) unsigned NOT NULL COMMENT '会话ID',
  `user_id` int(11) unsigned NOT NULL COMMENT '用户ID',
  `merchant_id` int(11) unsigned NOT NULL COMMENT '商家ID',
  `table_id` int(11) unsigned NOT NULL COMMENT '桌台ID',
  `call_type` enum('ORDER','WATER','BILL','OTHER') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'OTHER' COMMENT '呼叫类型',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '描述',
  `priority` enum('LOW','NORMAL','HIGH','URGENT') COLLATE utf8mb4_unicode_ci DEFAULT 'NORMAL' COMMENT '优先级',
  `status` enum('PENDING','PROCESSING','COMPLETED','CANCELLED') COLLATE utf8mb4_unicode_ci DEFAULT 'PENDING' COMMENT '呼叫状态',
  `staff_id` int(11) unsigned DEFAULT NULL COMMENT '处理员工ID',
  `response_time` int(11) DEFAULT NULL COMMENT '响应时间(秒)',
  `complete_time` datetime DEFAULT NULL COMMENT '完成时间',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_session` (`session_id`),
  KEY `idx_merchant_status` (`merchant_id`,`status`),
  KEY `idx_table` (`table_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='服务呼叫表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_service_calls`
--

LOCK TABLES `xmt_service_calls` WRITE;
/*!40000 ALTER TABLE `xmt_service_calls` DISABLE KEYS */;
/*!40000 ALTER TABLE `xmt_service_calls` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_session_users`
--

DROP TABLE IF EXISTS `xmt_session_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_session_users` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '关联ID',
  `session_id` int(11) unsigned NOT NULL COMMENT '会话ID',
  `user_id` int(11) unsigned NOT NULL COMMENT '用户ID',
  `is_host` tinyint(1) DEFAULT '0' COMMENT '是否为主用户',
  `join_time` datetime NOT NULL COMMENT '加入时间',
  `leave_time` datetime DEFAULT NULL COMMENT '离开时间',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_session_user` (`session_id`,`user_id`),
  KEY `idx_session` (`session_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_join_time` (`join_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用餐会话用户关联表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_session_users`
--

LOCK TABLES `xmt_session_users` WRITE;
/*!40000 ALTER TABLE `xmt_session_users` DISABLE KEYS */;
/*!40000 ALTER TABLE `xmt_session_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_sms_logs`
--

DROP TABLE IF EXISTS `xmt_sms_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_sms_logs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '日志ID',
  `phone` varchar(20) NOT NULL COMMENT '手机号',
  `code` varchar(10) DEFAULT NULL COMMENT '验证码',
  `content` varchar(500) DEFAULT NULL COMMENT '短信内容',
  `template` varchar(100) DEFAULT NULL COMMENT '短信模板',
  `provider` varchar(20) NOT NULL DEFAULT 'aliyun' COMMENT '服务商: aliyun/tencent',
  `success` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否发送成功',
  `error_code` varchar(50) DEFAULT NULL COMMENT '错误码',
  `error_message` varchar(500) DEFAULT NULL COMMENT '错误信息',
  `request_id` varchar(100) DEFAULT NULL COMMENT '请求ID',
  `send_time` datetime DEFAULT NULL COMMENT '发送时间',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_phone` (`phone`),
  KEY `idx_provider` (`provider`),
  KEY `idx_success` (`success`),
  KEY `idx_create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='短信日志表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_sms_logs`
--

LOCK TABLES `xmt_sms_logs` WRITE;
/*!40000 ALTER TABLE `xmt_sms_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `xmt_sms_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_statistics`
--

DROP TABLE IF EXISTS `xmt_statistics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_statistics` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '统计ID',
  `user_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',
  `merchant_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '商家ID',
  `stat_type` varchar(50) NOT NULL DEFAULT '' COMMENT '统计类型',
  `stat_key` varchar(100) NOT NULL DEFAULT '' COMMENT '统计键',
  `stat_value` bigint(20) NOT NULL DEFAULT '0' COMMENT '统计值',
  `stat_date` date NOT NULL COMMENT '统计日期',
  `extra_data` json DEFAULT NULL COMMENT '额外数据',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_stat` (`user_id`,`merchant_id`,`stat_type`,`stat_key`,`stat_date`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_merchant_id` (`merchant_id`),
  KEY `idx_stat_type` (`stat_type`),
  KEY `idx_stat_date` (`stat_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='统计数据表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_statistics`
--

LOCK TABLES `xmt_statistics` WRITE;
/*!40000 ALTER TABLE `xmt_statistics` DISABLE KEYS */;
/*!40000 ALTER TABLE `xmt_statistics` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_tables`
--

DROP TABLE IF EXISTS `xmt_tables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_tables` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '桌台ID',
  `merchant_id` int(11) unsigned NOT NULL COMMENT '所属商家ID',
  `table_number` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '桌号',
  `capacity` tinyint(3) NOT NULL DEFAULT '4' COMMENT '容纳人数',
  `area` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '区域',
  `qr_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '二维码',
  `status` enum('AVAILABLE','OCCUPIED','CLEANING') COLLATE utf8mb4_unicode_ci DEFAULT 'AVAILABLE' COMMENT '桌台状态',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_merchant_table` (`merchant_id`,`table_number`),
  KEY `idx_merchant_status` (`merchant_id`,`status`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='桌台表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_tables`
--

LOCK TABLES `xmt_tables` WRITE;
/*!40000 ALTER TABLE `xmt_tables` DISABLE KEYS */;
/*!40000 ALTER TABLE `xmt_tables` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_user`
--

DROP TABLE IF EXISTS `xmt_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_user` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '用户ID',
  `openid` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '微信openid',
  `unionid` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '微信unionid',
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '手机号',
  `nickname` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '昵称',
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '头像',
  `gender` tinyint(1) DEFAULT '0' COMMENT '性别 0未知 1男 2女',
  `member_level` enum('BASIC','VIP','PREMIUM') COLLATE utf8mb4_unicode_ci DEFAULT 'BASIC' COMMENT '会员等级',
  `points` int(11) DEFAULT '0' COMMENT '积分',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态 0禁用 1正常',
  `last_login_time` datetime DEFAULT NULL COMMENT '最后登录时间',
  `create_time` datetime NOT NULL COMMENT '创建时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `openid` (`openid`),
  KEY `phone` (`phone`)
) ENGINE=InnoDB AUTO_INCREMENT=100000 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_user`
--

LOCK TABLES `xmt_user` WRITE;
/*!40000 ALTER TABLE `xmt_user` DISABLE KEYS */;
INSERT INTO `xmt_user` VALUES (1,'oXXXX_user001',NULL,'13800138001','张三','https://cdn.example.com/avatar/zhangsan.jpg',0,'VIP',1500,1,NULL,'2026-01-10 09:00:00','2026-02-15 10:00:00'),(2,'oXXXX_user002',NULL,'13800138002','李四','https://cdn.example.com/avatar/lisi.jpg',0,'BASIC',800,1,NULL,'2026-01-15 14:30:00','2026-02-14 16:00:00'),(3,'oXXXX_user003',NULL,'13800138003','王五','https://cdn.example.com/avatar/wangwu.jpg',0,'PREMIUM',3200,1,NULL,'2025-12-01 08:00:00','2026-02-16 09:00:00'),(4,'oXXXX_user004',NULL,'13800138004','赵六','https://cdn.example.com/avatar/zhaoliu.jpg',0,'BASIC',200,1,NULL,'2026-02-01 11:00:00','2026-02-15 14:00:00'),(5,'oXXXX_user005',NULL,'13800138005','钱七','https://cdn.example.com/avatar/qianqi.jpg',0,'VIP',2100,1,NULL,'2025-11-20 10:30:00','2026-02-16 08:00:00'),(99999,'test_openid_99999',NULL,NULL,'测试用户','',0,'BASIC',0,1,NULL,'2026-02-12 15:23:57','2026-02-12 15:23:57');
/*!40000 ALTER TABLE `xmt_user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_user_violations`
--

DROP TABLE IF EXISTS `xmt_user_violations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_user_violations` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `material_id` int(11) DEFAULT NULL COMMENT '素材ID',
  `violation_type` varchar(50) NOT NULL COMMENT '违规类型',
  `severity` varchar(20) NOT NULL COMMENT '严重程度',
  `description` text COMMENT '违规描述',
  `provider` varchar(20) DEFAULT NULL COMMENT '检测服务商',
  `confidence` decimal(3,2) DEFAULT NULL COMMENT '置信度',
  `handled` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否已处理: 0-未处理,1-已处理',
  `handled_at` datetime DEFAULT NULL COMMENT '处理时间',
  `handled_by` int(11) DEFAULT NULL COMMENT '处理人ID',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_material_id` (`material_id`),
  KEY `idx_violation_type` (`violation_type`),
  KEY `idx_severity` (`severity`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户违规记录表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_user_violations`
--

LOCK TABLES `xmt_user_violations` WRITE;
/*!40000 ALTER TABLE `xmt_user_violations` DISABLE KEYS */;
/*!40000 ALTER TABLE `xmt_user_violations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_violation_appeals`
--

DROP TABLE IF EXISTS `xmt_violation_appeals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_violation_appeals` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '申诉ID',
  `violation_id` int(11) unsigned NOT NULL COMMENT '违规记录ID',
  `merchant_id` int(11) unsigned NOT NULL COMMENT '商家ID',
  `material_id` int(11) unsigned NOT NULL COMMENT '素材ID',
  `reason` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '申诉理由',
  `evidence` json DEFAULT NULL COMMENT '申诉证据(文档、截图等)',
  `contact_phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '联系电话',
  `contact_email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '联系邮箱',
  `status` enum('PENDING','REVIEWING','APPROVED','REJECTED','CANCELLED') COLLATE utf8mb4_unicode_ci DEFAULT 'PENDING' COMMENT '申诉状态',
  `reviewer_id` int(11) unsigned DEFAULT NULL COMMENT '审核人ID',
  `review_comment` text COLLATE utf8mb4_unicode_ci COMMENT '审核意见',
  `review_result` json DEFAULT NULL COMMENT '审核结果详情',
  `priority` tinyint(1) DEFAULT '0' COMMENT '优先级 0普通 1高',
  `submit_time` datetime NOT NULL COMMENT '提交时间',
  `review_time` datetime DEFAULT NULL COMMENT '审核时间',
  `update_time` datetime NOT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_violation` (`violation_id`),
  KEY `idx_merchant` (`merchant_id`),
  KEY `idx_material` (`material_id`),
  KEY `idx_status` (`status`),
  KEY `idx_submit_time` (`submit_time`),
  KEY `idx_priority` (`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='违规申诉记录表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_violation_appeals`
--

LOCK TABLES `xmt_violation_appeals` WRITE;
/*!40000 ALTER TABLE `xmt_violation_appeals` DISABLE KEYS */;
/*!40000 ALTER TABLE `xmt_violation_appeals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_violation_keywords`
--

DROP TABLE IF EXISTS `xmt_violation_keywords`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_violation_keywords` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '关键词ID',
  `keyword` varchar(255) NOT NULL COMMENT '关键词',
  `category` varchar(50) NOT NULL DEFAULT 'OTHER' COMMENT '违规类型: PORN/POLITICS/VIOLENCE/AD/ILLEGAL/ABUSE/TERRORISM/SPAM/OTHER',
  `severity` varchar(20) NOT NULL DEFAULT 'MEDIUM' COMMENT '严重程度: HIGH/MEDIUM/LOW',
  `match_type` varchar(20) NOT NULL DEFAULT 'EXACT' COMMENT '匹配类型: EXACT(精确)/FUZZY(模糊)/REGEX(正则)',
  `pattern` varchar(500) DEFAULT NULL COMMENT '正则表达式(当match_type=REGEX时使用)',
  `enabled` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否启用: 0-禁用,1-启用',
  `hit_count` int(11) NOT NULL DEFAULT '0' COMMENT '命中次数',
  `last_hit_time` datetime DEFAULT NULL COMMENT '最后命中时间',
  `created_by` int(11) DEFAULT NULL COMMENT '创建人ID',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_keyword` (`keyword`),
  KEY `idx_category` (`category`),
  KEY `idx_enabled` (`enabled`),
  KEY `idx_match_type` (`match_type`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COMMENT='违规关键词表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_violation_keywords`
--

LOCK TABLES `xmt_violation_keywords` WRITE;
/*!40000 ALTER TABLE `xmt_violation_keywords` DISABLE KEYS */;
INSERT INTO `xmt_violation_keywords` VALUES (1,'赌球','ILLEGAL','HIGH','EXACT',NULL,1,0,NULL,NULL,'2026-02-07 12:49:02','2026-02-07 12:49:02'),(2,'博彩','ILLEGAL','HIGH','EXACT',NULL,1,0,NULL,NULL,'2026-02-07 12:49:02','2026-02-07 12:49:02'),(3,'刷单','AD','MEDIUM','EXACT',NULL,1,0,NULL,NULL,'2026-02-07 12:49:02','2026-02-07 12:49:02'),(4,'代刷','AD','MEDIUM','EXACT',NULL,1,0,NULL,NULL,'2026-02-07 12:49:02','2026-02-07 12:49:02');
/*!40000 ALTER TABLE `xmt_violation_keywords` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_wechat_template_logs`
--

DROP TABLE IF EXISTS `xmt_wechat_template_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_wechat_template_logs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '日志ID',
  `user_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',
  `openid` varchar(100) NOT NULL DEFAULT '' COMMENT '微信OpenID',
  `platform` varchar(20) NOT NULL DEFAULT 'miniprogram' COMMENT '平台类型 miniprogram|official',
  `template_type` varchar(50) NOT NULL DEFAULT '' COMMENT '模板类型',
  `template_id` varchar(100) NOT NULL DEFAULT '' COMMENT '模板ID',
  `template_data` text COMMENT '模板数据JSON',
  `page` varchar(255) NOT NULL DEFAULT '' COMMENT '跳转页面',
  `related_data` text COMMENT '关联数据JSON',
  `status` varchar(20) NOT NULL DEFAULT 'sending' COMMENT '发送状态 sending|success|failed',
  `retry_count` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '重试次数',
  `error_code` varchar(50) DEFAULT NULL COMMENT '错误码',
  `error_message` varchar(500) DEFAULT NULL COMMENT '错误信息',
  `response_data` text COMMENT '响应数据JSON',
  `send_time` datetime DEFAULT NULL COMMENT '发送时间',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_openid` (`openid`),
  KEY `idx_platform` (`platform`),
  KEY `idx_template_type` (`template_type`),
  KEY `idx_status` (`status`),
  KEY `idx_create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='微信模板消息发送日志表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_wechat_template_logs`
--

LOCK TABLES `xmt_wechat_template_logs` WRITE;
/*!40000 ALTER TABLE `xmt_wechat_template_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `xmt_wechat_template_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `xmt_wechat_templates`
--

DROP TABLE IF EXISTS `xmt_wechat_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `xmt_wechat_templates` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '配置ID',
  `platform` varchar(20) NOT NULL DEFAULT 'miniprogram' COMMENT '平台类型 miniprogram|official',
  `template_key` varchar(50) NOT NULL DEFAULT '' COMMENT '模板键名',
  `template_id` varchar(100) NOT NULL DEFAULT '' COMMENT '模板ID',
  `template_name` varchar(100) NOT NULL DEFAULT '' COMMENT '模板名称',
  `content` text COMMENT '模板内容',
  `example` text COMMENT '模板示例',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态 0禁用 1启用',
  `remark` varchar(500) DEFAULT NULL COMMENT '备注',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_platform_template_key` (`platform`,`template_key`),
  KEY `idx_platform` (`platform`),
  KEY `idx_template_key` (`template_key`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COMMENT='微信模板配置表';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `xmt_wechat_templates`
--

LOCK TABLES `xmt_wechat_templates` WRITE;
/*!40000 ALTER TABLE `xmt_wechat_templates` DISABLE KEYS */;
INSERT INTO `xmt_wechat_templates` VALUES (1,'miniprogram','content_generated','CONTENT_GENERATED_TEMPLATE','内容生成完成通知','内容名称：{{thing1.DATA}}\n内容类型：{{thing2.DATA}}\n生成时间：{{date3.DATA}}\n发布平台：{{thing4.DATA}}','{\"thing1\":{\"value\":\"视频内容生成完成\"},\"thing2\":{\"value\":\"视频内容\"},\"date3\":{\"value\":\"2024-01-01 12:00:00\"},\"thing4\":{\"value\":\"抖音\"}}',0,'内容生成完成后通知用户','2026-02-07 11:14:26','2026-02-07 11:14:26'),(2,'miniprogram','device_alert','DEVICE_ALERT_TEMPLATE','设备告警通知','设备名称：{{thing1.DATA}}\n设备编号：{{character_string2.DATA}}\n告警类型：{{thing3.DATA}}\n告警时间：{{time4.DATA}}','{\"thing1\":{\"value\":\"智能设备A1\"},\"character_string2\":{\"value\":\"DEV001\"},\"thing3\":{\"value\":\"离线告警\"},\"time4\":{\"value\":\"2024-01-01 12:00:00\"}}',0,'设备离线或异常时通知商家','2026-02-07 11:14:26','2026-02-07 11:14:26'),(3,'miniprogram','coupon_received','COUPON_RECEIVED_TEMPLATE','优惠券领取通知','优惠券名称：{{thing1.DATA}}\n优惠金额：{{amount2.DATA}}元\n有效期至：{{date3.DATA}}\n商家名称：{{thing4.DATA}}','{\"thing1\":{\"value\":\"满100减20券\"},\"amount2\":{\"value\":\"20\"},\"date3\":{\"value\":\"2024-12-31\"},\"thing4\":{\"value\":\"示例商家\"}}',0,'用户领取优惠券后通知','2026-02-07 11:14:26','2026-02-07 11:14:26'),(4,'miniprogram','merchant_audit','MERCHANT_AUDIT_TEMPLATE','商家审核结果通知','商家名称：{{thing1.DATA}}\n审核结果：{{phrase2.DATA}}\n审核说明：{{thing3.DATA}}\n审核时间：{{date4.DATA}}','{\"thing1\":{\"value\":\"示例商家\"},\"phrase2\":{\"value\":\"审核通过\"},\"thing3\":{\"value\":\"您的申请已通过审核\"},\"date4\":{\"value\":\"2024-01-01 12:00:00\"}}',0,'商家审核结果通知','2026-02-07 11:14:26','2026-02-07 11:14:26'),(5,'miniprogram','order_status','ORDER_STATUS_TEMPLATE','订单状态变更通知','订单编号：{{character_string1.DATA}}\n商品名称：{{thing2.DATA}}\n订单状态：{{thing3.DATA}}\n订单金额：{{amount4.DATA}}元','{\"character_string1\":{\"value\":\"ORDER20240101001\"},\"thing2\":{\"value\":\"示例商品\"},\"thing3\":{\"value\":\"待支付\"},\"amount4\":{\"value\":\"99.00\"}}',0,'订单状态变更时通知用户','2026-02-07 11:14:26','2026-02-07 11:14:26'),(6,'official','content_generated','OFFICIAL_CONTENT_GENERATED_TEMPLATE','内容生成完成通知','内容名称：{{thing1.DATA}}\n内容类型：{{thing2.DATA}}\n生成时间：{{date3.DATA}}\n发布平台：{{thing4.DATA}}','{\"thing1\":{\"value\":\"视频内容生成完成\"},\"thing2\":{\"value\":\"视频内容\"},\"date3\":{\"value\":\"2024-01-01 12:00:00\"},\"thing4\":{\"value\":\"抖音\"}}',0,'内容生成完成后通知用户（公众号）','2026-02-07 11:14:26','2026-02-07 11:14:26'),(7,'official','device_alert','OFFICIAL_DEVICE_ALERT_TEMPLATE','设备告警通知','设备名称：{{thing1.DATA}}\n设备编号：{{character_string2.DATA}}\n告警类型：{{thing3.DATA}}\n告警时间：{{time4.DATA}}','{\"thing1\":{\"value\":\"智能设备A1\"},\"character_string2\":{\"value\":\"DEV001\"},\"thing3\":{\"value\":\"离线告警\"},\"time4\":{\"value\":\"2024-01-01 12:00:00\"}}',0,'设备离线或异常时通知商家（公众号）','2026-02-07 11:14:26','2026-02-07 11:14:26'),(8,'official','coupon_received','OFFICIAL_COUPON_RECEIVED_TEMPLATE','优惠券领取通知','优惠券名称：{{thing1.DATA}}\n优惠金额：{{amount2.DATA}}元\n有效期至：{{date3.DATA}}\n商家名称：{{thing4.DATA}}','{\"thing1\":{\"value\":\"满100减20券\"},\"amount2\":{\"value\":\"20\"},\"date3\":{\"value\":\"2024-12-31\"},\"thing4\":{\"value\":\"示例商家\"}}',0,'用户领取优惠券后通知（公众号）','2026-02-07 11:14:26','2026-02-07 11:14:26'),(9,'official','merchant_audit','OFFICIAL_MERCHANT_AUDIT_TEMPLATE','商家审核结果通知','商家名称：{{thing1.DATA}}\n审核结果：{{phrase2.DATA}}\n审核说明：{{thing3.DATA}}\n审核时间：{{date4.DATA}}','{\"thing1\":{\"value\":\"示例商家\"},\"phrase2\":{\"value\":\"审核通过\"},\"thing3\":{\"value\":\"您的申请已通过审核\"},\"date4\":{\"value\":\"2024-01-01 12:00:00\"}}',0,'商家审核结果通知（公众号）','2026-02-07 11:14:26','2026-02-07 11:14:26'),(10,'official','order_status','OFFICIAL_ORDER_STATUS_TEMPLATE','订单状态变更通知','订单编号：{{character_string1.DATA}}\n商品名称：{{thing2.DATA}}\n订单状态：{{thing3.DATA}}\n订单金额：{{amount4.DATA}}元','{\"character_string1\":{\"value\":\"ORDER20240101001\"},\"thing2\":{\"value\":\"示例商品\"},\"thing3\":{\"value\":\"待支付\"},\"amount4\":{\"value\":\"99.00\"}}',0,'订单状态变更时通知用户（公众号）','2026-02-07 11:14:26','2026-02-07 11:14:26');
/*!40000 ALTER TABLE `xmt_wechat_templates` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-03-04 11:39:10
