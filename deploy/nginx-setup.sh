#!/bin/bash

###############################################################################
# Nginx快速安装和配置脚本
# 项目：小魔推
# 用途：自动化部署Nginx配置
# 使用：sudo bash nginx-setup.sh
###############################################################################

set -e

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 日志函数
log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# 检查是否为root用户
check_root() {
    if [[ $EUID -ne 0 ]]; then
        log_error "此脚本需要root权限运行"
        echo "请使用: sudo bash $0"
        exit 1
    fi
}

# 检测操作系统
detect_os() {
    if [[ -f /etc/os-release ]]; then
        . /etc/os-release
        OS=$ID
        VER=$VERSION_ID
    else
        log_error "无法检测操作系统"
        exit 1
    fi

    log_info "检测到操作系统: $OS $VER"
}

# 安装Nginx
install_nginx() {
    log_info "开始安装Nginx..."

    if command -v nginx &> /dev/null; then
        log_warn "Nginx已安装，版本: $(nginx -v 2>&1 | cut -d'/' -f2)"
        read -p "是否重新安装? (y/N) " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            return 0
        fi
    fi

    case $OS in
        centos|rhel|fedora)
            # 添加Nginx官方源
            cat > /etc/yum.repos.d/nginx.repo << 'EOF'
[nginx-stable]
name=nginx stable repo
baseurl=http://nginx.org/packages/centos/$releasever/$basearch/
gpgcheck=1
enabled=1
gpgkey=https://nginx.org/keys/nginx_signing.key
module_hotfixes=true
EOF
            yum install -y nginx
            ;;

        ubuntu|debian)
            apt update
            apt install -y curl gnupg2 ca-certificates lsb-release ubuntu-keyring

            curl https://nginx.org/keys/nginx_signing.key | gpg --dearmor \
                | tee /usr/share/keyrings/nginx-archive-keyring.gpg >/dev/null

            echo "deb [signed-by=/usr/share/keyrings/nginx-archive-keyring.gpg] \
http://nginx.org/packages/ubuntu $(lsb_release -cs) nginx" \
                | tee /etc/apt/sources.list.d/nginx.list

            apt update
            apt install -y nginx
            ;;

        *)
            log_error "不支持的操作系统: $OS"
            exit 1
            ;;
    esac

    log_info "Nginx安装完成: $(nginx -v 2>&1)"
}

# 创建目录结构
create_directories() {
    log_info "创建目录结构..."

    # 创建网站根目录
    mkdir -p /var/www/xiaomotui/{api/public,admin/dist,h5/dist}

    # 创建日志目录
    mkdir -p /var/log/nginx

    # 创建SSL证书目录
    mkdir -p /etc/nginx/ssl

    # 创建Let's Encrypt验证目录
    mkdir -p /var/www/letsencrypt

    # 设置权限
    chown -R nginx:nginx /var/www/xiaomotui 2>/dev/null || \
    chown -R www-data:www-data /var/www/xiaomotui

    chmod -R 755 /var/www/xiaomotui

    log_info "目录创建完成"
}

