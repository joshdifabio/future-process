<?php
namespace Joshdifabio\FutureProcess;

/**
 * @author Josh Di Fabio <joshdifabio@gmail.com>
 */
interface ProcessOptionsInterface
{
    public function getCommandLine();
    
    public function getWorkingDirectory();
    
    public function getEnvironment();
}
