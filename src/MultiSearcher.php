<?php


namespace Sajya\Lucene;

use Sajya\Lucene\Exception\InvalidArgumentException;
use Sajya\Lucene\Exception\OutOfRangeException;
use Sajya\Lucene\Exception\RuntimeException;
use Sajya\Lucene\Exception\UnsupportedMethodCallException;
use Sajya\Lucene\Index\DocsFilter;
use Sajya\Lucene\Index\Term;
use Sajya\Lucene\Search\QueryHit;
use Sajya\Lucene\Search\Similarity\AbstractSimilarity;
use Sajya\Lucene\Storage\Directory\DirectoryInterface;

/**
 * Multisearcher allows to search through several independent indexes.
 *
 * @category   Zend
 * @package    Zend_Search_Lucene
 */
class MultiSearcher implements SearchIndexInterface
{
    /**
     * List of indices for searching.
     * Array of Zend_Search_Lucene_Interface objects
     *
     * @var array
     */
    protected $_indices;
    /**
     * Callback used to choose target index for new documents
     *
     * Function/method signature:
     *    Zend_Search_Lucene_Interface  callbackFunction(Zend_Search_Lucene_Document $document, array $indices);
     *
     * null means "default documents distributing algorithm"
     *
     * @var callback
     */
    protected $_documentDistributorCallBack = null;
    /**
     * Terms stream priority queue object
     *
     * @var TermStreamsPriorityQueue
     */
    private $_termsStream = null;

    /**
     * Object constructor.
     *
     * @param array $indices Arrays of indices for search
     *
     * @throws InvalidArgumentException
     */
    public function __construct($indices = [])
    {
        $this->_indices = $indices;

        foreach ($this->_indices as $index) {
            if (!$index instanceof SearchIndexInterface) {
                throw new InvalidArgumentException('sub-index objects have to implement Sajya\Lucene\Interface.');
            }
        }
    }

    /**
     * Get current generation number
     *
     * Returns generation number
     * 0 means pre-2.1 index format
     * -1 means there are no segments files.
     *
     * @param Storage\Directory\DirectoryInterface $directory
     *
     * @return integer
     * @throws UnsupportedMethodCallException
     */
    public static function getActualGeneration(Storage\Directory\DirectoryInterface $directory)
    {
        throw new UnsupportedMethodCallException("Generation number can't be retrieved for multi-searcher");
    }

    /**
     * Get segments file name
     *
     * @param integer $generation
     *
     * @return string
     */
    public static function getSegmentFileName($generation)
    {
        return Index::getSegmentFileName($generation);
    }

    /**
     * Add index for searching.
     *
     * @param SearchIndexInterface $index
     */
    public function addIndex(SearchIndexInterface $index): void
    {
        $this->_indices[] = $index;
    }

    /**
     * Get index format version
     *
     * @return integer
     * @throws UnsupportedMethodCallException
     */
    public function getFormatVersion()
    {
        throw new UnsupportedMethodCallException("Format version can't be retrieved for multi-searcher");
    }

    /**
     * Set index format version.
     * Index is converted to this format at the nearest upfdate time
     *
     * @param int $formatVersion
     */
    public function setFormatVersion($formatVersion)
    {
        foreach ($this->_indices as $index) {
            $index->setFormatVersion($formatVersion);
        }
    }

    /**
     * Returns the Zend_Search_Lucene_Storagedirectory instance for this index.
     *
     * @return DirectoryInterface
     * @throws UnsupportedMethodCallException
     */
    public function getDirectory()
    {
        throw new UnsupportedMethodCallException("Index directory can't be retrieved for multi-searcher");
    }

    /**
     * Returns one greater than the largest possible document number.
     * This may be used to, e.g., determine how big to allocate a structure which will have
     * an element for every document number in an index.
     *
     * @return integer
     */
    public function maxDoc()
    {
        return $this->count();
    }

