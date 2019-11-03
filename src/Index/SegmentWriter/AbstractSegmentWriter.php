<?php


namespace Sajya\Lucene\Index\SegmentWriter;

use Sajya\Lucene\Document\Field;
use Sajya\Lucene\Index\FieldInfo;
use Sajya\Lucene\Index\SegmentInfo;
use Sajya\Lucene\Index\Term;
use Sajya\Lucene\Index\TermInfo;
use Sajya\Lucene\Storage\Directory\DirectoryInterface;
use Sajya\Lucene\Storage\File\FileInterface;

/**
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage Index
 */
abstract class AbstractSegmentWriter
{
    /**
     * Expert: The fraction of terms in the "dictionary" which should be stored
     * in RAM.  Smaller values use more memory, but make searching slightly
     * faster, while larger values use less memory and make searching slightly
     * slower.  Searching is typically not dominated by dictionary lookup, so
     * tweaking this is rarely useful.
     *
     * @var integer
     */
    public static $indexInterval = 128;

    /**
     * Expert: The fraction of TermDocs entries stored in skip tables.
     * Larger values result in smaller indexes, greater acceleration, but fewer
     * accelerable cases, while smaller values result in bigger indexes,
     * less acceleration and more
     * accelerable cases. More detailed experiments would be useful here.
     *
     * 0x7FFFFFFF indicates that we don't use skip data
     *
     * Note: not used in current implementation
     *
     * @var integer
     */
    public static $skipInterval = 0x7FFFFFFF;

    /**
     * Expert: The maximum number of skip levels. Smaller values result in
     * slightly smaller indexes, but slower skipping in big posting lists.
     *
     * 0 indicates that we don't use skip data
     *
     * Note: not used in current implementation
     *
     * @var integer
     */
    public static $maxSkipLevels = 0;

    /**
     * Number of docs in a segment
     *
     * @var integer
     */
    protected $docCount = 0;

    /**
     * Segment name
     *
     * @var string
     */
    protected $name;

    /**
     * File system adapter.
     *
     * @var DirectoryInterface
     */
    protected $directory;

    /**
     * List of the index files.
     * Used for automatic compound file generation
     *
     * @var array
     */
    protected $files = [];

    /**
     * Segment fields. Array of Zend_Search_Lucene_Index_FieldInfo objects for this segment
     *
     * @var array
     */
    protected $fields = [];

    /**
     * Normalization factors.
     * An array fieldName => normVector
     * normVector is a binary string.
     * Each byte corresponds to an indexed document in a segment and
     * encodes normalization factor (float value, encoded by
     * \Sajya\Lucene\Search\Similarity\AbstractSimilarity::encodeNorm())
     *
     * @var array
     */
    protected $norms = [];


    /**
     * '.fdx'  file - Stored Fields, the field index.
     *
     * @var FileInterface
     */
    protected $fdxFile = null;

    /**
     * '.fdt'  file - Stored Fields, the field data.
     *
     * @var FileInterface
     */
    protected $fdtFile = null;
    /**
     * Term Dictionary file
     *
     * @var FileInterface
     */
    private $tisFile = null;
    /**
     * Term Dictionary index file
     *
     * @var FileInterface
     */
    private $tiiFile = null;
    /**
     * Frequencies file
     *
     * @var FileInterface
     */
    private $frqFile = null;
    /**
     * Positions file
     *
     * @var FileInterface
     */
    private $prxFile = null;
    /**
     * Number of written terms
     *
     * @var integer
     */
    private $termCount;
    /**
     * Last saved term
     *
     * @var Term
     */
    private $prevTerm;
    /**
     * Last saved term info
     *
     * @var TermInfo
     */
    private $prevTermInfo;
    /**
     * Last saved index term
     *
     * @var Term
     */
    private $prevIndexTerm;
    /**
     * Last saved index term info
     *
     * @var TermInfo
     */
    private $prevIndexTermInfo;
    /**
     * Last term dictionary file position
     *
     * @var integer
     */
    private $lastIndexPosition;

    /**
     * Object constructor.
     *
     * @param DirectoryInterface $directory
     * @param string             $name
     */
    public function __construct(DirectoryInterface $directory, $name)
    {
        $this->directory = $directory;
        $this->name = $name;
    }

