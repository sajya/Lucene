<?php


namespace Sajya\Lucene\Search\QueryEntry;

use Sajya\Lucene\Search\Query\AbstractQuery;

/**
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage Search
 */
abstract class AbstractQueryEntry
{
    /**
     * Query entry boost factor
     *
     * @var float
     */
    protected $_boost = 1.0;


    /**
     * Process modifier ('~')
     *
     * @param mixed $parameter
     */
    abstract public function processFuzzyProximityModifier($parameter = null);


    /**
     * Transform entry to a subquery
     *
     * @param string $encoding
     *
     * @return AbstractQuery
     */
    abstract public function getQuery($encoding);

    /**
     * Boost query entry
     *
     * @param float $boostFactor
     */
    public function boost($boostFactor): void
    {
        $this->_boost *= $boostFactor;
    }
}