    /**
     * Returns the total number of documents in this index (including deleted documents).
     *
     * @return integer
     */
    public function count()
    {
        $count = 0;

        foreach ($this->_indices as $index) {
            $count += $this->_indices->count();
        }

        return $count;
    }

    /**
     * Returns the total number of non-deleted documents in this index.
     *
     * @return integer
     */
    public function numDocs()
    {
        $docs = 0;

        foreach ($this->_indices as $index) {
            $docs += $index->numDocs();
        }

        return $docs;
    }

    /**
     * Checks, that document is deleted
     *
     * @param integer $id
     *
     * @return boolean
     * @throws OutOfRangeException    is thrown if $id is out of the range
     */
    public function isDeleted($id)
    {
        foreach ($this->_indices as $index) {
            $indexCount = $index->count();

            if ($indexCount > $id) {
                return $index->isDeleted($id);
            }

            $id -= $indexCount;
        }

        throw new OutOfRangeException('Document id is out of the range.');
    }

    /**
     * Retrieve index maxBufferedDocs option
     *
     * maxBufferedDocs is a minimal number of documents required before
     * the buffered in-memory documents are written into a new Segment
     *
     * Default value is 10
     *
     * @return integer
     * @throws RuntimeException
     */
    public function getMaxBufferedDocs()
    {
        if (count($this->_indices) == 0) {
            throw new RuntimeException('Indices list is empty');
        }

        $maxBufferedDocs = reset($this->_indices)->getMaxBufferedDocs();

        foreach ($this->_indices as $index) {
            if ($index->getMaxBufferedDocs() !== $maxBufferedDocs) {
                throw new RuntimeException('Indices have different default search field.');
            }
        }

        return $maxBufferedDocs;
    }

    /**
     * Set index maxBufferedDocs option
     *
     * maxBufferedDocs is a minimal number of documents required before
     * the buffered in-memory documents are written into a new Segment
     *
     * Default value is 10
     *
     * @param integer $maxBufferedDocs
     */
    public function setMaxBufferedDocs($maxBufferedDocs)
    {
        foreach ($this->_indices as $index) {
            $index->setMaxBufferedDocs($maxBufferedDocs);
        }
    }

    /**
     * Retrieve index maxMergeDocs option
     *
     * maxMergeDocs is a largest number of documents ever merged by addDocument().
     * Small values (e.g., less than 10,000) are best for interactive indexing,
     * as this limits the length of pauses while indexing to a few seconds.
     * Larger values are best for batched indexing and speedier searches.
     *
     * Default value is PHP_INT_MAX
     *
     * @return integer
     * @throws RuntimeException
     */
    public function getMaxMergeDocs()
    {
        if (count($this->_indices) == 0) {
            throw new RuntimeException('Indices list is empty');
        }

        $maxMergeDocs = reset($this->_indices)->getMaxMergeDocs();

        foreach ($this->_indices as $index) {
            if ($index->getMaxMergeDocs() !== $maxMergeDocs) {
                throw new RuntimeException('Indices have different default search field.');
            }
        }

        return $maxMergeDocs;
    }

    /**
     * Set index maxMergeDocs option
     *
     * maxMergeDocs is a largest number of documents ever merged by addDocument().
     * Small values (e.g., less than 10,000) are best for interactive indexing,
     * as this limits the length of pauses while indexing to a few seconds.
     * Larger values are best for batched indexing and speedier searches.
     *
     * Default value is PHP_INT_MAX
     *
     * @param integer $maxMergeDocs
     */
    public function setMaxMergeDocs($maxMergeDocs)
    {
        foreach ($this->_indices as $index) {
            $index->setMaxMergeDocs($maxMergeDocs);
        }
    }

