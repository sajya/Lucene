<?php


namespace Sajya\Lucene\Analysis\TokenFilter;

use Sajya\Lucene\Analysis\Token;
use Sajya\Lucene\Exception\ExtensionNotLoadedException;

/**
 * Lower case Token filter.
 *
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage Analysis
 */
class LowerCaseUtf8 implements TokenFilterInterface
{
    /**
     * Object constructor
     *
     * @throws ExtensionNotLoadedException
     */
    public function __construct()
    {
        if (!function_exists('mb_strtolower')) {
            // mbstring extension is disabled
            throw new ExtensionNotLoadedException('Utf8 compatible lower case filter needs mbstring extension to be enabled.');
        }
    }

    /**
     * Normalize Token or remove it (if null is returned)
     *
     * @param Token $srcToken
     *
     * @return Token
     */
    public function normalize(Token $srcToken): Token
    {
        $newToken = new Token(mb_strtolower($srcToken->getTermText(), 'UTF-8'),
            $srcToken->getStartOffset(),
            $srcToken->getEndOffset());

        $newToken->setPositionIncrement($srcToken->getPositionIncrement());

        return $newToken;
    }
}

