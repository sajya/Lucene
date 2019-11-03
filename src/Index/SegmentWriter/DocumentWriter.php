<?php


namespace Sajya\Lucene\Index\SegmentWriter;

use Sajya\Lucene\Analysis\Analyzer;
use Sajya\Lucene\Document;
use Sajya\Lucene\Exception as LuceneException;
use Sajya\Lucene\Index;
use Sajya\Lucene\Index\SegmentInfo;
use Sajya\Lucene\Search\Similarity\AbstractSimilarity;
use Sajya\Lucene\Storage\Directory;

/**
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage Index
 */
class DocumentWriter extends AbstractSegmentWriter
{
    /**
     * Term Dictionary
     * Array of the Zend_Search_Lucene_Index_Term objects
     * Corresponding Zend_Search_Lucene_Index_TermInfo object stored in the $_termDictionaryInfos
     *
     * @var array
     */
    protected $_termDictionary;

    /**
     * Documents, which contain the term
     *
     * @var array
     */
    protected $_termDocs;

    /**
     * Object constructor.
     *
     * @param Directory\DirectoryInterface $directory
     * @param string                       $name
     */
    public function __construct(Directory\DirectoryInterface $directory, $name)
    {
        parent::__construct($directory, $name);

        $this->_termDocs = [];
        $this->_termDictionary = [];
    }


    /**
     * Adds a document to this segment.
     *
     * @param Document $document
     *
     * @throws LuceneException\UnsupportedMethodCallException
     */
    public function addDocument(Document $document): void
    {
        $storedFields = [];
        $docNorms = [];
        $similarity = AbstractSimilarity::getDefault();

        foreach ($document->getFieldNames() as $fieldName) {
            $field = $document->getField($fieldName);

            if ($field->storeTermVector) {
                /**
                 * @todo term vector storing support
                 */
                throw new LuceneException\UnsupportedMethodCallException('Store term vector functionality is not supported yet.');
            }

            if ($field->isIndexed) {
                if ($field->isTokenized) {
                    $analyzer = Analyzer\Analyzer::getDefault();
                    $analyzer->setInput($field->value, $field->encoding);

                    $position = 0;
                    $tokenCounter = 0;
                    while (($token = $analyzer->nextToken()) !== null) {
                        $tokenCounter++;

                        $term = new Index\Term($token->getTermText(), $field->name);
                        $termKey = $term->key();

                        if (!isset($this->_termDictionary[$termKey])) {
                            // New term
                            $this->_termDictionary[$termKey] = $term;
                            $this->_termDocs[$termKey] = [];
                            $this->_termDocs[$termKey][$this->docCount] = [];
                        } else if (!isset($this->_termDocs[$termKey][$this->docCount])) {
                            // Existing term, but new term entry
                            $this->_termDocs[$termKey][$this->docCount] = [];
                        }
                        $position += $token->getPositionIncrement();
                        $this->_termDocs[$termKey][$this->docCount][] = $position;
                    }

                    if ($tokenCounter == 0) {
                        // Field contains empty value. Treat it as non-indexed and non-tokenized
                        $field = clone($field);
                        $field->isIndexed = $field->isTokenized = false;
                    } else {
                        $docNorms[$field->name] = chr($similarity->encodeNorm($similarity->lengthNorm($field->name,
                                $tokenCounter) *
                            $document->boost *
                            $field->boost));
                    }
                } else if (($fieldUtf8Value = $field->getUtf8Value()) == '') {
                    // Field contains empty value. Treat it as non-indexed and non-tokenized
                    $field = clone($field);
                    $field->isIndexed = $field->isTokenized = false;
                } else {
                    $term = new Index\Term($fieldUtf8Value, $field->name);
                    $termKey = $term->key();

                    if (!isset($this->_termDictionary[$termKey])) {
                        // New term
                        $this->_termDictionary[$termKey] = $term;
                        $this->_termDocs[$termKey] = [];
                        $this->_termDocs[$termKey][$this->docCount] = [];
                    } else if (!isset($this->_termDocs[$termKey][$this->docCount])) {
                        // Existing term, but new term entry
                        $this->_termDocs[$termKey][$this->docCount] = [];
                    }
                    $this->_termDocs[$termKey][$this->docCount][] = 0; // position

                    $docNorms[$field->name] = chr($similarity->encodeNorm($similarity->lengthNorm($field->name, 1) *
                        $document->boost *
                        $field->boost));
                }
            }

            if ($field->isStored) {
                $storedFields[] = $field;
            }

            $this->addField($field);
        }

        foreach ($this->fields as $fieldName => $field) {
            if (!$field->isIndexed) {
                continue;
            }

            if (!isset($this->norms[$fieldName])) {
                $this->norms[$fieldName] = str_repeat(chr($similarity->encodeNorm($similarity->lengthNorm($fieldName, 0))),
                    $this->docCount);
            }

            if (isset($docNorms[$fieldName])) {
                $this->norms[$fieldName] .= $docNorms[$fieldName];
            } else {
                $this->norms[$fieldName] .= chr($similarity->encodeNorm($similarity->lengthNorm($fieldName, 0)));
            }
        }

        $this->addStoredFields($storedFields);
    }

    /**
     * Close segment, write it to disk and return segment info
     *
     * @return SegmentInfo
     */
    public function close()
    {
        if ($this->docCount == 0) {
            return null;
        }

        $this->_dumpFNM();
        $this->_dumpDictionary();

        $this->_generateCFS();

        return new SegmentInfo($this->directory,
            $this->name,
            $this->docCount,
            -1,
            null,
            true,
            true);
    }

    /**
     * Dump Term Dictionary (.tis) and Term Dictionary Index (.tii) segment files
     */
    protected function _dumpDictionary(): void
    {
        ksort($this->_termDictionary, SORT_STRING);

        $this->initializeDictionaryFiles();

        foreach ($this->_termDictionary as $termId => $term) {
            $this->addTerm($term, $this->_termDocs[$termId]);
        }

        $this->closeDictionaryFiles();
    }

}

