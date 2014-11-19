<?php
namespace SDS\IoC;

/**
 * Binding resolver that resolves singleton objects.
 */
class SingletonObjectConcrete extends ObjectConcrete
{
    /**
     * Resolved class instance.
     *
     * @var object
     */
    protected $instance;
    
    /**
     * Resolves binding. If binding is already resolved then return resolved version ( because we need
     * a singleton ).
     *
     * @param array $arguments Class constructor arguments.
     *
     * @throws \SDS\IoC\Exceptions\BindingResolveException If binding can't be resolved
     *         ( child classes of this exception can be thrown ).
     *
     * @return object Resolved class object.
     */
    public function make(array $arguments)
    {
        if ($this->hasInstance()) {
            return $this->getInstance();
        } else {
            $instance = parent::make($arguments);
            
            $this->setInstance($instance);
            
            return $instance;
        }
    }
    
    /**
     * Set's instance if object binding is given - nothing to resolve!
     *
     * @param object $binding
     *
     * @return \SDS\IoC\SingletonObjectConcrete $this for chaining.
     */
    protected function setObjectBinding($binding)
    {
        $this->setInstance($binding);
        
        return parent::setObjectBinding($binding);
    }
    
    /**
     * Gets resolved instance.
     *
     * @return object|null null is returned if resolved instance isn't set.
     */
    protected function getInstance()
    {
        return $this->hasInstance() ? $this->instance : null;
    }
    
    /**
     * Sets resolved instance.
     *
     * @param object $instance
     *
     * @return \SDS\IoC\SingletonObjectConcrete $this for chaining.
     */
    protected function setInstance($instance)
    {
        $this->instance = $instance;
        
        return $this;
    }
    
    /**
     * Determine whether resolved instance is available.
     *
     * @return bool
     */
    protected function hasInstance()
    {
        return isset($this->instance);
    }
}