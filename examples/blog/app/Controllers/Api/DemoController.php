<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use AlphaKit\Auth\JWT;
use AlphaKit\Core\Controller;
use AlphaKit\Core\Request;
use AlphaKit\Core\Response;
use AlphaKit\Http\HttpClient;

/**
 * 框架能力演示集(杂项):日志级别 / text 响应 / JWT 多算法 / 一次性令牌 / HttpClient POST
 */
final class DemoController extends Controller
{
    /** GET /api/demo/log-levels —— 多级别日志写入 */
    public function logLevels(): Response
    {
        logger('demo')->debug('调试信息');
        logger('demo')->info('常规信息');
        logger('demo')->warning('警告信息');
        logger('demo')->error('错误信息');
        return $this->ok(['file' => 'storage/logs/demo-' . date('Y-m-d') . '.log']);
    }

    /** GET /api/demo/text —— Response::text */
    public function text(): Response
    {
        return Response::text('AlphaBlog plain-text demo @ ' . date('Y-m-d H:i:s'));
    }

    /** POST /api/token/refresh —— JWT HS512 重签(多算法演示,需认证) */
    public function refresh(Request $request): Response
    {
        $token = JWT::encode(
            ['sub' => (int)$request->user['id'], 'refreshed' => true],
            (string)config('auth.jwt.secret'),
            'HS512',
            1800
        );
        return $this->ok(['token' => $token, 'alg' => 'HS512', 'ttl' => 1800]);
    }

    /** GET /api/demo/one-time-token —— 签发一次性令牌 */
    public function issueToken(): Response
    {
        $token = bin2hex(random_bytes(16));
        cache_store()->set('one-time:' . $token, 1, 300);
        return $this->ok(['token' => $token, 'ttl' => 300]);
    }

    /** POST /api/demo/one-time-token —— 消费一次性令牌(cache pull:读取即删) */
    public function consumeToken(Request $request): Response
    {
        $token = (string)$request->input('token', '');
        $valid = $token !== '' && cache_store()->pull('one-time:' . $token) !== null;
        return $this->ok(['valid' => $valid], $valid ? '令牌有效(已消费)' : '令牌无效或已使用过');
    }

    /** POST /api/demo/relay-post —— HttpClient asJson POST + 自定义头(上游 401 如实转发) */
    public function relayPost(Request $request): Response
    {
        $parts = parse_url($request->url());
        $origin = ($parts['scheme'] ?? 'http') . '://' . ($parts['host'] ?? '127.0.0.1')
            . (isset($parts['port']) ? ':' . $parts['port'] : '');

        $resp = HttpClient::client()
            ->asJson()
            ->withHeaders(['X-Relay-From' => 'alphablog'])
            ->timeout(5)
            ->post($origin . '/api/auth/login', ['email' => 'nobody@x.dev', 'password' => 'wrong']);

        return $this->ok([
            'via' => 'HttpClient asJson POST',
            'upstream_status' => $resp->status,
            'upstream_code' => $resp->json()['code'] ?? null,
        ]);
    }
}
