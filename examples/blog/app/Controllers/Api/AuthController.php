<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use AlphaKit\Auth\AuthManager;
use AlphaKit\Core\Controller;
use AlphaKit\Core\Exception\AuthenticationException;
use AlphaKit\Core\Exception\ValidationException;
use AlphaKit\Core\Request;
use AlphaKit\Core\Response;
use App\Models\User;

/**
 * API 认证(JWT 演示)
 */
final class AuthController extends Controller
{
    public function __construct(protected AuthManager $auth)
    {
    }

    /** POST /api/auth/register */
    public function register(Request $request): Response
    {
        $data = $this->validate($request, [
            'name' => 'required|string|max:50',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|max:32|confirmed',
        ]);

        try {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => password_hash((string)$data['password'], PASSWORD_DEFAULT),
            ]);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'UNIQUE')) {
                throw new ValidationException(['email' => ['邮箱已被注册']]);
            }
            throw $e;
        }

        return $this->ok([
            'user' => $user,
            'token' => $this->auth->issueToken($user->toArray() + ['id' => $user->id]),
        ], '注册成功')->withStatus(201);
    }

    /** POST /api/auth/login */
    public function login(Request $request): Response
    {
        $data = $this->validate($request, [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = $this->auth->attempt((string)$data['email'], (string)$data['password']);
        if ($user === null) {
            throw new AuthenticationException('邮箱或密码错误');
        }
        return $this->ok(['user' => User::hydrate($user), 'token' => $this->auth->issueToken($user)], '登录成功');
    }

    /** GET /api/me(需认证) */
    public function me(Request $request): Response
    {
        return $this->ok(User::find((int)$request->user['id']));
    }
}
