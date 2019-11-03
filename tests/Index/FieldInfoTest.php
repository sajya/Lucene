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
class FieldInfoTest extends TestCase
{
    public function testCreate(): void
    {
        $fieldInfo = new Index\FieldInfo('field_name', true, 3, false);
        $this->assertInstanceOf(Index\FieldInfo::class, $fieldInfo);

        $this->assertEquals($fieldInfo->name, 'field_name');
        $this->assertEquals($fieldInfo->isIndexed, true);
        $this->assertEquals($fieldInfo->number, 3);
        $this->assertEquals($fieldInfo->storeTermVector, false);
    }
}

