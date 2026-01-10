#!/bin/bash

# 端到端测试运行脚本 (Linux/Mac)
# 用法: ./run_e2e.sh

echo "========================================"
echo "端到端测试执行脚本"
echo "========================================"
echo ""

# 检查PHP是否可用
if ! command -v php &> /dev/null; then
    echo "错误: 未找到PHP命令"
    echo "请确保PHP已安装并添加到PATH环境变量"
    exit 1
fi

# 显示PHP版本
echo "检查PHP版本..."
php -v
echo ""

# 切换到脚本所在目录
cd "$(dirname "$0")"

# 检查配置文件
if [ ! -f "config.php" ]; then
    echo "错误: 未找到配置文件 config.php"
    exit 1
fi

# 创建报告目录
if [ ! -d "reports" ]; then
    echo "创建报告目录..."
    mkdir -p reports
fi

# 运行测试
echo "========================================"
echo "开始执行端到端测试..."
echo "========================================"
echo ""

php full_flow.php

# 保存退出码
TEST_EXIT_CODE=$?

echo ""
echo "========================================"
if [ $TEST_EXIT_CODE -eq 0 ]; then
    echo "测试完成: 成功"
else
    echo "测试完成: 失败 (退出码: $TEST_EXIT_CODE)"
fi
echo "========================================"
echo ""

# 询问是否查看最新报告
read -p "是否查看测试报告? (y/n): " VIEW_REPORT

if [[ "$VIEW_REPORT" =~ ^[Yy]$ ]]; then
    # 查找最新的报告文件
    LATEST_REPORT=$(ls -t reports/e2e_test_report_*.txt 2>/dev/null | head -n 1)

    if [ -n "$LATEST_REPORT" ]; then
        echo ""
        echo "显示报告: $LATEST_REPORT"
        echo "========================================"
        cat "$LATEST_REPORT"
    else
        echo "未找到测试报告文件"
    fi
fi

echo ""
exit $TEST_EXIT_CODE
