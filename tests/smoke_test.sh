#!/usr/bin/env bash
# ================================================================
# AlphaKit 全量冒烟测试
# 覆盖:CLI / 迁移 / HTTP / 认证 / CRUD / 队列 / 调度 / 组件
# 用法: bash tests/smoke_test.sh [端口]
# ================================================================
set -uo pipefail

PORT="${1:-8453}"
BASE="http://127.0.0.1:${PORT}"
PASS=0
FAIL=0

check() { # check <描述> <命令> <期望子串>
    local desc="$1" cmd="$2" expect="$3" out
    out=$(eval "$cmd" 2>/dev/null)
    if [[ "$out" == *"$expect"* ]]; then
        echo "  ✓ $desc"; PASS=$((PASS+1))
    else
        echo "  ✗ $desc (期望包含: $expect)"; echo "    实际: ${out:0:160}"; FAIL=$((FAIL+1))
    fi
}

echo "── 1. CLI 基础 ──"
check "help 列表"          "php bin/alphakit"                        "可用命令"
check "env 信息"           "php bin/alphakit env"                    "AlphaKit"
check "routes 列表"        "php bin/alphakit routes"                 "api/tasks"
check "schedule:list"      "php bin/alphakit schedule:list"          "清理超期任务"

echo "── 2. 数据库 ──"
check "migrate:status"     "php bin/alphakit migrate:status"         "Yes"
check "db:seed"            "php bin/alphakit db:seed"                "数据填充完成"

echo "── 3. 启动 HTTP 服务 ──"
php -S "127.0.0.1:${PORT}" alphakit/server.php >/tmp/ak-smoke-serve.log 2>&1 &
SERVER_PID=$!
sleep 1

echo "── 4. API ──"
check "健康检查"            "curl -s $BASE/api/health"                '"status":"up"'
check "验证失败422"         "curl -s -X POST $BASE/api/auth/register -H 'Content-Type: application/json' -d '{\"email\":\"bad\"}'" '"code":422'
check "登录"               "curl -s -X POST $BASE/api/auth/login -H 'Content-Type: application/json' -d '{\"email\":\"demo@alphakit.dev\",\"password\":\"demo1234\"}'" '"code":0'
TOKEN=$(curl -s -X POST $BASE/api/auth/login -H 'Content-Type: application/json' -d '{"email":"demo@alphakit.dev","password":"demo1234"}' | php -r 'echo json_decode(stream_get_contents(STDIN), true)["data"]["token"] ?? "";')
check "未认证401"          "curl -s $BASE/api/me"                    '"code":401'
check "当前用户"            "curl -s $BASE/api/me -H 'Authorization: Bearer $TOKEN'"   'demo@alphakit.dev'
check "创建任务201"         "curl -s -X POST $BASE/api/tasks -H 'Authorization: Bearer $TOKEN' -H 'Content-Type: application/json' -d '{\"title\":\"冒烟测试\"}'"  '创建成功'
TASK_ID=$(curl -s "$BASE/api/tasks?per_page=1" -H "Authorization: Bearer $TOKEN" | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["data"]["data"][0]["id"] ?? "";')
check "任务列表"            "curl -s $BASE/api/tasks -H 'Authorization: Bearer $TOKEN'"          'total'
check "任务详情"            "curl -s $BASE/api/tasks/$TASK_ID -H 'Authorization: Bearer $TOKEN'" '"id":'"$TASK_ID"
check "更新任务"            "curl -s -X PUT $BASE/api/tasks/$TASK_ID -H 'Authorization: Bearer $TOKEN' -H 'Content-Type: application/json' -d '{\"done\":true}'" '更新成功'
check "删除任务"            "curl -s -X DELETE $BASE/api/tasks/$TASK_ID -H 'Authorization: Bearer $TOKEN'" '删除成功'
check "404详情"            "curl -s $BASE/api/tasks/99999 -H 'Authorization: Bearer $TOKEN'"   '404'
check "限流头"              "curl -sD- $BASE/api/demo/throttle -o /dev/null" 'X-RateLimit-Remaining'
check "路径穿越被拒(/../.env)"     "curl -s --path-as-is -o /dev/null -w '%{http_code}' $BASE/../.env" '404'
check "路径穿越被拒(编码变体)"     "curl -s -o /dev/null -w '%{http_code}' '$BASE/..%2f.env'" '404'
check "越权访问他人任务403"        "curl -s $BASE/api/users/2/tasks -H 'Authorization: Bearer $TOKEN'" '"code":403'

echo "── 5. Web 页面 ──"
check "首页200"            "curl -s -o /dev/null -w '%{http_code}' $BASE/"                     '200'
check "docs页"             "curl -s $BASE/docs | grep -o '<title>[^<]*'"                        '能力总览'
check "Session 计数"       "curl -sc /tmp/ak-smoke-cookie $BASE/demo/session | grep -oE '第 <strong>1</strong>' && curl -sb /tmp/ak-smoke-cookie $BASE/demo/session | grep -oE '第 <strong>2</strong>'" '第 <strong>2</strong>'

echo "── 6. 队列 ──"
curl -s -X POST $BASE/api/auth/register -H 'Content-Type: application/json' -d "{\"name\":\"冒烟\",\"email\":\"smoke$RANDOM@test.dev\",\"password\":\"pass1234\",\"password_confirmation\":\"pass1234\"}" > /dev/null
check "队列消费(邮件日志)"  "php bin/alphakit queue:work --max-jobs=10 >/dev/null 2>&1; grep '欢迎邮件已发送' storage/logs/mail-\$(date +%Y-%m-%d).log | tail -1" '欢迎邮件已发送'

echo "── 7. 调度 ──"
check "schedule:run 退出码0" "php bin/alphakit schedule:run; echo EXIT=\$?" 'EXIT=0'

echo "── 8. 组件(进程内) ──"
check "JWT/缓存/Cron/关联"  "php -d error_reporting=E_ERROR -r '
require \"alphakit/bootstrap.php\";
use AlphaKit\Auth\JWT; use AlphaKit\Schedule\CronExpression;
\$t = JWT::encode([\"sub\"=>7], \"k\", \"HS256\", 60); assert(JWT::decode(\$t, \"k\")[\"sub\"]===7);
cache_store()->set(\"x\", [1], 30); assert(cache_store()->get(\"x\")===[1]);
assert((new CronExpression(\"*/5 * * * *\"))->isDue(strtotime(\"2026-01-01 13:05:00\")));
assert(count(App\Models\User::find(1)->tasks())>=1);
echo \"components-ok\";'" 'components-ok'

kill "$SERVER_PID" 2>/dev/null

echo ""
echo "════════════════════════════"
echo "  通过: $PASS   失败: $FAIL"
[[ $FAIL -eq 0 ]] && echo "  ✅ ALL SMOKE TESTS PASSED" || { echo "  ❌ 存在失败"; exit 1; }
