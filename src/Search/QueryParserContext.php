<?php


namespace Sajya\Lucene\Search;

use Sajya\Lucene\Exception\ExceptionInterface;
use Sajya\Lucene\Exception\UnexpectedValueException;
use Sajya\Lucene\Search\Exception\QueryParserException;
use Sajya\Lucene\Search\Query\AbstractQuery;
use Sajya\Lucene\Search\QueryEntry\AbstractQueryEntry;

/**
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage Search
 */
class QueryParserContext
{
    /**
     * Entries grouping mode
     */
    public const GM_SIGNS = 0;
    public const GM_BOOLEAN = 1;
    /**
     * Default field for the context.
     *
     * null means, that term should be searched through all fields
     * Zend_Search_Lucene_Search_Query::rewriteQuery($index) transletes such queries to several
     *
     * @var string|null
     */
    private $_defaultField;
    /**
     * Field specified for next entry
     *
     * @var string
     */
    private $_nextEntryField = null;  // Signs mode: '+term1 term2 -term3 +(subquery1) -(subquery2)'
    /**
     * True means, that term is required.
     * False means, that term is prohibited.
     * null means, that term is neither prohibited, nor required
     *
     * @var boolean
     */
    private $_nextEntrySign = null;  // Boolean operators mode: 'term1 and term2  or  (subquery1) and not (subquery2)'
    /**
     * Grouping mode
     *
     * @var integer
     */
    private $_mode = null;

    /**
     * Entries signs.
     * Used in GM_SIGNS grouping mode
     *
     * @var array
     */
    private $_signs = [];

    /**
     * Query entries
     * Each entry is a Zend_Search_Lucene_Search_QueryEntry object or
     * boolean operator (Zend_Search_Lucene_Search_QueryToken class constant)
     *
     * @var array
     */
    private $_entries = [];

    /**
     * Query string encoding
     *
     * @var string
     */
    private $_encoding;


    /**
     * Context object constructor
     *
     * @param string      $encoding
     * @param string|null $defaultField
     */
    public function __construct($encoding, $defaultField = null)
    {
        $this->_encoding = $encoding;
        $this->_defaultField = $defaultField;
    }


    /**
     * Get context default field
     *
     * @return string|null
     */
    public function getField(): ?string
    {
        return $this->_nextEntryField ?? $this->_defaultField;
    }

    /**
     * Set field for next entry
     *
     * @param string $field
     */
    public function setNextEntryField($field): void
    {
        $this->_nextEntryField = $field;
    }


    /**
     * Set sign for next entry
     *
     * @param integer $sign
     *
     * @throws QueryParserException
     * @throws UnexpectedValueException
     */
    public function setNextEntrySign($sign): void
    {
        if ($this->_mode === self::GM_BOOLEAN) {
            throw new QueryParserException('It\'s not allowed to mix boolean and signs styles in the same subquery.');
        }

        $this->_mode = self::GM_SIGNS;

        if ($sign == QueryToken::TT_REQUIRED) {
            $this->_nextEntrySign = true;
        } else if ($sign == QueryToken::TT_PROHIBITED) {
            $this->_nextEntrySign = false;
        } else {
            throw new UnexpectedValueException('Unrecognized sign type.');
        }
    }


    /**
     * Add entry to a query
     *
     * @param AbstractQueryEntry $entry
     */
    public function addEntry(QueryEntry\AbstractQueryEntry $entry): void
    {
        if ($this->_mode !== self::GM_BOOLEAN) {
            $this->_signs[] = $this->_nextEntrySign;
        }

        $this->_entries[] = $entry;

        $this->_nextEntryField = null;
        $this->_nextEntrySign = null;
    }


    /**
     * Process fuzzy search or proximity search modifier
     *
     * @throws QueryParserException
     */
    public function processFuzzyProximityModifier($parameter = null): void
    {
        // Check, that modifier has came just after word or phrase
        if ($this->_nextEntryField !== null || $this->_nextEntrySign !== null) {
            throw new QueryParserException('\'~\' modifier must follow word or phrase.');
        }

        $lastEntry = array_pop($this->_entries);

        if (!$lastEntry instanceof QueryEntry\AbstractQueryEntry) {
            // there are no entries or last entry is boolean operator
            throw new QueryParserException('\'~\' modifier must follow word or phrase.');
        }

        $lastEntry->processFuzzyProximityModifier($parameter);

        $this->_entries[] = $lastEntry;
    }

    /**
     * Set boost factor to the entry
     *
     * @param float $boostFactor
     *
     * @throws QueryParserException
     */
    public function boost($boostFactor): void
    {
        // Check, that modifier has came just after word or phrase
        if ($this->_nextEntryField !== null || $this->_nextEntrySign !== null) {
            throw new QueryParserException('\'^\' modifier must follow word, phrase or subquery.');
        }

        $lastEntry = array_pop($this->_entries);

        if (!$lastEntry instanceof QueryEntry\AbstractQueryEntry) {
            // there are no entries or last entry is boolean operator
            throw new QueryParserException('\'^\' modifier must follow word, phrase or subquery.');
        }

        $lastEntry->boost($boostFactor);

        $this->_entries[] = $lastEntry;
    }

