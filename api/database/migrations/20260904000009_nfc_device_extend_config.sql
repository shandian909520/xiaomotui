-- Agent E: NFC 设备配置扩展字段
-- 现状:
--   - wifi_ssid / wifi_password 已存在(wifi 配置)
--   - qq_contact_config 已存在(Agent C 加的 QQ 配置)
--   - merchant.contact_config 已存在(商家级联系方式)
-- 新增字段(本次只补最必需的,其它复用已有):
--   - wechat_contact_config: 微信/企微配置(JSON,与 qq_contact_config 对齐)
--   - shop_owner_qr: 店长二维码 URL(VARCHAR 500,被 H5 私域区展示用)
--   - ai_copy_enabled: AI 文案模板开关(TINYINT,商家后台「任务配置」tab)
--
-- 设计: 保留 nullable DEFAULT,旧设备不受影响;不要破坏现有 JSON 字段名。

ALTER TABLE `xmt_nfc_devices`
ADD COLUMN `wechat_contact_config` json DEFAULT NULL
  COMMENT '微信/企微联系方式(JSON: wework_url, wechat_url, wework_qr_url, wechat_qr_url, kefu_wechat)'
  AFTER `qq_contact_config`,
ADD COLUMN `shop_owner_qr` varchar(500) DEFAULT NULL
  COMMENT '店长二维码 URL(H5 私域区展示用)'
  AFTER `wechat_contact_config`,
ADD COLUMN `ai_copy_enabled` tinyint(1) DEFAULT 1
  COMMENT 'AI 文案模板开关 0=关 1=开(默认开,商家后台可关)'
  AFTER `shop_owner_qr`;