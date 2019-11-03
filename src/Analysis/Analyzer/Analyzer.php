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
    private static $defaultImpl = null;

    /**
     * Set the default AnalyzerInterface implementation used by indexing code.
     *
     * @param AnalyzerInterface $analyzer
     */
    public static function setDefault(LuceneAnalyzer $analyzer): void
    {
        self::$defaultImpl = $analyzer;
    }

    /**
     * Return the default AnalyzerInterface implementation used by indexing code.
     *
     * @return AnalyzerInterface
     */
    public static function getDefault(): LuceneAnalyzer
    {
        if (self::$defaultImpl === null) {
            self::$defaultImpl = new Common\Text\CaseInsensitive();
        }

        return self::$defaultImpl;
    }
}
