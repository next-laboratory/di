<?php
declare(strict_types=1);

namespace Max;

use Max\Exception\{ContainerException, NotFoundException};
use Psr\Container\ContainerInterface;
use ArrayAccess;

/**
 * Class Container
 * @package Max
 * @author chengyao
 */
class Container implements ContainerInterface, ArrayAccess
{

    /**
     * 容器实例
     * @var
     */
    protected static $instance;

    /**
     * 容器
     * @var array
     */
    protected static $instances = [];

    /**
     * 绑定的类名
     * @var array|string[]
     */
    protected $bind = [];

    /**
     * 别名[开发]
     * @var array
     * @preview
     */
    protected $alias = [];

    /**
     * 实例标识
     * @var bool
     */
    protected $refreshable = false;

    /**
     * 设置实例
     * @param $abstract
     * @param $concrete
     */
    public static function instance($abstract, $concrete)
    {
        static::$instances[$abstract] = $concrete;
    }

    /**
     * 容器实例
     * @return mixed
     */
    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new static;
        }
        return self::$instance;
    }

    /**
     * 使用静态方法设置
     * @param string $abstract
     * @param object $concrete
     */
    public static function setInstance(ContainerInterface $container)
    {
        self::$instance = $container;
    }

    /**
     * 将实例化的类存放到数组中
     * @param string $abstract
     * 类名
     * @param object $instance
     * 实例
     */
    public function set(string $abstract, $instance)
    {
        static::$instances[$this->getAlias($abstract)] = $instance;
    }

    /**
     * 获取存在的实例
     * @param string $id
     * 类的标识[完整类名]
     * @return mixed
     */
    public function get(string $id)
    {
        $abstract = $this->getAlias($id);
        if ($this->has($abstract)) {
            return static::$instances[$abstract];
        }
        throw new NotFoundException('No instance found: ' . $abstract);
    }

    /**
     * 判断类的实例是否存在
     * @param string $id
     * 类的标识[完整类名]
     * @return bool
     */
    public function has(string $id): bool
    {
        return isset(static::$instances[$this->getAlias($id)]);
    }

    /**
     * 绑定类到标识
     * @param string $id
     * 绑定的类标识
     * @param mixed $concrete
     * 绑定的类名
     */
    public function bind(string $id, $concrete)
    {
        $this->bind[$this->getAlias($id)] = $concrete;
    }

    /**
     * 判断标识是否被绑定
     * @param $name
     * @return string
     */
    public function bound(string $id)
    {
        return isset($this->bind[$this->getAlias($id)]);
    }

    /**
     * 添加绑定[开发]
     * @param string $id
     * @param string $class
     * @return $this
     */
    public function alias(string $id, string $class)
    {
        $this->alias[$id] = $class;
        return $this;
    }

    /**
     * 移除别名
     * @param string $id
     * @return $this
     */
    public function unAlias(string $id)
    {
        if ($this->hasAlias($id)) {
            unset($this->alias[$id]);
        }
        return $this;
    }

    public function hasAlias(string $id)
    {
        return isset($this->alias[$id]);
    }

    /**
     * 通过标识获取别名
     * @param string $id
     * @return string
     */
    protected function getAlias(string $id): string
    {
        return $this->alias[$id] ?? $id;
    }

    /**
     * 注入的外部接口方法
     * @param string $abstract
     * 需要实例化的类名
     * @param array $arguments
     * 索引数组的参数列表
     * @param bool $renew
     * true 移除已经存在的实例重新实例化直接返回
     * @return mixed
     */
    public function make(string $abstract, array $arguments = [], bool $renew = false)
    {
        $abstract = $this->getAlias($abstract);
        if ($abstract instanceof \Closure) {
            return $abstract();
        }
        if ($renew) {
            $this->remove($abstract);
            return $this->resolve($abstract, $arguments);
        }
        if (!$this->has($abstract)) {
            $concrete = $this->resolve($abstract, $arguments);
            if ($this->refreshable) {
                $this->refreshable = false;
                return $concrete;
            }
            $this->set($abstract, $concrete);
        }
        return $this->get($abstract);
    }

    /**
     * 解除类的绑定
     * @param string $id
     * @return $this
     */
    public function unbind(string $id)
    {
        if ($this->bound($id)) {
            unset($this->bind[$id]);
        }
        return $this;
    }

    /**
     * 注销实例
     * @param string $abstract
     * @return bool
     */
    public function remove(string $abstract)
    {
        $abstract = $this->getAlias($abstract);
        if ($this->has($abstract)) {
            unset(static::$instances[$abstract]);
        }
        return $this;
    }

    /**
     * @param string $abstract
     * @param array $arguments
     * @return object
     * @throws \ReflectionException
     */
    protected function resolve(string $abstract, array $arguments)
    {
        $arguments       = array_values($arguments);
        $reflectionClass = new \ReflectionClass($abstract);
        if ($reflectionClass->hasProperty('__refreshable')) {
            $refreshable = $reflectionClass->getProperty('__refreshable');
            if ($refreshable->isStatic() && $refreshable) {
                $this->refreshable = true;
            }
        }
        if ($reflectionClass->hasMethod('__setter')) {
            $setter = $reflectionClass->getMethod('__setter');
            if ($setter->isPublic() && $setter->isStatic()) {
                return $setter->invokeArgs(null, $this->bindParams($setter, $arguments));
            }
        }
        if (null === ($constructor = $reflectionClass->getConstructor())) {
            return new $abstract(...$arguments);
        }
        if ($constructor->isPublic()) {
            return new $abstract(...$this->bindParams($constructor, $arguments));
        }
        throw new ContainerException('Cannot initialize class: ' . $abstract);
    }

    /**
     * 调用类的方法
     * @param array $callable
     * 可调用的类或者实例和方法数组[$class|$concrete, $method]
     * @param array $arguments
     * 给方法传递的参数
     * @param false $renew
     * true表示单例
     * @param array $constructorParameters
     * 给构造方法传递的参数
     * @return mixed
     */
    public function invokeMethod(array $callable, $arguments = [], bool $renew = true, array $constructorParameters = [])
    {
        [$abstract, $method] = [$callable[0], $callable[1]];
        if (is_string($abstract)) {
            $abstract = $this->getAlias($abstract);
        }
        $reflectionMethod = (new \ReflectionClass($abstract))->getMethod($method);
        if ($reflectionMethod->isPublic()) {
            $injectArguments = $this->bindParams($reflectionMethod, (array)$arguments);
            if ($reflectionMethod->isStatic()) {
                return $reflectionMethod->invokeArgs(null, $injectArguments);
            }
            if (!is_object($abstract)) {
                $abstract = $this->make($abstract, $constructorParameters, $renew);
            }
            return $abstract->{$method}(...$injectArguments);
        }
        throw new ContainerException('Unable to call method: ' . $method);
    }

    /**
     * 直接向容器推送实例
     * @param string $id
     * @param $concrete
     * @return bool
     */
    public function push(string $id, $concrete): bool
    {
        if ($this->has($id)) {
            return false;
        }
        array_push(static::$instances, $concrete);
        return true;
    }

    /**
     * 依赖注入调用闭包
     * @param \Closure $function
     * @param array $arguments
     * @return mixed
     * @throws \ReflectionException
     */
    public function invokeFunc(\Closure $function, array $arguments = [])
    {
        return $function(...$this->bindParams(
            (new \ReflectionFunction($function)),
            $arguments
        ));
    }

    /**
     * 反射获取方法的参数列表
     * @param \ReflectionFunctionAbstract $reflectionMethod
     * 反射方法
     * @param array $arguments
     * 用户传入的参数
     * @return array
     */
    protected function bindParams(\ReflectionFunctionAbstract $reflectionMethod, array $arguments): array
    {
        $dependencies = $reflectionMethod->getParameters();
        $injection    = [];
        foreach ($dependencies as $dependence) {
            $type = $dependence->getType();
            // TODO Closure的处理，之前做了，但是忘记在哪里会有问题
            if (is_null($type) || $type->isBuiltin()) {
                if (!empty($arguments)) {
                    $injection[] = array_shift($arguments);
                }
            } else {
                $injection[] = $this->make($type->getName());
            }
        }
        return $injection;
    }

    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    public function offsetGet($abstract)
    {
        return $this->make($abstract);
    }

    public function offsetSet($offset, $value)
    {
        $this->set($offset, $value);
    }

    public function offsetUnset($offset)
    {
        return $this->remove($offset);
    }

    public function __get($abstract)
    {
        return $this->make($abstract);
    }

    public function __isset(string $id): bool
    {
        return isset(static::$instances[$id]);
    }

    private function __clone()
    {
    }

    private function __construct()
    {
    }

    public function __destruct()
    {
        static::$instances = null;
    }

}
