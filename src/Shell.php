<?php
namespace FutureProcess;

/**
 * @author Josh Di Fabio <joshdifabio@gmail.com>
 */
class Shell
{
    private $processLimit = 10;
    private $activeProcesses;
    private $queue;
    private $canStartProcessFn;
    private $handleQueueFn;
    private $runUntilFutureRealisedFn;
    
    public function __construct()
    {
        $this->activeProcesses = new \SplObjectStorage;
        $this->queue = new \SplQueue;
        $this->canStartProcessFn = $this->createCanStartProcessFn();
        $this->handleQueueFn = $this->createHandleQueueFn();
        $this->runUntilFutureRealisedFn = $this->createRunUntilFutureRealisedFn();
    }
    
    /**
     * @return FutureProcess
     */
    public function startProcess(
        $command,
        array $descriptorSpec = null,
        $workingDirectory = null,
        array $environmentVariables = null,
        array $otherOptions = null,
        $timeLimit = null,
        $timeoutSignal = 15
    ) {
        $handleQueueFn = $this->handleQueueFn;
        $handleQueueFn();
        
        $process = $this->createProcess(
            array_slice(func_get_args(), 0, 5),
            $timeLimit,
            $timeoutSignal
        );

        $activeProcesses = $this->activeProcesses;
        $process->then(function () use ($activeProcesses, $process) {
            $activeProcesses->attach($process);
        });
        $onComplete = function () use ($activeProcesses, $process, $handleQueueFn) {
            $activeProcesses->detach($process);
            $handleQueueFn();
        };
        $process->getResult()->then($onComplete, $onComplete);
        
        return $process;
    }
    
    /**
     * @param null|int $processLimit
     */
    public function setProcessLimit($processLimit)
    {
        $this->processLimit = $processLimit;
    }
    
    /**
     * @param double $timeout OPTIONAL
     * @throws TimeoutException
     */
    public function wait($timeout = null)
    {
        if ($timeout) {
            $absoluteTimeout = microtime(true) + $timeout;
            
            while ($this->activeProcesses->count() || $this->queue->count()) {
                $this->refreshAllProcesses();

                if (microtime(true) >= $absoluteTimeout) {
                    throw new TimeoutException;
                }

                usleep(1000);
            }
        } else {
            while ($this->activeProcesses->count() || $this->queue->count()) {
                $this->refreshAllProcesses();
                usleep(1000);
            }
        }
    }
    
    public function refreshAllProcesses()
    {
        foreach ($this->activeProcesses as $process) {
            $process->getStatus(true);
        }
    }
    
    private function createProcess($options, $timeLimit, $timeoutSignal)
    {
        $futureExitCode = new FutureValue($this->runUntilFutureRealisedFn);
        
        $canStartProcessFn = $this->canStartProcessFn;
        if (!$canStartProcessFn()) {
            $queueSlot = new FutureValue($this->runUntilFutureRealisedFn);
            $process = new FutureProcess($options, $timeLimit, $timeoutSignal, $futureExitCode, $queueSlot);
            $this->queue->enqueue($queueSlot);
        } else {
            $process = new FutureProcess($options, $timeLimit, $timeoutSignal, $futureExitCode);
        }
        
        return $process;
    }
    
    private function createCanStartProcessFn()
    {
        $activeProcesses = $this->activeProcesses;
        $processLimit = &$this->processLimit;
        
        return function () use ($activeProcesses, &$processLimit) {
            return (!$processLimit || $activeProcesses->count() < $processLimit);
        };
    }
    
    private function createHandleQueueFn()
    {
        $queue = $this->queue;
        $canStartProcessFn = $this->canStartProcessFn;
        
        return function () use ($queue, $canStartProcessFn) {
            while ($queue->count() && $canStartProcessFn()) {
                $queue->dequeue()->resolve();
            }
        };
    }
    
    private function createRunUntilFutureRealisedFn()
    {
        $activeProcesses = $this->activeProcesses;
        
        return function ($timeout, $futureValue) use ($activeProcesses) {
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
}
