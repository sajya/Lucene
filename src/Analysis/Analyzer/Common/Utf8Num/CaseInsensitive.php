<?php


namespace Sajya\Lucene\Analysis\Analyzer\Common\Utf8Num;

use Sajya\Lucene\Analysis\Analyzer\Common;
use Sajya\Lucene\Analysis\TokenFilter;

/**
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage Analysis
 */
class CaseInsensitive extends Common\Utf8Num
{
    public function __construct()
    {
        parent::__construct();

        $this->addFilter(new TokenFilter\LowerCaseUtf8());
    }
}

