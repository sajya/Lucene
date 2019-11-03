<?php


namespace Sajya\Lucene\Test;

use PHPUnit\Framework\TestCase;
use Sajya\Lucene;

/**
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage UnitTests
 * @group      Zend_Search_Lucene
 */
class MultiIndexTest extends TestCase
{
    /**
     * @covers Sajya\Lucene\MultiSearcher::find
     * @covers Sajya\Lucene\Search\QueryHit::getDocument
     */
    public function testFind(): void
    {
        $index = new Lucene\MultiSearcher([
            Lucene\Lucene::open(__DIR__ . '/_indexSample/_files'),
            Lucene\Lucene::open(__DIR__ . '/_indexSample/_files'),
        ]);

        $hits = $index->find('submitting');
        $this->assertEquals(count($hits), 2 * 3);
        foreach ($hits as $hit) {
            $document = $hit->getDocument();
            $this->assertInstanceOf(Lucene\Document::class, $document);
        }
    }
}
