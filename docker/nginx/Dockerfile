FROM nginx:alpine

# 安装必要工具
RUN apk add --no-cache tzdata

# 设置时区
ENV TZ=Asia/Shanghai

# 复制 nginx 配置
COPY nginx.conf /etc/nginx/nginx.conf
COPY conf.d /etc/nginx/conf.d

# 创建必要目录
RUN mkdir -p /var/www/html/api /var/www/html/admin /var/log/nginx

# 设置权限
RUN chown -R nginx:nginx /var/www/html /var/log/nginx

EXPOSE 80 443

CMD ["nginx", "-g", "daemon off;"]
