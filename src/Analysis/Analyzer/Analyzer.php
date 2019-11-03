<?php


namespace Sajya\Lucene\Analysis\Analyzer;

use Sajya\Lucene\Analysis\Analyzer\AnalyzerInterface as LuceneAnalyzer;

/**
 * AnalyzerInterface manager.
 *
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage Analysis
 */
class Analyzer
{
    /**
     * The AnalyzerInterface implementation used by default.
     *
     * @var AnalyzerInterface
     */
    private static $_defaultImpl = null;

    /**
     * Set the default AnalyzerInterface implementation used by indexing code.
     *
     * @param AnalyzerInterface $analyzer
     */
    public static function setDefault(LuceneAnalyzer $analyzer): void
    {
        self::$_defaultImpl = $analyzer;
    }

    /**
     * Return the default AnalyzerInterface implementation used by indexing code.
     *
     * @return AnalyzerInterface
     */
    public static function getDefault(): LuceneAnalyzer
    {
        if (self::$_defaultImpl === null) {
            self::$_defaultImpl = new Common\Text\CaseInsensitive();
        }

        return self::$_defaultImpl;
    }
}