    /**
     * Add field to the segment
     *
     * Returns actual field number
     *
     * @param Field $field
     *
     * @return integer
     */
    public function addField(Field $field): int
    {
        if (!isset($this->fields[$field->name])) {
            $fieldNumber = count($this->fields);
            $this->fields[$field->name] = new FieldInfo($field->name,
                $field->isIndexed,
                $fieldNumber,
                $field->storeTermVector);

            return $fieldNumber;
        }

        $this->fields[$field->name]->isIndexed |= $field->isIndexed;
        $this->fields[$field->name]->storeTermVector |= $field->storeTermVector;

        return $this->fields[$field->name]->number;
    }

    /**
     * Add fieldInfo to the segment
     *
     * Returns actual field number
     *
     * @param FieldInfo $fieldInfo
     *
     * @return integer
     */
    public function addFieldInfo(FieldInfo $fieldInfo): int
    {
        if (!isset($this->fields[$fieldInfo->name])) {
            $fieldNumber = count($this->fields);
            $this->fields[$fieldInfo->name] = new FieldInfo($fieldInfo->name,
                $fieldInfo->isIndexed,
                $fieldNumber,
                $fieldInfo->storeTermVector);

            return $fieldNumber;
        }

        $this->fields[$fieldInfo->name]->isIndexed |= $fieldInfo->isIndexed;
        $this->fields[$fieldInfo->name]->storeTermVector |= $fieldInfo->storeTermVector;

        return $this->fields[$fieldInfo->name]->number;
    }

    /**
     * Returns array of FieldInfo objects.
     *
     * @return array
     */
    public function getFieldInfos(): array
    {
        return $this->fields;
    }

    /**
     * Add stored fields information
     *
     * @param array $storedFields array of \Sajya\Lucene\Document\Field objects
     */
    public function addStoredFields($storedFields): void
    {
        if (!isset($this->fdxFile)) {
            $this->fdxFile = $this->directory->createFile($this->name . '.fdx');
            $this->fdtFile = $this->directory->createFile($this->name . '.fdt');

            $this->files[] = $this->name . '.fdx';
            $this->files[] = $this->name . '.fdt';
        }

        $this->fdxFile->writeLong($this->fdtFile->tell());
        $this->fdtFile->writeVInt(count($storedFields));
        foreach ($storedFields as $field) {
            $this->fdtFile->writeVInt($this->fields[$field->name]->number);
            $fieldBits = ($field->isTokenized ? 0x01 : 0x00) |
                ($field->isBinary ? 0x02 : 0x00) |
                0x00; /* 0x04 - third bit, compressed (ZLIB) */
            $this->fdtFile->writeByte($fieldBits);
            if ($field->isBinary) {
                $this->fdtFile->writeVInt(strlen($field->value));
                $this->fdtFile->writeBytes($field->value);
            } else {
                $this->fdtFile->writeString($field->getUtf8Value());
            }
        }

        $this->docCount++;
    }

    /**
     * Returns the total number of documents in this segment.
     *
     * @return integer
     */
    public function count(): int
    {
        return $this->docCount;
    }

    /**
     * Return segment name
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Create dicrionary, frequency and positions files and write necessary headers
     */
    public function initializeDictionaryFiles(): void
    {
        $this->tisFile = $this->directory->createFile($this->name . '.tis');
        $this->tisFile->writeInt((int)0xFFFFFFFD);
        $this->tisFile->writeLong(0 /* dummy data for terms count */);
        $this->tisFile->writeInt(self::$indexInterval);
        $this->tisFile->writeInt(self::$skipInterval);
        $this->tisFile->writeInt(self::$maxSkipLevels);

        $this->tiiFile = $this->directory->createFile($this->name . '.tii');
        $this->tiiFile->writeInt((int)0xFFFFFFFD);
        $this->tiiFile->writeLong(0 /* dummy data for terms count */);
        $this->tiiFile->writeInt(self::$indexInterval);
        $this->tiiFile->writeInt(self::$skipInterval);
        $this->tiiFile->writeInt(self::$maxSkipLevels);

        /** Dump dictionary header */
        $this->tiiFile->writeVInt(0);                    // preffix length
        $this->tiiFile->writeString('');                 // suffix
        $this->tiiFile->writeInt((int)0xFFFFFFFF);       // field number
        $this->tiiFile->writeByte((int)0x0F);
        $this->tiiFile->writeVInt(0);                    // DocFreq
        $this->tiiFile->writeVInt(0);                    // FreqDelta
        $this->tiiFile->writeVInt(0);                    // ProxDelta
        $this->tiiFile->writeVInt(24);                   // IndexDelta

        $this->frqFile = $this->directory->createFile($this->name . '.frq');
        $this->prxFile = $this->directory->createFile($this->name . '.prx');

        $this->files[] = $this->name . '.tis';
        $this->files[] = $this->name . '.tii';
        $this->files[] = $this->name . '.frq';
        $this->files[] = $this->name . '.prx';

        $this->prevTerm = null;
        $this->prevTermInfo = null;
        $this->prevIndexTerm = null;
        $this->prevIndexTermInfo = null;
        $this->lastIndexPosition = 24;
        $this->termCount = 0;

    }

