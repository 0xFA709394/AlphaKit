<?php

declare(strict_types=1);

namespace AlphaKit\Auth;

use AlphaKit\Core\Exception\AuthenticationException;
use AlphaKit\Core\Request;
use AlphaKit\Database\Connection;

/**
 * 认证管理器:JWT 令牌模式 + 凭证校验
 *
 * 登录:
 *   $user = $auth->attempt($email, $password);   // 自动 password_verify
 *   $token = $auth->issueToken($user);
 *
 * 鉴权(auth 中间件内):
 *   $user = $auth->user();                       // 依据 Bearer Token 解析
 */
class AuthManager
{
    /** @var array<string, mixed>|null 当前请求用户 */
    protected ?array $user = null;

    public function __construct(protected ?Connection $connection = null)
    {
    }

    /** 凭证校验(默认 users 表 + password_hash),成功返回用户行 */
    public function attempt(string $email, string $password, ?string $table = null): ?array
    {
        $conn = $this->connection();
        $table = $table ?? (string)config('auth.table', 'users');
        $emailCol = $conn->wrap('email');
        $user = $conn->selectOne(
            "SELECT * FROM {$conn->wrap($table)} WHERE {$emailCol} = ? LIMIT 1",
            [$email]
        );
        if ($user === null) {
            return null;
        }
        if (!password_verify($password, (string)($user['password'] ?? ''))) {
            return null;
        }
        return $user;
    }

    /** 为用户签发 JWT(sub = 用户 ID) */
    public function issueToken(array $user, ?int $ttl = null): string
    {
        $ttl = $ttl ?? (int)config('auth.jwt.ttl', 7200);
        $secret = (string)config('auth.jwt.secret', env('JWT_SECRET', ''));

        return JWT::encode([
            'sub' => (int)$user['id'],
            'email' => $user['email'] ?? null,
            'iat' => time(),
        ], $secret, 'HS256', $ttl);
    }

    /** 从请求解析用户(Bearer Token) */
    public function user(Request $request): ?array
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $token = $request->bearerToken();
        if ($token === null) {
            return null;
        }

        try {
            $payload = JWT::decode($token, (string)config('auth.jwt.secret', env('JWT_SECRET', '')));
        } catch (\RuntimeException) {
            return null;
        }

        $conn = $this->connection();
        $table = (string)config('auth.table', 'users');
        $idCol = $conn->wrap('id');
        $this->user = $conn->selectOne(
            "SELECT * FROM {$conn->wrap($table)} WHERE {$idCol} = ? LIMIT 1",
            [(int)($payload['sub'] ?? 0)]
        );

        return $this->user;
    }

    /** 必须已认证,否则抛 401 */
    public function authenticate(Request $request): array
    {
        $user = $this->user($request);
        if ($user === null) {
            throw new AuthenticationException();
        }
        return $user;
    }

    /** 登出(无状态 JWT:仅清空当前实例) */
    public function logout(): void
    {
        $this->user = null;
    }

    protected function connection(): Connection
    {
        return $this->connection ??= app(Connection::class);
    }
}
