<?php
// +----------------------------------------------------------------------
// | 路由设置
// +----------------------------------------------------------------------

return [
    // pathinfo分隔符
    'pathinfo_depr'         => '/',
    // URL伪静态后缀
    'url_html_suffix'       => 'html',
    // URL普通方式参数 用于自动生成
    'url_common_param'      => false,
    // URL参数方式 0 按名称成对解析 1 按顺序解析
    'url_param_type'        => 0,
    // 是否开启路由延迟解析
    'url_lazy_route'        => true,
    // 是否强制使用路由
    'url_route_must'        => false,
    // 合并路由规则
    'route_rule_merge'      => false,
    // 路由是否完全匹配
    'route_complete_match'  => false,
    // 使用注解路由
    'route_annotation'      => false,
    // 域名根
    'url_domain_root'       => '',
    // 是否自动转换URL中的控制器和操作名
    'url_convert'           => true,
    // 默认的路由变量规则
    'default_route_pattern' => '[\w\.]+',
    // 是否开启请求缓存 true自动缓存 支持设置请求缓存规则
    'request_cache'         => false,
    // 请求缓存有效期
    'request_cache_expire'  => null,
    // 全局请求缓存排除规则
    'request_cache_except'  => [],
    // 是否开启路由跨域请求
    'route_cross_domain'    => true,

    // 默认全局过滤方法 用逗号分隔多个
    'default_filter'        => '',
    // 域名绑定（自动）
    'domain_bind'           => [],
    // 域名绑定到模块的参数
    'domain_params'         => [],
    // 禁止URL访问的应用列表
    'deny_app_list'         => [],

    // 异常处理handle类 留空使用 \think\exception\Handle
    'exception_handle'      => '',

];