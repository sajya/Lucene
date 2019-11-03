<?php

namespace Sajya\Lucene\Test\Index;

use PHPUnit\Framework\TestCase;
use Sajya\Lucene\Index;

/**
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage UnitTests
 * @group      Zend_Search_Lucene
 */
class TermInfoTest extends TestCase
{
    public function testCreate(): void
    {
        $termInfo = new Index\TermInfo(0, 1, 2, 3);
        $this->assertTrue($termInfo instanceof Index\TermInfo);

        $this->assertEquals($termInfo->docFreq, 0);
        $this->assertEquals($termInfo->freqPointer, 1);
        $this->assertEquals($termInfo->proxPointer, 2);
        $this->assertEquals($termInfo->skipOffset, 3);
        $this->assertEquals($termInfo->indexPointer, null);

        $termInfo = new Index\TermInfo(0, 1, 2, 3, 4);
        $this->assertEquals($termInfo->indexPointer, 4);
    }
}