    /**
     * Retrieve index mergeFactor option
     *
     * mergeFactor determines how often segment indices are merged by addDocument().
     * With smaller values, less RAM is used while indexing,
     * and searches on unoptimized indices are faster,
     * but indexing speed is slower.
     * With larger values, more RAM is used during indexing,
     * and while searches on unoptimized indices are slower,
     * indexing is faster.
     * Thus larger values (> 10) are best for batch index creation,
     * and smaller values (< 10) for indices that are interactively maintained.
     *
     * Default value is 10
     *
     * @return integer
     * @throws RuntimeException
     */
    public function getMergeFactor()
    {
        if (count($this->_indices) == 0) {
            throw new RuntimeException('Indices list is empty');
        }

        $mergeFactor = reset($this->_indices)->getMergeFactor();

        foreach ($this->_indices as $index) {
            if ($index->getMergeFactor() !== $mergeFactor) {
                throw new RuntimeException('Indices have different default search field.');
            }
        }

        return $mergeFactor;
    }

    /**
     * Set index mergeFactor option
     *
     * mergeFactor determines how often segment indices are merged by addDocument().
     * With smaller values, less RAM is used while indexing,
     * and searches on unoptimized indices are faster,
     * but indexing speed is slower.
     * With larger values, more RAM is used during indexing,
     * and while searches on unoptimized indices are slower,
     * indexing is faster.
     * Thus larger values (> 10) are best for batch index creation,
     * and smaller values (< 10) for indices that are interactively maintained.
     *
     * Default value is 10
     *
     * @param integer $maxMergeDocs
     */
    public function setMergeFactor($mergeFactor)
    {
        foreach ($this->_indices as $index) {
            $index->setMaxMergeDocs($mergeFactor);
        }
    }

    /**
     * Performs a query against the index and returns an array
     * of Zend_Search_Lucene_Search_QueryHit objects.
     * Input is a string or Zend_Search_Lucene_Search_Query.
     *
     * @param mixed $query
     *
     * @return array|QueryHit
     */
    public function find($query)
    {
        if (count($this->_indices) == 0) {
            return [];
        }

        $hitsList = [];

        $indexShift = 0;
        foreach ($this->_indices as $index) {
            $hits = $index->find($query);

            if ($indexShift != 0) {
                foreach ($hits as $hit) {
                    $hit->id += $indexShift;
                }
            }

            $indexShift += $index->count();
            $hitsList[] = $hits;
        }

        /** @todo Implement advanced sorting */

        return array_merge(...$hitsList);
    }

    /**
     * Returns a list of all unique field names that exist in this index.
     *
     * @param boolean $indexed
     *
     * @return array
     */
    public function getFieldNames($indexed = false)
    {
        $fieldNamesList = [];

        foreach ($this->_indices as $index) {
            $fieldNamesList[] = $index->getFieldNames($indexed);
        }

        return array_unique(array_merge(...$fieldNamesList));
    }

    /**
     * Returns a Zend_Search_Lucene_Document object for the document
     * number $id in this index.
     *
     * @param integer|QueryHit $id
     *
     * @return Document
     * @throws OutOfRangeException    is thrown if $id is out of the range
     */
    public function getDocument($id)
    {
        if ($id instanceof Search\QueryHit) {
            /* @var $id QueryHit */
            $id = $id->id;
        }

        foreach ($this->_indices as $index) {
            $indexCount = $index->count();

            if ($indexCount > $id) {
                return $index->getDocument($id);
            }

            $id -= $indexCount;
        }

        throw new OutOfRangeException('Document id is out of the range.');
    }

