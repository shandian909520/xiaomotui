#!/bin/bash

# 通知服务模块测试脚本
# 测试所有告警和通知相关接口

BASE_URL="http://localhost:8001"
TOKEN=""

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 测试计数器
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0

# 测试结果数组
declare -a FAILED_TESTS_LIST

# 打印函数
print_header() {
    echo -e "\n${GREEN}========================================${NC}"
    echo -e "${GREEN}$1${NC}"
    echo -e "${GREEN}========================================${NC}\n"
}

print_test() {
    echo -e "${YELLOW}测试: $1${NC}"
    ((TOTAL_TESTS++))
}

print_success() {
    echo -e "${GREEN}✓ 通过: $1${NC}\n"
    ((PASSED_TESTS++))
}

print_error() {
    echo -e "${RED}✗ 失败: $1${NC}"
    echo -e "${RED}响应: $2${NC}\n"
    ((FAILED_TESTS++))
    FAILED_TESTS_LIST+=("$1")
}

# 登录获取token
login_admin() {
    print_header "1. 登录获取Token"

    local response=$(curl -s -X POST "${BASE_URL}/api/auth/login" \
        -H "Content-Type: application/json" \
        -d '{"username":"admin","password":"admin123"}')

    TOKEN=$(echo $response | grep -o '"token":"[^"]*"' | cut -d'"' -f4)

    if [ -n "$TOKEN" ]; then
        print_success "管理员登录成功"
        echo "Token: ${TOKEN:0:50}..."
    else
        print_error "管理员登录失败" "$response"
        exit 1
    fi
}

# 测试告警列表
test_alert_list() {
    print_test "获取告警列表"

    local response=$(curl -s -X GET "${BASE_URL}/api/alert/list?token=${TOKEN}" \
        -H "Content-Type: application/json")

    local code=$(echo $response | grep -o '"code":[0-9]*' | cut -d':' -f2)

    if [ "$code" == "200" ]; then
        print_success "告警列表获取成功"
        echo "响应: $response" | head -c 200
    else
        print_error "告警列表获取失败" "$response"
    fi
}

# 测试告警详情
test_alert_detail() {
    print_test "获取告警详情(ID: 1)"

    local response=$(curl -s -X GET "${BASE_URL}/api/alert/1?token=${TOKEN}" \
        -H "Content-Type: application/json")

    local code=$(echo $response | grep -o '"code":[0-9]*' | cut -d':' -f2)

    if [ "$code" == "200" ] || [ "$code" == "404" ]; then
        print_success "告警详情接口响应正常"
    else
        print_error "告警详情获取失败" "$response"
    fi
}

# 测试告警统计
test_alert_stats() {
    print_test "获取告警统计"

    local response=$(curl -s -X GET "${BASE_URL}/api/alert/stats?token=${TOKEN}&merchant_id=1" \
        -H "Content-Type: application/json")

    local code=$(echo $response | grep -o '"code":[0-9]*' | cut -d':' -f2)

    if [ "$code" == "200" ]; then
        print_success "告警统计获取成功"
    else
        print_error "告警统计获取失败" "$response"
    fi
}

# 测试手动检查
test_manual_check() {
    print_test "手动触发告警检查"

    local response=$(curl -s -X POST "${BASE_URL}/api/alert/check?token=${TOKEN}" \
        -H "Content-Type: application/json" \
        -d '{}')

    local code=$(echo $response | grep -o '"code":[0-9]*' | cut -d':' -f2)

    if [ "$code" == "200" ]; then
        print_success "手动检查触发成功"
    else
        print_error "手动检查触发失败" "$response"
    fi
}

# 测试告警规则列表
test_alert_rules() {
    print_test "获取告警规则列表"

    local response=$(curl -s -X GET "${BASE_URL}/api/alert/rules?token=${TOKEN}&merchant_id=1" \
        -H "Content-Type: application/json")

    local code=$(echo $response | grep -o '"code":[0-9]*' | cut -d':' -f2)

    if [ "$code" == "200" ]; then
        print_success "告警规则列表获取成功"
    else
        print_error "告警规则列表获取失败" "$response"
    fi
}

# 测试告警规则模板
test_alert_rule_templates() {
    print_test "获取告警规则模板"

    local response=$(curl -s -X GET "${BASE_URL}/api/alert/rules/templates?token=${TOKEN}" \
        -H "Content-Type: application/json")

    local code=$(echo $response | grep -o '"code":[0-9]*' | cut -d':' -f2)

    if [ "$code" == "200" ]; then
        print_success "告警规则模板获取成功"
    else
        print_error "告警规则模板获取失败" "$response"
    fi
}

