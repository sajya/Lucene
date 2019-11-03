<?php


namespace Sajya\Lucene\Search\QueryEntry;

use Sajya\Lucene\Search\Exception\QueryParserException;
use Sajya\Lucene\Search\Query\AbstractQuery;

/**
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage Search
 */
class Subquery extends AbstractQueryEntry
{
    /**
     * Query
     *
     * @var AbstractQuery
     */
    private $_query;

    /**
     * Object constractor
     *
     * @param AbstractQuery $query
     */
    public function __construct(AbstractQuery $query)
    {
        $this->_query = $query;
    }

    /**
     * Process modifier ('~')
     *
     * @param mixed $parameter
     *
     * @throws QueryParserException
     */
    public function processFuzzyProximityModifier($parameter = null)
    {
        throw new QueryParserException(
            '\'~\' sign must follow term or phrase'
        );
    }


    /**
     * Transform entry to a subquery
     *
     * @param string $encoding
     *
     * @return AbstractQuery
     */
    public function getQuery($encoding)
    {
        $this->_query->setBoost($this->_boost);

        return $this->_query;
    }
}
