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
     * 别名[开发]
     * @var array
     * @preview
     */
    protected $alias = [];

    /**
     * 绑定的类和参数
     * @var array
     */
    protected $bind = [];

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
     * 带参数的绑定
     * @param string $id
     * 标识
     * @param $bind
     * 绑定的完整类名
     * @param array $arguments
     * 类构造函数的参数
     * @return $this
     */
    public function bind(string $id, $bind, array $arguments = [], bool $renew = false)
    {
        $this->alias($id, $bind);
        $this->bind[$bind] = [$arguments, $renew];
        return $this;
    }

    /**
     * 判断是否绑定
     * @param string $id
     * @return bool
     */
    public function bound(string $id)
    {
        return isset($this->bind[$this->getAlias($id)]);
    }

    /**
     * 根据标识获取绑定的参数
     * @param string $id
     * @return mixed
     */
    public function getBound(string $id)
    {
        return $this->bind[$this->getAlias($id)];
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

    /**
     * 判断是否有别名
     * @param string $id
     * @return bool
     */
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
        if ($this->bound($abstract)) {
            [$arguments, $renew] = $this->getBound($abstract);
        }
        if ($renew) {
            $this->remove($abstract);
            return $this->resolve($abstract, $arguments);
        }
        if (false === $this->has($abstract)) {
            $concrete = $this->resolve($abstract, $arguments);
            $this->set($abstract, $concrete);
        }
        return $this->get($abstract);
    }

    /**
     * 注销实例
     * @param string $abstract
     * @return $this
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
        $reflectionClass = new \ReflectionClass($abstract);
        if ($reflectionClass->hasMethod('__setter')) {
            $setter = $reflectionClass->getMethod('__setter');
            if ($setter->isPublic() && $setter->isStatic()) {
                return $setter->invokeArgs(null, $this->bindParams($setter, $arguments));
            }
        }
        return $reflectionClass->newInstanceArgs($this->getConstructorArgs($reflectionClass, $arguments));
    }

    /**
     * 获取构造函数参数
     * @param \ReflectionClass $reflectionClass
     * @param array $arguments
     * @return array
     */
    public function getConstructorArgs(\ReflectionClass $reflectionClass, array $arguments = []): array
    {
        if (null === ($constructor = $reflectionClass->getConstructor())) {
            return $arguments;
        }
        if ($reflectionClass->isInstantiable()) {
            return $this->bindParams($constructor, $arguments);
        }
        throw new ContainerException('Cannot initialize class: ' . $reflectionClass->getName());
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
    public function invokeMethod(array $callable, $arguments = [], bool $renew = false, array $constructorParameters = [])
    {
        [$abstract, $method] = $callable;
        $abstract = $this->getAlias($abstract);
        if ($this->bound($abstract)) {
            [$constructorParameters, $renew] = $this->getBound($abstract);
        }
        $reflectionMethod = new \ReflectionMethod($abstract, $method);
        if (false === $reflectionMethod->isAbstract()) {
            $bindParams = $this->bindParams($reflectionMethod, (array)$arguments);
            if ($reflectionMethod->isPublic()) {
                if ($reflectionMethod->isStatic()) {
                    return $reflectionMethod->invokeArgs(null, $bindParams);
                }
                return $reflectionMethod->invokeArgs(
                    is_object($abstract) ? $abstract : $this->make($abstract, $constructorParameters, $renew),
                    $bindParams
                );
            }
        }
        throw new ContainerException('Unable to call method: ' . $method);
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
        $reflectFunction = new \ReflectionFunction($function);
        return $reflectFunction->invokeArgs($this->bindParams($reflectFunction, $arguments));
    }

    /**
     * 反射获取方法的参数列表
     * @param \ReflectionFunctionAbstract $reflectionMethod
     * 反射方法
     * @param array $arguments
     * 用户传入的参数
     * @return array
     */
    public function bindParams(\ReflectionFunctionAbstract $reflectionMethod, array $arguments = []): array
    {
        $binds     = [];
        $arguments = array_values($arguments);
        foreach ($reflectionMethod->getParameters() as $dependence) {
            $type = $dependence->getType();
            // TODO Closure的处理，之前做了，但是忘记在哪里会有问题
            if (is_null($type) || $type->isBuiltin()) {
                if (!empty($arguments)) {
                    $binds[] = array_shift($arguments);
                }
            } else {
                $binds[] = $this->make($type->getName());
            }
        }
        return $binds;
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

}
