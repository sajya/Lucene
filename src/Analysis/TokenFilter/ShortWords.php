<?php


namespace Sajya\Lucene\Analysis\TokenFilter;

use Sajya\Lucene\Analysis\Token;

/**
 * Token filter that removes short words. What is short word can be configured with constructor.
 *
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage Analysis
 */
class ShortWords implements TokenFilterInterface
{
    /**
     * Minimum allowed term length
     *
     * @var integer
     */
    private $length;

    /**
     * Constructs new instance of this filter.
     *
     * @param integer $short minimum allowed length of term which passes this filter (default 2)
     */
    public function __construct(int $length = 2)
    {
        $this->length = $length;
    }

    /**
     * Normalize Token or remove it (if null is returned)
     *
     * @param Token $srcToken
     *
     * @return Token
     */
    public function normalize(Token $srcToken): ?Token
    {
        if (strlen($srcToken->getTermText()) < $this->length) {
            return null;
        }

        return $srcToken;
    }
}

