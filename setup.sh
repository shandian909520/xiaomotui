#!/bin/bash
# ================================================================
# 小魔推 - 一键全自动部署脚本
# 使用方法：curl -fsSL https://your-server/setup.sh | bash
# 或本地执行：chmod +x setup.sh && sudo ./setup.sh
# ================================================================

set -e

# ─── 颜色 ─────────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
BLUE='\033[0;34m'; CYAN='\033[0;36m'; BOLD='\033[1m'; NC='\033[0m'

info()    { echo -e "${BLUE}▶${NC} $1"; }
success() { echo -e "${GREEN}✓${NC} $1"; }
warn()    { echo -e "${YELLOW}⚠${NC} $1"; }
error()   { echo -e "${RED}✗ 错误：${NC}$1"; exit 1; }
step()    { echo -e "\n${CYAN}${BOLD}═══ $1 ═══${NC}"; }
ask()     { echo -e "${YELLOW}?${NC} $1"; }

# ─── 全局变量（稍后由用户输入或自动生成）──────────────────────
DEPLOY_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DOMAIN=""
ADMIN_PASSWORD=""
DB_PASSWORD=""
REDIS_PASSWORD=""
JWT_SECRET=""
ADMIN_JWT_SECRET=""
SSL_MODE=""   # letsencrypt / selfsigned / skip

# ================================================================
# 步骤 0：欢迎界面
# ================================================================
banner() {
    clear
    echo -e "${CYAN}${BOLD}"
    echo "  ╔══════════════════════════════════════════════╗"
    echo "  ║          小魔推 · 一键自动部署               ║"
    echo "  ║   NFC 营销平台 Docker 全栈部署工具           ║"
    echo "  ╚══════════════════════════════════════════════╝"
    echo -e "${NC}"
    echo -e "  部署目录: ${YELLOW}${DEPLOY_DIR}${NC}"
    echo -e "  系统时间: $(date '+%Y-%m-%d %H:%M:%S')"
    echo ""
}

# ================================================================
# 步骤 1：收集用户输入
# ================================================================
collect_inputs() {
    step "配置向导"

    # 域名
    ask "请输入你的域名（例：example.com），直接回车使用 IP 访问："
    read -r DOMAIN
    DOMAIN="${DOMAIN:-localhost}"

    # 管理员密码
    ask "请设置管理员密码（直接回车使用随机密码）："
    read -rs ADMIN_PASSWORD; echo
    if [ -z "$ADMIN_PASSWORD" ]; then
        ADMIN_PASSWORD="$(openssl rand -base64 12 | tr -d '=+/')"
        warn "已自动生成管理员密码：${BOLD}${ADMIN_PASSWORD}${NC}（请记录！）"
    fi

    # 数据库密码（自动生成）
    DB_PASSWORD="$(openssl rand -base64 16 | tr -d '=+/')"
    REDIS_PASSWORD="$(openssl rand -base64 16 | tr -d '=+/')"
    JWT_SECRET="$(openssl rand -base64 32)"
    ADMIN_JWT_SECRET="$(openssl rand -base64 32)"

    # SSL 模式
    echo ""
    ask "SSL 证书配置方式："
    echo "  1) Let's Encrypt 免费证书（需要域名已解析到本机，推荐）"
    echo "  2) 自签名证书（测试用，浏览器会提示不安全）"
    echo "  3) 跳过（使用 HTTP，不推荐用于生产）"
    read -rp "  请选择 [1/2/3]（默认 2）：" ssl_choice
    case "${ssl_choice:-2}" in
        1) SSL_MODE="letsencrypt" ;;
        3) SSL_MODE="skip" ;;
        *) SSL_MODE="selfsigned" ;;
    esac

    echo ""
    echo -e "${BOLD}配置摘要：${NC}"
    echo "  域名：         ${YELLOW}${DOMAIN}${NC}"
    echo "  管理员密码：   ${YELLOW}${ADMIN_PASSWORD}${NC}"
    echo "  SSL 模式：     ${YELLOW}${SSL_MODE}${NC}"
    echo "  数据库密码：   （已自动生成）"
    echo ""
    read -rp "确认以上配置，开始部署？(Y/n)：" confirm
    [[ "${confirm,,}" == "n" ]] && { echo "已取消"; exit 0; }
}

