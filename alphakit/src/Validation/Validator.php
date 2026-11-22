<?php

declare(strict_types=1);

namespace AlphaKit\Validation;

/**
 * 数据验证器
 *
 * $v = validator($data, [
 *     'email' => 'required|email|unique:users,email',
 *     'age'   => 'required|integer|min:1|max:150',
 * ]);
 * $v->fails(); $v->errors(); $v->validated();
 *
 * 规则:required nullable integer numeric string boolean email url ip date in:a,b
 *       min:n max:n between:a,b regex:/…/ confirmed same:field unique:table,column(排除id) exists:table,column
 */
class Validator
{
    /** @var array<string, array<string>> 字段 => 错误消息 */
    protected array $errors = [];

    public function __construct(protected array $data, protected array $rules)
    {
    }

    public function fails(): bool
    {
        return $this->errors() !== [];
    }

    public function passes(): bool
    {
        return !$this->fails();
    }

    /** @return array<string, array<string>> */
    public function errors(): array
    {
        if ($this->errors === []) {
            $this->validate();
        }
        return $this->errors;
    }

    /** 首条错误消息 */
    public function firstError(): ?string
    {
        foreach ($this->errors() as $messages) {
            return $messages[0] ?? null;
        }
        return null;
    }

    /** 通过校验的数据(仅含规则中出现的字段) */
    public function validated(): array
    {
        if ($this->fails()) {
            throw new \RuntimeException('校验未通过,请先检查 fails()');
        }
        $result = [];
        foreach (array_keys($this->rules) as $field) {
            if ($this->has($field)) {
                $result[$field] = $this->get($field);
            }
        }
        return $result;
    }

    protected function validate(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $rules = is_array($ruleString) ? $ruleString : explode('|', $ruleString);
            $value = $this->get($field);
            $isEmpty = $value === null || $value === '' || $value === [];

            $isRequired = in_array('required', $rules, true);
            if ($isEmpty) {
                if ($isRequired) {
                    $this->addError($field, "{$field} 不能为空");
                }
                continue; // 可空字段空值跳过后续规则
            }

            foreach ($rules as $rule) {
                if ($rule === 'required' || $rule === 'nullable' || $rule === '') {
                    continue;
                }
                [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);
                $this->applyRule($field, $name, $parameter, $value);
            }
        }
    }

    /** @param mixed $value */
    protected function applyRule(string $field, string $name, ?string $parameter, $value): void
    {
        switch ($name) {
            case 'integer':
                if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $this->addError($field, "{$field} 必须是整数");
                }
                break;
            case 'numeric':
                if (!is_numeric($value)) {
                    $this->addError($field, "{$field} 必须是数字");
                }
                break;
            case 'string':
                if (!is_string($value)) {
                    $this->addError($field, "{$field} 必须是字符串");
                }
                break;
            case 'boolean':
                if (!in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false'], true)) {
                    $this->addError($field, "{$field} 必须是布尔值");
                }
                break;
            case 'array':
                if (!is_array($value)) {
                    $this->addError($field, "{$field} 必须是数组");
                }
                break;
            case 'email':
                if (!filter_var((string)$value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, "{$field} 必须是合法邮箱");
                }
                break;
            case 'url':
                if (!filter_var((string)$value, FILTER_VALIDATE_URL)) {
                    $this->addError($field, "{$field} 必须是合法 URL");
                }
                break;
            case 'ip':
                if (!filter_var((string)$value, FILTER_VALIDATE_IP)) {
                    $this->addError($field, "{$field} 必须是合法 IP");
                }
                break;
            case 'date':
                if (!strtotime((string)$value)) {
                    $this->addError($field, "{$field} 必须是合法日期");
                }
                break;
            case 'min':
                if ($this->getSize($value) < (float)$parameter) {
                    $this->addError($field, "{$field} 长度/值不能小于 {$parameter}");
                }
                break;
            case 'max':
                if ($this->getSize($value) > (float)$parameter) {
                    $this->addError($field, "{$field} 长度/值不能大于 {$parameter}");
                }
                break;
            case 'between':
                [$a, $b] = array_pad(explode(',', (string)$parameter), 2, '0');
                $size = $this->getSize($value);
                if ($size < (float)$a || $size > (float)$b) {
                    $this->addError($field, "{$field} 必须介于 {$a} 与 {$b} 之间");
                }
                break;
            case 'in':
                $options = explode(',', (string)$parameter);
                if (!in_array((string)$value, array_map('strval', $options), true)) {
                    $this->addError($field, "{$field} 必须是: " . implode(' / ', $options));
                }
                break;
            case 'not_in':
                $options = explode(',', (string)$parameter);
                if (in_array((string)$value, array_map('strval', $options), true)) {
                    $this->addError($field, "{$field} 不能是: " . implode(' / ', $options));
                }
                break;
            case 'regex':
                if (!preg_match((string)$parameter, (string)$value)) {
                    $this->addError($field, "{$field} 格式不正确");
                }
                break;
            case 'confirmed':
                if ($value !== $this->get($field . '_confirmation')) {
                    $this->addError($field, "{$field} 两次输入不一致");
                }
                break;
            case 'same':
                if ($value !== $this->get((string)$parameter)) {
                    $this->addError($field, "{$field} 必须与 {$parameter} 一致");
                }
                break;
            case 'alpha_dash':
                if (!preg_match('/^[\w\-]+$/u', (string)$value)) {
                    $this->addError($field, "{$field} 只能包含字母数字下划线中划线");
                }
                break;
            case 'mobile':
                if (!preg_match('/^1[3-9]\d{9}$/', (string)$value)) {
                    $this->addError($field, "{$field} 必须是合法手机号");
                }
                break;
            case 'unique':
                // unique:table,column,ignoreId
                $parts = array_pad(explode(',', (string)$parameter), 3, null);
                $table = $parts[0];
                $column = $parts[1] ?? $field;
                $ignoreId = $parts[2] ?? null;

                $qb = \AlphaKit\Database\QueryBuilder::table($table)->where($column, '=', $value);
                if ($ignoreId !== null && $ignoreId !== '') {
                    $qb->whereGroup(function ($q) use ($ignoreId) {
                        $q->where('id', '!=', $ignoreId);
                    });
                }
                if ($qb->exists()) {
                    $this->addError($field, "{$field} 已被占用");
                }
                break;
            case 'exists':
                // exists:table,column
                [$table, $column] = array_pad(explode(',', (string)$parameter), 2, $field);
                if (!\AlphaKit\Database\QueryBuilder::table($table)->where($column, '=', $value)->exists()) {
                    $this->addError($field, "{$field} 不存在");
                }
                break;
            default:
                throw new \InvalidArgumentException("未知验证规则 [{$name}]");
        }
    }

    /** 数值型返回数值本身,字符串返回长度 */
    protected function getSize($value): float
    {
        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
            return (float)$value;
        }
        if (is_array($value)) {
            return (float)count($value);
        }
        return (float)mb_strlen((string)$value);
    }

    protected function has(string $field): bool
    {
        return array_key_exists($field, $this->data);
    }

    /** @return mixed */
    protected function get(string $field)
    {
        // 支持 a.b 点号取嵌套
        if (str_contains($field, '.')) {
            $value = $this->data;
            foreach (explode('.', $field) as $segment) {
                if (!is_array($value) || !array_key_exists($segment, $value)) {
                    return null;
                }
                $value = $value[$segment];
            }
            return $value;
        }
        return $this->data[$field] ?? null;
    }

    protected function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }
}
