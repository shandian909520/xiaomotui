<?php
// 应用公共函数文件

if (!function_exists('success')) {
    /**
     * 成功响应
     */
    function success($data = null, $msg = 'success', $code = 200)
    {
        return json([
            'code' => $code,
            'msg' => $msg,
            'data' => $data,
            'timestamp' => time()
        ]);
    }
}

if (!function_exists('error')) {
    /**
     * 错误响应
     */
    function error($msg = 'error', $code = 400, $data = null)
    {
        return json([
            'code' => $code,
            'msg' => $msg,
            'data' => $data,
            'timestamp' => time()
        ]);
    }
}

if (!function_exists('generate_order_no')) {
    /**
     * 生成订单号
     */
    function generate_order_no($prefix = '')
    {
        return $prefix . date('YmdHis') . sprintf('%06d', mt_rand(0, 999999));
    }
}

if (!function_exists('format_bytes')) {
    /**
     * 格式化字节大小
     */
    function format_bytes($size, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

        for ($i = 0; $size >= 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }

        return round($size, $precision) . ' ' . $units[$i];
    }
}

if (!function_exists('mask_string')) {
    /**
     * 字符串脱敏
     */
    function mask_string($string, $start = 3, $end = 4, $mask = '*')
    {
        $len = mb_strlen($string, 'UTF-8');

        if ($len <= $start + $end) {
            return str_repeat($mask, $len);
        }

        return mb_substr($string, 0, $start, 'UTF-8')
            . str_repeat($mask, $len - $start - $end)
            . mb_substr($string, -$end, null, 'UTF-8');
    }
}

if (!function_exists('generate_random_string')) {
    /**
     * 生成随机字符串
     */
    function generate_random_string($length = 10, $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789')
    {
        $str = '';
        $max = strlen($chars) - 1;

        for ($i = 0; $i < $length; $i++) {
            $str .= $chars[mt_rand(0, $max)];
        }

        return $str;
    }
}

if (!function_exists('is_mobile')) {
    /**
     * 验证手机号
     */
    function is_mobile($mobile)
    {
        return preg_match('/^1[3456789]\d{9}$/', $mobile);
    }
}

if (!function_exists('get_client_ip')) {
    /**
     * 获取客户端IP
     */
    function get_client_ip()
    {
        static $ip = null;

        if ($ip !== null) {
            return $ip;
        }

        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $pos = array_search('unknown', $arr);
            if (false !== $pos) {
                unset($arr[$pos]);
            }
            $ip = trim($arr[0]);
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        // IP地址合法验证
        $ip = (false !== ip2long($ip)) ? $ip : '0.0.0.0';

        return $ip;
    }
}

if (!function_exists('time_ago')) {
    /**
     * 时间距离现在多久前
     */
    function time_ago($time)
    {
        $time = is_numeric($time) ? $time : strtotime($time);
        $diff = time() - $time;

        if ($diff < 60) {
            return '刚刚';
        } elseif ($diff < 3600) {
            return floor($diff / 60) . '分钟前';
        } elseif ($diff < 86400) {
            return floor($diff / 3600) . '小时前';
        } elseif ($diff < 2592000) {
            return floor($diff / 86400) . '天前';
        } elseif ($diff < 31536000) {
            return floor($diff / 2592000) . '个月前';
        } else {
            return floor($diff / 31536000) . '年前';
        }
    }
}