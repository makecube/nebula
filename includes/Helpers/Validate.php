<?php

namespace Nebula\Helpers;

class Validate
{
    /**
     * 验证数据
     *
     * @var array
     */
    private $data = [];

    /**
     * 验证规则
     *
     * @var array
     */
    private $rules = [];

    /**
     * 验证结果
     *
     * @var array
     */
    public $result = [];

    public function __construct($data, $rules)
    {
        $this->data = $data;
        $this->rules = $rules;
    }

    /**
     * 验证是否为邮箱
     *
     * @param string $value
     * @return bool
     */
    public function email($value)
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL);
    }

    /**
     * 一致性验证
     */
    public function confirm($value, $key)
    {
        $confirmValue = $this->data[$key] ?? null;
        return null !== $confirmValue ? $value === $this->data[$key] : false;
    }

    /**
     * 必填验证
     *
     * @param string $name 数据项名称
     */
    public function required($value)
    {
        return !empty($value);
    }

    /**
     * 运行验证
     *
     * @return bool|array 验证成功返回 true，失败返回错误消息
     */
    public function run()
    {
        $this->result = [];

        foreach ($this->rules as $key => $rule) {
            foreach ($rule as $ruleItem) {
                $value = $this->data[$key] ?? null;
                if ($ruleItem['type'] === 'confirm') {
                    if (!$this->{$ruleItem['type']}($value, $ruleItem['key'])) {
                        array_push($this->result, [
                            'key' => $key,
                            'message' => $ruleItem['message'],
                        ]);
                    }
                } else {
                    if (!$this->{$ruleItem['type']}($value)) {
                        array_push($this->result, [
                            'key' => $key,
                            'message' => $ruleItem['message'],
                        ]);
                    }
                }
            }
        }

        return count($this->result) === 0;
    }
}
