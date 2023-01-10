<?php

namespace MauticPlugin\PipedriveBundle\Exception;

use Throwable;

class PipedriveBundleException extends \Exception
{
    /**
     * @param string $message
     * @param int    $code
     */
    public function __construct($message = '', $code = 0, Throwable $previous = null)
    {
        $message = 'Pipedrive: '.$message;
        parent::__construct($message, $code, $previous);
    }
}
