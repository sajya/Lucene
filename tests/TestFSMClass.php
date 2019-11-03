<?php


namespace Sajya\Lucene\Test;

use Sajya\Lucene\AbstractFSM;
use Sajya\Lucene\FSMAction;

/**
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage UnitTests
 */
class TestFSMClass extends AbstractFSM
{
    public const OPENED = 0;
    public const CLOSED = 1;
    public const CLOSED_AND_LOCKED = 2;

    public const OPENED_AND_LOCKED = 3; // Wrong state, should not be used


    public const OPEN = 0;
    public const CLOSE = 1;
    public const LOCK = 3;
    public const UNLOCK = 4;

    /**
     * Object to trace FSM actions
     *
     * @var FSMData
     */
    public $actionTracer;

    public function __construct()
    {
        $this->actionTracer = new FSMData();

        $this->addStates([self::OPENED, self::CLOSED, self::CLOSED_AND_LOCKED]);
        $this->addInputSymbols([self::OPEN, self::CLOSE, self::LOCK, self::UNLOCK]);

        $unlockAction = new FSMAction($this->actionTracer, 'action4');
        $openAction = new FSMAction($this->actionTracer, 'action6');
        $closeEntryAction = new FSMAction($this->actionTracer, 'action2');
        $closeExitAction = new FSMAction($this->actionTracer, 'action8');

        $this->addRules([[self::OPENED, self::CLOSE, self::CLOSED],
                         [self::CLOSED, self::OPEN, self::OPEN],
                         [self::CLOSED, self::LOCK, self::CLOSED_AND_LOCKED],
                         [self::CLOSED_AND_LOCKED, self::UNLOCK, self::CLOSED, $unlockAction],
        ]);

        $this->addInputAction(self::CLOSED_AND_LOCKED, self::UNLOCK, $unlockAction);

        $this->addTransitionAction(self::CLOSED, self::OPENED, $openAction);

        $this->addEntryAction(self::CLOSED, $closeEntryAction);

        $this->addExitAction(self::CLOSED, $closeExitAction);
    }
}