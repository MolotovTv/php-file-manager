<?php

namespace Asticode\FileManager\Exception;

use Exception;

/**
 * @author apally
 */
class FileManagerException extends Exception
{

    const E_SOURCE        = 'param-source';
    const E_DESTINATION   = 'param-destination';
    const ACCES_DENIED    = 1009;
    const FAIL_WRITE_FILE = 1023;

}