# 生成自签名证书（开发环境）
generate_ssl_cert() {
    log_info "生成自签名SSL证书..."

    DOMAINS=("api.xiaomotui.com" "admin.xiaomotui.com" "h5.xiaomotui.com" "default")

    for domain in "${DOMAINS[@]}"; do
        if [[ -f "/etc/nginx/ssl/${domain}.crt" ]]; then
            log_warn "证书已存在: ${domain}.crt"
            continue
        fi

        openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
            -keyout /etc/nginx/ssl/${domain}.key \
            -out /etc/nginx/ssl/${domain}.crt \
            -subj "/C=CN/ST=Beijing/L=Beijing/O=XiaoMoTui/CN=${domain}" \
            2>/dev/null

        log_info "生成证书: ${domain}.crt"
    done

    # 设置权限
    chmod 600 /etc/nginx/ssl/*.key
    chmod 644 /etc/nginx/ssl/*.crt

    log_info "SSL证书生成完成"
}

# 部署Nginx配置
deploy_nginx_config() {
    log_info "部署Nginx配置..."

    # 备份原配置
    if [[ -f /etc/nginx/nginx.conf ]]; then
        cp /etc/nginx/nginx.conf /etc/nginx/nginx.conf.backup.$(date +%Y%m%d_%H%M%S)
        log_info "原配置已备份"
    fi

    # 复制新配置
    SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

    if [[ -f "${SCRIPT_DIR}/nginx.conf" ]]; then
        cp "${SCRIPT_DIR}/nginx.conf" /etc/nginx/nginx.conf
        log_info "配置文件已部署"
    else
        log_error "找不到nginx.conf文件"
        exit 1
    fi

    # 测试配置
    log_info "测试Nginx配置..."
    if nginx -t; then
        log_info "配置测试通过"
    else
        log_error "配置测试失败，请检查配置文件"
        exit 1
    fi
}

# 配置防火墙
configure_firewall() {
    log_info "配置防火墙..."

    if command -v firewall-cmd &> /dev/null; then
        # FirewallD (CentOS/RHEL)
        firewall-cmd --permanent --add-service=http
        firewall-cmd --permanent --add-service=https
        firewall-cmd --reload
        log_info "FirewallD规则已添加"
    elif command -v ufw &> /dev/null; then
        # UFW (Ubuntu)
        ufw allow 80/tcp
        ufw allow 443/tcp
        log_info "UFW规则已添加"
    else
        log_warn "未检测到防火墙，请手动开放80和443端口"
    fi
}

# 优化系统参数
optimize_system() {
    log_info "优化系统参数..."

    # 备份原配置
    if [[ -f /etc/sysctl.conf ]]; then
        cp /etc/sysctl.conf /etc/sysctl.conf.backup.$(date +%Y%m%d_%H%M%S)
    fi

    # 添加优化参数
    cat >> /etc/sysctl.conf << 'EOF'

# Nginx性能优化参数
fs.file-max = 65535
net.ipv4.tcp_tw_reuse = 1
net.ipv4.tcp_fin_timeout = 30
net.ipv4.tcp_keepalive_time = 1200
net.ipv4.tcp_max_syn_backlog = 8192
net.core.somaxconn = 8192
EOF

    # 应用配置
    sysctl -p >/dev/null 2>&1

    log_info "系统参数优化完成"
}

# 配置日志轮转
configure_logrotate() {
    log_info "配置日志轮转..."

    cat > /etc/logrotate.d/nginx << 'EOF'
/var/log/nginx/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 0640 nginx adm
    sharedscripts
    postrotate
        [ -f /var/run/nginx.pid ] && kill -USR1 `cat /var/run/nginx.pid`
    endscript
}
EOF

    log_info "日志轮转配置完成"
}

# 启动Nginx
start_nginx() {
    log_info "启动Nginx服务..."

    # 启用并启动服务
    systemctl enable nginx
    systemctl restart nginx

    if systemctl is-active --quiet nginx; then
        log_info "Nginx服务已启动"
    else
        log_error "Nginx服务启动失败"
        systemctl status nginx
        exit 1
    fi
}

# 显示状态信息
show_status() {
    echo ""
    echo "=========================================="
    echo "Nginx安装配置完成！"
    echo "=========================================="
    echo ""
    echo "服务状态："
    systemctl status nginx --no-pager | grep -E "Active|Main PID"
    echo ""
    echo "监听端口："
    netstat -tlnp | grep nginx || ss -tlnp | grep nginx
    echo ""
    echo "配置文件："
    echo "  - 主配置: /etc/nginx/nginx.conf"
    echo "  - 网站目录: /var/www/xiaomotui/"
    echo "  - SSL证书: /etc/nginx/ssl/"
    echo "  - 日志目录: /var/log/nginx/"
    echo ""
    echo "常用命令："
    echo "  - 测试配置: nginx -t"
    echo "  - 重载配置: nginx -s reload"
    echo "  - 查看状态: systemctl status nginx"
    echo "  - 查看日志: tail -f /var/log/nginx/access.log"
    echo ""
    echo "访问地址："
    echo "  - API: https://api.xiaomotui.com"
    echo "  - 管理后台: https://admin.xiaomotui.com"
    echo "  - H5页面: https://h5.xiaomotui.com"
    echo ""
    echo "注意事项："
    echo "  1. 当前使用自签名证书，生产环境请使用Let's Encrypt"
    echo "  2. 请确保后端ThinkPHP服务运行在8000端口"
    echo "  3. 请配置DNS解析指向本服务器"
    echo "  4. 详细文档请查看: deploy/NGINX_SETUP.md"
    echo ""
    echo "=========================================="
}

# 主函数
main() {
    echo "=========================================="
    echo "Nginx自动化安装配置脚本"
    echo "项目：小魔推"
    echo "=========================================="
    echo ""

    check_root
    detect_os

    # 执行安装步骤
    install_nginx
    create_directories
    generate_ssl_cert
    deploy_nginx_config
    configure_firewall
    optimize_system
    configure_logrotate
    start_nginx

    # 显示状态
    show_status
}

# 运行主函数
main "$@"
