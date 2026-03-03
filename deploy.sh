#!/bin/bash
# ============================================================
# 小魔推 - Docker 生产环境部署脚本
# 使用方法：chmod +x deploy.sh && ./deploy.sh
# ============================================================

set -e

# 颜色输出
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info()    { echo -e "${BLUE}[INFO]${NC} $1"; }
log_success() { echo -e "${GREEN}[OK]${NC} $1"; }
log_warn()    { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error()   { echo -e "${RED}[ERROR]${NC} $1"; exit 1; }

# 检查依赖
check_dependencies() {
    log_info "检查依赖..."
    command -v docker    >/dev/null 2>&1 || log_error "Docker 未安装"
    command -v docker-compose >/dev/null 2>&1 || command -v docker compose >/dev/null 2>&1 || log_error "Docker Compose 未安装"
    command -v node >/dev/null 2>&1 || log_error "Node.js 未安装"
    command -v npm  >/dev/null 2>&1 || log_error "npm 未安装"
    log_success "依赖检查通过"
}

# 检查环境配置
check_env() {
    log_info "检查环境配置..."
    [ ! -f "api/.env.production" ] && log_error "api/.env.production 不存在，请参考 api/.env.production 进行配置"

    # 检查必填的密钥是否已修改
    if grep -q "CHANGE_THIS\|YOUR_PRODUCTION\|YOUR_DB_\|YOUR_REDIS_\|YOUR_JWT" api/.env.production; then
        log_warn "检测到 api/.env.production 中仍有未配置的占位符，请确认已正确设置所有密钥！"
        read -p "是否继续部署？(y/N): " -n 1 -r
        echo
        [[ ! $REPLY =~ ^[Yy]$ ]] && exit 1
    fi

    [ ! -f "docker/nginx/ssl/fullchain.pem" ] && log_warn "SSL 证书未找到：docker/nginx/ssl/fullchain.pem，将跳过 HTTPS 配置"
    [ ! -f "docker/nginx/ssl/privkey.pem" ]  && log_warn "SSL 私钥未找到：docker/nginx/ssl/privkey.pem"

    log_success "环境配置检查完成"
}

# 构建前端
build_frontend() {
    log_info "构建前端管理后台..."
    cd admin
    npm ci --production=false
    npm run build
    cd ..
    log_success "前端构建完成 → admin/dist/"
}

# 复制生产环境配置
setup_api_config() {
    log_info "配置后端生产环境..."
    cp api/.env.production api/.env
    log_success "后端配置完成"
}

# 构建并启动 Docker 服务
start_services() {
    log_info "拉取基础镜像..."
    docker-compose pull mysql redis nginx 2>/dev/null || true

    log_info "构建 API 镜像..."
    docker-compose build api

    log_info "启动所有服务..."
    docker-compose up -d

    log_info "等待数据库就绪..."
    local retries=30
    until docker-compose exec -T mysql mysqladmin ping -h localhost --silent 2>/dev/null; do
        retries=$((retries - 1))
        [ $retries -eq 0 ] && log_error "数据库启动超时"
        sleep 2
    done
    log_success "数据库已就绪"
}

# 运行数据库迁移
run_migrations() {
    log_info "运行数据库迁移..."
    docker-compose exec -T api php think migrate:run
    log_success "数据库迁移完成"
}

# 优化生产环境
optimize() {
    log_info "优化生产环境..."
    docker-compose exec -T api php think optimize:all 2>/dev/null || true
    log_success "优化完成"
}

# 健康检查
health_check() {
    log_info "执行健康检查..."
    sleep 3
    local retries=10
    until curl -sf http://localhost/api >/dev/null 2>&1; do
        retries=$((retries - 1))
        [ $retries -eq 0 ] && { log_warn "健康检查未通过，请手动检查"; return; }
        sleep 2
    done
    log_success "服务健康检查通过"
}

# 打印完成信息
print_summary() {
    echo ""
    echo -e "${GREEN}========================================${NC}"
    echo -e "${GREEN}  部署完成！${NC}"
    echo -e "${GREEN}========================================${NC}"
    echo -e "  管理后台: ${BLUE}https://your-domain.com${NC}"
    echo -e "  API 接口: ${BLUE}https://your-domain.com/api${NC}"
    echo ""
    echo -e "  查看日志:     ${YELLOW}docker-compose logs -f${NC}"
    echo -e "  重启服务:     ${YELLOW}docker-compose restart${NC}"
    echo -e "  停止服务:     ${YELLOW}docker-compose down${NC}"
    echo -e "${GREEN}========================================${NC}"
}

# 主流程
main() {
    echo -e "${BLUE}"
    echo "  ╔═══════════════════════════════╗"
    echo "  ║   小魔推 Docker 部署脚本      ║"
    echo "  ╚═══════════════════════════════╝"
    echo -e "${NC}"

    check_dependencies
    check_env
    build_frontend
    setup_api_config
    start_services
    run_migrations
    optimize
    health_check
    print_summary
}

main "$@"