    /**
     * Process logical operator
     *
     * @param integer $operator
     *
     * @throws QueryParserException
     */
    public function addLogicalOperator($operator): void
    {
        if ($this->_mode === self::GM_SIGNS) {
            throw new QueryParserException('It\'s not allowed to mix boolean and signs styles in the same subquery.');
        }

        $this->_mode = self::GM_BOOLEAN;

        $this->_entries[] = $operator;
    }

    /**
     * Generate query from current context
     *
     * @return AbstractQuery
     */
    public function getQuery(): ?AbstractQuery
    {
        if ($this->_mode === self::GM_BOOLEAN) {
            return $this->_booleanExpressionQuery();
        }

        return $this->_signStyleExpressionQuery();
    }

    /**
     * Generate 'boolean style' query from the context
     * 'term1 and term2   or   term3 and (<subquery1>) and not (<subquery2>)'
     *
     * @return AbstractQuery
     * @throws QueryParserException
     */
    private function _booleanExpressionQuery(): AbstractQuery
    {
        /**
         * We treat each level of an expression as a boolean expression in
         * a Disjunctive Normal Form
         *
         * AND operator has higher precedence than OR
         *
         * Thus logical query is a disjunction of one or more conjunctions of
         * one or more query entries
         */

        $expressionRecognizer = new BooleanExpressionRecognizer();

        try {
            foreach ($this->_entries as $entry) {
                if ($entry instanceof QueryEntry\AbstractQueryEntry) {
                    $expressionRecognizer->processLiteral($entry);
                } else {
                    switch ($entry) {
                        case QueryToken::TT_AND_LEXEME:
                            $expressionRecognizer->processOperator(BooleanExpressionRecognizer::IN_AND_OPERATOR);
                            break;

                        case QueryToken::TT_OR_LEXEME:
                            $expressionRecognizer->processOperator(BooleanExpressionRecognizer::IN_OR_OPERATOR);
                            break;

                        case QueryToken::TT_NOT_LEXEME:
                            $expressionRecognizer->processOperator(BooleanExpressionRecognizer::IN_NOT_OPERATOR);
                            break;

                        default:
                            throw new UnexpectedValueException('Boolean expression error. Unknown operator type.');
                    }
                }
            }

            $conjuctions = $expressionRecognizer->finishExpression();
        } catch (ExceptionInterface $e) {
            // It's query syntax error message and it should be user friendly. So FSM message is omitted
            throw new QueryParserException('Boolean expression error.', 0, $e);
        }

        // Remove 'only negative' conjunctions
        foreach ($conjuctions as $conjuctionId => $conjuction) {
            $nonNegativeEntryFound = false;

            foreach ($conjuction as $conjuctionEntry) {
                if ($conjuctionEntry[1]) {
                    $nonNegativeEntryFound = true;
                    break;
                }
            }

            if (!$nonNegativeEntryFound) {
                unset($conjuctions[$conjuctionId]);
            }
        }


        $subqueries = [];
        foreach ($conjuctions as $conjuction) {
            // Check, if it's a one term conjuction
            if (count($conjuction) == 1) {
                $subqueries[] = $conjuction[0][0]->getQuery($this->_encoding);
            } else {
                $subquery = new Query\Boolean();

                foreach ($conjuction as $conjuctionEntry) {
                    $subquery->addSubquery($conjuctionEntry[0]->getQuery($this->_encoding), $conjuctionEntry[1]);
                }

                $subqueries[] = $subquery;
            }
        }

        if (count($subqueries) == 0) {
            return new Query\Insignificant();
        }

        if (count($subqueries) == 1) {
            return $subqueries[0];
        }


        $query = new Query\Boolean();

        foreach ($subqueries as $subquery) {
            // Non-requirered entry/subquery
            $query->addSubquery($subquery);
        }

        return $query;
    }

    /**
     * Generate 'signs style' query from the context
     * '+term1 term2 -term3 +(<subquery1>) ...'
     *
     * @return AbstractQuery
     */
    public function _signStyleExpressionQuery(): AbstractQuery
    {
        $query = new Query\Boolean();

        if (QueryParser::getDefaultOperator() == QueryParser::B_AND) {
            $defaultSign = true; // required
        } else {
            $defaultSign = null; // optional
        }

        foreach ($this->_entries as $entryId => $entry) {
            $sign = $this->_signs[$entryId] ?? $defaultSign;
            $query->addSubquery($entry->getQuery($this->_encoding), $sign);
        }

        return $query;
    }
}
