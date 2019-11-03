<?php


namespace Sajya\Lucene\Search\Query;

use Sajya\Lucene\Index;
use Sajya\Lucene\Index\DocsFilter;
use Sajya\Lucene\Search\Highlighter\HighlighterInterface as Highlighter;
use Sajya\Lucene\Search\Weight;
use Sajya\Lucene\SearchIndexInterface;

/**
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage Search
 */
class Term extends AbstractQuery
{
    /**
     * Term to find.
     *
     * @var Index\Term
     */
    private $_term;

    /**
     * Documents vector.
     *
     * @var array
     */
    private $_docVector = null;

    /**
     * Term freqs vector.
     * array(docId => freq, ...)
     *
     * @var array
     */
    private $_termFreqs;


    /**
     * Zend_Search_Lucene_Search_Query_Term constructor
     *
     * @param Index\Term $term
     * @param boolean    $sign
     */
    public function __construct(Index\Term $term)
    {
        $this->_term = $term;
    }

    /**
     * Re-write query into primitive queries in the context of specified index
     *
     * @param SearchIndexInterface $index
     *
     * @return AbstractQuery
     */
    public function rewrite(SearchIndexInterface $index)
    {
        if ($this->_term->field != null) {
            return $this;
        }

        $query = new MultiTerm();
        $query->setBoost($this->getBoost());

        foreach ($index->getFieldNames(true) as $fieldName) {
            $term = new Index\Term($this->_term->text, $fieldName);

            $query->addTerm($term);
        }

        return $query->rewrite($index);
    }

    /**
     * Optimize query in the context of specified index
     *
     * @param SearchIndexInterface $index
     *
     * @return AbstractQuery
     */
    public function optimize(SearchIndexInterface $index)
    {
        // Check, that index contains specified term
        if (!$index->hasTerm($this->_term)) {
            return new EmptyResult();
        }

        return $this;
    }


    /**
     * Constructs an appropriate Weight implementation for this query.
     *
     * @param SearchIndexInterface $reader
     *
     * @return Weight\Term
     */
    public function createWeight(SearchIndexInterface $reader)
    {
        $this->_weight = new Weight\Term($this->_term, $this, $reader);
        return $this->_weight;
    }

    /**
     * Execute query in context of index reader
     * It also initializes necessary internal structures
     *
     * @param SearchIndexInterface $reader
     * @param DocsFilter|null      $docsFilter
     */
    public function execute(SearchIndexInterface $reader, $docsFilter = null)
    {
        $this->_docVector = array_flip($reader->termDocs($this->_term, $docsFilter));
        $this->_termFreqs = $reader->termFreqs($this->_term, $docsFilter);

        // Initialize weight if it's not done yet
        $this->_initWeight($reader);
    }

    /**
     * Get document ids likely matching the query
     *
     * It's an array with document ids as keys (performance considerations)
     *
     * @return array
     */
    public function matchedDocs()
    {
        return $this->_docVector;
    }

    /**
     * Score specified document
     *
     * @param integer              $docId
     * @param SearchIndexInterface $reader
     *
     * @return float
     */
    public function score($docId, SearchIndexInterface $reader)
    {
        if (isset($this->_docVector[$docId])) {
            return $reader->getSimilarity()->tf($this->_termFreqs[$docId]) *
                $this->_weight->getValue() *
                $reader->norm($docId, $this->_term->field) *
                $this->getBoost();
        }

        return 0;
    }

    /**
     * Return query terms
     *
     * @return array
     */
    public function getQueryTerms()
    {
        return [$this->_term];
    }

    /**
     * Return query term
     *
     * @return Index\Term
     */
    public function getTerm(): Index\Term
    {
        return $this->_term;
    }

    /**
     * Print a query
     *
     * @return string
     */
    public function __toString()
    {
        // It's used only for query visualisation, so we don't care about characters escaping
        if ($this->_term->field !== null) {
            $query = $this->_term->field . ':';
        } else {
            $query = '';
        }

        $query .= $this->_term->text;

        if ($this->getBoost() != 1) {
            $query .= '^' . round($this->getBoost(), 4);
        }

        return $query;
    }

    /**
     * Query specific matches highlighting
     *
     * @param Highlighter $highlighter Highlighter object (also contains doc for highlighting)
     */
    protected function _highlightMatches(Highlighter $highlighter)
    {
        $highlighter->highlight($this->_term->text);
    }
}

