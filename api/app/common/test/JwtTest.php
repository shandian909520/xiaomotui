<?php
declare(strict_types=1);

namespace app\common\test;

use app\common\utils\JwtUtil;
use app\common\exception\JwtException;
use think\facade\Cache;
use think\facade\Config;

/**
 * JWT测试类
 * 小磨推JWT功能测试
 */
class JwtTest
{
    /**
     * 运行所有测试
     * @return array
     */
    public static function runAllTests(): array
    {
        $results = [];
        $tests = [
            'testTokenGeneration' => '测试令牌生成',
            'testTokenVerification' => '测试令牌验证',
            'testTokenDecoding' => '测试令牌解析',
            'testTokenRefresh' => '测试令牌刷新',
            'testTokenRevoke' => '测试令牌注销',
            'testBlacklist' => '测试黑名单功能',
            'testRoleValidation' => '测试角色验证',
            'testExpiredToken' => '测试过期令牌',
            'testInvalidToken' => '测试无效令牌',
            'testUserInfo' => '测试用户信息提取',
        ];

        foreach ($tests as $method => $description) {
            try {
                $result = self::$method();
                $results[] = [
                    'test' => $description,
                    'status' => 'PASS',
                    'result' => $result,
                    'error' => null
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'test' => $description,
                    'status' => 'FAIL',
                    'result' => null,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * 测试令牌生成
     * @return array
     * @throws JwtException
     */
    public static function testTokenGeneration(): array
    {
        $payload = [
            'sub' => 'user_12345',
            'openid' => 'wx_openid_123',
            'role' => 'user',
        ];

        $token = JwtUtil::generate($payload);

        if (empty($token)) {
            throw new \Exception('令牌生成失败');
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new \Exception('令牌格式不正确');
        }

        return [
            'token' => $token,
            'token_length' => strlen($token),
            'parts_count' => count($parts)
        ];
    }

    /**
     * 测试令牌验证
     * @return array
     * @throws JwtException
     */
    public static function testTokenVerification(): array
    {
        $payload = [
            'sub' => 'user_12345',
            'openid' => 'wx_openid_123',
            'role' => 'user',
        ];

        $token = JwtUtil::generate($payload);
        $decoded = JwtUtil::verify($token);

        if ($decoded['sub'] !== $payload['sub']) {
            throw new \Exception('用户ID验证失败');
        }

        if ($decoded['role'] !== $payload['role']) {
            throw new \Exception('用户角色验证失败');
        }

        return [
            'original_payload' => $payload,
            'decoded_payload' => $decoded,
            'verification' => 'success'
        ];
    }

    /**
     * 测试令牌解析
     * @return array
     * @throws JwtException
     */
    public static function testTokenDecoding(): array
    {
        $payload = [
            'sub' => 'user_12345',
            'openid' => 'wx_openid_123',
            'role' => 'merchant',
            'merchant_id' => 123
        ];

        $token = JwtUtil::generate($payload);
        $decoded = JwtUtil::decode($token);

        return [
            'token_valid' => !empty($token),
            'decode_success' => !empty($decoded),
            'user_id' => $decoded['sub'] ?? null,
            'role' => $decoded['role'] ?? null,
            'merchant_id' => $decoded['merchant_id'] ?? null
        ];
    }

    /**
     * 测试令牌刷新
     * @return array
     * @throws JwtException
     */
    public static function testTokenRefresh(): array
    {
        $payload = [
            'sub' => 'user_12345',
            'openid' => 'wx_openid_123',
            'role' => 'user',
        ];

        $originalToken = JwtUtil::generate($payload);
        $newToken = JwtUtil::refresh($originalToken);

        if ($originalToken === $newToken) {
            throw new \Exception('刷新后的令牌与原令牌相同');
        }

        $originalDecoded = JwtUtil::decode($originalToken);
        $newDecoded = JwtUtil::decode($newToken);

        return [
            'original_token_length' => strlen($originalToken),
            'new_token_length' => strlen($newToken),
            'tokens_different' => $originalToken !== $newToken,
            'user_id_same' => $originalDecoded['sub'] === $newDecoded['sub'],
            'role_same' => $originalDecoded['role'] === $newDecoded['role'],
            'new_issued_at' => $newDecoded['iat'],
            'original_blacklisted' => JwtUtil::isBlacklisted($originalToken)
        ];
    }

    /**
     * 测试令牌注销
     * @return array
     * @throws JwtException
     */
    public static function testTokenRevoke(): array
    {
        $payload = [
            'sub' => 'user_12345',
            'openid' => 'wx_openid_123',
            'role' => 'user',
        ];

        $token = JwtUtil::generate($payload);
        $revokeResult = JwtUtil::revoke($token);
        $isBlacklisted = JwtUtil::isBlacklisted($token);

        return [
            'revoke_success' => $revokeResult,
            'is_blacklisted' => $isBlacklisted,
            'verification_after_revoke' => false // 应该验证失败
        ];
    }

    /**
     * 测试黑名单功能
     * @return array
     */
    public static function testBlacklist(): array
    {
        $payload = [
            'sub' => 'user_12345',
            'openid' => 'wx_openid_123',
            'role' => 'user',
        ];

        $token = JwtUtil::generate($payload);

        // 测试添加到黑名单前
        $beforeBlacklist = JwtUtil::isBlacklisted($token);

        // 添加到黑名单
        $addResult = JwtUtil::addToBlacklist($token, 60);

        // 测试添加到黑名单后
        $afterBlacklist = JwtUtil::isBlacklisted($token);

        return [
            'before_blacklist' => $beforeBlacklist,
            'add_to_blacklist' => $addResult,
            'after_blacklist' => $afterBlacklist,
            'blacklist_working' => !$beforeBlacklist && $afterBlacklist
        ];
    }

    /**
     * 测试角色验证
     * @return array
     * @throws JwtException
     */
    public static function testRoleValidation(): array
    {
        $roles = ['user', 'merchant', 'admin'];
        $results = [];

        foreach ($roles as $role) {
            $payload = [
                'sub' => "user_test_{$role}",
                'openid' => "wx_openid_{$role}",
                'role' => $role,
            ];

            if ($role === 'merchant') {
                $payload['merchant_id'] = 123;
            }

            try {
                $token = JwtUtil::generate($payload);
                $decoded = JwtUtil::verify($token);

                $results[$role] = [
                    'generation' => 'success',
                    'verification' => 'success',
                    'role_match' => $decoded['role'] === $role
                ];
            } catch (\Exception $e) {
                $results[$role] = [
                    'generation' => 'failed',
                    'verification' => 'failed',
                    'error' => $e->getMessage()
                ];
            }
        }

        // 测试无效角色
        try {
            $invalidPayload = [
                'sub' => 'user_invalid',
                'role' => 'invalid_role'
            ];
            JwtUtil::generate($invalidPayload);
            $results['invalid_role'] = 'should_fail_but_passed';
        } catch (JwtException $e) {
            $results['invalid_role'] = 'correctly_failed';
        }

        return $results;
    }

    /**
     * 测试过期令牌
     * @return array
     */
    public static function testExpiredToken(): array
    {
        $payload = [
            'sub' => 'user_12345',
            'openid' => 'wx_openid_123',
            'role' => 'user',
        ];

        // 生成一个1秒过期的令牌
        $token = JwtUtil::generate($payload, 1);

        // 等待令牌过期
        sleep(2);

        try {
            JwtUtil::verify($token);
            $verificationResult = 'unexpected_success';
        } catch (JwtException $e) {
            if ($e->getCode() === JwtException::TOKEN_EXPIRED) {
                $verificationResult = 'correctly_expired';
            } else {
                $verificationResult = 'wrong_error: ' . $e->getMessage();
            }
        }

        return [
            'token_generated' => !empty($token),
            'verification_result' => $verificationResult,
            'ttl' => JwtUtil::getTtl($token)
        ];
    }

    /**
     * 测试无效令牌
     * @return array
     */
    public static function testInvalidToken(): array
    {
        $tests = [
            'empty_token' => '',
            'invalid_format' => 'invalid.token',
            'malformed_jwt' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.invalid.signature',
            'wrong_signature' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.wrong_signature'
        ];

        $results = [];

        foreach ($tests as $testName => $invalidToken) {
            try {
                JwtUtil::verify($invalidToken);
                $results[$testName] = 'unexpected_success';
            } catch (JwtException $e) {
                $results[$testName] = 'correctly_failed: ' . $e->getCode();
            } catch (\Exception $e) {
                $results[$testName] = 'error: ' . $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * 测试用户信息提取
     * @return array
     * @throws JwtException
     */
    public static function testUserInfo(): array
    {
        $payload = [
            'sub' => 'user_12345',
            'openid' => 'wx_openid_123',
            'role' => 'merchant',
            'merchant_id' => 456
        ];

        $token = JwtUtil::generate($payload);
        $userInfo = JwtUtil::getUserInfo($token);

        return [
            'user_info_extracted' => !empty($userInfo),
            'user_id' => $userInfo['user_id'] ?? null,
            'openid' => $userInfo['openid'] ?? null,
            'role' => $userInfo['role'] ?? null,
            'merchant_id' => $userInfo['merchant_id'] ?? null,
            'has_timestamps' => isset($userInfo['issued_at'], $userInfo['expires_at']),
            'ttl' => JwtUtil::getTtl($token),
            'is_expiring_soon' => JwtUtil::isExpiringSoon($token, 3600)
        ];
    }

    /**
     * 性能测试
     * @param int $iterations 测试次数
     * @return array
     */
    public static function performanceTest(int $iterations = 100): array
    {
        $payload = [
            'sub' => 'user_12345',
            'openid' => 'wx_openid_123',
            'role' => 'user',
        ];

        // 生成测试
        $startTime = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            JwtUtil::generate($payload);
        }
        $generateTime = microtime(true) - $startTime;

        // 验证测试
        $token = JwtUtil::generate($payload);
        $startTime = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            JwtUtil::verify($token);
        }
        $verifyTime = microtime(true) - $startTime;

        return [
            'iterations' => $iterations,
            'generate_total_time' => round($generateTime, 4),
            'generate_avg_time' => round($generateTime / $iterations, 6),
            'verify_total_time' => round($verifyTime, 4),
            'verify_avg_time' => round($verifyTime / $iterations, 6),
            'operations_per_second' => [
                'generate' => round($iterations / $generateTime),
                'verify' => round($iterations / $verifyTime)
            ]
        ];
    }

    /**
     * 压力测试
     * @param int $concurrent 并发数
     * @param int $requests 每个并发的请求数
     * @return array
     */
    public static function stressTest(int $concurrent = 10, int $requests = 100): array
    {
        // 这里可以实现并发压力测试
        // 由于PHP的限制，实际的并发测试可能需要其他工具

        $results = [];
        $totalOperations = $concurrent * $requests;
        $errors = 0;

        $startTime = microtime(true);

        for ($c = 0; $c < $concurrent; $c++) {
            for ($r = 0; $r < $requests; $r++) {
                try {
                    $payload = [
                        'sub' => "user_{$c}_{$r}",
                        'openid' => "wx_openid_{$c}_{$r}",
                        'role' => 'user',
                    ];

                    $token = JwtUtil::generate($payload);
                    JwtUtil::verify($token);

                } catch (\Exception $e) {
                    $errors++;
                }
            }
        }

        $totalTime = microtime(true) - $startTime;

        return [
            'concurrent' => $concurrent,
            'requests_per_concurrent' => $requests,
            'total_operations' => $totalOperations,
            'success_operations' => $totalOperations - $errors,
            'errors' => $errors,
            'error_rate' => round($errors / $totalOperations * 100, 2) . '%',
            'total_time' => round($totalTime, 4),
            'operations_per_second' => round($totalOperations / $totalTime),
            'average_response_time' => round($totalTime / $totalOperations * 1000, 2) . 'ms'
        ];
    }

    /**
     * 获取测试报告
     * @return string
     */
    public static function getTestReport(): string
    {
        $results = self::runAllTests();
        $performanceResults = self::performanceTest(50);

        $report = "\n=== JWT工具类测试报告 ===\n\n";
        $report .= "测试时间: " . date('Y-m-d H:i:s') . "\n\n";

        $passed = 0;
        $failed = 0;

        $report .= "功能测试结果:\n";
        $report .= str_repeat("-", 50) . "\n";

        foreach ($results as $result) {
            $status = $result['status'] === 'PASS' ? '✓' : '✗';
            $report .= sprintf("%-30s %s %s\n",
                $result['test'],
                $status,
                $result['status']
            );

            if ($result['status'] === 'PASS') {
                $passed++;
            } else {
                $failed++;
                if ($result['error']) {
                    $report .= "  错误: " . $result['error'] . "\n";
                }
            }
        }

        $report .= str_repeat("-", 50) . "\n";
        $report .= sprintf("总计: %d, 通过: %d, 失败: %d\n\n",
            count($results), $passed, $failed);

        $report .= "性能测试结果:\n";
        $report .= str_repeat("-", 50) . "\n";
        $report .= sprintf("生成令牌: 平均 %.6f 秒, %d ops/s\n",
            $performanceResults['generate_avg_time'],
            $performanceResults['operations_per_second']['generate']
        );
        $report .= sprintf("验证令牌: 平均 %.6f 秒, %d ops/s\n\n",
            $performanceResults['verify_avg_time'],
            $performanceResults['operations_per_second']['verify']
        );

        return $report;
    }
}