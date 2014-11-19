<?php
namespace SDS\IoC;

use \Closure;

/**
 * Binding resolver that resolves arguments.
 */
class ArgumentConcrete extends Concrete
{
    /**
     * Resolves binding.
     *
     * @return mixed Resolved value.
     */
    public function make()
    {
        $binding = $this->getBinding();
        
        return $binding($this->container);
    }
    
    /**
     * Sets binding resolver. It's always set to Closure.
     *
     * @param mixed $binding Binding value.
     *
     * @return \SDS\IoC\ArgumentConcrete $this for chaining.
     */
    protected function setBinding($binding)
    {
        if (!is_object($binding) || !$binding instanceof Closure) {
            $binding = function() use ($binding) {
                return $binding;
            };
        }
        
        return parent::setBinding($binding);
    }
}