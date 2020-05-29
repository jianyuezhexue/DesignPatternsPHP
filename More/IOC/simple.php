<?php

class Container
{
    protected static $_singleton = [];

    // 添加一个实例到单例
    public static function singleton($instance)
    {
        if ( ! is_object($instance)) {
            throw new InvalidArgumentException("Object need!");
        }
        $class_name = get_class($instance);
        // singleton not exist, create
        if ( ! array_key_exists($class_name, self::$_singleton)) {
            self::$_singleton[$class_name] = $instance;
        }
    }
    // 获取一个单例实例
    public static function getSingleton($class_name)
    {
        return array_key_exists($class_name, self::$_singleton) ?
            self::$_singleton[$class_name] : NULL;
    }
    // 销毁一个单例实例
    public static function unsetSingleton($class_name)
    {
        self::$_singleton[$class_name] = NULL;
    }


    // 改造 getInstance 方法
    public static function getInstance($class_name, $params = [])
    {
        // 获取反射实例
        $reflector = new ReflectionClass($class_name);
        // 获取反射实例的构造方法
        $constructor = $reflector->getConstructor();

        $di_params = [];
        if ($constructor) {
            // 获取反射实例构造方法的形参
            foreach ($constructor->getParameters() as $param) {
                $class = $param->getClass();
                if ($class) {
                    // 如果依赖是单例，则直接获取，反之创建实例
                    $singleton = self::getSingleton($class->name);
                    $di_params[] = $singleton ? $singleton : self::getInstance($class->name);
                }
            }
        }

        $di_params = array_merge($di_params, $params);
        // 创建实例
        return $reflector->newInstanceArgs($di_params);
    }

    //增加 run 方法
    public static function run($class_name, $method, $params = [], $construct_params = [])
    {
        if ( ! class_exists($class_name)) {
            throw new BadMethodCallException("Class $class_name is not found!");
        }

        if ( ! method_exists($class_name, $method)) {
            throw new BadMethodCallException("undefined method $method in $class_name !");
        }
        // 获取外层实例 new $class_name
        $instance = self::getInstance($class_name, $construct_params);

        //以下是为了获取 $method 方法的参数

        // 通过反射实例，获取 $class_name 类的相关方法和属性等
        $reflector = new \ReflectionClass($class_name);
        // 获取方法
        $reflectorMethod = $reflector->getMethod($method);

        $di_params = [];

        // 查找方法的形参 $method
        foreach ($reflectorMethod->getParameters() as $param) {
            $class = $param->getClass(); // 如果类，则实例
            if ($class) {
                $singleton = self::getSingleton($class->name);
                $di_params[] = $singleton ? $singleton : self::getInstance($class->name);
            }
        }

        // 运行方法
        return call_user_func_array([$instance, $method], array_merge($di_params, $params));
    }
}


class A
{
    public $count = 10;
}

class B
{
    public function getCount(A $a, $count)
    {
        return $a->count + $count;
    }
}

$result = Container::run(B::class, 'getCount', [10]);
var_dump($result); // result is 20