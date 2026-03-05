#!/bin/bash
# xiaomotui 生产环境部署脚本
# 用法: ./deploy.sh [版本号]
# 示例: ./deploy.sh 20260305-aef1bb59

set -e

# ============ 配置区域 ============
REGISTRY="crpi-6wut8f1pmvyiv5nm.cn-beijing.personal.cr.aliyuncs.com"
NAMESPACE="xiaomotui"
DOCKER_COMPOSE_FILE="docker-compose.prod.yml"
ENV_FILE=".env"
ENV_EXAMPLE=".env.production.example"

# 镜像版本（默认 latest）
IMAGE_TAG=${1:-latest}

# 颜色输出
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}=========================================="
echo "xiaomotui 生产环境部署"
echo "版本: ${IMAGE_TAG}"
echo -e "==========================================${NC}"

# ============ 检查 Docker ============
if ! command -v docker &> /dev/null; then
    echo -e "${RED}错误: Docker 未安装${NC}"
    exit 1
fi

if ! command -v docker-compose &> /dev/null && ! docker compose version &> /dev/null 2>&1; then
    echo -e "${RED}错误: Docker Compose 未安装${NC}"
    exit 1
fi

# ============ 检查环境变量文件 ============
if [ ! -f "$ENV_FILE" ]; then
    if [ -f "$ENV_EXAMPLE" ]; then
        echo -e "${YELLOW}>>> 复制环境变量模板...${NC}"
        cp "$ENV_EXAMPLE" "$ENV_FILE"
        echo -e "${RED}=========================================="
        echo "请先编辑 $ENV_FILE 设置密码后重新运行此脚本"
        echo "vim $ENV_FILE"
        echo -e "==========================================${NC}"
        exit 1
    else
        echo -e "${RED}错误: 找不到 $ENV_FILE 或 $ENV_EXAMPLE${NC}"
        exit 1
    fi
fi

# ============ 登录镜像仓库 ============
echo -e "${YELLOW}>>> 登录阿里云镜像仓库...${NC}"
docker login --username=shandian520 --password=Dear19840520! ${REGISTRY}

# ============ 拉取最新镜像 ============
echo -e "${YELLOW}>>> 拉取镜像 (版本: ${IMAGE_TAG})...${NC}"
export IMAGE_TAG
if docker-compose version &> /dev/null 2>&1; then
    docker-compose -f "$DOCKER_COMPOSE_FILE" pull
else
    docker compose -f "$DOCKER_COMPOSE_FILE" pull
fi

# ============ 停止旧容器 ============
echo -e "${YELLOW}>>> 停止旧容器...${NC}"
if docker-compose version &> /dev/null 2>&1; then
    docker-compose -f "$DOCKER_COMPOSE_FILE" down --remove-orphans
else
    docker compose -f "$DOCKER_COMPOSE_FILE" down --remove-orphans
fi

# ============ 启动新容器 ============
echo -e "${YELLOW}>>> 启动服务...${NC}"
if docker-compose version &> /dev/null 2>&1; then
    docker-compose -f "$DOCKER_COMPOSE_FILE" up -d
else
    docker compose -f "$DOCKER_COMPOSE_FILE" up -d
fi

# ============ 等待服务启动 ============
echo -e "${YELLOW}>>> 等待服务启动...${NC}"
sleep 15

# ============ 检查服务状态 ============
echo -e "${GREEN}>>> 服务状态:${NC}"
if docker-compose version &> /dev/null 2>&1; then
    docker-compose -f "$DOCKER_COMPOSE_FILE" ps
else
    docker compose -f "$DOCKER_COMPOSE_FILE" ps
fi

# ============ 健康检查 ============
echo ""
echo -e "${YELLOW}>>> 健康检查...${NC}"
sleep 5
if curl -sf http://localhost/api/health > /dev/null 2>&1; then
    echo -e "${GREEN}API 服务正常${NC}"
else
    echo -e "${YELLOW}警告: API 健康检查失败，请检查日志${NC}"
fi

# ============ 清理旧镜像 ============
echo -e "${YELLOW}>>> 清理无用镜像...${NC}"
docker image prune -f

# ============ 完成 ============
echo ""
echo -e "${GREEN}=========================================="
echo "部署完成!"
echo -e "==========================================${NC}"
echo ""
echo "常用命令:"
echo "  查看状态: docker-compose -f $DOCKER_COMPOSE_FILE ps"
echo "  查看日志: docker-compose -f $DOCKER_COMPOSE_FILE logs -f"
echo "  查看某服务: docker-compose -f $DOCKER_COMPOSE_FILE logs -f api"
echo "  重启服务: docker-compose -f $DOCKER_COMPOSE_FILE restart"
echo "  停止服务: docker-compose -f $DOCKER_COMPOSE_FILE down"
echo ""