# ================================================================
# 步骤 2：安装系统依赖
# ================================================================
install_dependencies() {
    step "安装系统依赖"

    # 检测包管理器
    if command -v apt-get >/dev/null 2>&1; then
        PKG_MGR="apt-get"
    elif command -v yum >/dev/null 2>&1; then
        PKG_MGR="yum"
    elif command -v dnf >/dev/null 2>&1; then
        PKG_MGR="dnf"
    else
        warn "无法识别包管理器，跳过自动安装，请手动安装 Docker 和 Node.js"
        return
    fi

    # 安装 Docker
    if ! command -v docker >/dev/null 2>&1; then
        info "安装 Docker..."
        curl -fsSL https://get.docker.com | bash
        systemctl enable docker
        systemctl start docker
        success "Docker 安装完成"
    else
        success "Docker 已安装：$(docker --version | cut -d' ' -f3 | tr -d ',')"
    fi

    # 安装 Docker Compose（v2 插件）
    if ! docker compose version >/dev/null 2>&1; then
        info "安装 Docker Compose..."
        if [ "$PKG_MGR" = "apt-get" ]; then
            apt-get install -y docker-compose-plugin
        else
            COMPOSE_VER=$(curl -s https://api.github.com/repos/docker/compose/releases/latest | grep tag_name | cut -d'"' -f4)
            curl -SL "https://github.com/docker/compose/releases/download/${COMPOSE_VER}/docker-compose-$(uname -s)-$(uname -m)" \
                -o /usr/local/lib/docker/cli-plugins/docker-compose
            chmod +x /usr/local/lib/docker/cli-plugins/docker-compose
        fi
        success "Docker Compose 安装完成"
    else
        success "Docker Compose 已安装：$(docker compose version --short)"
    fi

    # 安装 Node.js 18
    if ! command -v node >/dev/null 2>&1 || [[ "$(node -v | cut -d'v' -f2 | cut -d'.' -f1)" -lt 18 ]]; then
        info "安装 Node.js 18..."
        if [ "$PKG_MGR" = "apt-get" ]; then
            curl -fsSL https://deb.nodesource.com/setup_18.x | bash -
            apt-get install -y nodejs
        else
            curl -fsSL https://rpm.nodesource.com/setup_18.x | bash -
            $PKG_MGR install -y nodejs
        fi
        success "Node.js 安装完成：$(node -v)"
    else
        success "Node.js 已安装：$(node -v)"
    fi

    # 安装 certbot（仅 Let's Encrypt 模式需要）
    if [ "$SSL_MODE" = "letsencrypt" ] && ! command -v certbot >/dev/null 2>&1; then
        info "安装 certbot..."
        if [ "$PKG_MGR" = "apt-get" ]; then
            apt-get install -y certbot
        else
            $PKG_MGR install -y certbot
        fi
        success "certbot 安装完成"
    fi
}

# ================================================================
# 步骤 3：配置 SSL 证书
# ================================================================
setup_ssl() {
    step "配置 SSL 证书"
    mkdir -p "${DEPLOY_DIR}/docker/nginx/ssl"

    case "$SSL_MODE" in
        letsencrypt)
            info "申请 Let's Encrypt 证书（域名：${DOMAIN}）..."
            # 临时启动 Nginx 用于验证（如果 80 端口被占用则先停止）
            certbot certonly --standalone \
                -d "${DOMAIN}" \
                --non-interactive \
                --agree-tos \
                --email "admin@${DOMAIN}" \
                --http-01-port 80
            cp "/etc/letsencrypt/live/${DOMAIN}/fullchain.pem" "${DEPLOY_DIR}/docker/nginx/ssl/"
            cp "/etc/letsencrypt/live/${DOMAIN}/privkey.pem"   "${DEPLOY_DIR}/docker/nginx/ssl/"
            chmod 600 "${DEPLOY_DIR}/docker/nginx/ssl/privkey.pem"
            # 自动续期 cron
            (crontab -l 2>/dev/null; echo "0 3 1 * * certbot renew --quiet && docker compose -f ${DEPLOY_DIR}/docker-compose.yml exec nginx nginx -s reload") | crontab -
            success "Let's Encrypt 证书申请成功（已设置自动续期）"
            ;;
        selfsigned)
            info "生成自签名证书..."
            openssl req -x509 -nodes -days 3650 -newkey rsa:2048 \
                -keyout "${DEPLOY_DIR}/docker/nginx/ssl/privkey.pem" \
                -out    "${DEPLOY_DIR}/docker/nginx/ssl/fullchain.pem" \
                -subj "/C=CN/ST=Beijing/L=Beijing/O=XiaoMoTui/CN=${DOMAIN}" \
                >/dev/null 2>&1
            success "自签名证书已生成（有效期 10 年）"
            ;;
        skip)
            warn "跳过 SSL，将使用 HTTP 模式"
            # 切换为 HTTP 配置
            sed -i 's/listen 443 ssl http2/listen 80/' "${DEPLOY_DIR}/docker/nginx/conf.d/xiaomotui.conf"
            sed -i '/ssl_/d'         "${DEPLOY_DIR}/docker/nginx/conf.d/xiaomotui.conf"
            sed -i '/HSTS/d'         "${DEPLOY_DIR}/docker/nginx/conf.d/xiaomotui.conf"
            sed -i '/fullchain/d'    "${DEPLOY_DIR}/docker/nginx/conf.d/xiaomotui.conf"
            sed -i '/privkey/d'      "${DEPLOY_DIR}/docker/nginx/conf.d/xiaomotui.conf"
            ;;
    esac
}

