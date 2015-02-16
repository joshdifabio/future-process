<?php
namespace FutureProcess;

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
            $this->value = $value;
            $this->isRealised = true;
            $this->deferred->resolve($value);
        }
    }
    
    public function reject(\Exception $e)
    {
        if (!$this->isRealised) {
            $this->error = $e;
            $this->isRealised = true;
            $this->deferred->reject($e);
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
            call_user_func($this->waitFn, $timeout, $this);
        }
    }
    
    public function then($onFulfilled = null, $onError = null, $onProgress = null)
    {
        return $this->deferred->promise()->then($onFulfilled, $onError, $onProgress);
    }
}
