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
        $absoluteTimeout = $timeout ? microtime(true) + $timeout : null;
        
        while (!$stopAutomatically || $this->activeProcesses->count() || $this->queue->count()) {
            $this->refreshAllProcesses();
            
            if ($absoluteTimeout && microtime(true) >= $absoluteTimeout) {
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
        $queue = $this->queue;
        $this->handleQueueFn = function () use ($queue) {
            while ($queue->count() && $this->canStartProcess()) {
                $queue->dequeue()->resolve();
            }
        };
        
        $activeProcesses = $this->activeProcesses;
        $this->runUntilFutureRealisedFn = function ($futureValue, $timeout) use ($activeProcesses) {
            $absoluteTimeout = $timeout ? microtime(true) + $timeout : null;

            while (!$futureValue->isRealised()) {
                foreach ($activeProcesses as $process) {
                    $process->getStatus(true);
                    if ($futureValue->isRealised()) {
                        return;
                    }
                }

                if ($absoluteTimeout && microtime(true) >= $absoluteTimeout) {
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
