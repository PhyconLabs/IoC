<?php
namespace SDS\IoC;

/**
 * Manages Dependency Injection bindings following Inversion Of Control principles.
 */
class Container
{
    const BIND_SINGLETON = "singleton";
    const BIND_ARGUMENT = "argument";
    const BIND_OVERWRITE_IF_EXISTS = "overwriteIfExists";
    
    /**
     * Registered concretes.
     *
     * @var array
     */
    protected $concretes;
    
    public function __construct()
    {
        $this->concretes = [];
    }
    
    /**
     * Registers new object or argument binding.
     *
     * @param string $abstract Binding name.
     * @param mixed $concrete Binding value.
     * @param array $options Binding options.
     *
     * @return \SDS\IoC\Container $this for chaining.
     */
    public function bind($abstract, $concrete, array $options = [])
    {
        $isSingleton = (isset($options[static::BIND_SINGLETON]) && $options[static::BIND_SINGLETON]);
        $isArgument = isset($options[static::BIND_ARGUMENT]) ? $options[static::BIND_ARGUMENT] : null;
        $overwriteIfExists = (isset($options[static::BIND_OVERWRITE_IF_EXISTS]) && $options[static::BIND_OVERWRITE_IF_EXISTS]);
        
        // If wasn't defined in options guess from $abstract name
        if (!isset($isArgument)) {
            $isArgument = $this->isArgumentAbstract($abstract);
        }
        
        $concrete = $this->createConcrete($abstract, $concrete, $isArgument, $isSingleton);
        
        return $overwriteIfExists ? $this->setConcrete($abstract, $concrete) : $this->addConcrete($abstract, $concrete);
    }
    
    /**
     * Registers new singleton object or argument binding.
     *
     * @param string $abstract Binding name.
     * @param mixed $concrete Binding value.
     * @param array $options Binding options ( option for singleton will be automatically added ).
     *
     * @return \SDS\IoC\Container $this for chaining.
     */
    public function bindSingleton($abstract, $concrete, array $options = [])
    {
        return $this->bind($abstract, $concrete, array_merge($options, [
            static::BIND_SINGLETON => true
        ]));
    }
    
    /**
     * Registers new argument binding.
     *
     * @param string $abstractClass Binding class name.
     * @param string $abstractArgument Binding argument name.
     * @param mixed $concrete Binding value.
     * @param array $options Binding options ( option for argument will be automatically added )
     *
     * @return \SDS\IoC\Container $this for chaining.
     */
    public function bindArgument($abstractClass, $abstractArgument, $concrete, array $options = [])
    {
        return $this->bind("{$abstractClass}::{$abstractArgument}", $concrete, array_merge($options, [
            static::BIND_ARGUMENT => true
        ]));
    }
    
    /**
     * Registers new singleton argument binding.
     *
     * @param string $abstractClass Binding class name.
     * @param string $abstractArgument Binding argument name.
     * @param mixed $concrete Binding value.
     * @param array $options Binding options ( options for argument and singleton will be automatically added )
     *
     * @return \SDS\IoC\Container $this for chaining.
     */
    public function bindSingletonArgument($abstractClass, $abstractArgument, $concrete, array $options = [])
    {
        return $this->bindArgument($abstractClass, $abstractArgument, $concrete, array_merge($options, [
            static::BIND_SINGLETON => true
        ]));
    }
    
    /**
     * Removes registered binding.
     *
     * @param string $abstract Binding name.
     *
     * @return \SDS\IoC\Container $this for chaining.
     */
    public function unbind($abstract)
    {
        return $this->removeConcrete($abstract);
    }
    
    /**
     * Resolves registered binding. It will also try to resolve unregistered class bindings through
     * Reflection API.
     *
     * @param string $abstract Binding name.
     * @param array $arguments Arguments passed to binding resolver.
     *
     * @throws \SDS\IoC\Exceptions\IoCException If binding can't be resolved
     *         ( subclasses of this exception will be thrown ).
     *
     * @return mixed Resolved binding.
     */
    public function make($abstract, array $arguments = [])
    {
        $concrete = $this->getConcrete($abstract);
        
        // try to automagically resolve unregistered bindings
        if (!isset($concrete)) {
            // We can only auto-resolve class bindings so throw an exception if
            // we have to resolve an argument binding.
            if ($this->isArgumentAbstract($abstract)) {
                throw new Exceptions\ArgumentBindingException(
                    "Can't resolve argument `{$abstract}`."
                );
            } else {
                $concrete = $this->createConcrete($abstract, $abstract, false, false);
            }
        }
        
        return $concrete->make($arguments);
    }
    