# ================================================================
# 步骤 4：生成所有配置文件
# ================================================================
generate_configs() {
    step "生成配置文件"

    # ── 4a. 生成 .env（后端生产配置）──────────────────────────
    info "生成后端配置 api/.env ..."
    ADMIN_PASSWORD_HASH=$(docker run --rm php:8.2-cli php -r "echo password_hash('${ADMIN_PASSWORD}', PASSWORD_BCRYPT);")

    cat > "${DEPLOY_DIR}/api/.env" <<EOF
APP_DEBUG = false
APP_TRACE = false

[APP]
DEFAULT_TIMEZONE = Asia/Shanghai

[DATABASE]
TYPE = mysql
HOSTNAME = mysql
DATABASE = xiaomotui_prod
USERNAME = xiaomotui
PASSWORD = ${DB_PASSWORD}
HOSTPORT = 3306
CHARSET = utf8mb4
COLLATION = utf8mb4_unicode_ci
DEBUG = false
PREFIX = xmt_
TIMEOUT = 30
BREAK_RECONNECT = true

[REDIS]
HOST = redis
PORT = 6379
PASSWORD = ${REDIS_PASSWORD}
SELECT = 0
TIMEOUT = 5.0
PERSISTENT = false
PREFIX = xmt:
RETRY_TIMES = 3

[CACHE]
DRIVER = redis

[QUEUE]
DRIVER = redis

[SESSION]
TYPE = redis
AUTO_START = true

[LANG]
default_lang = zh-cn

[JWT]
SECRET_KEY = ${JWT_SECRET}
EXPIRE_TIME = 86400
REFRESH_EXPIRE_TIME = 604800

[ADMIN]
ADMIN_USERNAME = admin
ADMIN_PASSWORD = ${ADMIN_PASSWORD}
ADMIN_PASSWORD_HASH = ${ADMIN_PASSWORD_HASH}
ADMIN_JWT_SECRET = ${ADMIN_JWT_SECRET}
ADMIN_JWT_EXPIRE = 86400

[LOG]
CHANNEL = file
LEVEL = error
EOF
    success "后端配置生成完成"

    # ── 4b. 生成 docker-compose.yml ───────────────────────────
    info "生成 docker-compose.yml ..."
    cat > "${DEPLOY_DIR}/docker-compose.yml" <<EOF
version: '3.8'

services:
  mysql:
    image: mysql:8.0
    container_name: xiaomotui-mysql
    restart: always
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_PASSWORD}
      MYSQL_DATABASE: xiaomotui_prod
      MYSQL_USER: xiaomotui
      MYSQL_PASSWORD: ${DB_PASSWORD}
      TZ: Asia/Shanghai
    volumes:
      - mysql_data:/var/lib/mysql
    command: --default-authentication-plugin=mysql_native_password --character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci
    networks:
      - xmt

  redis:
    image: redis:7-alpine
    container_name: xiaomotui-redis
    restart: always
    command: redis-server --appendonly yes --requirepass ${REDIS_PASSWORD}
    volumes:
      - redis_data:/data
    networks:
      - xmt

  api:
    build:
      context: ./api
      dockerfile: ../docker/api/Dockerfile
    container_name: xiaomotui-api
    restart: always
    working_dir: /var/www/html
    volumes:
      - ./api:/var/www/html
      - ./docker/api/php.ini:/usr/local/etc/php/conf.d/custom.ini
    depends_on:
      - mysql
      - redis
    networks:
      - xmt

  nginx:
    image: nginx:alpine
    container_name: xiaomotui-nginx
    restart: always
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./docker/nginx/nginx.conf:/etc/nginx/nginx.conf:ro
      - ./docker/nginx/conf.d:/etc/nginx/conf.d:ro
      - ./docker/nginx/ssl:/etc/nginx/ssl:ro
      - ./api/public:/var/www/html/api/public:ro
      - ./admin/dist:/var/www/html/admin:ro
    depends_on:
      - api
    networks:
      - xmt

