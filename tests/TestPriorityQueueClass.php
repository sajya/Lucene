<?php


namespace Sajya\Lucene\Test;

use Sajya\Lucene\AbstractPriorityQueue;

class TestPriorityQueueClass extends AbstractPriorityQueue
{
    /**
     * Compare elements
     *
     * Returns true, if $el1 is less than $el2; else otherwise
     *
     * @param mixed $el1
     * @param mixed $el2
     *
     * @return boolean
     */
    protected function _less($el1, $el2)
    {
        return ($el1 < $el2);
    }
}
