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
class PriorityQueueTest extends TestCase
{
    public function testCreate(): void
    {
        $queue = new testPriorityQueueClass();

        $this->assertInstanceOf(Lucene\AbstractPriorityQueue::class, $queue);
    }

    public function testPut(): void
    {
        $queue = new testPriorityQueueClass();

        $queue->put(1);
        $queue->put(100);
        $queue->put(46);
        $queue->put(347);
        $queue->put(11);
        $queue->put(125);
        $queue->put(-10);
        $queue->put(100);
    }

    public function testPop(): void
    {
        $queue = new testPriorityQueueClass();

        $queue->put(1);
        $queue->put(100);
        $queue->put(46);
        $queue->put(347);
        $queue->put(11);
        $queue->put(125);
        $queue->put(-10);
        $queue->put(100);

        $this->assertEquals($queue->pop(), -10);
        $this->assertEquals($queue->pop(), 1);
        $this->assertEquals($queue->pop(), 11);
        $this->assertEquals($queue->pop(), 46);
        $this->assertEquals($queue->pop(), 100);
        $this->assertEquals($queue->pop(), 100);
        $this->assertEquals($queue->pop(), 125);

        $queue->put(144);
        $queue->put(546);
        $queue->put(15);
        $queue->put(125);
        $queue->put(325);
        $queue->put(-12);
        $queue->put(347);

        $this->assertEquals($queue->pop(), -12);
        $this->assertEquals($queue->pop(), 15);
        $this->assertEquals($queue->pop(), 125);
        $this->assertEquals($queue->pop(), 144);
        $this->assertEquals($queue->pop(), 325);
        $this->assertEquals($queue->pop(), 347);
        $this->assertEquals($queue->pop(), 347);
        $this->assertEquals($queue->pop(), 546);
    }

    public function testClear(): void
    {
        $queue = new testPriorityQueueClass();

        $queue->put(1);
        $queue->put(100);
        $queue->put(46);
        $queue->put(-10);
        $queue->put(100);

        $this->assertEquals($queue->pop(), -10);
        $this->assertEquals($queue->pop(), 1);
        $this->assertEquals($queue->pop(), 46);

        $queue->clear();
        $this->assertEquals($queue->pop(), null);

        $queue->put(144);
        $queue->put(546);
        $queue->put(15);

        $this->assertEquals($queue->pop(), 15);
        $this->assertEquals($queue->pop(), 144);
        $this->assertEquals($queue->pop(), 546);
    }
}

