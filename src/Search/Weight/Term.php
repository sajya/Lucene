<?php


namespace Sajya\Lucene\Search\Weight;

use Sajya\Lucene\Index;
use Sajya\Lucene\Search\Query\AbstractQuery;
use Sajya\Lucene\SearchIndexInterface;

/**
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage Search
 */
class Term extends AbstractWeight
{
    /**
     * IndexReader.
     *
     * @var SearchIndexInterface
     */
    private $_reader;

    /**
     * Term
     *
     * @var Index\Term
     */
    private $_term;

    /**
     * The query that this concerns.
     *
     * @var AbstractQuery
     */
    private $_query;

    /**
     * Score factor
     *
     * @var float
     */
    private $_idf;

    /**
     * Query weight
     *
     * @var float
     */
    private $_queryWeight;


    /**
     * Zend_Search_Lucene_Search_Weight_Term constructor
     * reader - index reader
     *
     * @param Index\Term           $term
     * @param AbstractQuery        $query
     * @param SearchIndexInterface $reader
     */
    public function __construct(Index\Term $term,
                                AbstractQuery $query,
                                SearchIndexInterface $reader)
    {
        $this->_term = $term;
        $this->_query = $query;
        $this->_reader = $reader;
    }


    /**
     * The sum of squared weights of contained query clauses.
     *
     * @return float
     */
    public function sumOfSquaredWeights()
    {
        // compute idf
        $this->_idf = $this->_reader->getSimilarity()->idf($this->_term, $this->_reader);

        // compute query weight
        $this->_queryWeight = $this->_idf * $this->_query->getBoost();

        // square it
        return $this->_queryWeight * $this->_queryWeight;
    }


    /**
     * Assigns the query normalization factor to this.
     *
     * @param float $queryNorm
     */
    public function normalize($queryNorm)
    {
        $this->_queryNorm = $queryNorm;

        // normalize query weight
        $this->_queryWeight *= $queryNorm;

        // idf for documents
        $this->_value = $this->_queryWeight * $this->_idf;
    }
}
