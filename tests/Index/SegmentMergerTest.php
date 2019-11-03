<?php


namespace Sajya\Lucene\Test\Index;

use PHPUnit\Framework\TestCase;
use Sajya\Lucene\Index;
use Sajya\Lucene\Storage\Directory;

/**
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage UnitTests
 * @group      Zend_Search_Lucene
 */
class SegmentMergerTest extends TestCase
{
    public function testMerge(): void
    {
        $segmentsDirectory = new Directory\Filesystem(__DIR__ . '/_source/_files');
        $outputDirectory = new Directory\Filesystem(__DIR__ . '/_files');
        $segmentsList = ['_0', '_1', '_2', '_3', '_4'];

        $segmentMerger = new Index\SegmentMerger($outputDirectory, 'mergedSegment');

        foreach ($segmentsList as $segmentName) {
            $segmentMerger->addSource(new Index\SegmentInfo($segmentsDirectory, $segmentName, 2));
        }

        $mergedSegment = $segmentMerger->merge();
        $this->assertTrue($mergedSegment instanceof Index\SegmentInfo);
        unset($mergedSegment);

        $mergedFile = $outputDirectory->getFileObject('mergedSegment.cfs');
        $mergedFileData = $mergedFile->readBytes($outputDirectory->fileLength('mergedSegment.cfs'));

        $sampleFile = $outputDirectory->getFileObject('mergedSegment.cfs.sample');
        $sampleFileData = $sampleFile->readBytes($outputDirectory->fileLength('mergedSegment.cfs.sample'));

        $this->assertEquals($mergedFileData, $sampleFileData);

        $outputDirectory->deleteFile('mergedSegment.cfs');
    }
}

