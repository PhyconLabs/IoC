<?php
namespace SDS\IoC;

use \Closure;

/**
 * Binding resolver that resolves singleton arguments.
 * Singleton argument is an argument that's only resolved once ( similar to singleton objects ).
 */
class SingletonArgumentConcrete extends ArgumentConcrete
{
    /**
     * Resolved argument.
     *
     * @var mixed
     */
    protected $resolved;
    
    /**
     * Whether argument is resolved.
     *
     * @var bool
     */
    protected $hasResolved;
    
    public function __construct(Container $container, $binding)
    {
        $this->hasResolved = false;
        
        parent::__construct($container, $binding);
    }
    
    /**
     * Resolves binding. If binding is already resolved then just use that.
     *
     * @return mixed Resolved value.
     */
    public function make()
    {
        if ($this->hasResolved()) {
            return $this->getResolved();
        } else {
            $resolved = parent::make($arguments);
            
            $this->setResolved($resolved);
            
            return $resolved;
        }
    }
    
    /**
     * Sets binding resolver. If resolver isn't a Closure then sets resolved value as well.
     *
     * @param mixed $binding Binding value.
     *
     * @return \SDS\IoC\SingletonArgumentConcrete $this for chaining.
     */
    protected function setBinding($binding)
    {
        if (!is_object($binding) || !$binding instanceof Closure) {
            $this->setResolved($binding);
        }
        
        return parent::setBinding($binding);
    }
    
    /**
     * Gets resolved value.
     *
     * @throws \SDS\IoC\Exceptions\NoResolvedException If resolved value isn't set.
     *
     * @return mixed
     */
    protected function getResolved()
    {
        if (!$this->hasResolved()) {
            throw new Exceptions\NoResolvedException(
                "No resolved is set."
            );
        }
        
        return $this->resolved;
    }
    
    /**
     * Sets resolved value.
     *
     * @param mixed $resolved
     *
     * @return \SDS\IoC\SingletonArgumentConcrete $this for chaining.
     */
    protected function setResolved($resolved)
    {
        $this->hasResolved = true;
        $this->resolved = $resolved;
        
        return $this;
    }
    
    /**
     * Determine whether resolved value is set.
     *
     * @return bool
     */
    protected function hasResolved()
    {
        return $this->hasResolved;
    }
}