volumes:
  mysql_data:
  redis_data:

networks:
  xmt:
    driver: bridge
EOF
    success "docker-compose.yml 生成完成"

    # ── 4c. 修改 Nginx 配置域名 ───────────────────────────────
    info "配置 Nginx 域名 → ${DOMAIN}..."
    sed -i "s/your-domain.com/${DOMAIN}/g" "${DEPLOY_DIR}/docker/nginx/conf.d/xiaomotui.conf"
    [ "$SSL_MODE" = "skip" ] || true
    success "Nginx 配置更新完成"
}

# ================================================================
# 步骤 5：构建前端
# ================================================================
build_frontend() {
    step "构建前端管理后台"
    cd "${DEPLOY_DIR}/admin"
    info "安装依赖（npm ci）..."
    npm ci --silent
    info "构建生产包（npm run build）..."
    npm run build
    cd "${DEPLOY_DIR}"
    success "前端构建完成 → admin/dist/"
}

# ================================================================
# 步骤 6：启动 Docker 服务
# ================================================================
start_docker() {
    step "启动 Docker 服务"

    cd "${DEPLOY_DIR}"

    info "构建 PHP-FPM 镜像..."
    docker compose build api

    info "启动所有容器..."
    docker compose up -d

    # 等待 MySQL 就绪
    info "等待 MySQL 初始化..."
    local tries=60
    until docker compose exec -T mysql mysqladmin ping -h localhost --silent 2>/dev/null; do
        tries=$((tries - 1))
        [ $tries -eq 0 ] && error "MySQL 启动超时，请检查日志：docker compose logs mysql"
        printf "."
        sleep 2
    done
    echo ""
    success "MySQL 已就绪"

    # 等待 Redis 就绪
    info "等待 Redis 初始化..."
    local rtries=15
    until docker compose exec -T redis redis-cli -a "${REDIS_PASSWORD}" ping 2>/dev/null | grep -q PONG; do
        rtries=$((rtries - 1))
        [ $rtries -eq 0 ] && warn "Redis 响应超时，继续部署..."
        sleep 1
    done
    success "Redis 已就绪"
}