    /**
     * Add term
     *
     * Term positions is an array( docId => array(pos1, pos2, pos3, ...), ... )
     *
     * @param Term  $termEntry
     * @param array $termDocs
     */
    public function addTerm($termEntry, $termDocs): void
    {
        $freqPointer = $this->frqFile->tell();
        $proxPointer = $this->prxFile->tell();

        $prevDoc = 0;
        foreach ($termDocs as $docId => $termPositions) {
            $docDelta = ($docId - $prevDoc) * 2;
            $prevDoc = $docId;
            if (count($termPositions) > 1) {
                $this->frqFile->writeVInt($docDelta);
                $this->frqFile->writeVInt(count($termPositions));
            } else {
                $this->frqFile->writeVInt($docDelta + 1);
            }

            $prevPosition = 0;
            foreach ($termPositions as $position) {
                $this->prxFile->writeVInt($position - $prevPosition);
                $prevPosition = $position;
            }
        }

        if (count($termDocs) >= self::$skipInterval) {
            /**
             * @todo Write Skip Data to a freq file.
             * It's not used now, but make index more optimal
             */
            $skipOffset = $this->frqFile->tell() - $freqPointer;
        } else {
            $skipOffset = 0;
        }

        $term = new Term($termEntry->text, $this->fields[$termEntry->field]->number);
        $termInfo = new TermInfo(count($termDocs), $freqPointer, $proxPointer, $skipOffset);

        $this->_dumpTermDictEntry($this->tisFile, $this->prevTerm, $term, $this->prevTermInfo, $termInfo);

        if (($this->termCount + 1) % self::$indexInterval == 0) {
            $this->_dumpTermDictEntry($this->tiiFile, $this->prevIndexTerm, $term, $this->prevIndexTermInfo, $termInfo);

            $indexPosition = $this->tisFile->tell();
            $this->tiiFile->writeVInt($indexPosition - $this->lastIndexPosition);
            $this->lastIndexPosition = $indexPosition;

        }
        $this->termCount++;
    }

    /**
     * Dump Term Dictionary segment file entry.
     * Used to write entry to .tis or .tii files
     *
     * @param FileInterface $dicFile
     * @param Term          $prevTerm
     * @param Term          $term
     * @param TermInfo      $prevTermInfo
     * @param TermInfo      $termInfo
     */
    protected function _dumpTermDictEntry(FileInterface $dicFile,
                                          &$prevTerm, Term $term,
                                          &$prevTermInfo, TermInfo $termInfo): void
    {
        if (isset($prevTerm) && $prevTerm->field == $term->field) {
            $matchedBytes = 0;
            $maxBytes = min(strlen($prevTerm->text), strlen($term->text));
            while ($matchedBytes < $maxBytes &&
                $prevTerm->text[$matchedBytes] == $term->text[$matchedBytes]) {
                $matchedBytes++;
            }

            // Calculate actual matched UTF-8 pattern
            $prefixBytes = 0;
            $prefixChars = 0;
            while ($prefixBytes < $matchedBytes) {
                $charBytes = 1;
                if ((ord($term->text[$prefixBytes]) & 0xC0) == 0xC0) {
                    $charBytes++;
                    if (ord($term->text[$prefixBytes]) & 0x20) {
                        $charBytes++;
                        if (ord($term->text[$prefixBytes]) & 0x10) {
                            $charBytes++;
                        }
                    }
                }

                if ($prefixBytes + $charBytes > $matchedBytes) {
                    // char crosses matched bytes boundary
                    // skip char
                    break;
                }

                $prefixChars++;
                $prefixBytes += $charBytes;
            }

            // Write preffix length
            $dicFile->writeVInt($prefixChars);
            // Write suffix
            $dicFile->writeString(substr($term->text, $prefixBytes));
        } else {
            // Write preffix length
            $dicFile->writeVInt(0);
            // Write suffix
            $dicFile->writeString($term->text);
        }
        // Write field number
        $dicFile->writeVInt($term->field);
        // DocFreq (the count of documents which contain the term)
        $dicFile->writeVInt($termInfo->docFreq);

        $prevTerm = $term;

        if (!isset($prevTermInfo)) {
            // Write FreqDelta
            $dicFile->writeVInt($termInfo->freqPointer);
            // Write ProxDelta
            $dicFile->writeVInt($termInfo->proxPointer);
        } else {
            // Write FreqDelta
            $dicFile->writeVInt($termInfo->freqPointer - $prevTermInfo->freqPointer);
            // Write ProxDelta
            $dicFile->writeVInt($termInfo->proxPointer - $prevTermInfo->proxPointer);
        }
        // Write SkipOffset - it's not 0 when $termInfo->docFreq > self::$skipInterval
        if ($termInfo->skipOffset != 0) {
            $dicFile->writeVInt($termInfo->skipOffset);
        }

        $prevTermInfo = $termInfo;
    }

