FROM mysql:8.0

# 设置时区
ENV TZ=Asia/Shanghai

# 复制初始化 SQL 脚本
# MySQL 容器首次启动时会自动执行 /docker-entrypoint-initdb.d/ 目录下的 .sql 文件
COPY init.sql /docker-entrypoint-initdb.d/

# 设置字符集
COPY my.cnf /etc/mysql/conf.d/my.cnf
