<?php
namespace Joshdifabio\ChildProcess;

/**
 * @author Josh Di Fabio <joshdifabio@gmail.com>
 */
interface ProcessOptionsInterface
{
    public function getCommandLine();
    
    public function getWorkingDirectory();
    
    public function getEnvironment();
}
