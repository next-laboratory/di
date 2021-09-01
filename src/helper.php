<?php

if (false === function_exists('app')) {

    /**
     * 容器实例化和获取实例
     * @param string|null $id
     * @param array $arguments
     * @param bool $renew
     * @return mixed|object
     */
    function app(string $id = null, array $arguments = [], bool $renew = false)
    {
        $app = App::getInstance();
        return is_null($id) ? $app : $app->make($id, $arguments, $renew);
    }
}

if (false === function_exists('invoke')) {

    /**
     * 容器调用方法
     * @param array|Closure $callable
     * 数组或者闭包
     * @param array $arguments
     * 给方法传递的参数列表
     * @param bool $renew
     * 重新实例化，仅$callable为数组时候生效
     * @param array $constructorParameters
     * 构造函数参数数组，仅$callable为数组时候生效
     * @return mixed
     * @throws Exception
     */
    function invoke($callable, array $arguments = [], bool $renew = false, array $constructorParameters = [])
    {
        if (is_array($callable)) {
            return App::getInstance()->invokeMethod($callable, $arguments, $renew, $constructorParameters);
        }
        if ($callable instanceof Closure) {
            return App::getInstance()->invokeFunc($callable, $arguments);
        }
        throw new ContainerException('Cannot invoke method.');
    }
}

if (false === function_exists('make')) {
    function make(string $id, array $arguments = [], $renew = false)
    {
        return \Max\App::getInstance()->make($id, $arguments, $renew);
    }
}