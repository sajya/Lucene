<?php


namespace Sajya\Lucene\Analysis\TokenFilter;

use Sajya\Lucene\Analysis\Token;

/**
 * Lower case Token filter.
 *
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage Analysis
 */
class LowerCase implements TokenFilterInterface
{
    /**
     * Normalize Token or remove it (if null is returned)
     *
     * @param Token $srcToken
     *
     * @return Token
     */
    public function normalize(Token $srcToken): Token
    {
        $newToken = new Token(strtolower($srcToken->getTermText()),
            $srcToken->getStartOffset(),
            $srcToken->getEndOffset());

        $newToken->setPositionIncrement($srcToken->getPositionIncrement());

        return $newToken;
    }
}

