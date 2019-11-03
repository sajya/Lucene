<?php


namespace Sajya\Lucene\Search;

use Sajya\Lucene\Document;
use Sajya\Lucene\SearchIndexInterface;

/**
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage Search
 */
class QueryHit
{
    /**
     * Unique hit id
     *
     * @var integer
     */
    public $id;
    /**
     * Number of the document in the index
     *
     * @var integer
     */
    public $document_id;
    /**
     * Score of the hit
     *
     * @var float
     */
    public $score;
    /**
     * Object handle of the index
     *
     * @var SearchIndexInterface
     */
    protected $_index = null;
    /**
     * Object handle of the document associated with this hit
     *
     * @var Document
     */
    protected $_document = null;

    /**
     * Constructor - pass object handle of Zend_Search_Lucene_Interface index that produced
     * the hit so the document can be retrieved easily from the hit.
     *
     * @param SearchIndexInterface $index
     */

    public function __construct(SearchIndexInterface $index)
    {
        $this->_index = $index;
    }

    /**
     * Magic method for checking the existence of a field
     *
     * @param string $offset
     *
     * @return boolean TRUE if the field exists else FALSE
     */
    public function __isset($offset)
    {
        return isset($this->getDocument()->$offset);
    }

    /**
     * Return the document object for this hit
     *
     * @return Document
     */
    public function getDocument(): Document
    {
        if (!$this->_document instanceof Document) {
            $this->_document = $this->_index->getDocument($this->document_id);
        }

        return $this->_document;
    }

    /**
     * Convenience function for getting fields from the document
     * associated with this hit.
     *
     * @param string $offset
     *
     * @return string
     */
    public function __get($offset)
    {
        return $this->getDocument()->getFieldValue($offset);
    }

    /**
     * Return the index object for this hit
     *
     * @return SearchIndexInterface
     */
    public function getIndex(): SearchIndexInterface
    {
        return $this->_index;
    }
}
