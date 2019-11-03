<?php


namespace Sajya\Lucene\Index;

use Sajya\Lucene\Document;
use Sajya\Lucene\Exception\RuntimeException;
use Sajya\Lucene\Index\SegmentWriter\StreamWriter;
use Sajya\Lucene\Storage\Directory\DirectoryInterface;

/**
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage Index
 */
class SegmentMerger
{
    /**
     * Target segment writer
     *
     * @var StreamWriter
     */
    private $_writer;

    /**
     * Number of docs in a new segment
     *
     * @var integer
     */
    private $docCount;

    /**
     * A set of segments to be merged
     *
     * @var array|SegmentInfo
     */
    private $segmentInfos = [];

    /**
     * Flag to signal, that merge is already done
     *
     * @var boolean
     */
    private $_mergeDone = false;

    /**
     * Field map
     * [<segmentname>][<field_number>] => <target_field_number>
     *
     * @var array
     */
    private $fieldsMap = [];


    /**
     * Object constructor.
     *
     * Creates new segment merger with $directory as target to merge segments into
     * and $name as a name of new segment
     *
     * @param DirectoryInterface $directory
     * @param string             $name
     */
    public function __construct(DirectoryInterface $directory, $name)
    {
        /** \Sajya\Lucene\Index\SegmentWriter\StreamWriter */
        $this->_writer = new SegmentWriter\StreamWriter($directory, $name);
    }


    /**
     * Add segmnet to a collection of segments to be merged
     *
     * @param SegmentInfo $segment
     */
    public function addSource(SegmentInfo $segmentInfo): void
    {
        $this->segmentInfos[$segmentInfo->getName()] = $segmentInfo;
    }


    /**
     * Do merge.
     *
     * Returns number of documents in newly created segment
     *
     * @return SegmentInfo
     * @throws RuntimeException
     */
    public function merge(): SegmentInfo
    {
        if ($this->_mergeDone) {
            throw new RuntimeException('Merge is already done.');
        }

        if (count($this->segmentInfos) < 1) {
            throw new RuntimeException('Wrong number of segments to be merged ('
                . count($this->segmentInfos)
                . ').');
        }

        $this->_mergeFields();
        $this->_mergeNorms();
        $this->_mergeStoredFields();
        $this->_mergeTerms();

        $this->_mergeDone = true;

        return $this->_writer->close();
    }


    /**
     * Merge fields information
     */
    private function _mergeFields(): void
    {
        foreach ($this->segmentInfos as $segName => $segmentInfo) {
            foreach ($segmentInfo->getFieldInfos() as $fieldInfo) {
                $this->fieldsMap[$segName][$fieldInfo->number] = $this->_writer->addFieldInfo($fieldInfo);
            }
        }
    }

    /**
     * Merge field's normalization factors
     */
    private function _mergeNorms(): void
    {
        foreach ($this->_writer->getFieldInfos() as $fieldInfo) {
            if ($fieldInfo->isIndexed) {
                foreach ($this->segmentInfos as $segName => $segmentInfo) {
                    if ($segmentInfo->hasDeletions()) {
                        $srcNorm = $segmentInfo->normVector($fieldInfo->name);
                        $norm = '';
                        $docs = $segmentInfo->count();
                        for ($count = 0; $count < $docs; $count++) {
                            if (!$segmentInfo->isDeleted($count)) {
                                $norm .= $srcNorm[$count];
                            }
                        }
                        $this->_writer->addNorm($fieldInfo->name, $norm);
                    } else {
                        $this->_writer->addNorm($fieldInfo->name, $segmentInfo->normVector($fieldInfo->name));
                    }
                }
            }
        }
    }

    /**
     * Merge fields information
     */
    private function _mergeStoredFields(): void
    {
        $this->docCount = 0;

        foreach ($this->segmentInfos as $segName => $segmentInfo) {
            $fdtFile = $segmentInfo->openCompoundFile('.fdt');

            for ($count = 0; $count < $segmentInfo->count(); $count++) {
                $fieldCount = $fdtFile->readVInt();
                $storedFields = [];

                for ($count2 = 0; $count2 < $fieldCount; $count2++) {
                    $fieldNum = $fdtFile->readVInt();
                    $bits = $fdtFile->readByte();
                    $fieldInfo = $segmentInfo->getField($fieldNum);

                    if (!($bits & 2)) { // Text data
                        $storedFields[] =
                            new Document\Field($fieldInfo->name,
                                $fdtFile->readString(),
                                'UTF-8',
                                true,
                                $fieldInfo->isIndexed,
                                $bits & 1);
                    } else {            // Binary data
                        $storedFields[] =
                            new Document\Field($fieldInfo->name,
                                $fdtFile->readBinary(),
                                '',
                                true,
                                $fieldInfo->isIndexed,
                                $bits & 1,
                                true);
                    }
                }

                if (!$segmentInfo->isDeleted($count)) {
                    $this->docCount++;
                    $this->_writer->addStoredFields($storedFields);
                }
            }
        }
    }


    /**
     * Merge fields information
     */
    private function _mergeTerms(): void
    {
        $segmentInfoQueue = new TermsPriorityQueue();

        $segmentStartId = 0;
        foreach ($this->segmentInfos as $segName => $segmentInfo) {
            $segmentStartId = $segmentInfo->resetTermsStream($segmentStartId, SegmentInfo::SM_MERGE_INFO);

            // Skip "empty" segments
            if ($segmentInfo->currentTerm() !== null) {
                $segmentInfoQueue->put($segmentInfo);
            }
        }

        $this->_writer->initializeDictionaryFiles();

        $termDocs = [];
        while (($segmentInfo = $segmentInfoQueue->pop()) !== null) {
            // Merge positions array
            $termDocs += $segmentInfo->currentTermPositions();

            if ($segmentInfoQueue->top() === null ||
                $segmentInfoQueue->top()->currentTerm()->key() !=
                $segmentInfo->currentTerm()->key()) {
                // We got new term
                ksort($termDocs, SORT_NUMERIC);

                // Add term if it's contained in any document
                if (count($termDocs) > 0) {
                    $this->_writer->addTerm($segmentInfo->currentTerm(), $termDocs);
                }
                $termDocs = [];
            }

            $segmentInfo->nextTerm();
            // check, if segment dictionary is finished
            if ($segmentInfo->currentTerm() !== null) {
                // Put segment back into the priority queue
                $segmentInfoQueue->put($segmentInfo);
            }
        }

        $this->_writer->closeDictionaryFiles();
    }
}
