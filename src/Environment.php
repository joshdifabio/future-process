<?php
namespace Joshdifabio\FutureProcess;

/**
 * @author Josh Di Fabio <joshdifabio@gmail.com>
 */
class Environment
{
    private $values = array();
    
    public function get($name)
    {
        if (!is_string($name)) {
            throw new \InvalidArgumentException('The provided variable name is not a string');
        }
        
        return !array_key_exists($name, $this->values) ? null : $this->values[$name];
    }
    
    public function set($name, $value)
    {
        if (!is_string($name)) {
            throw new \InvalidArgumentException('The provided variable name is not a string');
        }
        
        if (is_null($value)) {
            unset($this->values[$name]);
        } else {
            $this->values[$name] = $value;
        }
        
        return $this;
    }
    
    public function toArray()
    {
        return $this->values;
    }
}
