# AlphaBlog —— AlphaKit 全用法示例项目

一个基于 AlphaKit 的完整博客系统,覆盖框架**每一个方面**的用法(63 项端到端冒烟)。

## 运行

```bash
cd examples/blog

php bin/console migrate        # 建表(users/posts/comments)
php bin/console db:seed        # 种子数据(作者 author@blog.dev / blog1234,3 篇文章 2 评论)
php bin/console blog:stats     # 统计面板
PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8090 server.php   # 启动(多 worker 支持 relay 自调)
bash tests/smoke.sh 8090       # 63 项端到端冒烟测试
```

## 用法覆盖清单(41 项冒烟逐项验证)

| 框架能力 | 示例位置 |
|---------|---------|
| RESTful 资源路由 | `routes/api.php` → `resource('/posts')` |
| 路由分组 + prefix | `/api` 组 |
| 中间件别名 + 参数 | `throttle:30,60`、`timed:500`(洋葱模型计时) |
| 全局中间件 | `RequestId`(每响应 X-Request-Id) |
| 路由参数约束 | `{id:\d+}` |
| 命名路由 + url() 生成 | `post.show` / `login.submit` 等 9 个命名 |
| 重定向 | `GET /go/{id}` → `redirect(url(...))` |
| 文件下载 | `GET /posts/{id}/export` → Markdown 导出 |
| 视图布局继承 + insert | `layouts/blog.php` + `partials/post-card.php` |
| Session + Flash + regenerate | 登录流程:`/login-form` |
| JWT 认证 | `POST /api/auth/register\|login` + `GET /api/me` |
| 数据校验(含 unique/confirmed) | 注册/登录/文章/评论全部入口 |
| 事件系统 | `PostPublished` → 审计日志监听器 |
| 服务提供者(绑定+订阅) | `AppServiceProvider`(Slugifier 接口绑定 + 事件订阅) |
| 队列:派发/延迟/重试/失败 | 评论通知 `SendCommentNotification` |
| 队列:真实产物任务 | `GenerateSitemap`(chunk 分批写 sitemap.txt) |
| 调度(闭包+command+频率) | `app/schedule.php`(3 个任务) |
| 缓存 getOrSet / increment / 失效 | 热门文章 / 浏览计数 / 创建后删缓存 |
| 缓存驱动 array/file | CacheManager 双驱动(测试可切 array) |
| 日志多通道 | app / audit / mail / sitemap / cleanup / slow |
| QueryBuilder:分页/关键词/增量 | 文章列表 `paginate` + `whereLike` + `increment` |
| QueryBuilder:chunk 分批 | sitemap 生成 |
| Model 关联(hasMany/belongsTo) | `Post::comments()` / `Post::author()` |
| Model casts(bool/int) + hidden | published/views + users.password |
| 依赖注入(接口绑定) | `SlugifierInterface → SimpleSlugifier` |
| HttpClient 服务间调用 | `POST /api/relay/health`(回调本站 /api/health) |
| 统一响应结构 | `code/message/data` + 422/401/403/404/405/502 |
| 异常 → 错误页 | `abort(403/404)` 渲染 |
| CLI 自定义命令 | `blog:stats` / `posts:publish` / `posts:cleanup` |
| CLI → 事件 → 监听 → 队列 | `posts:publish` 全链路 |
| 迁移 + 种子 + 幂等 + 回滚闭环 | `migrate` / `db:seed` / `migrate:rollback`→`migrate`(冒烟验证) |
| 静态文件安全(realpath 限定) | `server.php` 路径穿越防护(冒烟验证 404) |

### 第二批补全(63 项冒烟)

| 框架能力 | 示例位置 |
|---------|---------|
| **代码生成器全链路** | `make:migration/model/controller --api --resource/job/command` 生成 Tag 功能骨架后填充真实现 |
| QueryBuilder whereBetween + sum/max | `GET /api/tags` |
| QueryBuilder insertMany | `POST /api/tags/bulk` |
| QueryBuilder upsert(HTTP + CLI 双入口) | `POST /api/tags/sync` + `tags:sync`(幂等双跑) |
| QueryBuilder whereIn + orWhere 条件组 | `GET /api/tags/filter` |
| 命名队列 | 评论通知走 `notifications`、Tag 预热走 `tags`,`queue:work --queue=xx` 分别消费 |
| 事件 dispatch 返回回执 | 创建文章响应含 `event_receipts: ["audit-logged","sitemap-queued"]` |
| JWT 多算法 | `POST /api/token/refresh` 签发 HS512 |
| HttpClient asJson POST + 自定义头 | `POST /api/demo/relay-post`(上游 401 如实转发) |
| Response::text | `GET /api/demo/text` |
| 缓存 pull(一次性令牌) | `GET/POST /api/demo/one-time-token`(二次消费拒绝) |
| 限流 429 实际触发 | `GET /api/demo/limited`(throttle:2,60,第 3 次 429) |
| 日志多级别 | `GET /api/demo/log-levels`(debug…error 写入,WARNING/ERROR 落盘验证) |
| 可选路由参数 | `GET /archive/{page?}`(带参/缺省双态) |
| 301 永久重定向 | `GET /legacy-home` |
| Session has/all | `/session-info` 键数量与登录态 |

## 关键文件导览

```
bin/console            CLI 入口(定义 BASE_PATH 指向本项目)
public/index.php       HTTP 入口
server.php             php -S 路由脚本(含穿越防护)
config/app.php         providers / commands / middleware 注册中心
routes/{web,api}.php   全部路由定义
app/Providers/         容器绑定 + 事件订阅(AppServiceProvider)
app/Events+Listeners/  事件与监听器
app/Jobs/              队列任务(重试/失败兜底)
app/Commands/          三个自定义命令
app/schedule.php       调度注册表
database/              迁移 + 种子
tests/smoke.sh         63 项端到端测试
```
