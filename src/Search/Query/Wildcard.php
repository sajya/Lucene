<?php


namespace Sajya\Lucene\Search\Query;

use Sajya\Lucene;
use Sajya\Lucene\Analysis\Analyzer\Analyzer;
use Sajya\Lucene\Exception\OutOfBoundsException;
use Sajya\Lucene\Exception\RuntimeException;
use Sajya\Lucene\Exception\UnsupportedMethodCallException;
use Sajya\Lucene\Index;
use Sajya\Lucene\Index\DocsFilter;
use Sajya\Lucene\Search\Highlighter\HighlighterInterface as Highlighter;
use Sajya\Lucene\SearchIndexInterface;
use Zend\Stdlib\ErrorHandler;

/**
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage Search
 */
class Wildcard extends AbstractQuery
{
    /**
     * Minimum term prefix length (number of minimum non-wildcard characters)
     *
     * @var integer
     */
    private static $_minPrefixLength = 3;
    /**
     * Search pattern.
     *
     * Field has to be fully specified or has to be null
     * Text may contain '*' or '?' symbols
     *
     * @var \Sajya\Lucene\Index\Term
     */
    private $_pattern;
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
     * Zend_Search_Lucene_Search_Query_Wildcard constructor.
     *
     * @param \Sajya\Lucene\Index\Term $pattern
     */
    public function __construct(Index\Term $pattern)
    {
        $this->_pattern = $pattern;
    }

    /**
     * Get minimum prefix length
     *
     * @return integer
     */
    public static function getMinPrefixLength(): int
    {
        return self::$_minPrefixLength;
    }

    /**
     * Set minimum prefix length
     *
     * @param integer $minPrefixLength
     */
    public static function setMinPrefixLength($minPrefixLength): void
    {
        self::$_minPrefixLength = $minPrefixLength;
    }

    /**
     * Re-write query into primitive queries in the context of specified index
     *
     * @param SearchIndexInterface $index
     *
     * @return AbstractQuery
     * @throws OutOfBoundsException
     * @throws RuntimeException
     */
    public function rewrite(SearchIndexInterface $index)
    {
        $this->_matches = [];

        if ($this->_pattern->field === null) {
            // Search through all fields
            $fields = $index->getFieldNames(true /* indexed fields list */);
        } else {
            $fields = [$this->_pattern->field];
        }

        $prefix = self::_getPrefix($this->_pattern->text);
        $prefixLength = strlen($prefix);
        $matchExpression = '/^' . str_replace(['\\?', '\\*'], ['.', '.*'], preg_quote($this->_pattern->text, '/')) . '$/';

        if ($prefixLength < self::$_minPrefixLength) {
            throw new RuntimeException(
                'At least ' . self::$_minPrefixLength . ' non-wildcard characters are required at the beginning of pattern.'
            );
        }

        /**
         * @todo check for PCRE unicode support may be performed through Zend_Environment in some future
         */
        ErrorHandler::start(E_WARNING);
        $result = preg_match('/\pL/u', 'a');
        ErrorHandler::stop();
        if ($result == 1) {
            // PCRE unicode support is turned on
            // add Unicode modifier to the match expression
            $matchExpression .= 'u';
        }

        $maxTerms = Lucene\Lucene::getTermsPerQueryLimit();
        foreach ($fields as $field) {
            $index->resetTermsStream();

            if ($prefix != '') {
                $index->skipTo(new Index\Term($prefix, $field));

                while ($index->currentTerm() !== null &&
                    $index->currentTerm()->field == $field &&
                    strpos($index->currentTerm()->text, $prefix) === 0) {
                    if (preg_match($matchExpression, $index->currentTerm()->text) === 1) {
                        $this->_matches[] = $index->currentTerm();

                        if ($maxTerms != 0 && count($this->_matches) > $maxTerms) {
                            throw new OutOfBoundsException('Terms per query limit is reached.');
                        }
                    }

                    $index->nextTerm();
                }
            } else {
                $index->skipTo(new Index\Term('', $field));

                while ($index->currentTerm() !== null && $index->currentTerm()->field == $field) {
                    if (preg_match($matchExpression, $index->currentTerm()->text) === 1) {
                        $this->_matches[] = $index->currentTerm();

                        if ($maxTerms != 0 && count($this->_matches) > $maxTerms) {
                            throw new OutOfBoundsException('Terms per query limit is reached.');
                        }
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
     * Get terms prefix
     *
     * @param string $word
     *
     * @return string
     */
    private static function _getPrefix($word): string
    {
        $questionMarkPosition = strpos($word, '?');
        $astrericPosition = strpos($word, '*');

        if ($questionMarkPosition !== false) {
            if ($astrericPosition !== false) {
                return substr($word, 0, min($questionMarkPosition, $astrericPosition));
            }

            return substr($word, 0, $questionMarkPosition);
        }

        if ($astrericPosition !== false) {
            return substr($word, 0, $astrericPosition);
        }

        return $word;
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
        throw new UnsupportedMethodCallException('Wildcard query should not be directly used for search. Use $query->rewrite($index)');
    }


    /**
     * Returns query pattern
     *
     * @return \Sajya\Lucene\Index\Term
     */
    public function getPattern(): \Sajya\Lucene\Index\Term
    {
        return $this->_pattern;
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
            throw new RuntimeException('Search has to be performed first to get matched terms');
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
        throw new UnsupportedMethodCallException('Wildcard query should not be directly used for search. Use $query->rewrite($index)');
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
        throw new UnsupportedMethodCallException('Wildcard query should not be directly used for search. Use $query->rewrite($index)');
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
            'Wildcard query should not be directly used for search. Use $query->rewrite($index)'
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
            'Wildcard query should not be directly used for search. Use $query->rewrite($index)'
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
        if ($this->_pattern->field !== null) {
            $query = $this->_pattern->field . ':';
        } else {
            $query = '';
        }

        $query .= $this->_pattern->text;

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
        $words = [];

        $matchExpression = '/^' . str_replace(['\\?', '\\*'], ['.', '.*'], preg_quote($this->_pattern->text, '/')) . '$/';
        ErrorHandler::start(E_WARNING);
        $result = preg_match('/\pL/u', 'a');
        ErrorHandler::stop();
        if ($result == 1) {
            // PCRE unicode support is turned on
            // add Unicode modifier to the match expression
            $matchExpression .= 'u';
        }

        $docBody = $highlighter->getDocument()->getFieldUtf8Value('body');
        $tokens = Analyzer::getDefault()->tokenize($docBody, 'UTF-8');
        foreach ($tokens as $token) {
            if (preg_match($matchExpression, $token->getTermText()) === 1) {
                $words[] = $token->getTermText();
            }
        }

        $highlighter->highlight($words);
    }
}
