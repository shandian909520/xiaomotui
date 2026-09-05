-- 模块7 加QQ支持
-- 检查发现:xmt_nfc_devices 没有 ext_json 字段,但存在大量的 JSON 配置型字段(group_buy_config/promo_*/wifi_*),
-- 为保持现有代码完全不动,新增 qq_contact_config(空对象)字段专用于 QQ/客服联系方式配置。
-- 若不需要修改 ContactService.php,本字段仅供新模块扩展使用。
--
-- 同时把 contact_type 字段长度从 20 扩到 30,以容纳 future 类型标识(原 xmt_contact_actions.contact_type varchar(20))。

ALTER TABLE `xmt_nfc_devices`
ADD COLUMN `qq_contact_config` json DEFAULT NULL COMMENT 'QQ/客服联系方式(JSON: qq_number, qq_qrcode_url, qq_group_url, kefu_qrcode_url 等)' AFTER `wifi_password`;

ALTER TABLE `xmt_contact_actions`
MODIFY COLUMN `contact_type` varchar(30) NOT NULL COMMENT '联系方式类型 wework/wechat/phone/qq';
