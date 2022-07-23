<?php

namespace Nebula;

class Response
{
    /**
     * 单例实例
     *
     * @var Response
     */
    private static $instance;

    /**
     * 设置 cookie
     *
     * @param string $name
     * @param string $value
     * @param int $expiresOrOptions
     * @param string $path
     * @param string $domain
     * @param bool $secure
     * @param bool $httponly
     * @return $this
     */
    public function setCookie($name, $value, $expiresOrOptions, $path, $domain, $secure, $httponly)
    {
        setrawcookie($name, rawurlencode($value), $expiresOrOptions, $path, $domain, $secure, $httponly);
        return $this;
    }

    /**
     * 加载视图
     *
     * @param string $fileName 文件名
     * @param array $data 视图数据
     * @return $this
     */
    public function render($fileName, $data = [])
    {
        header('Content-Type:text/html; charset=utf-8');
        ob_start();
        $filePath = NEBULA_ROOT_PATH . 'content/themes/default/' . $fileName . '.php';
        if (file_exists($filePath)) {
            extract($data);
            include $filePath;
        }
        $html = ob_get_contents();
        ob_end_clean();
        echo $html;
        return $this;
    }

    /**
     * 重定向
     *
     * @param string $url 重定向地址
     * @return $this
     */
    public function redirect($url)
    {
        header('Location:' . $url);
        return $this;
    }

    /**
     * 获取单例实例
     *
     * @return Response
     */
    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }
}