# 测试系统通知列表
test_notifications() {
    print_test "获取系统通知列表"

    local response=$(curl -s -X GET "${BASE_URL}/api/alert/notifications?token=${TOKEN}&merchant_id=1" \
        -H "Content-Type: application/json")

    local code=$(echo $response | grep -o '"code":[0-9]*' | cut -d':' -f2)

    if [ "$code" == "200" ]; then
        print_success "系统通知列表获取成功"
    else
        print_error "系统通知列表获取失败" "$response"
    fi
}

# 测试批量操作(无告警数据)
test_batch_action() {
    print_test "批量操作告警(空列表)"

    local response=$(curl -s -X POST "${BASE_URL}/api/alert/batch-action?token=${TOKEN}" \
        -H "Content-Type: application/json" \
        -d '{"alert_ids":[],"action":"resolve","user_id":1}')

    local code=$(echo $response | grep -o '"code":[0-9]*' | cut -d':' -f2)

    if [ "$code" == "200" ]; then
        print_success "批量操作接口响应正常"
    else
        print_error "批量操作接口失败" "$response"
    fi
}

# 测试应用规则模板
test_apply_template() {
    print_test "应用告警规则模板"

    local response=$(curl -s -X POST "${BASE_URL}/api/alert/rules/apply-template?token=${TOKEN}" \
        -H "Content-Type: application/json" \
        -d '{"merchant_id":1,"template":"basic"}')

    local code=$(echo $response | grep -o '"code":[0-9]*' | cut -d':' -f2)

    if [ "$code" == "200" ]; then
        print_success "应用规则模板成功"
    else
        print_error "应用规则模板失败" "$response"
    fi
}

# 测试管理员告警监控状态
test_monitor_status() {
    print_test "获取告警监控状态"

    local response=$(curl -s -X GET "${BASE_URL}/admin/alert-monitor/status?token=${TOKEN}" \
        -H "Content-Type: application/json")

    local code=$(echo $response | grep -o '"code":[0-9]*' | cut -d':' -f2)

    if [ "$code" == "200" ]; then
        print_success "告警监控状态获取成功"
    else
        print_error "告警监控状态获取失败" "$response"
    fi
}

# 测试运行监控任务
test_run_monitor() {
    print_test "运行告警监控任务"

    local response=$(curl -s -X POST "${BASE_URL}/admin/alert-monitor/run?token=${TOKEN}" \
        -H "Content-Type: application/json")

    local code=$(echo $response | grep -o '"code":[0-9]*' | cut -d':' -f2)

    if [ "$code" == "200" ]; then
        print_success "监控任务运行成功"
    else
        print_error "监控任务运行失败" "$response"
    fi
}

# 测试清理任务
test_cleanup_task() {
    print_test "运行清理任务"

    local response=$(curl -s -X POST "${BASE_URL}/admin/alert-monitor/cleanup?token=${TOKEN}" \
        -H "Content-Type: application/json")

    local code=$(echo $response | grep -o '"code":[0-9]*' | cut -d':' -f2)

    if [ "$code" == "200" ]; then
        print_success "清理任务运行成功"
    else
        print_error "清理任务运行失败" "$response"
    fi
}

# 测试统计任务
test_stats_task() {
    print_test "运行统计任务"

    local response=$(curl -s -X POST "${BASE_URL}/admin/alert-monitor/stats?token=${TOKEN}" \
        -H "Content-Type: application/json")

    local code=$(echo $response | grep -o '"code":[0-9]*' | cut -d':' -f2)

    if [ "$code" == "200" ]; then
        print_success "统计任务运行成功"
    else
        print_error "统计任务运行失败" "$response"
    fi
}

# 主测试流程
main() {
    print_header "通知服务模块功能测试"

    # 登录
    login_admin

    # 告警管理接口测试
    print_header "2. 告警管理接口测试"
    test_alert_list
    test_alert_detail
    test_alert_stats
    test_manual_check
    test_batch_action

    # 告警规则管理接口测试
    print_header "3. 告警规则管理接口测试"
    test_alert_rules
    test_alert_rule_templates
    test_apply_template

    # 通知管理接口测试
    print_header "4. 通知管理接口测试"
    test_notifications

    # 管理员监控接口测试
    print_header "5. 管理员监控接口测试"
    test_monitor_status
    test_run_monitor
    test_cleanup_task
    test_stats_task

    # 打印测试总结
    print_header "测试总结"
    echo -e "总测试数: $TOTAL_TESTS"
    echo -e "${GREEN}通过: $PASSED_TESTS${NC}"
    echo -e "${RED}失败: $FAILED_TESTS${NC}"

    if [ $FAILED_TESTS -gt 0 ]; then
        echo -e "\n${RED}失败的测试:${NC}"
        for test in "${FAILED_TESTS_LIST[@]}"; do
            echo -e "${RED}  - $test${NC}"
        done
    fi

    echo -e "\n测试完成!"
}

# 运行主函数
main
