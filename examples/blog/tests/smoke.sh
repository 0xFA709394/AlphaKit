#!/usr/bin/env bash
# ================================================================
# AlphaBlog 示例项目冒烟测试 —— 覆盖框架全部用法点
# 用法: bash tests/smoke.sh [端口]   (默认 8090)
# ================================================================
set -uo pipefail
cd "$(dirname "$0")/.."

PORT="${1:-8090}"
BASE="http://127.0.0.1:${PORT}"
PASS=0 FAIL=0

check() { # check <描述> <命令> <期望子串>
    local desc="$1" cmd="$2" expect="$3" out
    out=$(eval "$cmd" 2>/dev/null)
    if [[ "$out" == *"$expect"* ]]; then
        echo "  ✓ $desc"; PASS=$((PASS+1))
    else
        echo "  ✗ $desc (期望: $expect)"; echo "    实际: ${out:0:150}"; FAIL=$((FAIL+1))
    fi
}

echo "── 0. CLI ──"
check "迁移状态"        "php bin/console migrate:status"           "Yes"
check "种子幂等"        "php bin/console db:seed"                  "数据填充完成"
check "自定义命令"      "php bin/console blog:stats"               "浏览 Top"
check "调度清单"        "php bin/console schedule:list"            "预热热门文章缓存"

echo "── 1. 启动服务 ──"
# 多 worker:relay 需要在处理请求的同时回调自身 /api/health
PHP_CLI_SERVER_WORKERS=4 php -S "127.0.0.1:${PORT}" server.php >/tmp/ak-blog-serve.log 2>&1 &
SERVER_PID=$!
sleep 1

echo "── 2. Web 页面(视图/布局/缓存/全局中间件) ──"
check "首页 200"         "curl -s -o /dev/null -w '%{http_code}' $BASE/"                '200'
check "X-Request-Id 全局中间件"  "curl -sD- $BASE/ -o /dev/null"                        'X-Request-Id'
check "X-Elapsed-Ms timed 中间件" "curl -sD- $BASE/ -o /dev/null"                       'X-Elapsed-Ms'
check "首页含文章"       "curl -s $BASE/"                                               'AlphaBlog 示例项目欢迎你'
check "文章详情(关联作者)" "curl -s $BASE/posts/1"                                      '示例作者'
check "详情含评论(hasMany)" "curl -s $BASE/posts/1"                                     '读者小王'
check "404 未发布/不存在" "curl -s -o /dev/null -w '%{http_code}' $BASE/posts/99999"     '404'

echo "── 3. 命名路由 + 重定向 + 下载 ──"
check "重定向 Location"  "curl -sD- $BASE/go/1 -o /dev/null"                            'Location: /posts/1'
check "导出下载"         "curl -sD- '$BASE/posts/1/export' -o /dev/null"                'attachment; filename="welcome.md"'

echo "── 4. Session(regenerate/flash/redirect) ──"
check "登录表单页"       "curl -s -o /dev/null -w '%{http_code}' $BASE/login-form"      '200'
# 全新 jar 登录:-c 保存服务端会话(登录内部走 regenerate 轮换 ID)
rm -f /tmp/ak-blog-cookie
check "登录 flash+重定向" "curl -s -c /tmp/ak-blog-cookie -D- $BASE/login -d 'name=小明' -o /dev/null" 'Location: /'
check "flash 消息显示"   "curl -sb /tmp/ak-blog-cookie $BASE/"                          '欢迎回来,小明!'
check "Session 计数"     "curl -sb /tmp/ak-blog-cookie $BASE/session-info | grep -oE '<strong>[0-9]+</strong>'" '<strong>1</strong>'

echo "── 5. API:注册/登录/JWT ──"
EMAIL="u$RANDOM@blog.dev"
check "注册 201"         "curl -s -X POST $BASE/api/auth/register -H 'Content-Type: application/json' -d '{\"name\":\"测试\",\"email\":\"$EMAIL\",\"password\":\"pass1234\",\"password_confirmation\":\"pass1234\"}'" '注册成功'
check "校验失败 422"     "curl -s -X POST $BASE/api/auth/login -H 'Content-Type: application/json' -d '{\"email\":\"bad\"}'" '"code":422'
TOKEN=$(curl -s -X POST $BASE/api/auth/login -H 'Content-Type: application/json' -d "{\"email\":\"$EMAIL\",\"password\":\"pass1234\"}" | php -r 'echo json_decode(stream_get_contents(STDIN), true)["data"]["token"] ?? "";')
check "登录获 JWT"       "php -r 'echo strlen(\"$TOKEN\") > 100 ? \"token-ok\" : \"bad\";'" 'token-ok'
check "GET /api/me"      "curl -s $BASE/api/me -H 'Authorization: Bearer $TOKEN'"       "$EMAIL"
check "未认证 401"       "curl -s $BASE/api/me"                                         '"code":401'

