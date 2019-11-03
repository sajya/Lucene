<?php


namespace Sajya\Lucene\Index;

/**
 * A Zend_Search_Lucene_Index_DocsFilter is used to filter documents while searching.
 *
 * It may or _may_not_ be used for actual filtering, so it's just a hint that upper query limits
 * search result by specified list.
 *
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage Index
 */
class DocsFilter
{
    /**
     * Set of segment filters:
     *  array( <segmentName> => array(<docId> => <undefined_value>,
     *                                <docId> => <undefined_value>,
     *                                <docId> => <undefined_value>,
     *                                ...                          ),
     *         <segmentName> => array(<docId> => <undefined_value>,
     *                                <docId> => <undefined_value>,
     *                                <docId> => <undefined_value>,
     *                                ...                          ),
     *         <segmentName> => array(<docId> => <undefined_value>,
     *                                <docId> => <undefined_value>,
     *                                <docId> => <undefined_value>,
     *                                ...                          ),
     *         ...
     *       )
     *
     * @var array
     */
    public $segmentFilters = [];
}

