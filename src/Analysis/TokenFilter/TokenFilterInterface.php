<?php


namespace Sajya\Lucene\Analysis\TokenFilter;

use Sajya\Lucene\Analysis\Token;

/**
 * Token filter converts (normalizes) Token ore removes it from a token stream.
 *
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage Analysis
 */
interface TokenFilterInterface
{
    /**
     * Normalize Token or remove it (if null is returned)
     *
     * @param Token $srcToken
     *
     * @return Token
     */
    public function normalize(Token $srcToken);
}
