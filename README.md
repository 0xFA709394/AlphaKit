# ⚡ AlphaKit

一套支持各类需求类型的 PHP 快速开发框架 —— **API / Web / CLI / 队列 / 定时任务**全栈覆盖，开箱即用。

> PHP ≥ 7.4 · 零 Composer 依赖 · SQLite 开箱即用（可选 MySQL / Redis）

---

## 为什么选择 AlphaKit

| 需求类型 | 支持方式 |
|---------|---------|
| **RESTful API** | 路由 + 中间件 + JWT 认证 + 统一 JSON 响应 + 参数校验 |
| **Web 应用** | PHP 原生模板（布局继承）+ Session + Flash 消息 |
| **后台任务** | 数据库队列：延迟执行 / 自动重试 / 失败记录 |
| **定时任务** | cron 表达式调度器，一条系统 cron 搞定所有周期任务 |
| **CLI 工具** | 19 个内置命令 + 代码生成器（controller/model/job/migration…） |
| **微服务调用** | cURL HTTP 客户端（链式 API / JSON / Bearer Token） |

**核心特性**

- 🚀 **零依赖启动** —— 内置 PSR-4 自动加载器，`git clone` 即用；有 vendor 时自动切换 Composer
- 🗄️ **双数据库** —— SQLite（零配置）/ MySQL，SQL 方言自动适配
- 🔐 **JWT 认证** —— 纯 PHP 实现 HS256/384/512，无第三方库
- 🧩 **依赖注入** —— 反射容器，构造函数自动装配
- 🛠️ **脚手架** —— `make:controller --api --resource` 一条命令生成标准 CRUD

## 快速开始（60 秒）

```bash
# 0. 环境要求:PHP >= 7.4(需 pdo_sqlite;可选 curl / redis 扩展)

# 1. 启动开发服务器
php bin/alphakit serve              # http://127.0.0.1:8000

# 2. 初始化数据库(自动建表 + 种子数据)
php bin/alphakit migrate
php bin/alphakit db:seed            # 演示账号: demo@alphakit.dev / demo1234

# 3. 体验 API
curl http://127.0.0.1:8000/api/health
TOKEN=$(curl -s -X POST http://127.0.0.1:8000/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"demo@alphakit.dev","password":"demo1234"}' \
  | php -r 'echo json_decode(stream_get_contents(STDIN), true)["data"]["token"];')
curl -H "Authorization: Bearer $TOKEN" http://127.0.0.1:8000/api/me
```

打开浏览器访问 `http://127.0.0.1:8000` 查看 Web 首页，`/docs` 查看能力总览。

## 目录结构

```
AlphaKit/
├── bin/alphakit              # CLI 入口(所有命令)
├── public/index.php          # HTTP 入口(前端控制器)
├── alphakit/                 # ★ 框架源码(可复用的框架包)
│   ├── bootstrap.php         # 引导:自动加载 + .env + 助手函数
│   ├── server.php            # php -S 路由脚本
│   └── src/
│       ├── Core/             # 容器/内核/路由/请求响应/中间件/Session/事件
│       ├── Database/         # 连接/查询构造器/Model ORM/迁移/Schema
│       ├── Cache/            # file / redis / array 缓存
│       ├── Log/              # 分通道日志
│       ├── Queue/            # 数据库队列 + Worker
│       ├── Schedule/         # cron 调度器
│       ├── Validation/       # 数据校验
│       ├── View/             # 模板引擎
│       ├── Auth/             # JWT + 认证管理
│       ├── Http/             # HTTP 客户端
│       └── Console/          # 控制台(19 个内置命令)
├── app/                      # ★ 业务代码(演示应用)
│   ├── Controllers/          #   Api/ + Web/ 控制器
│   ├── Models/               #   User / Task 模型
│   ├── Middleware/           #   JWT 认证 / 限流
│   ├── Jobs/                 #   队列任务
│   ├── Commands/             #   自定义命令
│   ├── views/                #   视图(布局继承)
│   └── schedule.php          #   定时任务注册表
├── config/                   # 配置(app/database/cache/queue/auth/...)
├── routes/                   # 路由(web.php + api.php)
├── database/
│   ├── migrations/           # 数据库迁移
│   ├── seeders.php           # 数据填充
│   └── database.sqlite       # SQLite(自动生成)
├── storage/                  # 日志/缓存/会话(运行时产物)
└── docs/                     # ★ 中文文档
```

## 命令速查

```bash
# 开发
php bin/alphakit serve [--host=0.0.0.0] [--port=8000]   # 开发服务器
php bin/alphakit routes                                   # 路由列表
php bin/alphakit env                                      # 环境信息

# 数据库
php bin/alphakit migrate                                  # 执行迁移
php bin/alphakit migrate:rollback / migrate:reset         # 回滚
php bin/alphakit migrate:status                           # 迁移状态
php bin/alphakit db:seed                                  # 数据填充

# 代码生成
php bin/alphakit make:controller PostController --api --resource
php bin/alphakit make:model Post --migration
php bin/alphakit make:job SyncData
php bin/alphakit make:command SendEmails
php bin/alphakit make:middleware AuditLog
php bin/alphakit make:migration create_posts_table

# 队列 / 调度
php bin/alphakit queue:work [--queue=high] [--once|--max-jobs=100]
php bin/alphakit queue:failed
php bin/alphakit schedule:run                             # 由系统 cron 每分钟调用
php bin/alphakit schedule:list

# 安全
php bin/alphakit key:generate                             # 生成 JWT_SECRET 写入 .env
```

## 文档

| 文档 | 内容 |
|------|------|
| [examples/blog/](examples/blog/) | ★ **全用法示例项目 AlphaBlog**(41 项端到端测试,覆盖框架每个方面) |
| [docs/01-快速开始.md](docs/01-快速开始.md) | 安装、配置、目录结构、生命周期 |
| [docs/02-路由与控制器.md](docs/02-路由与控制器.md) | 路由定义、参数约束、中间件、控制器、响应 |
| [docs/03-数据库与模型.md](docs/03-数据库与模型.md) | 查询构造器、Model ORM、关联、事务、迁移 |
| [docs/04-队列与调度.md](docs/04-队列与调度.md) | 异步任务、重试、定时任务、crontab 配置 |
| [docs/05-CLI命令.md](docs/05-CLI命令.md) | 内置命令、自定义命令、代码生成器 |
| [docs/06-缓存日志验证.md](docs/06-缓存日志验证.md) | 缓存、日志、参数校验、Session、视图 |
| [docs/07-认证与HTTP客户端.md](docs/07-认证与HTTP客户端.md) | JWT、登录注册、限流、HTTP 客户端 |
| [docs/08-部署指南.md](docs/08-部署指南.md) | Nginx/FPM、队列常驻、cron、安全清单 |
| [docs/09-测试与质量保障.md](docs/09-测试与质量保障.md) | 三套测试套件、框架不变量、审查方法论 |

## 典型场景

- 📱 **移动端/小程序后端** —— JWT + 资源路由 + 分页 + 校验，一条 `make:controller --api --resource` 起步
- 🏢 **企业内部系统** —— Web 页面 + Session + RBAC 扩展点(中间件)
- 🔁 **数据同步/爬虫** —— 队列削峰 + 重试 + `schedule:run` 周期触发
- 🤖 **微信/支付回调处理** —— 路由闭包 + 事件系统 + 队列异步落库
- ⚙️ **运维脚本** —— `make:command` 生成自定义 CLI,复用容器与模型

## License

MIT