echo "── 6. 文章 CRUD(资源路由+事件链路) ──"
check "创建文章 201"     "curl -s -X POST $BASE/api/posts -H 'Authorization: Bearer $TOKEN' -H 'Content-Type: application/json' -d '{\"title\":\"冒烟测试文章\",\"content\":\"覆盖全部框架用法的文章\"}'" '发布成功'
check "事件审计日志"     "grep -s '文章已发布' storage/logs/audit-$(date +%Y-%m-%d).log | tail -1" '文章已发布'
check "列表分页"         "curl -s '$BASE/api/posts?page=1&per_page=5' -H 'Authorization: Bearer $TOKEN'" 'total'
check "关键词过滤"       "curl -sG --data-urlencode 'keyword=冒烟' '$BASE/api/posts' -H 'Authorization: Bearer $TOKEN'" '冒烟测试文章'
POST_ID=$(curl -s "$BASE/api/posts?per_page=1" -H "Authorization: Bearer $TOKEN" | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["data"]["data"][0]["id"] ?? "";')
check "详情"             "curl -s $BASE/api/posts/$POST_ID -H 'Authorization: Bearer $TOKEN'" '冒烟测试文章'
check "更新"             "curl -s -X PUT $BASE/api/posts/$POST_ID -H 'Authorization: Bearer $TOKEN' -H 'Content-Type: application/json' -d '{\"published\":false}'" '更新成功'
check "越权 403"         "curl -s -X PUT $BASE/api/posts/1 -H 'Authorization: Bearer $TOKEN' -H 'Content-Type: application/json' -d '{\"title\":\"x\"}'" '"code":403'

echo "── 7. 缓存(getOrSet/increment/失效) ──"
check "热门文章缓存"     "curl -s $BASE/api/posts/hot -H 'Authorization: Bearer $TOKEN'" 'cached_at'
check "浏览自增(双计数)" "curl -s -X POST $BASE/api/posts/$POST_ID/view -H 'Authorization: Bearer $TOKEN'" 'cached_views'

echo "── 8. 评论 + 队列派发 ──"
check "提交评论 201"     "curl -s -X POST $BASE/api/posts/$POST_ID/comments -H 'Authorization: Bearer $TOKEN' -H 'Content-Type: application/json' -d '{\"author_name\":\"小李\",\"body\":\"好文章\"}'" '通知已入队'

echo "── 9. HttpClient 中继 ──"
check "relay 经 HttpClient 调上游" "curl -s -X POST $BASE/api/relay/health -H 'Authorization: Bearer $TOKEN'" '"via":"HttpClient"'

echo "── 10. 405/路径安全 ──"
check "HEAD 方法可用"    "curl -s -o /dev/null -w '%{http_code}' -I $BASE/api/health"   '200'
check "错误方法 405"     "curl -s -X PATCH $BASE/api/health"                            '"code":405'
check "路径穿越被拒"     "curl -s --path-as-is -o /dev/null -w '%{http_code}' $BASE/../.env" '404'

echo "── 11. CLI 事件链路 + 队列消费 ──"
check "posts:publish 事件" "php bin/console posts:publish $POST_ID"                    '事件已派发'
check "sitemap 任务入队" "php bin/console queue:work --max-jobs=10 >/dev/null 2>&1; grep -s 'sitemap 已生成' storage/logs/sitemap-$(date +%Y-%m-%d).log | tail -1" 'sitemap 已生成'
check "评论通知消费"     "grep -s '评论通知已发送' storage/logs/mail-$(date +%Y-%m-%d).log | tail -1" '评论通知已发送'
check "sitemap 产物存在" "php -r 'echo is_file(\"storage/app/sitemap.txt\") ? \"yes\" : \"no\";'" 'yes'
check "posts:cleanup"    "php bin/console posts:cleanup --days=30"                      '清理完成'

kill "$SERVER_PID" 2>/dev/null

echo ""
echo "════════════════════════════"
echo "  通过: $PASS   失败: $FAIL"
[[ $FAIL -eq 0 ]] && echo "  ✅ ALL BLOG SMOKE TESTS PASSED" || { echo "  ❌ 存在失败"; exit 1; }
