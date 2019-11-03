<?php

namespace Sajya\Lucene\Test\Storage;

use PHPUnit\Framework\TestCase;
use Sajya\Lucene\Storage\Directory;
use Sajya\Lucene\Storage\File;

/**
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage UnitTests
 * @group      Zend_Search_Lucene
 */
class DirectoryTest extends TestCase
{
    public function testFilesystem(): void
    {
        $tempPath = __DIR__ . '/_tempFiles/_files';

        // remove files from temporary directory
        $dir = opendir($tempPath);
        while (($file = readdir($dir)) !== false) {
            if (!is_dir($tempPath . '/' . $file)) {
                @unlink($tempPath . '/' . $file);
            }
        }
        closedir($dir);

        $directory = new Directory\Filesystem($tempPath);

        $this->assertInstanceOf(Directory\DirectoryInterface::class, $directory);
        $this->assertEquals(count($directory->fileList()), 0);

        $fileObject = $directory->createFile('file1');
        $this->assertInstanceOf(File\FileInterface::class, $fileObject);
        unset($fileObject);
        $this->assertEquals($directory->fileLength('file1'), 0);

        $this->assertEquals(count(array_diff($directory->fileList(), ['file1'])), 0);

        $directory->deleteFile('file1');
        $this->assertEquals(count($directory->fileList()), 0);

        $this->assertFalse($directory->fileExists('file2'));

        $fileObject = $directory->createFile('file2');
        $this->assertEquals($directory->fileLength('file2'), 0);
        $fileObject->writeBytes('0123456789');
        unset($fileObject);
        $this->assertEquals($directory->fileLength('file2'), 10);

        $directory->renameFile('file2', 'file3');
        $this->assertEquals(count(array_diff($directory->fileList(), ['file3'])), 0);

        $modifiedAt1 = $directory->fileModified('file3');
        clearstatcache();
        $directory->touchFile('file3');
        $modifiedAt2 = $directory->fileModified('file3');
        sleep(1);
        clearstatcache();
        $directory->touchFile('file3');
        $modifiedAt3 = $directory->fileModified('file3');

        $this->assertTrue($modifiedAt2 >= $modifiedAt1);
        $this->assertTrue($modifiedAt3 > $modifiedAt2);

        $fileObject = $directory->getFileObject('file3');
        $this->assertEquals($fileObject->readBytes($directory->fileLength('file3')), '0123456789');
        unset($fileObject);

        $fileObject = $directory->createFile('file3');
        $this->assertEquals($fileObject->readBytes($directory->fileLength('file3')), '');
        unset($fileObject);

        $directory->deleteFile('file3');
        $this->assertEquals(count($directory->fileList()), 0);

        $directory->close();
    }

    public function testFilesystemSubfoldersAutoCreation(): void
    {
        $directory = new Directory\Filesystem(__DIR__ . '/_tempFiles/_files/dir1/dir2/dir3');
        $this->assertInstanceOf(Directory\DirectoryInterface::class, $directory);
        $directory->close();

        rmdir(__DIR__ . '/_tempFiles/_files/dir1/dir2/dir3');
        rmdir(__DIR__ . '/_tempFiles/_files/dir1/dir2');
        rmdir(__DIR__ . '/_tempFiles/_files/dir1');
    }
}

