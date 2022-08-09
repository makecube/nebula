<?php

namespace Nebula\Helpers;

use PDO;
use PDOException;

class MySQL
{
    /**
     * 单例实例
     *
     * @var MySQL
     */
    private static $instance;

    /**
     * @var PDO
     */
    private $mysql;

    /**
     * @var string
     */
    private $prefix;

    /**
     * 操作类型
     *
     * @var string
     */
    private $type;

    /**
     * SQL 语句
     *
     * @var string
     */
    private $query = '';

    /**
     * SQL 参数
     *
     * @var array
     */
    private $params = [];

    private function __construct()
    {
    }

    /**
     * 初始化
     *
     * @param array $config 数据库配置
     * @return void
     */
    public function init($config)
    {
        try {
            $this->mysql = new PDO('mysql:dbname=' . $config['dbname'] . ';host=' . $config['host'] . ';port=' . $config['port'], $config['username'], $config['password'], [PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8']);
        } catch (PDOException $e) {
            throw $e;
        }

        $this->prefix = $config['prefix'];
    }

    /**
     * 表名解析
     *
     * @param string $table 表名
     * @return string
     */
    private function tableParse($table)
    {
        return '`' . $this->prefix . $table . '`';
    }

    /**
     * 列名解析
     *
     * @param string $column
     * @return string
     */
    private function columnParse($column)
    {
        $columnArray = explode('.', $column);
        array_walk($columnArray, function (&$part) {
            $part = '`' . $part . '`';
        });

        return implode('.', $columnArray);
    }

    /**
     * 多项列名解析
     *
     * @param string $column
     * @return string
     */
    private function columnsParse($columns)
    {
        array_walk($columns, function (&$column) {
            $column = $this->columnParse($column);
        });

        return implode(', ', $columns);
    }

    /**
     * where 格式化
     *
     * @param array $wheres
     * @param string $separator
     * @return string
     */
    private function whereParse($wheres, $separator = 'AND')
    {
        $where = '';

        foreach ($wheres as $key => $value) {
            if ($key === 'AND' || $key === 'OR') {
                end($wheres);
                if ($key === key($wheres)) {
                    $where .= '(' . $this->whereParse($value, $key) . ')';
                } else {
                    $where .= '(' . $this->whereParse($value, $key) . ') ' . $separator . ' ';
                }
            } else {
                preg_match('/^(.*)\[(.*)\]$/', $key, $matches);
                $column = $this->columnParse($matches[1] ?? $key);
                $operator = $matches[2] ?? '=';
                $where .=  $column . ' ' . $operator . ' ? ' . $separator . ' ';

                array_push($this->params, $value);
            }
        }

        return rtrim($where, ' ' . $separator . ' ');
    }

    /**
     * 查询构造器
     *
     * @param string $table 表名
     * @param array $columns
     * @return $this
     */
    public function select($table, $columns)
    {
        $this->type = 'SELECT';

        $this->query = 'SELECT ' . $this->columnsParse($columns) . ' FROM ' . $this->tableParse($table);

        return $this;
    }

    /**
     * 只获取一条数据
     *
     * @param string $table 表名
     * @param array $columns
     * @return $this
     */
    public function get($table, $columns)
    {
        $this->type = 'GET';

        $this->query = 'SELECT ' . $this->columnsParse($columns) . ' FROM ' . $this->tableParse($table);

        return $this;
    }

    /**
     * 更新数据
     *
     * @param string $table 表名
     * @param array $values 数据
     * @return $this
     */
    public function update($table, $values)
    {
        $this->type = 'UPDATE';

        $stack = [];
        foreach ($values as $key => $value) {
            array_push($stack, $this->columnParse($key) . ' = ?');
            array_push($this->params, $value);
        }

        $this->query = 'UPDATE ' . $this->tableParse($table) . ' SET ' . implode(', ', $stack);

        return $this;
    }

    /**
     * 插入数据
     *
     * @param string $table 表名
     * @param array $values 数据
     * @return bool
     */
    public function insert($table, $values)
    {
        if (!isset($values[0])) {
            $values = [$values];
        }

        $columns = $this->columnsParse(array_keys($values[0]));
        $stack = [];
        $params = [];

        foreach ($values as $value) {
            $values = array_map(function () {
                return '?';
            }, $value);

            array_push($stack, '(' . implode(', ', $values) . ')');

            $params = array_merge($params, array_values($value));
        }

        $sth = $this->mysql->prepare('INSERT INTO ' . $this->tableParse($table) . ' (' . $columns . ') VALUES ' . implode(', ', $stack));

        return $sth->execute($params);
    }

    /**
     * 删除数据
     *
     * @param string $table 表名
     * @return $this
     */
    public function delete($table)
    {
        $this->type = 'DELETE';
        $this->query = 'DELETE FROM ' . $this->tableParse($table);

        return $this;
    }

    /**
     * 创建表
     *
     * @param string $table 表名
     * @param array $columns 列定义
     * @return bool
     */
    public function create($table, $columns)
    {
        $options = [];
        foreach ($columns as $column => $option) {
            array_push($options, $this->columnParse($column) . ' ' . implode(' ', $option));
        }

        $table = $this->tableParse($table);

        return false !== $this->mysql->exec('CREATE TABLE ' . $table . ' (' . implode(', ', $options) . ')');
    }

    /**
     * 删除表
     *
     * @param string $table 表名
     * @return bool
     */
    public function drop($table)
    {
        $table = $this->tableParse($table);

        return false !== $this->mysql->exec('DROP TABLE ' . $table);
    }

    /**
     * where 构造
     *
     * @param array $wheres 条件列表
     * @param string $rel 关系
     * @return $this
     */
    public function where($wheres)
    {
        $this->query .= ' WHERE ' . $this->whereParse($wheres);

        return $this;
    }

    /**
     * 执行
     *
     * @return mixed
     */
    public function execute()
    {
        $sth = $this->mysql->prepare($this->query);
        $result = $sth->execute($this->params);

        $this->query = '';
        $this->params = [];

        if ($this->type === 'SELECT') {
            return json_decode(json_encode($sth->fetchAll(PDO::FETCH_CLASS)), true);
        } else if ($this->type === 'GET') {
            return json_decode(json_encode($sth->fetchAll(PDO::FETCH_CLASS)), true)[0] ?? [];
        } else {
            return $result;
        }
    }

    /**
     * 获取单例实例
     *
     * @return MySQL
     */
    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }
}