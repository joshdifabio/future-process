<?php
namespace Joshdifabio\ChildProcess;

class Shell
{
    private $processLimit = 10;
    private $activeProcesses;
    private $queue;
    private $handleQueueFn;
    private $runUntilFutureRealisedFn;
    
    public function __construct()
    {
        $this->activeProcesses = new \SplObjectStorage;
        $this->queue = new \SplQueue;
        $this->initClosures();
    }
    
    public function startProcess(ProcessOptionsInterface $options)
    {
        $handleQueueFn = $this->handleQueueFn;
        $runUntilFutureRealisedFn = $this->runUntilFutureRealisedFn;
        
        $handleQueueFn();
        $activeProcesses = $this->activeProcesses;
        $futureExitCode = new FutureValue($runUntilFutureRealisedFn);
        
        if ($activeProcesses->count() >= $this->processLimit) {
            $queueSlot = new FutureValue($runUntilFutureRealisedFn);
            $process = new FutureProcess($options, $futureExitCode, $queueSlot);
            $this->queue->enqueue($queueSlot);
        } else {
            $process = new FutureProcess($options, $futureExitCode);
        }
        
        $process->then(function () use ($activeProcesses, $process) {
            $activeProcesses->attach($process);
        });
        
        $onComplete = function () use ($activeProcesses, $process, $handleQueueFn) {
            $activeProcesses->detach($process);
            $handleQueueFn();
        };
        $process->getResult()->then($onComplete, $onComplete, $onComplete);
        
        return $process;
    }
    
    public function setProcessLimit($processLimit)
    {
        $this->processLimit = $processLimit;
    }
    
    public function run($stopAutomatically = true, $timeout = null)
    {
        if ($timeout) {
            $absoluteTimeout = microtime(true) + $timeout;
        }
        
        while (!$stopAutomatically || $this->activeProcesses->count() || $this->queue->count()) {
            $this->refreshAllProcesses();
            
            if ($timeout && microtime(true) >= $absoluteTimeout) {
                throw new TimeoutException;
            }
            
            usleep(1000);
        }
        
        return $this;
    }
    
    public function refreshAllProcesses()
    {
        foreach ($this->activeProcesses as $process) {
            $process->getStatus(true);
        }
        
        return $this;
    }
    
    private function initClosures()
    {
        $this->handleQueueFn = function () {
            while ($this->queue->count() && $this->canStartProcess()) {
                $this->queue->dequeue()->resolve();
            }
        };
        
        $this->runUntilFutureRealisedFn = function (FutureValue $futureValue, $timeout = null) {
            if ($timeout) {
                $absoluteTimeout = microtime(true) + $timeout;
            }

            while (!$futureValue->isRealised()) {
                foreach ($this->activeProcesses as $process) {
                    $process->getStatus(true);
                    if ($futureValue->isRealised()) {
                        return;
                    }
                }

                if ($timeout && microtime(true) >= $absoluteTimeout) {
                    throw new TimeoutException;
                }

                usleep(1000);
            }
        };
    }
    
    private function canStartProcess()
    {
        return (!$this->processLimit || $this->activeProcesses->count() < $this->processLimit);
    }
}
