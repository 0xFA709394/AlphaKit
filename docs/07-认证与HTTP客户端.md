# 07 · 认证与 HTTP 客户端

## JWT 认证

纯 PHP 实现(HS256/384/512,HMAC-SHA),无需第三方库。

### 完整流程(演示应用已实现)

**1. 注册** `POST /api/auth/register`

```json
{ "name": "张三", "email": "a@b.dev", "password": "123456", "password_confirmation": "123456" }
```

**2. 登录换取 token** `POST /api/auth/login`

```json
// 响应
{ "code": 0, "data": { "user": {...}, "token": "eyJhbGciOi..." } }
```

**3. 携带 token 访问受保护接口**

```bash
curl -H "Authorization: Bearer <token>" http://127.0.0.1:8000/api/me
```

### 框架 API

```php
use AlphaKit\Auth\{AuthManager, JWT};

// 签发(自定义 payload / 有效期)
$token = JWT::encode(['sub' => $userId, 'role' => 'admin'], $secret, 'HS256', 7200);

// 解码(自动验签 + exp/nbf 校验,失败抛 RuntimeException)
$payload = JWT::decode($token, $secret);

// AuthManager 高层封装
$auth = app(AuthManager::class);
$user = $auth->attempt($email, $password);       // 凭证校验(password_verify)
$token = $auth->issueToken($user);               // 签发标准 token
$user  = $auth->user($request);                  // 从 Bearer 解析用户(null = 未认证)
$auth->authenticate($request);                   // 同上,未认证抛 401
```

### 保护路由

`auth` 别名中间件(演示实现见 `app/Middleware/Authenticate.php`):

```php
// routes/api.php
$router->group(['middleware' => ['auth']], function (Router $router): void {
    $router->get('/me', [AuthController::class, 'me']);
    $router->resource('/tasks', TaskController::class);
});

// 控制器中获取当前用户
$user = $request->user;               // 中间件注入
```

配置 `config/auth.php`:用户表 / JWT 密钥(`.env JWT_SECRET`)/ 有效期(`JWT_TTL`)。

> 生产必做:`php bin/alphakit key:generate` 生成强随机密钥。

## 限流

`throttle:次数,窗口秒` 中间件(基于缓存计数,超出返回 429):

```php
$router->get('/sms/send', [SmsController::class, 'send'])->middleware('throttle:1,60');
// 每分钟最多 1 次(短信场景)

$router->get('/search', [SearchController::class, 'index'])->middleware('throttle:60,60');
// 每分钟 60 次(默认)
```

响应携带 `X-RateLimit-Remaining` 头。

## HTTP 客户端(调用第三方)

```php
use AlphaKit\Http\HttpClient;

$client = HttpClient::client();

// GET
$r = $client->get('https://api.github.com/search/repositories', ['q' => 'php', 'per_page' => 5]);

// POST JSON(数组默认 JSON)
$r = $client->post('https://api.example.com/orders', ['sku' => 'A1', 'qty' => 2]);

// 表单
$r = $client->asForm()->post($url, ['grant_type' => 'client_credentials']);

// 带认证 / 头 / 超时
$r = $client->asJson()
    ->withToken($token)                    // Authorization: Bearer xxx
    ->withHeaders(['X-Tenant' => 'shop1'])
    ->timeout(15)
    ->put("https://api.example.com/orders/{$id}", ['qty' => 3]);

// 响应
$r->ok();              // 2xx
$r->status;            // 状态码
$r->json();            // 解码后的 body
$r->body();            // 原始 body
$r->header('content-type');
```

**典型场景**:微信/支付宝接口、OAuth2 客户端、微服务间调用、webhook 推送。

```php
// 微服务调用 + 容错示例
try {
    $r = HttpClient::client()->timeout(5)->get($userService . '/internal/users/' . $id);
    return $r->ok() ? $r->json() : null;
} catch (RuntimeException $e) {
    logger('rpc')->error('用户服务调用失败', ['id' => $id, 'err' => $e->getMessage()]);
    return null;
}
```
