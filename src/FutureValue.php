<?php
namespace Joshdifabio\FutureProcess;

use React\Promise\Deferred;

class FutureValue
{
    private $waitFn;
    private $deferred;
    private $isRealised = false;
    private $value;
    private $error;
    
    public function __construct($waitFn)
    {
        $this->waitFn = $waitFn;
        $this->deferred = new Deferred;
    }
    
    public function isRealised()
    {
        return $this->isRealised;
    }
    
    public function resolve($value = null)
    {
        if (!$this->isRealised) {
            $this->deferred->resolve($value);
            $this->isRealised = true;
        }
    }
    
    public function reject(\Exception $e)
    {
        if (!$this->isRealised) {
            $this->deferred->reject($e);
            $this->isRealised = true;
        }
    }
    
    public function getValue($timeout = null)
    {
        $this->wait($timeout);
        
        if ($this->error) {
            throw $this->error;
        }
        
        return $this->value;
    }

    public function wait($timeout = null)
    {
        if (!$this->isRealised) {
            $waitFn = $this->waitFn;
            $waitFn($this, $timeout);
        }
    }
    
    public function then($onFulfilled = null, $onError = null, $onProgress = null)
    {
        return $this->deferred->promise()->then($onFulfilled, $onError, $onProgress);
    }
}
