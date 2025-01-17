<?php


namespace Sajya\Lucene\Search\Query;

use Sajya\Lucene;
use Sajya\Lucene\Exception\InvalidArgumentException;
use Sajya\Lucene\Exception\OutOfBoundsException;
use Sajya\Lucene\Exception\RuntimeException;
use Sajya\Lucene\Exception\UnsupportedMethodCallException;
use Sajya\Lucene\Index;
use Sajya\Lucene\Index\DocsFilter;
use Sajya\Lucene\Search\Highlighter\HighlighterInterface as Highlighter;
use Sajya\Lucene\SearchIndexInterface;

/**
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage Search
 */
class Range extends AbstractQuery
{
    /**
     * Lower term.
     *
     * @var \Sajya\Lucene\Index\Term
     */
    private $_lowerTerm;

    /**
     * Upper term.
     *
     * @var \Sajya\Lucene\Index\Term
     */
    private $_upperTerm;


    /**
     * Search field
     *
     * @var string
     */
    private $_field;

    /**
     * Inclusive
     *
     * @var boolean
     */
    private $_inclusive;

    /**
     * Matched terms.
     *
     * Matched terms list.
     * It's filled during the search (rewrite operation) and may be used for search result
     * post-processing
     *
     * Array of Zend_Search_Lucene_Index_Term objects
     *
     * @var array
     */
    private $_matches = null;


    /**
     * Zend_Search_Lucene_Search_Query_Range constructor.
     *
     * @param \Sajya\Lucene\Index\Term|null $lowerTerm
     * @param \Sajya\Lucene\Index\Term|null $upperTerm
     * @param boolean                       $inclusive
     *
     * @throws InvalidArgumentException
     */
    public function __construct($lowerTerm, $upperTerm, $inclusive)
    {
        if ($lowerTerm === null && $upperTerm === null) {
            throw new InvalidArgumentException('At least one term must be non-null');
        }
        if ($lowerTerm !== null && $upperTerm !== null && $lowerTerm->field != $upperTerm->field) {
            throw new InvalidArgumentException('Both terms must be for the same field');
        }

        $this->_field = ($lowerTerm !== null) ? $lowerTerm->field : $upperTerm->field;
        $this->_lowerTerm = $lowerTerm;
        $this->_upperTerm = $upperTerm;
        $this->_inclusive = $inclusive;
    }

    /**
     * Get query field name
     *
     * @return string|null
     */
    public function getField(): ?string
    {
        return $this->_field;
    }

    /**
     * Get lower term
     *
     * @return \Sajya\Lucene\Index\Term|null
     */
    public function getLowerTerm(): ?\Sajya\Lucene\Index\Term
    {
        return $this->_lowerTerm;
    }

    /**
     * Get upper term
     *
     * @return \Sajya\Lucene\Index\Term|null
     */
    public function getUpperTerm(): ?\Sajya\Lucene\Index\Term
    {
        return $this->_upperTerm;
    }

    /**
     * Get upper term
     *
     * @return boolean
     */
    public function isInclusive(): bool
    {
        return $this->_inclusive;
    }

    /**
     * Re-write query into primitive queries in the context of specified index
     *
     * @param SearchIndexInterface $index
     *
     * @return AbstractQuery
     * @throws OutOfBoundsException
     */
    public function rewrite(SearchIndexInterface $index)
    {
        $this->_matches = [];

        if ($this->_field === null) {
            // Search through all fields
            $fields = $index->getFieldNames(true /* indexed fields list */);
        } else {
            $fields = [$this->_field];
        }

        $maxTerms = Lucene\Lucene::getTermsPerQueryLimit();
        foreach ($fields as $field) {
            $index->resetTermsStream();

            if ($this->_lowerTerm !== null) {
                $lowerTerm = new Index\Term($this->_lowerTerm->text, $field);

                $index->skipTo($lowerTerm);

                if (!$this->_inclusive &&
                    $index->currentTerm() == $lowerTerm) {
                    // Skip lower term
                    $index->nextTerm();
                }
            } else {
                $index->skipTo(new Index\Term('', $field));
            }


            if ($this->_upperTerm !== null) {
                // Walk up to the upper term
                $upperTerm = new Index\Term($this->_upperTerm->text, $field);

                while ($index->currentTerm() !== null &&
                    $index->currentTerm()->field == $field &&
                    $index->currentTerm()->text < $upperTerm->text) {
                    $this->_matches[] = $index->currentTerm();

                    if ($maxTerms != 0 && count($this->_matches) > $maxTerms) {
                        throw new OutOfBoundsException('Terms per query limit is reached.');
                    }

                    $index->nextTerm();
                }

                if ($this->_inclusive && $index->currentTerm() == $upperTerm) {
                    // Include upper term into result
                    $this->_matches[] = $upperTerm;
                }
            } else {
                // Walk up to the end of field data
                while ($index->currentTerm() !== null && $index->currentTerm()->field == $field) {
                    $this->_matches[] = $index->currentTerm();

                    if ($maxTerms != 0 && count($this->_matches) > $maxTerms) {
                        throw new OutOfBoundsException('Terms per query limit is reached.');
                    }

                    $index->nextTerm();
                }
            }

            $index->closeTermsStream();
        }

        if (count($this->_matches) == 0) {
            return new EmptyResult();
        }

        if (count($this->_matches) == 1) {
            return new Term(reset($this->_matches));
        } else {
            $rewrittenQuery = new MultiTerm();

            foreach ($this->_matches as $matchedTerm) {
                $rewrittenQuery->addTerm($matchedTerm);
            }

            return $rewrittenQuery;
        }
    }

