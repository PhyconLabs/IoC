<?php
namespace SDS\IoC;

/**
 * Binding resolver base class.
 */
abstract class Concrete
{
    /**
     * @var \SDS\IoC\Container
     */
    protected $container;
    
    /**
     * Binding resolver.
     *
     * @var mixed
     */
    protected $binding;
    
    /**
     * @param \SDS\IoC\Container $container
     * @param mixed $binding Binding value.
     */
    public function __construct(Container $container, $binding)
    {
        $this->container = $container;
        
        $this->setBinding($binding);
    }
    
    /**
     * Resolves binding.
     *
     * @return mixed Resolved binding value.
     */
    public function make()
    {
        return $this->getBinding();
    }
    
    /**
     * Retrieves binding.
     *
     * @return mixed
     */
    protected function getBinding()
    {
        return $this->binding;
    }
    
    /**
     * Sets binding.
     *
     * @param mixed $binding
     *
     * @return \SDS\IoC\Concrete $this for chaining.
     */
    protected function setBinding($binding)
    {
        $this->binding = $binding;
        
        return $this;
    }
}