    /**
     * Returns true if index contain documents with specified term.
     *
     * Is used for query optimization.
     *
     * @param Term $term
     *
     * @return boolean
     */
    public function hasTerm(Index\Term $term)
    {
        foreach ($this->_indices as $index) {
            if ($index->hasTerm($term)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns IDs of all the documents containing term.
     *
     * @param Term            $term
     * @param DocsFilter|null $docsFilter
     *
     * @return array
     * @throws InvalidArgumentException
     */
    public function termDocs(Index\Term $term, $docsFilter = null)
    {
        if ($docsFilter != null) {
            throw new InvalidArgumentException('Document filters could not used with multi-searcher');
        }

        $docsList = [];

        $indexShift = 0;
        foreach ($this->_indices as $index) {
            $docs = $index->termDocs($term);

            if ($indexShift != 0) {
                foreach ($docs as $id => $docId) {
                    $docs[$id] += $indexShift;
                }
            }

            $indexShift += $index->count();
            $docsList[] = $docs;
        }

        return array_merge(...$docsList);
    }

    /**
     * Returns documents filter for all documents containing term.
     *
     * It performs the same operation as termDocs, but return result as
     * Zend_Search_Lucene_Index_DocsFilter object
     *
     * @param Term            $term
     * @param DocsFilter|null $docsFilter
     *
     * @return DocsFilter
     * @throws UnsupportedMethodCallException
     */
    public function termDocsFilter(Index\Term $term, $docsFilter = null)
    {
        throw new UnsupportedMethodCallException('Document filters could not used with multi-searcher');
    }

    /**
     * Returns an array of all term freqs.
     * Return array structure: array( docId => freq, ...)
     *
     * @param Term            $term
     * @param DocsFilter|null $docsFilter
     *
     * @return integer
     * @throws InvalidArgumentException
     */
    public function termFreqs(Index\Term $term, $docsFilter = null)
    {
        if ($docsFilter != null) {
            throw new InvalidArgumentException('Document filters could not used with multi-searcher');
        }

        $freqsList = [];

        $indexShift = 0;
        foreach ($this->_indices as $index) {
            $freqs = $index->termFreqs($term);

            if ($indexShift != 0) {
                $freqsShifted = [];

                foreach ($freqs as $docId => $freq) {
                    $freqsShifted[$docId + $indexShift] = $freq;
                }
                $freqs = $freqsShifted;
            }

            $indexShift += $index->count();
            $freqsList[] = $freqs;
        }

        return array_merge(...$freqsList);
    }

    /**
     * Returns an array of all term positions in the documents.
     * Return array structure: array( docId => array( pos1, pos2, ...), ...)
     *
     * @param Term            $term
     * @param DocsFilter|null $docsFilter
     *
     * @return array
     * @throws InvalidArgumentException
     */
    public function termPositions(Index\Term $term, $docsFilter = null)
    {
        if ($docsFilter != null) {
            throw new InvalidArgumentException('Document filters could not used with multi-searcher');
        }

        $termPositionsList = [];

        $indexShift = 0;
        foreach ($this->_indices as $index) {
            $termPositions = $index->termPositions($term);

            if ($indexShift != 0) {
                $termPositionsShifted = [];

                foreach ($termPositions as $docId => $positions) {
                    $termPositions[$docId + $indexShift] = $positions;
                }
                $termPositions = $termPositionsShifted;
            }

            $indexShift += $index->count();
            $termPositionsList[] = $termPositions;
        }

        return array_merge(...$termPositions);
    }

    /**
     * Returns the number of documents in this index containing the $term.
     *
     * @param Term $term
     *
     * @return integer
     */
    public function docFreq(Index\Term $term)
    {
        $docFreq = 0;

        foreach ($this->_indices as $index) {
            $docFreq += $index->docFreq($term);
        }

        return $docFreq;
    }

    /**
     * Retrive similarity used by index reader
     *
     * @return AbstractSimilarity
     * @throws RuntimeException
     */
    public function getSimilarity()
    {
        if (count($this->_indices) == 0) {
            throw new RuntimeException('Indices list is empty');
        }

        $similarity = reset($this->_indices)->getSimilarity();

        foreach ($this->_indices as $index) {
            if ($index->getSimilarity() !== $similarity) {
                throw new RuntimeException('Indices have different similarity.');
            }
        }

        return $similarity;
    }

    /**
     * Returns a normalization factor for "field, document" pair.
     *
     * @param integer $id
     * @param string  $fieldName
     *
     * @return float
     */
    public function norm($id, $fieldName)
    {
        foreach ($this->_indices as $index) {
            $indexCount = $index->count();

            if ($indexCount > $id) {
                return $index->norm($id, $fieldName);
            }

            $id -= $indexCount;
        }

        return null;
    }

    /**
     * Returns true if any documents have been deleted from this index.
     *
     * @return boolean
     */
    public function hasDeletions()
    {
        foreach ($this->_indices as $index) {
            if ($index->hasDeletions()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Deletes a document from the index.
     * $id is an internal document id
     *
     * @param integer|QueryHit $id
     *
     * @throws OutOfRangeException
     */
    public function delete($id)
    {
        foreach ($this->_indices as $index) {
            $indexCount = $index->count();

            if ($indexCount > $id) {
                $index->delete($id);
                return;
            }

            $id -= $indexCount;
        }

        throw new OutOfRangeException('Document id is out of the range.');
    }

    /**
     * Get callback for choosing target index.
     *
     * @return callback
     */
    public function getDocumentDistributorCallback(): callable
    {
        return $this->_documentDistributorCallBack;
    }

    /**
     * Set callback for choosing target index.
     *
     * @param callback $callback
     *
     * @throws InvalidArgumentException
     */
    public function setDocumentDistributorCallback($callback): void
    {
        if ($callback !== null && !is_callable($callback)) {
            throw new InvalidArgumentException('$callback parameter must be a valid callback.');
        }

        $this->_documentDistributorCallBack = $callback;
    }

    /**
     * Adds a document to this index.
     *
     * @param Document $document
     */
    public function addDocument(Document $document)
    {
        if ($this->_documentDistributorCallBack !== null) {
            $index = call_user_func($this->_documentDistributorCallBack, $document, $this->_indices);
        } else {
            $index = $this->_indices[array_rand($this->_indices)];
        }

        $index->addDocument($document);
    }

    /**
     * Commit changes resulting from delete() or undeleteAll() operations.
     */
    public function commit()
    {
        foreach ($this->_indices as $index) {
            $index->commit();
        }
    }

    /**
     * Optimize index.
     *
     * Merges all segments into one
     */
    public function optimize()
    {
        foreach ($this->_indices as $index) {
            $index->optimise();
        }
    }

    /**
     * Returns an array of all terms in this index.
     *
     * @return array
     */
    public function terms()
    {
        $termsList = [];

        foreach ($this->_indices as $index) {
            $termsList[] = $index->terms();
        }

        return array_unique(array_merge(...$termsList));
    }

    /**
     * Reset terms stream.
     */
    public function resetTermsStream()
    {
        if ($this->_termsStream === null) {
            $this->_termsStream = new TermStreamsPriorityQueue($this->_indices);
        } else {
            $this->_termsStream->resetTermsStream();
        }
    }

    /**
     * Skip terms stream up to specified term preffix.
     *
     * Prefix contains fully specified field info and portion of searched term
     *
     * @param Term $prefix
     */
    public function skipTo(Index\Term $prefix)
    {
        $this->_termsStream->skipTo($prefix);
    }

    /**
     * Scans terms dictionary and returns next term
     *
     * @return Term|null
     */
    public function nextTerm()
    {
        return $this->_termsStream->nextTerm();
    }

    /**
     * Returns term in current position
     *
     * @return Term|null
     */
    public function currentTerm()
    {
        return $this->_termsStream->currentTerm();
    }

    /**
     * Close terms stream
     *
     * Should be used for resources clean up if stream is not read up to the end
     */
    public function closeTermsStream()
    {
        $this->_termsStream->closeTermsStream();
        $this->_termsStream = null;
    }


    /**
     * Undeletes all documents currently marked as deleted in this index.
     */
    public function undeleteAll()
    {
        foreach ($this->_indices as $index) {
            $index->undeleteAll();
        }
    }
}
