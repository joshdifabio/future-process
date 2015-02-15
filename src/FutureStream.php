<?php
namespace FutureProcess;

class FutureStream
{
    private $waitFn;
    private $promise;
    private $resource;
    
    public function __construct($waitFn, $resourcePromise)
    {
        $this->waitFn = $waitFn;
        $resource = &$this->resource;
        $that = $this;
        $this->promise = $resourcePromise->then(function ($_resource) use (&$resource, $that) {
            $resource = $_resource;
            return $that;
        });
    }
    
    public function getContents()
    {
        $this->wait();
        
        if ($this->resource) {
            return stream_get_contents($this->resource);
        }
    }
    
    public function getResource()
    {
        $this->wait();
        
        return $this->resource;
    }
    
    public function wait($timeout = null)
    {
        call_user_func($this->waitFn, $timeout, $this);
        
        return $this;
    }
    
    public function promise()
    {
        return $this->promise;
    }
    
    public function then($onFulfilled = null, $onError = null, $onProgress = null)
    {
        return $this->promise->then($onFulfilled, $onError, $onProgress);
    }
}
