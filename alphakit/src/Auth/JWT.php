<?php

declare(strict_types=1);

namespace AlphaKit\Auth;

/**
 * 纯 PHP 实现的 JWT(HS256 / HS512),无外部依赖
 *
 * JWT::encode(['sub' => 1, 'exp' => time() + 3600], $secret);
 * JWT::decode($token, $secret);  // 验签 + 过期校验,失败抛异常
 */
class JWT
{
    private const SUPPORTED_ALGS = ['HS256' => 'sha256', 'HS384' => 'sha384', 'HS512' => 'sha512'];

    /**
     * 签发 token
     *
     * @param array<string, mixed> $payload
     */
    public static function encode(array $payload, string $secret, string $alg = 'HS256', ?int $ttl = null): string
    {
        if ($ttl !== null) {
            $payload['iat'] = $payload['iat'] ?? time();
            $payload['exp'] = time() + $ttl;
        }

        $header = self::base64urlEncode(json_encode(['alg' => $alg, 'typ' => 'JWT'], JSON_UNESCAPED_SLASHES));
        $body = self::base64urlEncode(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $signature = self::base64urlEncode(self::sign("{$header}.{$body}", $secret, $alg));

        return "{$header}.{$body}.{$signature}";
    }

    /**
     * 校验并解码
     *
     * @return array<string, mixed> payload
     * @throws \RuntimeException 签名/过期/格式错误
     */
    public static function decode(string $token, string $secret): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new \RuntimeException('JWT 格式错误');
        }
        [$header, $body, $signature] = $parts;

        $headerData = json_decode(self::base64urlDecode($header), true);
        $alg = $headerData['alg'] ?? '';
        if (!isset(self::SUPPORTED_ALGS[$alg])) {
            throw new \RuntimeException("不支持的 JWT 算法 [{$alg}]");
        }

        $expected = self::sign("{$header}.{$body}", $secret, $alg);
        if (!hash_equals($expected, self::base64urlDecode($signature))) {
            throw new \RuntimeException('JWT 签名无效');
        }

        $payload = json_decode(self::base64urlDecode($body), true);
        if (!is_array($payload)) {
            throw new \RuntimeException('JWT payload 无法解析');
        }

        if (isset($payload['exp']) && (int)$payload['exp'] < time()) {
            throw new \RuntimeException('JWT 已过期');
        }
        if (isset($payload['nbf']) && (int)$payload['nbf'] > time()) {
            throw new \RuntimeException('JWT 尚未生效');
        }

        return $payload;
    }

    protected static function sign(string $data, string $secret, string $alg): string
    {
        return hash_hmac(self::SUPPORTED_ALGS[$alg], $data, $secret, true);
    }

    protected static function base64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    protected static function base64urlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        if ($decoded === false) {
            throw new \RuntimeException('Base64 解码失败');
        }
        return $decoded;
    }
}
