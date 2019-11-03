<?php


namespace Sajya\Lucene\Test;


/**
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage UnitTests
 */
class FSMData
{
    public $action1Passed = false;
    public $action2Passed = false;
    public $action3Passed = false;
    public $action4Passed = false;
    public $action5Passed = false;
    public $action6Passed = false;
    public $action7Passed = false;
    public $action8Passed = false;

    public function action1(): void
    {
        $this->action1Passed = true;
    }

    public function action2(): void
    {
        $this->action2Passed = true;
    }

    public function action3(): void
    {
        $this->action3Passed = true;
    }

    public function action4(): void
    {
        $this->action4Passed = true;
    }

    public function action5(): void
    {
        $this->action5Passed = true;
    }

    public function action6(): void
    {
        $this->action6Passed = true;
    }

    public function action7(): void
    {
        $this->action7Passed = true;
    }

    public function action8(): void
    {
        $this->action8Passed = true;
    }
}