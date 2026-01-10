#!/bin/bash
# 小魔推API测试运行脚本 - Linux/Mac

echo "====================================="
echo "小魔推API测试套件"
echo "====================================="
echo ""

# 检查composer依赖
if [ ! -f "vendor/autoload.php" ]; then
    echo "[错误] 未找到vendor目录，请先运行: composer install"
    exit 1
fi

# 检查PHPUnit
if [ ! -f "vendor/bin/phpunit" ]; then
    echo "[错误] 未找到PHPUnit，请先运行: composer install"
    exit 1
fi

# 设置测试环境
export APP_ENV=testing
export APP_DEBUG=true

# 解析参数
if [ -z "$1" ]; then
    echo "[运行] 运行所有测试..."
    ./vendor/bin/phpunit
elif [ "$1" = "auth" ]; then
    echo "[运行] 运行认证测试..."
    ./vendor/bin/phpunit tests/api/AuthTest.php
elif [ "$1" = "coverage" ]; then
    echo "[运行] 生成测试覆盖率报告..."
    ./vendor/bin/phpunit --coverage-html tests/coverage
    echo ""
    echo "覆盖率报告已生成到: tests/coverage/index.html"
elif [ "$1" = "filter" ]; then
    echo "[运行] 运行指定测试: $2"
    ./vendor/bin/phpunit --filter "$2"
else
    echo "[运行] 运行指定文件: $1"
    ./vendor/bin/phpunit "$1"
fi

echo ""
echo "====================================="
echo "测试完成"
echo "====================================="
