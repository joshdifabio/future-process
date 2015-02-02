<?php
namespace Joshdifabio\ChildProcess;

/**
 * @author Josh Di Fabio <joshdifabio@gmail.com>
 */
class ProcessOptions implements ProcessOptionsInterface
{
    private $commandLine;
    private $workingDirectory;
    private $environment;
    
    public function __construct($commandLine = null)
    {
        $this->commandLine = $commandLine;
    }
    
    public function getCommandLine()
    {
        return $this->commandLine;
    }
    
    public function setCommandLine($commandLine)
    {
        $this->commandLine = $commandLine;
        
        return $this;
    }
    
    public function getWorkingDirectory()
    {
        return $this->workingDirectory;
    }
    
    public function setWorkingDirectory($workingDirectory)
    {
        $this->workingDirectory = $workingDirectory;
        
        return $this;
    }
    
    public function getEnvironment()
    {
        if (is_null($this->environment)) {
            $this->environment = new Environment;
        }
        
        return $this->environment;
    }
    
    public function setEnvironment(Environment $environment)
    {
        $this->environment = $environment;
        
        return $this;
    }
}
