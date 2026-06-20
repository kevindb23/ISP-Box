<?php

namespace Framework;

use ReflectionClass;
use Exception;

class Container
{
    protected array $bindings = [];

    /*
    |--------------------------------------------------------------------------
    | Bind Class
    |--------------------------------------------------------------------------
    */

    public function bind(string $abstract, callable $resolver)
    {
        $this->bindings[$abstract] = $resolver;
    }

    /*
    |--------------------------------------------------------------------------
    | Resolve Class
    |--------------------------------------------------------------------------
    */

    public function get(string $abstract)
    {
        if (isset($this->bindings[$abstract])) {
            return $this->bindings[$abstract]($this);
        }

        return $this->resolve($abstract);
    }

    /*
    |--------------------------------------------------------------------------
    | Automatic Dependency Resolution
    |--------------------------------------------------------------------------
    */

    protected function resolve(string $class)
    {
        $reflection = new ReflectionClass($class);

        if (!$reflection->isInstantiable()) {
            throw new Exception("Class {$class} is not instantiable");
        }

        $constructor = $reflection->getConstructor();

        if (!$constructor) {
            return new $class;
        }

        $dependencies = [];

        foreach ($constructor->getParameters() as $param) {

            $type = $param->getType();

            if (!$type || $type->isBuiltin()) {
                throw new Exception("Cannot resolve dependency for {$class}");
            }

            $dependencies[] = $this->get($type->getName());
        }

        return $reflection->newInstanceArgs($dependencies);
    }
}