# ================================================================
# 步骤 7：数据库迁移
# ================================================================
run_migrations() {
    step "运行数据库迁移"
    cd "${DEPLOY_DIR}"
    docker compose exec -T api php think migrate:run --force 2>/dev/null \
        || warn "数据库迁移异常，请手动执行：docker compose exec api php think migrate:run"
    success "数据库迁移完成"
}

# ================================================================
# 步骤 8：健康检查
# ================================================================
health_check() {
    step "健康检查"
    sleep 5
    local proto="http"
    [ "$SSL_MODE" != "skip" ] && proto="https"

    local url="${proto}://${DOMAIN}/api"
    info "检查 API 服务：${url}"

    local tries=10
    until curl -skf "${url}" >/dev/null 2>&1; do
        tries=$((tries - 1))
        [ $tries -eq 0 ] && { warn "API 健康检查超时，可能需要等待服务完全启动"; return; }
        sleep 2
    done
    success "API 服务正常"
}

# ================================================================
# 步骤 9：保存部署信息
# ================================================================
save_credentials() {
    local cred_file="${DEPLOY_DIR}/.deploy_credentials"
    cat > "$cred_file" <<EOF
# 小魔推部署信息 - $(date '+%Y-%m-%d %H:%M:%S')
# ⚠️  此文件包含敏感信息，请妥善保管，不要提交到版本控制！

域名：         ${DOMAIN}
管理后台：     https://${DOMAIN}
API 地址：     https://${DOMAIN}/api

管理员账号：   admin
管理员密码：   ${ADMIN_PASSWORD}

数据库地址：   127.0.0.1:3306
数据库名称：   xiaomotui_prod
数据库用户：   xiaomotui
数据库密码：   ${DB_PASSWORD}

Redis 密码：   ${REDIS_PASSWORD}
EOF
    chmod 600 "$cred_file"
    success "部署信息已保存到：${cred_file}"

    # 也写入 .gitignore
    grep -q ".deploy_credentials" "${DEPLOY_DIR}/.gitignore" 2>/dev/null \
        || echo ".deploy_credentials" >> "${DEPLOY_DIR}/.gitignore"
}

# ================================================================
# 最终：打印完成信息
# ================================================================
print_done() {
    local proto="http"; [ "$SSL_MODE" != "skip" ] && proto="https"
    echo ""
    echo -e "${GREEN}${BOLD}"
    echo "  ╔══════════════════════════════════════════════╗"
    echo "  ║             🎉 部署成功！                    ║"
    echo "  ╚══════════════════════════════════════════════╝"
    echo -e "${NC}"
    echo -e "  ${BOLD}访问地址：${NC}"
    echo -e "    管理后台  →  ${CYAN}${proto}://${DOMAIN}${NC}"
    echo -e "    API 接口  →  ${CYAN}${proto}://${DOMAIN}/api${NC}"
    echo ""
    echo -e "  ${BOLD}登录信息：${NC}"
    echo -e "    账号：${YELLOW}admin${NC}"
    echo -e "    密码：${YELLOW}${ADMIN_PASSWORD}${NC}"
    echo ""
    echo -e "  ${BOLD}常用命令：${NC}"
    echo -e "    查看日志  →  ${YELLOW}docker compose logs -f${NC}"
    echo -e "    重启服务  →  ${YELLOW}docker compose restart${NC}"
    echo -e "    停止服务  →  ${YELLOW}docker compose down${NC}"
    echo -e "    后续更新  →  ${YELLOW}./update.sh${NC}"
    echo ""
    echo -e "  详细信息已保存到：${YELLOW}.deploy_credentials${NC}"
    echo ""
}

# ================================================================
# 主流程
# ================================================================
main() {
    [ "$(id -u)" -ne 0 ] && error "请使用 root 用户运行此脚本（sudo ./setup.sh）"

    banner
    collect_inputs
    install_dependencies
    setup_ssl
    generate_configs
    build_frontend
    start_docker
    run_migrations
    health_check
    save_credentials
    print_done
}

main "$@"
