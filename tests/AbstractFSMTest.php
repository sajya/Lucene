<?php


namespace Sajya\Lucene\Test;

use PHPUnit\Framework\TestCase;
use Sajya\Lucene;
use Sajya\Lucene\Exception\ExceptionInterface;
use Sajya\Lucene\Exception\InvalidArgumentException;

/**
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage UnitTests
 * @group      Zend_Search_Lucene
 */
class AbstractFSMTest extends TestCase
{
    public function testCreate(): void
    {
        $doorFSM = new TestFSMClass();

        $this->assertInstanceOf(Lucene\AbstractFSM::class, $doorFSM);
        $this->assertEquals($doorFSM->getState(), TestFSMClass::OPENED);
    }

    public function testSetState(): void
    {
        $doorFSM = new TestFSMClass();

        $this->assertEquals($doorFSM->getState(), TestFSMClass::OPENED);

        $doorFSM->setState(TestFSMClass::CLOSED_AND_LOCKED);
        $this->assertEquals($doorFSM->getState(), TestFSMClass::CLOSED_AND_LOCKED);

        $wrongStateExceptionCatched = false;
        try {
            $doorFSM->setState(TestFSMClass::OPENED_AND_LOCKED);
        } catch (InvalidArgumentException $e) {
            $wrongStateExceptionCatched = true;
        }
        $this->assertTrue($wrongStateExceptionCatched);
    }

    public function testReset(): void
    {
        $doorFSM = new TestFSMClass();

        $doorFSM->setState(TestFSMClass::CLOSED_AND_LOCKED);
        $this->assertEquals($doorFSM->getState(), TestFSMClass::CLOSED_AND_LOCKED);

        $doorFSM->reset();
        $this->assertEquals($doorFSM->getState(), TestFSMClass::OPENED);
    }

    public function testProcess(): void
    {
        $doorFSM = new TestFSMClass();

        $doorFSM->process(TestFSMClass::CLOSE);
        $this->assertEquals($doorFSM->getState(), TestFSMClass::CLOSED);

        $doorFSM->process(TestFSMClass::LOCK);
        $this->assertEquals($doorFSM->getState(), TestFSMClass::CLOSED_AND_LOCKED);

        $doorFSM->process(TestFSMClass::UNLOCK);
        $this->assertEquals($doorFSM->getState(), TestFSMClass::CLOSED);

        $doorFSM->process(TestFSMClass::OPEN);
        $this->assertEquals($doorFSM->getState(), TestFSMClass::OPENED);

        $wrongInputExceptionCatched = false;
        try {
            $doorFSM->process(TestFSMClass::LOCK);
        } catch (ExceptionInterface $e) {
            $wrongInputExceptionCatched = true;
        }
        $this->assertTrue($wrongInputExceptionCatched);
    }

    public function testActions(): void
    {
        $doorFSM = new TestFSMClass();

        $this->assertFalse($doorFSM->actionTracer->action2Passed /* 'closed' state entry action*/);
        $doorFSM->process(TestFSMClass::CLOSE);
        $this->assertTrue($doorFSM->actionTracer->action2Passed);

        $this->assertFalse($doorFSM->actionTracer->action8Passed /* 'closed' state exit action*/);
        $doorFSM->process(TestFSMClass::LOCK);
        $this->assertTrue($doorFSM->actionTracer->action8Passed);

        $this->assertFalse($doorFSM->actionTracer->action4Passed /* 'closed&locked' state +'unlock' input action */);
        $doorFSM->process(TestFSMClass::UNLOCK);
        $this->assertTrue($doorFSM->actionTracer->action4Passed);

        $this->assertFalse($doorFSM->actionTracer->action6Passed /* 'locked' -> 'opened' transition action action */);
        $doorFSM->process(TestFSMClass::OPEN);
        $this->assertTrue($doorFSM->actionTracer->action6Passed);
    }
}