    /**
     * Close dictionary
     */
    public function closeDictionaryFiles(): void
    {
        $this->tisFile->seek(4);
        $this->tisFile->writeLong($this->termCount);

        $this->tiiFile->seek(4);
        // + 1 is used to count an additional special index entry (empty term at the start of the list)
        $this->tiiFile->writeLong(($this->termCount - $this->termCount % self::$indexInterval) / self::$indexInterval + 1);
    }

    /**
     * Close segment, write it to disk and return segment info
     *
     * @return SegmentInfo
     */
    abstract public function close();

    /**
     * Dump Field Info (.fnm) segment file
     */
    protected function _dumpFNM(): void
    {
        $fnmFile = $this->directory->createFile($this->name . '.fnm');
        $fnmFile->writeVInt(count($this->fields));

        $nrmFile = $this->directory->createFile($this->name . '.nrm');
        // Write header
        $nrmFile->writeBytes('NRM');
        // Write format specifier
        $nrmFile->writeByte((int)0xFF);

        foreach ($this->fields as $field) {
            $fnmFile->writeString($field->name);
            $fnmFile->writeByte(($field->isIndexed ? 0x01 : 0x00) |
                ($field->storeTermVector ? 0x02 : 0x00)
// not supported yet            0x04 /* term positions are stored with the term vectors */ |
// not supported yet            0x08 /* term offsets are stored with the term vectors */   |
            );

            if ($field->isIndexed) {
                // pre-2.1 index mode (not used now)
                // $normFileName = $this->name . '.f' . $field->number;
                // $fFile = $this->directory->createFile($normFileName);
                // $fFile->writeBytes($this->norms[$field->name]);
                // $this->files[] = $normFileName;

                $nrmFile->writeBytes($this->norms[$field->name]);
            }
        }

        $this->files[] = $this->name . '.fnm';
        $this->files[] = $this->name . '.nrm';
    }

    /**
     * Generate compound index file
     */
    protected function _generateCFS(): void
    {
        $cfsFile = $this->directory->createFile($this->name . '.cfs');
        $cfsFile->writeVInt(count($this->files));

        $dataOffsetPointers = [];
        foreach ($this->files as $fileName) {
            $dataOffsetPointers[$fileName] = $cfsFile->tell();
            $cfsFile->writeLong(0); // write dummy data
            $cfsFile->writeString($fileName);
        }

        foreach ($this->files as $fileName) {
            // Get actual data offset
            $dataOffset = $cfsFile->tell();
            // Seek to the data offset pointer
            $cfsFile->seek($dataOffsetPointers[$fileName]);
            // Write actual data offset value
            $cfsFile->writeLong($dataOffset);
            // Seek back to the end of file
            $cfsFile->seek($dataOffset);

            $dataFile = $this->directory->getFileObject($fileName);

            $byteCount = $this->directory->fileLength($fileName);
            while ($byteCount > 0) {
                $data = $dataFile->readBytes(min($byteCount, 131072 /*128Kb*/));
                $byteCount -= strlen($data);
                $cfsFile->writeBytes($data);
            }

            $this->directory->deleteFile($fileName);
        }
    }
}

