#!/bin/bash
# 生产环境部署脚本
# 用法: ./deploy.sh [版本号]

set -e

# ============ 配置区域 ============
REGISTRY="registry.cn-hangzhou.aliyuncs.com"
NAMESPACE="xiaomotui"
IMAGE_TAG=${1:-latest}

echo "=========================================="
echo "部署版本: ${IMAGE_TAG}"
echo "=========================================="

# ============ 检查环境变量 ============
if [ ! -f .env ]; then
    echo "错误: .env 文件不存在！"
    echo "请创建 .env 文件并配置以下变量:"
    echo "  MYSQL_ROOT_PASSWORD=xxx"
    echo "  MYSQL_PASSWORD=xxx"
    echo "  REDIS_PASSWORD=xxx"
    echo "  REGISTRY=${REGISTRY}"
    echo "  NAMESPACE=${NAMESPACE}"
    exit 1
fi

# 加载环境变量
export $(cat .env | grep -v '^#' | xargs)
export REGISTRY NAMESPACE IMAGE_TAG

# ============ 登录镜像仓库 ============
echo ">>> 登录镜像仓库..."
docker login ${REGISTRY}

# ============ 拉取最新镜像 ============
echo ">>> 拉取最新镜像..."
docker pull ${REGISTRY}/${NAMESPACE}/api:${IMAGE_TAG}
docker pull ${REGISTRY}/${NAMESPACE}/nginx:${IMAGE_TAG}

# ============ 停止旧容器 ============
echo ">>> 停止旧容器..."
docker-compose -f docker-compose.prod.yml down --remove-orphans

# ============ 启动新容器 ============
echo ">>> 启动新容器..."
docker-compose -f docker-compose.prod.yml up -d

# ============ 等待服务就绪 ============
echo ">>> 等待服务启动..."
sleep 10

# ============ 健康检查 ============
echo ">>> 健康检查..."
if curl -sf http://localhost/api/health > /dev/null 2>&1; then
    echo "API 服务正常"
else
    echo "警告: API 健康检查失败，请检查日志"
fi

# ============ 清理旧镜像 ============
echo ">>> 清理无用镜像..."
docker image prune -f

echo "=========================================="
echo "部署完成！"
echo ""
echo "查看状态: docker-compose -f docker-compose.prod.yml ps"
echo "查看日志: docker-compose -f docker-compose.prod.yml logs -f"
echo "=========================================="
