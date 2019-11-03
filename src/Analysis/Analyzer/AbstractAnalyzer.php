<?php


namespace Sajya\Lucene\Analysis\Analyzer;

use Sajya\Lucene\Analysis\Analyzer\AnalyzerInterface as LuceneAnalyzer;
use Sajya\Lucene\Analysis\Token;

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
    protected $input;

    /**
     * Input string encoding
     *
     * @var string
     */
    protected $encoding = '';

    /**
     * Tokenize text to a terms
     * Tokens are returned in UTF-8 (internal Zend_Search_Lucene encoding)
     *
     * @param string $data
     * @param string $encoding
     *
     * @return Token[]
     */
    public function tokenize(string $data, string $encoding = ''): array
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
     * @param string $encoding
     */
    public function setInput(string $data, string $encoding = '')
    {
        $this->input = $data;
        $this->encoding = $encoding;
        $this->reset();
    }
}
