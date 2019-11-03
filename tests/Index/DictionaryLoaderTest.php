<?php

namespace Sajya\Lucene\Test\Index;

use PHPUnit\Framework\TestCase;
use Sajya\Lucene\Index\DictionaryLoader;
use Sajya\Lucene\Index\SegmentInfo;
use Sajya\Lucene\Storage\Directory\Filesystem;


/**
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage UnitTests
 * @group      Zend_Search_Lucene
 */
class DictionaryLoaderTest extends TestCase
{
    public function testCreate(): void
    {
        $directory = new Filesystem(__DIR__ . '/_source/_files');

        $stiFile = $directory->getFileObject('_1.sti');
        $stiFileData = $stiFile->readBytes($directory->fileLength('_1.sti'));

        // Load dictionary index data
        [$termDictionary, $termDictionaryInfos] = unserialize($stiFileData);


        $segmentInfo = new SegmentInfo($directory, '_1', 2);
        $tiiFile = $segmentInfo->openCompoundFile('.tii');
        $tiiFileData = $tiiFile->readBytes($segmentInfo->compoundFileLength('.tii'));

        // Load dictionary index data
        [$loadedTermDictionary, $loadedTermDictionaryInfos] =
            DictionaryLoader::load($tiiFileData);

        $this->assertEquals($termDictionary, $loadedTermDictionary);
        $this->assertEquals($termDictionaryInfos, $loadedTermDictionaryInfos);
    }
}

