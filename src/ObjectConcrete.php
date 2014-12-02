<?php
namespace SDS\IoC;

use \Closure;
use \ReflectionClass;
use \ReflectionException;

/**
 * Binding resolver that resolves class bindings.
 */
class ObjectConcrete extends Concrete
{
    /**
     * Name of the binding how it's bound in Container.
     *
     * @var string
     */
    protected $boundAs;
    
    /**
     * @param \SDS\IoC\Container $container
     * @param mixed $binding Binding value.
     * @param string $boundAs Binding name how it's bound in Container.
     */
    public function __construct(Container $container, $binding, $boundAs)
    {
        $this->boundAs = $boundAs;
        
        parent::__construct($container, $binding);
    }
    
    /**
     * Resolves binding.
     *
     * @param array $arguments Class constructor arguments.
     *
     * @throws \SDS\IoC\Exceptions\BindingResolveException If binding can't be resolved
     *         ( child classes of this exception can be thrown ).
     *
     * @return object Resolved class object.
     */
    public function make(array $arguments = [])
    {
        $binding = $this->getBinding();
        
        return $binding($arguments, $this->container);
    }
    
    /**
     * Sets binding resolver. It's always set as a Closure.
     *
     * @param mixed $binding Binding value.
     *
     * @throws \SDS\IoC\Exceptions\InvalidConcreteBindingException If binding value is not an object or string.
     *
     * @return \SDS\IoC\ObjectConcrete $this for chaining.
     */
    protected function setBinding($binding)
    {
        if (is_object($binding)) {
            if ($binding instanceof Closure) {
                return $this->setClosureBinding($binding);
            } else {
                return $this->setObjectBinding($binding);
            }
        } elseif (is_string($binding)) {
            return $this->setStringBinding($binding);
        } else {
            $type = gettype($binding);
            
            throw new Exceptions\InvalidConcreteBindingException(
                "Invalid concrete binding of type `{$type}` given."
            );
        }
    }
    
    /**
     * Sets a Closure binding resolver.
     *
     * @param \Closure $binding
     *
     * @return \SDS\IoC\ObjectConcrete $this for chaining.
     */
    protected function setClosureBinding(Closure $binding)
    {
        $this->binding = $binding;
        
        return $this;
    }
    
    /**
     * Sets an object binding resolver.
     *
     * @param object $binding
     *
     * @return \SDS\IoC\ObjectConcrete $this for chaining.
     */
    protected function setObjectBinding($binding)
    {
        $class = get_class($binding);
        
        return $this->setStringBinding($class);
    }
    
    /**
     * Sets a string binding resolver.
     *
     * @param string $binding
     *
     * @return \SDS\IoC\ObjectConcrete $this for chaining.
     */
    protected function setStringBinding($binding)
    {
        $closure = function($arguments) use ($binding) {
            return $this->build($binding, $arguments);
        };
        
        return $this->setClosureBinding($closure);
    }
    
    /**
     * Builds class and resolves its constructor dependencies.
     *
     * @param string $class Class name.
     * @param array $arguments Class constructor arguments.
     *
     * @throws \SDS\IoC\Exceptions\BindingResolveException If class can't be instantiated
     *         ( child classes of this exception can be thrown ).
     *
     * @return object
     */
    protected function build($class, array $arguments)
    {
        try {
            $reflection = new ReflectionClass($class);
        } catch (ReflectionException $e) {
            throw new Exceptions\UninstantiableBindingException(
                "`{$class}` isn't instantiable.",
                0,
                $e
            );
        }
        
        if (!$reflection->isInstantiable()) {
            throw new Exceptions\UninstantiableBindingException(
                "`{$class}` isn't instantiable."
            );
        }
        
        $constructor = $reflection->getConstructor();
        
        if (!isset($constructor)) {
            return new $class;
        }
        
        $dependencies = $constructor->getParameters();
        
        if (empty($dependencies)) {
            return new $class;
        }
        
        $dependencies = $this->mapDependenciesByName($dependencies);
        list($namedArguments, $varidicArgument) = $this->mapArgumentsByNameAndVaridic($arguments, $dependencies);
        $dependencies = $this->resolveDependencies($dependencies, $namedArguments, $varidicArgument);
        
        return $reflection->newInstanceArgs($dependencies);
    }
    
