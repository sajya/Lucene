<?php


namespace Sajya\Lucene\Analysis\Analyzer\Common\Text;

use Sajya\Lucene\Analysis\Analyzer\Common;
use Sajya\Lucene\Analysis\TokenFilter;

/**
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage Analysis
 */
class CaseInsensitive extends Common\Text
{
    public function __construct()
    {
        $this->addFilter(new TokenFilter\LowerCase());
    }
}

