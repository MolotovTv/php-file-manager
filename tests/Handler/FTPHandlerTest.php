<?php
namespace Asticode\FileManager\Tests;

use Asticode\FileManager\Exception\Handler\FTPHandlerException;
use Asticode\FileManager\Handler\FTPHandler;
use PHPUnit_Framework_TestCase;

class FTPHandlerTest extends PHPUnit_Framework_TestCase
{
    private $oFTPHandler;

    private $sTmpFolder = '/tmp_test';
    private $sTmpFile = '/test_file.tmp';
    private $sTestContent = 'Test writing content in file.';

    public function __construct()
    {
        // Init
        $this->oFTPHandler = new FTPHandler([
            'port' => 22,
            'host' => '',
            'username' => '',
            'password' => '',
            'root_path' => '/data/replay-dev/',
            'sftp' => true
        ]);

        parent::__construct();
    }

    public function testCreateDir() {
        $this->oFTPHandler->createDir($this->sTmpFolder);

        $folderPaths = array_map(function($v) { return $v->getPath(); }, $this->oFTPHandler->explore('/'));
        $this->assertTrue(in_array($this->sTmpFolder, $folderPaths));
    }

    public function testCreateFile() {
        $this->oFTPHandler->createFile($this->sTmpFolder . $this->sTmpFile);

        $folderPaths = array_map(function($v) { return $v->getPath(); }, $this->oFTPHandler->explore($this->sTmpFolder));
        $this->assertTrue(in_array($this->sTmpFolder . $this->sTmpFile, $folderPaths));
    }

    public function testWriteInFile() {
        $this->oFTPHandler->write($this->sTestContent, $this->sTmpFolder . $this->sTmpFile);

        $this->assertTrue($this->oFTPHandler->read($this->sTmpFolder . $this->sTmpFile) == $this->sTestContent);
    }

    public function testRenameFile() {
        $this->oFTPHandler->rename($this->sTmpFolder . $this->sTmpFile, $this->sTmpFolder . $this->sTmpFile . '.processed');

        $folderPaths = array_map(function($v) { return $v->getPath(); }, $this->oFTPHandler->explore($this->sTmpFolder));
        $this->assertTrue(in_array($this->sTmpFolder . $this->sTmpFile . '.processed', $folderPaths));
    }

    public function testDeleteFile() {
        $this->oFTPHandler->delete($this->sTmpFolder . $this->sTmpFile . '.processed');

        $folderPaths = array_map(function($v) { return $v->getPath(); }, $this->oFTPHandler->explore($this->sTmpFolder));
        $this->assertTrue(empty($folderPaths));
    }

    public function testDeleteDirectory() {
        $this->oFTPHandler->deleteDir($this->sTmpFolder);

        $folderPaths = array_map(function($v) { return $v->getPath(); }, $this->oFTPHandler->explore('/'));
        $this->assertTrue(!in_array($this->sTmpFolder, $folderPaths));
    }
}
