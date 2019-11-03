<?php


namespace Sajya\Lucene\Search\Weight;

use Sajya\Lucene\Search\Query\AbstractQuery;
use Sajya\Lucene\SearchIndexInterface;

/**
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage Search
 */
class MultiTerm extends AbstractWeight
{
    /**
     * IndexReader.
     *
     * @var SearchIndexInterface
     */
    private $_reader;

    /**
     * The query that this concerns.
     *
     * @var AbstractQuery
     */
    private $_query;

    /**
     * Query terms weights
     * Array of Zend_Search_Lucene_Search_Weight_Term
     *
     * @var array
     */
    private $_weights;


    /**
     * Zend_Search_Lucene_Search_Weight_MultiTerm constructor
     * query - the query that this concerns.
     * reader - index reader
     *
     * @param AbstractQuery        $query
     * @param SearchIndexInterface $reader
     */
    public function __construct(AbstractQuery $query, SearchIndexInterface $reader)
    {
        $this->_query = $query;
        $this->_reader = $reader;
        $this->_weights = [];

        $signs = $query->getSigns();

        foreach ($query->getTerms() as $id => $term) {
            if ($signs === null || $signs[$id] === null || $signs[$id]) {
                $this->_weights[$id] = new Term($term, $query, $reader);
                $query->setWeight($id, $this->_weights[$id]);
            }
        }
    }


    /**
     * The weight for this query
     * Standard Weight::$_value is not used for boolean queries
     *
     * @return float
     */
    public function getValue()
    {
        return $this->_query->getBoost();
    }


    /**
     * The sum of squared weights of contained query clauses.
     *
     * @return float
     */
    public function sumOfSquaredWeights()
    {
        $sum = 0;
        foreach ($this->_weights as $weight) {
            // sum sub weights
            $sum += $weight->sumOfSquaredWeights();
        }

        // boost each sub-weight
        $sum *= $this->_query->getBoost() * $this->_query->getBoost();

        // check for empty query (like '-something -another')
        if ($sum == 0) {
            $sum = 1.0;
        }
        return $sum;
    }


    /**
     * Assigns the query normalization factor to this.
     *
     * @param float $queryNorm
     */
    public function normalize($queryNorm)
    {
        // incorporate boost
        $queryNorm *= $this->_query->getBoost();

        foreach ($this->_weights as $weight) {
            $weight->normalize($queryNorm);
        }
    }
}
