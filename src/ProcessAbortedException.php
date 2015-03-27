<?php
namespace FutureProcess;

/**
 * @author Josh Di Fabio <joshdifabio@gmail.com>
 */
class ProcessAbortedException extends \RuntimeException
{
    private $process;
    
    public function __construct(FutureProcess $process, $message = null, \Exception $previous = null)
    {
        parent::__construct($message ?: 'The process was aborted.', null, $previous);
        
        $this->process = $process;
    }
    
    public function getProcess()
    {
        return $this->process;
    }
}
