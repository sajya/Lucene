<?php


namespace Sajya\Lucene\Analysis\Analyzer\Common\TextNum;

use Sajya\Lucene\Analysis\Analyzer\Common;
use Sajya\Lucene\Analysis\TokenFilter;

/**
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage Analysis
 */
class CaseInsensitive extends Common\TextNum
{
    public function __construct()
    {
        $this->addFilter(new TokenFilter\LowerCase());
    }
}

