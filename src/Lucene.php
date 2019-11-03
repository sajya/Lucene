<?php


namespace Sajya\Lucene;

use Sajya\Lucene\Exception\UnsupportedMethodCallException;

/**
 * @category   Zend
 * @package    Zend_Search_Lucene
 */
class Lucene
{
    /**
     * Default field name for search
     *
     * Null means search through all fields
     *
     * @var string
     */
    private static $_defaultSearchField = null;

    /**
     * Result set limit
     *
     * 0 means no limit
     *
     * @var integer
     */
    private static $_resultSetLimit = 0;

    /**
     * Terms per query limit
     *
     * 0 means no limit
     *
     * @var integer
     */
    private static $_termsPerQueryLimit = 1024;

    /**
     * @throws UnsupportedMethodCallException
     */
    public function __construct()
    {
        throw new UnsupportedMethodCallException('\Sajya\Lucene class is the only container for static methods. Use Lucene::open() or Lucene::create() methods.');
    }

    /**
     * Create index
     *
     * @param mixed $directory
     *
     * @return SearchIndexInterface
     */
    public static function create($directory): SearchIndexInterface
    {
        return new Index($directory, true);
    }

    /**
     * Open index
     *
     * @param mixed $directory
     *
     * @return SearchIndexInterface
     */
    public static function open($directory): SearchIndexInterface
    {
        return new Index($directory, false);
    }

    /**
     * Get default search field.
     *
     * Null means, that search is performed through all fields by default
     *
     * @return string
     */
    public static function getDefaultSearchField(): ?string
    {
        return self::$_defaultSearchField;
    }

    /**
     * Set default search field.
     *
     * Null means, that search is performed through all fields by default
     *
     * Default value is null
     *
     * @param string $fieldName
     */
    public static function setDefaultSearchField($fieldName): void
    {
        self::$_defaultSearchField = $fieldName;
    }

    /**
     * Get result set limit.
     *
     * 0 means no limit
     *
     * @return integer
     */
    public static function getResultSetLimit(): int
    {
        return self::$_resultSetLimit;
    }

    /**
     * Set result set limit.
     *
     * 0 (default) means no limit
     *
     * @param integer $limit
     */
    public static function setResultSetLimit($limit): void
    {
        self::$_resultSetLimit = $limit;
    }

    /**
     * Get result set limit.
     *
     * 0 (default) means no limit
     *
     * @return integer
     */
    public static function getTermsPerQueryLimit(): int
    {
        return self::$_termsPerQueryLimit;
    }

    /**
     * Set terms per query limit.
     *
     * 0 means no limit
     *
     * @param integer $limit
     */
    public static function setTermsPerQueryLimit($limit): void
    {
        self::$_termsPerQueryLimit = $limit;
    }
}
