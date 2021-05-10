<?php

namespace Asticode\FileManager\Handler;

use Asticode\FileManager\Enum\ObjectType;
use Asticode\FileManager\Toolbox;
use Asticode\Toolbox\ExtendedArray;
use Asticode\FileManager\Entity\FileMethod;
use Asticode\FileManager\Enum\Datasource;
use Asticode\FileManager\Enum\OrderDirection;
use Asticode\FileManager\Enum\OrderField;
use Asticode\FileManager\Enum\WriteMethod;
use Exception;
use RuntimeException;
use Asticode\FileManager\Exception\Handler\FTPHandlerException;

class FTPHandler extends AbstractHandler
{

    // Attributes
    private $aConfig;

    // Construct
    public function __construct(array $aConfig)
    {
        // Initialize
        $this->aConfig = $aConfig;

        // Default values
        $this->aConfig = ExtendedArray::extendWithDefaultValues(
            $this->aConfig, [
                'port' => 21,
                'proxy' => '',
                'timeout' => 90,
                'sftp' => false,
                'root_path' => '/~'
            ]
        );

        // Check config required attributes
        ExtendedArray::checkRequiredKeys(
            $this->aConfig, [
                'host',
            ]
        );
    }

    private function curlInit()
    {
        // Initialize
        $oCurl = curl_init();
        curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($oCurl, CURLOPT_PORT, $this->aConfig['port']);
        curl_setopt($oCurl, CURLOPT_TIMEOUT, $this->aConfig['timeout']);
        curl_setopt($oCurl, CURLOPT_CONNECTTIMEOUT, $this->aConfig['timeout']);

        // Set SFTP configuration
        if ($this->aConfig['sftp']) {
            curl_setopt($oCurl, CURLOPT_PROTOCOLS, CURLPROTO_SFTP);
        }

        // Add user and password
        if (isset($this->aConfig['username']) and isset($this->aConfig['password'])) {
            curl_setopt($oCurl, CURLOPT_USERPWD, sprintf(
                '%s:%s', $this->aConfig['username'], $this->aConfig['password']
            ));
        }

        // Return
        return $oCurl;
    }

    private function curlExec($oCurl, $sPath, $iObjectTypeId = ObjectType::FILE, $sCustomRequest = '', $aPostCommands = [])
    {
        // Set URL
        $sUrl = $this->getFullPath($sPath, $iObjectTypeId);
        curl_setopt($oCurl, CURLOPT_URL, $sUrl);

        // Set custom request
        if ($sCustomRequest !== '') {
            curl_setopt($oCurl, CURLOPT_CUSTOMREQUEST, $sCustomRequest);
        }

        // Set post quote
        if ($aPostCommands !== []) {
            curl_setopt($oCurl, CURLOPT_POSTQUOTE, $aPostCommands);
        }

        // Exec
        $sResponse = curl_exec($oCurl);

        // Failure
        if (curl_errno($oCurl) > 0) {
            $sMessage = FTPHandlerException::getMessageByError(curl_errno($oCurl), $sUrl, ($sCustomRequest === '' ? implode('","', $aPostCommands) : $sCustomRequest));
            throw new FTPHandlerException($sMessage, 1000 + curl_errno($oCurl));
        }

        // Return
        return $sResponse;
    }

    private function getFullPath($sPath, $iObjectType)
    {
        // Build path
        $sPath = sprintf(
            '%sftp://%s%s%s',
            $this->aConfig['sftp'] ? 's' : null,
            $this->aConfig['host'],
            $this->aConfig['sftp'] ? $this->aConfig['root_path'] : null,
            $sPath
        );

        // Add trailing slash
        if ($iObjectType === ObjectType::DIRECTORY) {
            $sPath .= '/';
        }

        // Return
        return $sPath;
    }

    /**
     * Return the correct file/directory path for SFTP connection
     * @param $sPath
     */
    private function getSFTPPath($sPath) {
        if ($this->aConfig['sftp']) {
            return $this->aConfig['root_path'] . $sPath;
        }

        return $sPath;
    }

    public function getDatasource()
    {
        return Datasource::FTP;
    }

    public function getCopyMethods()
    {
        return [
            new FileMethod(
                Datasource::FTP, Datasource::LOCAL, [$this, 'download']
            ),
            new FileMethod(
                Datasource::LOCAL, Datasource::FTP, [$this, 'upload']
            ),
        ];
    }

    public function getMoveMethods()
    {
        return [
            new FileMethod(
                Datasource::FTP, Datasource::FTP, [$this, 'rename']
            ),
        ];
    }

    public function metadata($sPath)
    {
        // Get files
        $aFiles = $this->searchPattern(sprintf(
            '/^%s$/', basename($sPath)
        ), dirname($sPath));

        // Path exists
        if ($aFiles !== []) {
            return $aFiles[0];
        } else {
            throw new RuntimeException(sprintf(
                'Path %s doesn\'t exist', $sPath
            ));
        }
    }

