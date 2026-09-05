-- Agent E: 漏斗埋点事件表(funnel_event)
-- 用途: 记录用户在小魔推 C 端 H5 上的全链路行为
--   step    = 漏斗层级阶段
--     - nfc_trigger   : 用户碰 NFC 设备
--     - h5_enter      : 落地页打开
--     - hub_view      : 看到 Hub 主页
--     - task_start    : 启动任务
--     - task_complete : 完成任务
--     - reward_claim  : 领取奖励
--     - wifi_connect  : 连接 Wi-Fi
--     - contact_copy  : 复制联系方式
--     - add_wechat    : 加粉转化(漏斗底部)
--     - add_qq        : 加 QQ
--     - add_wework    : 加企微
--     - review_post   : 写点评
--     - lottery_draw  : 参与抽奖
--     - coupon_claim  : 领券
--   block   = 漏斗所在区块 wifi/publish/groupbuy/review/contact/lottery/hub
--   action  = 具体动作 click/submit/copy/long_press/scan 等
--   meta    = 自由 JSON: {ref_id, source, page_url, device_code, extra}
--
-- 设计:
--   - 表名沿用项目前缀 xmt_ 与既有表一致
--   - 索引: (device_id, step) 用于按设备漏斗聚合;
--           (created_at) 用于按日 / 按时段统计
--           (merchant_id, step) 用于商家视角聚合
--   - user_hash 用 CHAR(32) 存放 md5(ip+ua),不存明文 IP/UA
--
-- 数据插入走 FunnelService::record() (异步,失败只 log warn,不抛错)

CREATE TABLE IF NOT EXISTS `xmt_funnel_event` (
  `id`          bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `device_id`   int(11) unsigned DEFAULT NULL COMMENT 'NFC 设备 ID,可空(匿名入口)',
  `merchant_id` int(11) unsigned DEFAULT NULL COMMENT '商家 ID(冗余,加速聚合)',
  `user_hash`   char(32) NOT NULL DEFAULT '' COMMENT '脱敏 user id (md5(ip+ua))',
  `step`        varchar(32) NOT NULL DEFAULT '' COMMENT '漏斗层级: nfc_trigger / h5_enter / hub_view / task_start / task_complete / reward_claim / wifi_connect / contact_copy / add_wechat / add_qq / add_wework / review_post / lottery_draw / coupon_claim',
  `block`       varchar(32) NOT NULL DEFAULT '' COMMENT '区块: hub / wifi / publish / groupbuy / review / contact / lottery',
  `action`      varchar(32) NOT NULL DEFAULT '' COMMENT '动作: view / click / submit / copy / scan / long_press / claim',
  `meta`        json DEFAULT NULL COMMENT '附加 JSON: {ref_id, source, page_url, device_code, extra}',
  `created_at`  datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '记录时间',
  PRIMARY KEY (`id`),
  KEY `idx_device_step` (`device_id`, `step`),
  KEY `idx_merchant_step` (`merchant_id`, `step`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_step_block` (`step`, `block`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='C 端漏斗埋点事件';