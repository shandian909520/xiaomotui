#!/bin/bash
# 快速更新脚本（不重建镜像）

set -e
GREEN='\033[0;32m'; BLUE='\033[0;34m'; NC='\033[0m'

echo -e "${BLUE}[更新]${NC} 拉取最新代码..."
git pull

echo -e "${BLUE}[更新]${NC} 重新构建前端..."
cd admin && npm ci && npm run build && cd ..

echo -e "${BLUE}[更新]${NC} 运行数据库迁移..."
docker-compose exec -T api php think migrate:run 2>/dev/null || true

echo -e "${BLUE}[更新]${NC} 重启 API 服务..."
docker-compose restart api

echo -e "${BLUE}[更新]${NC} 重载 Nginx..."
docker-compose exec -T nginx nginx -s reload

echo -e "${GREEN}[完成]${NC} 更新完成！"
