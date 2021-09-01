<?php

use Max\Container;

if (false === function_exists('container')) {
    /**
     * 返回容器实例
     * @return Container
     */
    function container()
    {
        return Container::getInstance();
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
            return container()->invokeMethod($callable, $arguments, $renew, $constructorParameters);
        }
        if ($callable instanceof Closure) {
            return container()->invokeFunc($callable, $arguments);
        }
        throw new ContainerException('Cannot invoke method.');
    }
}

if (false === function_exists('make')) {
    /**
     * 实例化类
     * @param string $id
     * @param array $arguments
     * @param false $renew
     * @return mixed|object
     */
    function make(string $id, array $arguments = [], $renew = false)
    {
        return container()->make($id, $arguments, $renew);
    }
}