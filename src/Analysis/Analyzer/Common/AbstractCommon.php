<?php


namespace Sajya\Lucene\Analysis\Analyzer\Common;

use Sajya\Lucene\Analysis;
use Sajya\Lucene\Analysis\Token;
use Sajya\Lucene\Analysis\TokenFilter\TokenFilterInterface;

/**
 * AbstractCommon implementation of the analyzerfunctionality.
 *
 * There are several standard standard subclasses provided
 * by Analysis subpackage.
 *
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage Analysis
 */
abstract class AbstractCommon extends Analysis\Analyzer\AbstractAnalyzer
{
    /**
     * The set of Token filters applied to the Token stream.
     * Array of \Sajya\Lucene\Analysis\TokenFilter\TokenFilterInterface objects.
     *
     * @var array
     */
    private $_filters = [];

    /**
     * Add Token filter to the AnalyzerInterface
     *
     * @param TokenFilterInterface $filter
     */
    public function addFilter(TokenFilterInterface $filter): void
    {
        $this->_filters[] = $filter;
    }

    /**
     * Apply filters to the token. Can return null when the token was removed.
     *
     * @param Token $token
     *
     * @return Token
     */
    public function normalize(Token $token): ?Token
    {
        foreach ($this->_filters as $filter) {
            $token = $filter->normalize($token);

            // resulting token can be null if the filter removes it
            if ($token === null) {
                return null;
            }
        }

        return $token;
    }
}