    /**
     * Splits arguments into named and varidic arguments and maps named arguments by argument name not
     * argument place.
     *
     * @param array $arguments Arguments mapped by either a place, name or both.
     * @param array $dependencies ReflectionParameter instances mapped by name.
     *
     * @return array First item is arguments mapped by name and second item is varidic arguments.
     */
    protected function mapArgumentsByNameAndVaridic(array $arguments, array $dependencies)
    {
        reset($arguments);
        
        $named = [];
        $varidic = [];
        $firstKey = key($arguments);
        
        // If first key for $arguments is a string assume that they are mapped by name and rest are varidic
        // arguments. If first key is a number then assume that no named arguments are given ( if there are then they
        // will be considered as varidic arguments ).
        if (isset($firstKey) && !is_numeric($firstKey)) {
            foreach ($arguments as $name => $value) {
                if (is_numeric($name)) {
                    $varidic[] = $value;
                } else {
                    $named[$name] = $value;
                }
            }
        } else {
            $i = 0;
            foreach ($dependencies as $name => $value) {
                if (array_key_exists($i, $arguments)) {
                    $named[$name] = $arguments[$i];
                    unset($arguments[$i]);
                }
            }
            
            $varidic = array_values($arguments);
        }
        
        return [ $named, $varidic ];
    }
    
    /**
     * Maps argument dependencies by argument name.
     *
     * @param array $dependencies Array of ReflectionParameter objects.
     *
     * @return array
     */
    protected function mapDependenciesByName(array $dependencies)
    {
        $map = [];
        
        foreach ($dependencies as $parameter) {
            $map[$parameter->getName()] = $parameter;
        }
        
        return $map;
    }
    
    /**
     * Resolves argument dependencies.
     *
     * @param array $dependencies Array of ReflectionParameter objects mapped by argument name.
     * @param array $namedArguments Array of argument values mapped by argument name.
     * @param array $varidicArguments Varidic argument.
     *
     * @throws \SDS\IoC\Exceptions\BindingResolveException If dependencies can't be resolved
     *         ( child classes of this exception can be thrown ).
     *
     * @return array Array of argument values mapped by their place.
     */
    protected function resolveDependencies(array $dependencies, array $namedArguments, array $varidicArgument)
    {
        $resolved = [];
        
        foreach ($dependencies as $name => $parameter) {
            // If argument is in $namedArguments then we don't have to resolve anything - just use what's given.
            // If not then check if argument is bound in Container - if it is then great! Use it!
            // If not then check if argument type is a class - if it is try to resolve it.
            // If not again then check if default value is available for argument - if it is then we can still make
            // this work. If even default value isn't there then we can just throw an Exception.
            if (array_key_exists($name, $namedArguments)) {
                $resolved[] = $namedArguments[$name];
            } else {
                $boundArgumentAbstract = "{$this->boundAs}::{$parameter->getName()}";
                
                if ($this->container->isBound($boundArgumentAbstract)) {
                    $resolved[] = $this->container->make($boundArgumentAbstract);
                } else {
                    $hintedClass = $parameter->getClass();
                    
                    if (isset($hintedClass)) {
                        $resolved[] = $this->container->make($hintedClass->getName());
                    } else {
                        if ($parameter->isDefaultValueAvailable()) {
                            $resolved[] = $parameter->getDefaultValue();
                        } else {
                            $declaringClass = $parameter->getDeclaringClass();
                            
                            throw new Exceptions\ArgumentBindingException(
                                "Can't resolve argument `{$declaringClass->getName()}::\${$parameter->getName()}`."
                            );
                        }
                    }
                }
            }
        }
        
        // Just append varidic argument at the end as it can only appear as the last argument.
        $resolved = array_merge($resolved, $varidicArgument);
        
        return $resolved;
    }
}