    /**
     * Determine if binding is registered.
     *
     * @param string $abstract Binding name.
     *
     * @return bool
     */
    public function isBound($abstract)
    {
        return $this->hasConcrete($abstract);
    }
    
    /**
     * Determine if binding is singleton.
     *
     * @param string $abstract Binding name.
     *
     * @return bool
     */
    public function isSingleton($abstract)
    {
        $concrete = $this->getConcrete($abstract);
        
        return (
            isset($concrete) &&
            (
                $concrete instanceof SingletonObjectConcrete ||
                $concrete instanceof SingletonArgumentConcrete
            )
        );
    }
    
    /**
     * Determine if binding is an argument binding.
     *
     * @param string $abstract Binding name.
     *
     * @return bool
     */
    public function isArgument($abstract)
    {
        $concrete = $this->getConcrete($abstract);
        
        return (isset($concrete) && $concrete instanceof ArgumentConcrete);
    }
    
    /**
     * Creates correct Concrete child class based on given options.
     *
     * @param string $abstract Binding name.
     * @param mixed $concrete Binding value.
     * @param bool $isArgument Whether binding is an argument binding.
     * @param bool $isSingleton Whether binding is singleton.
     *
     * @return \SDS\IoC\Concrete Correct child class.
     */
    protected function createConcrete($abstract, $concrete, $isArgument, $isSingleton)
    {
        if ($isArgument) {
            if ($isSingleton) {
                $concrete = new SingletonArgumentConcrete($this, $concrete);
            } else {
                $concrete = new ArgumentConcrete($this, $concrete);
            }
        } else {
            if ($isSingleton) {
                $concrete = new SingletonObjectConcrete($this, $concrete, $abstract);
            } else {
                $concrete = new ObjectConcrete($this, $concrete, $abstract);
            }
        }
        
        return $concrete;
    }
    
    /**
     * Registers binding if one with that name hasn't already been registered.
     *
     * @param string $abstract Binding name.
     * @param \SDS\IoC\Concrete Binding Concrete.
     *
     * @throws \SDS\IoC\Exceptions\ConcreteAlreadyBoundException If binding with that name already exists.
     *
     * @return \SDS\IoC\Container $this for chaining.
     */
    protected function addConcrete($abstract, Concrete $concrete)
    {
        if ($this->hasConcrete($abstract)) {
            throw new Exceptions\ConcreteAlreadyBoundException(
                "Concrete `{$abstract}` is already bound."
            );
        }
        
        return $this->setConcrete($abstract, $concrete);
    }
    
    /**
     * Registers binding ( will overwrite previous binding with same name if it exists ).
     *
     * @param string $abstract Binding name.
     * @param \SDS\IoC\Concrete Binding Concrete.
     *
     * @return \SDS\IoC\Container $this for chaining.
     */
    protected function setConcrete($abstract, Concrete $concrete)
    {
        $this->concretes[$abstract] = $concrete;
        
        return $this;
    }
    
    /**
     * Retrieves registered Concrete.
     *
     * @param string $abstract Binding name.
     *
     * @return \SDS\IoC\Concrete|null Registered Concrete or null if binding with given name isn't registered.
     */
    protected function getConcrete($abstract)
    {
        return $this->hasConcrete($abstract) ? $this->concretes[$abstract] : null;
    }
    
    /**
     * Removes registered binding Concrete.
     *
     * @param string $abstract Binding name.
     *
     * @return \SDS\IoC\Container $this for chaining.
     */
    protected function removeConcrete($abstract)
    {
        unset($this->concretes[$abstract]);
        
        return $this;
    }
    
    /**
     * Determine if binding has a Concrete.
     *
     * @param string $abstract Binding name.
     *
     * @return bool
     */
    protected function hasConcrete($abstract)
    {
        return isset($this->concretes[$abstract]);
    }
    
    /**
     * Determine if binding name is an argument binding.
     *
     * @param string $abstract Binding name.
     *
     * @return bool
     */
    protected function isArgumentAbstract($abstract)
    {
        return strpos($abstract, "::") !== false;
    }
}