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
        $doorFSM = new testFSMClass();

        $this->assertInstanceOf(Lucene\AbstractFSM::class, $doorFSM);
        $this->assertEquals($doorFSM->getState(), testFSMClass::OPENED);
    }

    public function testSetState(): void
    {
        $doorFSM = new testFSMClass();

        $this->assertEquals($doorFSM->getState(), testFSMClass::OPENED);

        $doorFSM->setState(testFSMClass::CLOSED_AND_LOCKED);
        $this->assertEquals($doorFSM->getState(), testFSMClass::CLOSED_AND_LOCKED);

        $wrongStateExceptionCatched = false;
        try {
            $doorFSM->setState(testFSMClass::OPENED_AND_LOCKED);
        } catch (InvalidArgumentException $e) {
            $wrongStateExceptionCatched = true;
        }
        $this->assertTrue($wrongStateExceptionCatched);
    }

    public function testReset(): void
    {
        $doorFSM = new testFSMClass();

        $doorFSM->setState(testFSMClass::CLOSED_AND_LOCKED);
        $this->assertEquals($doorFSM->getState(), testFSMClass::CLOSED_AND_LOCKED);

        $doorFSM->reset();
        $this->assertEquals($doorFSM->getState(), testFSMClass::OPENED);
    }

    public function testProcess(): void
    {
        $doorFSM = new testFSMClass();

        $doorFSM->process(testFSMClass::CLOSE);
        $this->assertEquals($doorFSM->getState(), testFSMClass::CLOSED);

        $doorFSM->process(testFSMClass::LOCK);
        $this->assertEquals($doorFSM->getState(), testFSMClass::CLOSED_AND_LOCKED);

        $doorFSM->process(testFSMClass::UNLOCK);
        $this->assertEquals($doorFSM->getState(), testFSMClass::CLOSED);

        $doorFSM->process(testFSMClass::OPEN);
        $this->assertEquals($doorFSM->getState(), testFSMClass::OPENED);

        $wrongInputExceptionCatched = false;
        try {
            $doorFSM->process(testFSMClass::LOCK);
        } catch (ExceptionInterface $e) {
            $wrongInputExceptionCatched = true;
        }
        $this->assertTrue($wrongInputExceptionCatched);
    }

    public function testActions(): void
    {
        $doorFSM = new testFSMClass();

        $this->assertFalse($doorFSM->actionTracer->action2Passed /* 'closed' state entry action*/);
        $doorFSM->process(testFSMClass::CLOSE);
        $this->assertTrue($doorFSM->actionTracer->action2Passed);

        $this->assertFalse($doorFSM->actionTracer->action8Passed /* 'closed' state exit action*/);
        $doorFSM->process(testFSMClass::LOCK);
        $this->assertTrue($doorFSM->actionTracer->action8Passed);

        $this->assertFalse($doorFSM->actionTracer->action4Passed /* 'closed&locked' state +'unlock' input action */);
        $doorFSM->process(testFSMClass::UNLOCK);
        $this->assertTrue($doorFSM->actionTracer->action4Passed);

        $this->assertFalse($doorFSM->actionTracer->action6Passed /* 'locked' -> 'opened' transition action action */);
        $doorFSM->process(testFSMClass::OPEN);
        $this->assertTrue($doorFSM->actionTracer->action6Passed);
    }
}
