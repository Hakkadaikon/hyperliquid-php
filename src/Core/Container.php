<?php

declare(strict_types=1);

namespace App\Core;

final class Container
{
    private static array $bindings = [];
    private static array $instances = [];

    /**
     * Bind an interface or class to a resolver.
     *
     * @param string $abstract
     * @param callable|string|null $concrete
     * @return void
     */
    public static function bind(string $abstract, callable|string|null $concrete = null): void
    {
        self::$bindings[$abstract] = $concrete ?? $abstract;
    }

    /**
     * Bind a singleton instance.
     *
     * @param string $abstract
     * @param callable|string|null $concrete
     * @return void
     */
    public static function singleton(string $abstract, callable|string|null $concrete = null): void
    {
        self::$bindings[$abstract] = $concrete ?? $abstract;
        self::$instances[$abstract] = null;
    }

    /**
     * Resolve the given abstract type.
     *
     * @param string $abstract
     * @return mixed
     * @throws \Exception
     */
    public static function resolve(string $abstract): mixed
    {
        // Check if an instance already exists (for singletons)
        if (array_key_exists($abstract, self::$instances) && self::$instances[$abstract] !== null) {
            return self::$instances[$abstract];
        }

        // Check if the binding exists
        if (!isset(self::$bindings[$abstract])) {
            throw new \RuntimeException("No binding found for {$abstract}");
        }

        $concrete = self::$bindings[$abstract];

        // If the concrete is a closure, call it
        if (is_callable($concrete)) {
            $object = $concrete(new self());
        } else {
            $object = self::build($concrete);
        }

        // Save the instance for singletons
        if (array_key_exists($abstract, self::$instances)) {
            self::$instances[$abstract] = $object;
        }

        return $object;
    }

    /**
     * Automatically resolve dependencies and instantiate the class.
     *
     * @param string $concrete
     * @return mixed
     * @throws \ReflectionException
     */
    private static function build(string $concrete): mixed
    {
        $reflectionClass = new \ReflectionClass($concrete);

        // Check if the class is instantiable
        if (!$reflectionClass->isInstantiable()) {
            throw new \RuntimeException("Cannot instantiate {$concrete}");
        }

        $constructor = $reflectionClass->getConstructor();

        // If there is no constructor, return a new instance
        if ($constructor === null) {
            return new $concrete();
        }

        $parameters = $constructor->getParameters();
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            if ($type === null) {
                throw new \RuntimeException("Cannot resolve parameter {$parameter->getName()} in {$concrete}");
            }

            $dependencies[] = self::resolve($type->getName());
        }

        return $reflectionClass->newInstanceArgs($dependencies);
    }
}
