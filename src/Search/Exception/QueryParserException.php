<?php


namespace Sajya\Lucene\Search\Exception;

use Sajya\Lucene\Exception;

/**
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage Search
 *
 * Special exception type, which may be used to intercept wrong user input
 */
class QueryParserException
    extends Exception\UnexpectedValueException
    implements ExceptionInterface
{
}