    /**
     * Optimize query in the context of specified index
     *
     * @param SearchIndexInterface $index
     *
     * @return AbstractQuery
     * @throws UnsupportedMethodCallException
     */
    public function optimize(SearchIndexInterface $index)
    {
        throw new UnsupportedMethodCallException(
            'Range query should not be directly used for search. Use $query->rewrite($index)'
        );
    }

    /**
     * Return query terms
     *
     * @return array
     * @throws RuntimeException
     */
    public function getQueryTerms()
    {
        if ($this->_matches === null) {
            throw new RuntimeException('Search or rewrite operations have to be performed before.');
        }

        return $this->_matches;
    }

    /**
     * Constructs an appropriate Weight implementation for this query.
     *
     * @param SearchIndexInterface $reader
     *
     * @throws UnsupportedMethodCallException
     */
    public function createWeight(SearchIndexInterface $reader)
    {
        throw new UnsupportedMethodCallException(
            'Range query should not be directly used for search. Use $query->rewrite($index)'
        );
    }


    /**
     * Execute query in context of index reader
     * It also initializes necessary internal structures
     *
     * @param SearchIndexInterface $reader
     * @param DocsFilter|null      $docsFilter
     *
     * @throws UnsupportedMethodCallException
     */
    public function execute(SearchIndexInterface $reader, $docsFilter = null)
    {
        throw new UnsupportedMethodCallException(
            'Range query should not be directly used for search. Use $query->rewrite($index)'
        );
    }

    /**
     * Get document ids likely matching the query
     *
     * It's an array with document ids as keys (performance considerations)
     *
     * @return array
     * @throws UnsupportedMethodCallException
     */
    public function matchedDocs()
    {
        throw new UnsupportedMethodCallException(
            'Range query should not be directly used for search. Use $query->rewrite($index)'
        );
    }

    /**
     * Score specified document
     *
     * @param integer              $docId
     * @param SearchIndexInterface $reader
     *
     * @return float
     * @throws UnsupportedMethodCallException
     */
    public function score($docId, SearchIndexInterface $reader)
    {
        throw new UnsupportedMethodCallException(
            'Range query should not be directly used for search. Use $query->rewrite($index)'
        );
    }

    /**
     * Print a query
     *
     * @return string
     */
    public function __toString()
    {
        // It's used only for query visualisation, so we don't care about characters escaping
        return (($this->_field === null) ? '' : $this->_field . ':')
            . (($this->_inclusive) ? '[' : '{')
            . (($this->_lowerTerm !== null) ? $this->_lowerTerm->text : 'null')
            . ' TO '
            . (($this->_upperTerm !== null) ? $this->_upperTerm->text : 'null')
            . (($this->_inclusive) ? ']' : '}')
            . (($this->getBoost() != 1) ? '^' . round($this->getBoost(), 4) : '');
    }

    /**
     * Query specific matches highlighting
     *
     * @param Highlighter $highlighter Highlighter object (also contains doc for highlighting)
     */
    protected function _highlightMatches(Highlighter $highlighter)
    {
        $words = [];

        $docBody = $highlighter->getDocument()->getFieldUtf8Value('body');
        $tokens = Lucene\Analysis\Analyzer\Analyzer::getDefault()->tokenize($docBody, 'UTF-8');

        $lowerTermText = ($this->_lowerTerm !== null) ? $this->_lowerTerm->text : null;
        $upperTermText = ($this->_upperTerm !== null) ? $this->_upperTerm->text : null;

        if ($this->_inclusive) {
            foreach ($tokens as $token) {
                $termText = $token->getTermText();
                if (($lowerTermText == null || $lowerTermText <= $termText) &&
                    ($upperTermText == null || $termText <= $upperTermText)) {
                    $words[] = $termText;
                }
            }
        } else {
            foreach ($tokens as $token) {
                $termText = $token->getTermText();
                if (($lowerTermText == null || $lowerTermText < $termText) &&
                    ($upperTermText == null || $termText < $upperTermText)) {
                    $words[] = $termText;
                }
            }
        }

        $highlighter->highlight($words);
    }
}
