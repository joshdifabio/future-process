<?php
namespace FutureProcess;

use React\EventLoop\LoopInterface;
use Clue\React\Block\Blocker;
use React\Promise\Deferred;
use React\Promise\FulfilledPromise;
use React\Promise\PromiseInterface;
use React\Stream\Stream;

/**
 * @author Josh Di Fabio <joshdifabio@gmail.com>
 */
class FutureProcess
{
    const STATUS_QUEUED   = 0;
    const STATUS_RUNNING  = 1;
    const STATUS_EXITED   = 2;
    const STATUS_ABORTED  = 3;
    const STATUS_ERROR    = 4;

    private $blocker;
    private $process;
    private $promise;
    private $result;
    private $deferredStart;

    public function __construct(
        LoopInterface $eventLoop,
        Blocker $blocker,
        $command,
        array $options,
        Deferred $deferredStart = null
    ) {
        $this->blocker = $blocker;
        $this->process = new Process($eventLoop, $command, $options);
        
        if ($this->deferredStart = $deferredStart) {
            $this->promise = $this->startDeferred();
        } else {
            $this->process->start();
            $this->promise = new FulfilledPromise($this);
        }
    }

    /**
     * @return int
     */
    public function getPid()
    {
        $this->wait();
        
        return $this->process->getPid();
    }
    
    /**
     * @param int $descriptor
     * @return Stream
     */
    public function getPipe($descriptor)
    {
        $this->wait();
            
        return $this->process->getPipes()->getPipe($descriptor);
    }
    
    /**
     * @param bool $refresh OPTIONAL
     * @return int One of the status constants defined in this class
     */
    public function getStatus($refresh = true)
    {
        return $this->process->getStatus($refresh);
    }
    
    /**
     * @return FutureResult
     */
    public function getResult()
    {
        if (is_null($this->result)) {
            $this->result = new FutureResult(
                $this->blocker,
                $this->process->whenExited(),
                $this->process->getPipes()
            );
        }
        
        return $this->result;
    }
    
    /**
     * @param \Exception|null $error
     * @param int|null $signal If null is passed, no signal will be sent to the process
     */
    public function abort(\Exception $error = null, $signal = 15)
    {
        if ($this->getStatus() === self::STATUS_QUEUED) {
            $this->process->abort($error);
            $this->deferredStart->reject($error);
            return;
        }

        $this->process->abort($error, $signal);
    }
    
    /**
     * Wait for the process to start
     * 
     * @param double $timeout OPTIONAL
     * @return static
     */
    public function wait($timeout = null)
    {
        if ($this->deferredStart) {
            $this->blocker->awaitOne($this->promise, $timeout);
        }
        
        return $this;
    }
    
    /**
     * @return PromiseInterface
     */
    public function promise()
    {
        return $this->promise;
    }
    
    /**
     * @param callable $onFulfilled
     * @param callable $onError
     * @return PromiseInterface
     */
    public function then($onFulfilled = null, $onError = null)
    {
        return $this->promise->then($onFulfilled, $onError);
    }

    private function startDeferred()
    {
        $process = $this->process;
        $that = $this;

        return $this->deferredStart->promise()->then(
            function () use ($process, $that) {
                $process->start();
                return $that;
            },
            function ($reason) use ($that) {
                $that->abort($reason);
                if ($reason instanceof \Exception) {
                    throw $reason;
                }
                return $that;
            }
        );
    }
}
