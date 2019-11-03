<?php


namespace Sajya\Lucene\Analysis\Analyzer;

use Sajya\Lucene\Analysis\Analyzer\AnalyzerInterface as LuceneAnalyzer;

/**
 * General analyzer implementation.
 *
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage Analysis
 */
abstract class AbstractAnalyzer implements LuceneAnalyzer
{
    /**
     * Input string
     *
     * @var string
     */
    protected $_input = null;

    /**
     * Input string encoding
     *
     * @var string
     */
    protected $_encoding = '';

    /**
     * Tokenize text to a terms
     * Returns array of \Sajya\Lucene\Analysis\Token objects
     *
     * Tokens are returned in UTF-8 (internal Zend_Search_Lucene encoding)
     *
     * @param string $data
     *
     * @return array
     */
    public function tokenize($data, $encoding = '')
    {
        $this->setInput($data, $encoding);

        $tokenList = [];
        while (($nextToken = $this->nextToken()) !== null) {
            $tokenList[] = $nextToken;
        }

        return $tokenList;
    }

    /**
     * Tokenization stream API
     * Set input
     *
     * @param string $data
     */
    public function setInput($data, $encoding = '')
    {
        $this->_input = $data;
        $this->_encoding = $encoding;
        $this->reset();
    }
}