    public function explore($sPath, $iOrderField = OrderField::NONE, $iOrderDirection = OrderDirection::ASC, array $aAllowedExtensions = [], array $aAllowedBasenamePatterns = [])
    {
        // Initialize
        $aFiles = [];

        // Get CURL
        $oCurl = $this->curlInit();

        $sResponse = $this->curlExec($oCurl, $sPath, ObjectType::DIRECTORY, 'LIST -a');

        // Get files
        $aList = explode("\n", $sResponse);

        // Add file
        foreach ($aList as $sFile) {
            if ($sFile !== '') {
                // Initialize
                $oFile = Toolbox::parseRawList($sFile, $sPath);

                // Add file
                Toolbox::addFile($aFiles, $oFile, $aAllowedExtensions, $aAllowedBasenamePatterns);
            }
        }

        // Order
        Toolbox::sortFiles($aFiles, $iOrderField, $iOrderDirection);

        // Return
        return $aFiles;
    }

    public function createDir($sPath)
    {
        // Get CURL
        $oCurl = $this->curlInit();

        $aCommands = [
            sprintf('MKD %s', $sPath),
        ];

        if ($this->aConfig['sftp']) {
            $aCommands = [
                sprintf('MKDIR %s', $this->getSFTPPath($sPath))
            ];
        }

        $this->curlExec(
            $oCurl, '', ObjectType::DIRECTORY, '', $aCommands
        );
    }

    public function createFile($sPath)
    {
        // Write
        $this->write('', $sPath);
    }

    public function write($sContent, $sPath, $iWriteMethod = WriteMethod::APPEND)
    {
        // Create source path
        $sSourcePath = tempnam(sys_get_temp_dir(), 'asticode_filehandler_');
        file_put_contents($sSourcePath, $sContent);

        // Upload
        $this->upload($sSourcePath, $sPath);
    }

    public function read($sPath)
    {
        // Initialize
        $sTargetPath = tempnam(sys_get_temp_dir(), 'asticode_filehandler_');

        // Download
        $this->download($sPath, $sTargetPath);

        // Get content
        $sContent = file_get_contents($sTargetPath);

        // Remove temp file
        unlink($sTargetPath);

        // Return
        return $sContent;
    }

    public function rename($sSourcePath, $sTargetPath)
    {
        // Get CURL
        $oCurl = $this->curlInit();

        $aCommands = [
            sprintf('RNFR %s', $sSourcePath),
            sprintf('RNTO %s', $sTargetPath),
        ];

        if ($this->aConfig['sftp']) {
            $aCommands = [
                sprintf('RENAME %s  %s',
                    $this->getSFTPPath($sSourcePath),
                    $this->getSFTPPath($sTargetPath)
                )
            ];
        }

        // Execute CURL
        $this->curlExec(
            $oCurl, '', ObjectType::FILE, '', $aCommands
        );
    }

    public function download($sSourcePath, $sTargetPath)
    {
        // Initialize
        $oFile = fopen($sTargetPath, 'w');

        // Get CURL
        $oCurl = $this->curlInit();

        // Download
        curl_setopt($oCurl, CURLOPT_FILE, $oFile);
        try {
            $this->curlExec($oCurl, $sSourcePath);
        } catch (Exception $oException) {
            fclose($oFile);
            throw $oException;
        }

        // Close file
        fclose($oFile);
    }

    public function upload($sSourcePath, $sTargetPath)
    {
        // Initialize
        $oFile = fopen($sSourcePath, 'r');

        // Get CURL
        $oCurl = $this->curlInit();

        // Download
        curl_setopt($oCurl, CURLOPT_UPLOAD, true);
        curl_setopt($oCurl, CURLOPT_INFILE, $oFile);
        curl_setopt($oCurl, CURLOPT_INFILESIZE, filesize($sSourcePath));
        try {
            $this->curlExec($oCurl, $sTargetPath);
        } catch (Exception $oException) {
            fclose($oFile);
            throw $oException;
        }

        // Close file
        fclose($oFile);
    }

    public function delete($sPath)
    {
        // Get CURL
        $oCurl = $this->curlInit();

        $aCommands = [
            sprintf('DELE %s', $sPath),
        ];

        if ($this->aConfig['sftp']) {
            $aCommands = [
                sprintf('RM %s', $this->getSFTPPath($sPath))
            ];
        }

        // Execute CURL
        $this->curlExec(
            $oCurl, '', ObjectType::FILE, '', $aCommands
        );
    }

    public function deleteDir($sPath)
    {
        // Get CURL
        $oCurl = $this->curlInit();

        $aCommands = [
            sprintf('DELE %s', $sPath),
        ];

        if ($this->aConfig['sftp']) {
            $aCommands = [
                sprintf('RMDIR %s', $this->getSFTPPath($sPath))
            ];
        }

        $this->curlExec(
            $oCurl, '', ObjectType::DIRECTORY, '', $aCommands
        );
    }

}
