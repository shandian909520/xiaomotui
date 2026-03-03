-- 操作日志表
CREATE TABLE IF NOT EXISTS xmt_operation_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作用户ID',
  username VARCHAR(50) DEFAULT '' COMMENT '操作用户名',
  module VARCHAR(50) NOT NULL DEFAULT '' COMMENT '操作模块',
  action VARCHAR(50) NOT NULL DEFAULT '' COMMENT '操作动作',
  description VARCHAR(500) DEFAULT '' COMMENT '操作描述',
  method VARCHAR(10) DEFAULT '' COMMENT '请求方法',
  url VARCHAR(500) DEFAULT '' COMMENT '请求URL',
  params TEXT COMMENT '请求参数',
  ip VARCHAR(45) DEFAULT '' COMMENT '操作IP',
  user_agent VARCHAR(500) DEFAULT '' COMMENT '用户代理',
  create_time DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  INDEX idx_user_id (user_id),
  INDEX idx_module (module),
  INDEX idx_create_time (create_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='操作日志表';
