<?php


namespace Sajya\Lucene\Analysis\Analyzer;

use Sajya\Lucene\Analysis\Token;

/**
 * An AnalyzerInterface is used to analyze text.
 *
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage Analysis
 */
interface AnalyzerInterface
{
    /**
     * Tokenize text to terms
     * Returns array of Sajya\Lucene\Analysis\Token objects
     *
     * Tokens are returned in UTF-8 (internal Zend_Search_Lucene encoding)
     *
     * @param string $data
     * @param string $encoding
     *
     * @return array
     */
    public function tokenize(string $data, string $encoding = '');

    /**
     * Tokenization stream API
     * Set input
     *
     * @param string $data
     * @param string $encoding
     */
    public function setInput(string $data, string $encoding = '');

    /**
     * Reset token stream
     */
    public function reset();

    /**
     * Tokenization stream API
     * Get next token
     * Returns null at the end of stream
     *
     * Tokens are returned in UTF-8 (internal Zend_Search_Lucene encoding)
     *
     * @return Token|null
     */
    public function nextToken();
}
