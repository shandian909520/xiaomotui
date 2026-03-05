#!/bin/bash
# Docker 镜像构建和推送脚本
# 用法: ./docker-build.sh [版本号]

set -e

# ============ 配置区域 ============
# 镜像仓库地址（阿里云）
REGISTRY="crpi-6wut8f1pmvyiv5nm.cn-beijing.personal.cr.aliyuncs.com"
NAMESPACE="xiaomotui"

# 镜像版本（默认使用日期+git commit）
VERSION=${1:-$(date +%Y%m%d)-$(git rev-parse --short HEAD 2>/dev/null || echo "local")}

# 镜像名称
API_IMAGE="${REGISTRY}/${NAMESPACE}/api"
NGINX_IMAGE="${REGISTRY}/${NAMESPACE}/nginx"
MYSQL_IMAGE="${REGISTRY}/${NAMESPACE}/mysql"
REDIS_IMAGE="${REGISTRY}/${NAMESPACE}/redis"

echo "=========================================="
echo "构建版本: ${VERSION}"
echo "=========================================="

# ============ 登录镜像仓库 ============
echo ">>> 登录镜像仓库..."
docker login --username=shandian520 --password=Dear19840520! ${REGISTRY}

# ============ 构建 API 镜像 ============
echo ">>> 构建 API 镜像..."
docker build \
    -t ${API_IMAGE}:${VERSION} \
    -t ${API_IMAGE}:latest \
    -f docker/api/Dockerfile \
    --build-arg APP_ENV=production \
    .

# ============ 构建 Nginx 镜像 ============
echo ">>> 构建 Nginx 镜像..."
docker build \
    -t ${NGINX_IMAGE}:${VERSION} \
    -t ${NGINX_IMAGE}:latest \
    -f docker/nginx/Dockerfile \
    ./docker/nginx

# ============ 构建 MySQL 镜像 ============
echo ">>> 构建 MySQL 镜像..."
docker build \
    -t ${MYSQL_IMAGE}:${VERSION} \
    -t ${MYSQL_IMAGE}:latest \
    -f docker/mysql/Dockerfile \
    ./docker/mysql

# ============ 处理 Redis 镜像 ============
echo ">>> 处理 Redis 镜像..."
docker pull redis:7-alpine
docker tag redis:7-alpine ${REDIS_IMAGE}:${VERSION}
docker tag redis:7-alpine ${REDIS_IMAGE}:latest

# ============ 推送镜像 ============
echo ">>> 推送 API 镜像..."
docker push ${API_IMAGE}:${VERSION}
docker push ${API_IMAGE}:latest

echo ">>> 推送 Nginx 镜像..."
docker push ${NGINX_IMAGE}:${VERSION}
docker push ${NGINX_IMAGE}:latest

echo ">>> 推送 MySQL 镜像..."
docker push ${MYSQL_IMAGE}:${VERSION}
docker push ${MYSQL_IMAGE}:latest

echo ">>> 推送 Redis 镜像..."
docker push ${REDIS_IMAGE}:${VERSION}
docker push ${REDIS_IMAGE}:latest

echo "=========================================="
echo "构建和推送完成！"
echo ""
echo "部署命令:"
echo "  docker pull ${API_IMAGE}:${VERSION}"
echo "  docker pull ${NGINX_IMAGE}:${VERSION}"
echo "  docker pull ${MYSQL_IMAGE}:${VERSION}"
echo "  docker pull ${REDIS_IMAGE}:${VERSION}"
echo ""
echo "或使用 docker-compose:"
echo "  IMAGE_TAG=${VERSION} docker-compose -f docker-compose.prod.yml pull"
echo "  IMAGE_TAG=${VERSION} docker-compose -f docker-compose.prod.yml up -d"
echo "=========================================="
