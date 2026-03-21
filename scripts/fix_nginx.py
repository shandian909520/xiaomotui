import paramiko
import io
import time

password = r'Dear19840520!!!'
client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())

try:
    client.connect('47.113.226.37', username='root', password=password, timeout=30)
    print('SSH connected!')

    # 修改 Nginx 配置 - 根路径使用 try_files
    nginx_config = '''# HTTP 网站
server {
    listen 80;
    server_name localhost;

    root /var/www/html/admin;
    index index.html;

    # 前端管理后台
    location /admin {
        alias /var/www/html/admin;
        try_files $uri $uri/ /admin/index.html;
    }

    # 静态资源路径
    location ^~ /assets/ {
        alias /var/www/html/admin/assets/;
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    location ^~ /static/ {
        alias /var/www/html/admin/static/;
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # 根路径 - 使用 try_files 返回 admin/index.html
    location = / {
        try_files /index.html =404;
    }

    # API 路由 - 转发 /api 请求到 index.php
    location /api {
        rewrite ^(.*)$ /index.php?s=$1 last;
    }

    # PHP 处理
    location ~ \.php$ {
        fastcgi_pass api:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME /var/www/html/public$fastcgi_script_name;
        include fastcgi_params;
    }

    # 静态资源缓存（优先级较低）
    location ~* \.(jpg|jpeg|png|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # 禁止访问隐藏文件
    location ~ /\. {
        deny all;
    }

    # 日志
    access_log /var/log/nginx/xiaomotui-access.log;
    error_log /var/log/nginx/xiaomotui-error.log;
}
'''

    # 上传配置
    sftp = client.open_sftp()
    sftp.putfo(io.BytesIO(nginx_config.encode('utf-8')), '/tmp/xiaomotui.conf')
    sftp.close()
    print('Uploaded nginx config')

    # 复制到容器
    stdin, stdout, stderr = client.exec_command('docker cp /tmp/xiaomotui.conf xiaomotui-nginx:/etc/nginx/conf.d/xiaomotui.conf')
    print('Copied config')

    # 测试配置
    stdin, stdout, stderr = client.exec_command('docker exec xiaomotui-nginx nginx -t')
    output = stdout.read().decode('utf-8')
    error = stderr.read().decode('utf-8')
    print(f'Nginx 测试：{output}')
    if error:
        print(f'Nginx 错误：{error}')

    # 重启 Nginx
    client.exec_command('docker restart xiaomotui-nginx')
    print('Restarted nginx')

    time.sleep(3)

    # 测试
    stdin, stdout, stderr = client.exec_command('curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/')
    status = stdout.read().decode('utf-8').strip()
    print(f'根路径状态码：{status}')

    stdin, stdout, stderr = client.exec_command('curl -sL http://localhost:8080/ | head -3')
    print(f'根路径内容：{stdout.read().decode()[:100]}')

    stdin, stdout, stderr = client.exec_command('curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/admin/')
    status2 = stdout.read().decode('utf-8').strip()
    print(f'/admin/ 状态码：{status2}')

    # 测试静态资源
    stdin, stdout, stderr = client.exec_command('curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/assets/index-C66ciw83.css')
    status3 = stdout.read().decode('utf-8').strip()
    print(f'/assets/ 状态码：{status3}')

    # 测试短信 API
    stdin, stdout, stderr = client.exec_command('curl -s -X POST http://localhost:8080/api/common/sms/send -d "phone=13800138000"')
    print(f'短信 API: {stdout.read().decode()[:100]}')

    client.close()
    print('Done!')
except Exception as e:
    print(f'Error: {e}')
