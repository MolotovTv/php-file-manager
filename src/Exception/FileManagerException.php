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
    
    public static function getMessageByError(int $iCurlError, string $sPath = '', $sCustomRequest = ''){
        
        $sExplain = $sPath . ' | ' . $sCustomRequest;
        if (CURLE_FTP_ACCESS_DENIED === (int) $iCurlError) {
            return sprintf("Access denied for this resource : %s (Folder exist ?)", $sExplain);
        } else if (CURLE_WRITE_ERROR === (int) $iCurlError) {
            return sprintf("Fail to write on destination : %s (Full disk ?)", $sExplain);
        } else {
            return sprintf("Fail on cUrl #%s for : %s (Unknown)", $iCurlError, $sExplain);
        }
    }

}
