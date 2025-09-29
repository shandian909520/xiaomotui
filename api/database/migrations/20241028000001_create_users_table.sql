-- 用户表
CREATE TABLE `xmt_user` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '用户ID',
  `username` varchar(50) NOT NULL COMMENT '用户名',
  `password` varchar(255) NOT NULL COMMENT '密码',
  `email` varchar(100) DEFAULT NULL COMMENT '邮箱',
  `phone` varchar(20) DEFAULT NULL COMMENT '手机号',
  `nickname` varchar(50) DEFAULT NULL COMMENT '昵称',
  `avatar` varchar(255) DEFAULT NULL COMMENT '头像',
  `gender` tinyint(1) DEFAULT '0' COMMENT '性别：0未知，1男，2女',
  `birthday` date DEFAULT NULL COMMENT '生日',
  `bio` text COMMENT '个人简介',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态：0禁用，1正常，2待审核',
  `last_login_time` timestamp NULL DEFAULT NULL COMMENT '最后登录时间',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `delete_time` timestamp NULL DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `phone` (`phone`),
  KEY `status` (`status`),
  KEY `create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户表';

-- 用户关注表
CREATE TABLE `xmt_user_follow` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `follower_id` int(10) unsigned NOT NULL COMMENT '关注者ID',
  `followed_id` int(10) unsigned NOT NULL COMMENT '被关注者ID',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '关注时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `follower_followed` (`follower_id`, `followed_id`),
  KEY `follower_id` (`follower_id`),
  KEY `followed_id` (`followed_id`),
  KEY `create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户关注表';

-- 帖子表
CREATE TABLE `xmt_post` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '帖子ID',
  `user_id` int(10) unsigned NOT NULL COMMENT '用户ID',
  `title` varchar(200) NOT NULL COMMENT '标题',
  `content` text NOT NULL COMMENT '内容',
  `images` json DEFAULT NULL COMMENT '图片列表',
  `video` varchar(255) DEFAULT NULL COMMENT '视频地址',
  `tags` varchar(500) DEFAULT NULL COMMENT '标签，逗号分隔',
  `platform` varchar(50) DEFAULT NULL COMMENT '目标平台',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态：0草稿，1已发布，2已删除',
  `is_ai_generated` tinyint(1) DEFAULT '0' COMMENT '是否AI生成',
  `view_count` int(10) unsigned DEFAULT '0' COMMENT '浏览数',
  `like_count` int(10) unsigned DEFAULT '0' COMMENT '点赞数',
  `comment_count` int(10) unsigned DEFAULT '0' COMMENT '评论数',
  `share_count` int(10) unsigned DEFAULT '0' COMMENT '分享数',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `delete_time` timestamp NULL DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`),
  KEY `create_time` (`create_time`),
  KEY `platform` (`platform`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='帖子表';

-- 评论表
CREATE TABLE `xmt_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '评论ID',
  `post_id` int(10) unsigned NOT NULL COMMENT '帖子ID',
  `user_id` int(10) unsigned NOT NULL COMMENT '用户ID',
  `parent_id` int(10) unsigned DEFAULT '0' COMMENT '父评论ID',
  `content` text NOT NULL COMMENT '评论内容',
  `like_count` int(10) unsigned DEFAULT '0' COMMENT '点赞数',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态：0待审核，1已通过，2已拒绝',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `delete_time` timestamp NULL DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  KEY `post_id` (`post_id`),
  KEY `user_id` (`user_id`),
  KEY `parent_id` (`parent_id`),
  KEY `create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='评论表';