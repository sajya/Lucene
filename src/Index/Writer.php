<?php


namespace Sajya\Lucene\Index;

use Exception;
use Sajya\Lucene;
use Sajya\Lucene\Document;
use Sajya\Lucene\Exception\ExceptionInterface;
use Sajya\Lucene\Exception\InvalidFileFormatException;
use Sajya\Lucene\Exception\RuntimeException;
use Sajya\Lucene\Index\SegmentWriter\DocumentWriter;
use Sajya\Lucene\Storage\Directory\DirectoryInterface;
use Sajya\Lucene\Storage\File\FileInterface;

/**
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage Index
 */
class Writer
{
    /**
     * @todo Implement AnalyzerInterface substitution
     * @todo Implement Zend_Search_Lucene_StoragedirectoryRAM and Zend_Search_Lucene_Storage_FileRAM to use it for
     *       temporary index files
     * @todo DirectoryInterface lock processing
     */

    /**
     * List of indexfiles extensions
     *
     * @var array
     */
    private static $_indexExtensions = [
        '.cfs' => '.cfs',
        '.cfx' => '.cfx',
        '.fnm' => '.fnm',
        '.fdx' => '.fdx',
        '.fdt' => '.fdt',
        '.tis' => '.tis',
        '.tii' => '.tii',
        '.frq' => '.frq',
        '.prx' => '.prx',
        '.tvx' => '.tvx',
        '.tvd' => '.tvd',
        '.tvf' => '.tvf',
        '.del' => '.del',
        '.sti' => '.sti',
    ];

    /**
     * Number of documents required before the buffered in-memory
     * documents are written into a new Segment
     *
     * Default value is 10
     *
     * @var integer
     */
    public $maxBufferedDocs = 10;

    /**
     * Largest number of documents ever merged by addDocument().
     * Small values (e.g., less than 10,000) are best for interactive indexing,
     * as this limits the length of pauses while indexing to a few seconds.
     * Larger values are best for batched indexing and speedier searches.
     *
     * Default value is PHP_INT_MAX
     *
     * @var integer
     */
    public $maxMergeDocs = PHP_INT_MAX;
    /**
     * Determines how often segment indices are merged by addDocument().
     *
     * With smaller values, less RAM is used while indexing,
     * and searches on unoptimized indices are faster,
     * but indexing speed is slower.
     *
     * With larger values, more RAM is used during indexing,
     * and while searches on unoptimized indices are slower,
     * indexing is faster.
     *
     * Thus larger values (> 10) are best for batch index creation,
     * and smaller values (< 10) for indices that are interactively maintained.
     *
     * Default value is 10
     *
     * @var integer
     */
    public $mergeFactor = 10;

    /**
     * File system adapter.
     *
     * @var DirectoryInterface
     */
    private $directory = null;

    /**
     * Changes counter.
     *
     * @var integer
     */
    private $versionUpdate = 0;

    /**
     * List of the segments, created by index writer
     * Array of Zend_Search_Lucene_Index_SegmentInfo objects
     *
     * @var array
     */
    private $newSegments = [];

    /**
     * List of segments to be deleted on commit
     *
     * @var array
     */
    private $_segmentsToDelete = [];

    /**
     * Current segment to add documents
     *
     * @var DocumentWriter
     */
    private $currentSegment = null;

    /**
     * Array of Zend_Search_Lucene_Index_SegmentInfo objects for this index.
     *
     * It's a reference to the corresponding Zend_Search_Lucene::$segmentInfos array
     *
     * @var array|SegmentInfo
     */
    private $segmentInfos;

    /**
     * Index target format version
     *
     * @var integer
     */
    private $_targetFormatVersion;

    /**
     * Open the index for writing
     *
     * @param DirectoryInterface $directory
     * @param array              $segmentInfos
     * @param integer            $targetFormatVersion
     * @param FileInterface      $cleanUpLock
     */
    public function __construct(DirectoryInterface $directory, &$segmentInfos, $targetFormatVersion)
    {
        $this->directory = $directory;
        $this->segmentInfos = &$segmentInfos;
        $this->_targetFormatVersion = $targetFormatVersion;
    }

    /**
     * Create empty index
     *
     * @param DirectoryInterface $directory
     * @param integer            $generation
     * @param integer            $nameCount
     */
    public static function createIndex(DirectoryInterface $directory, $generation, $nameCount): void
    {
        if ($generation == 0) {
            // Create index in pre-2.1 mode
            foreach ($directory->fileList() as $file) {
                if ($file == 'deletable' ||
                    $file == 'segments' ||
                    isset(self::$_indexExtensions[substr($file, strlen($file) - 4)]) ||
                    preg_match('/\.f\d+$/i', $file) /* matches <segmentname>.f<decimal_nmber> file names */) {
                    $directory->deleteFile($file);
                }
            }

            $segmentsFile = $directory->createFile('segments');
            $segmentsFile->writeInt((int)0xFFFFFFFF);

            // write version (initialized by current time)
            $segmentsFile->writeLong(round(microtime(true)));

            // write name counter
            $segmentsFile->writeInt($nameCount);
            // write segment counter
            $segmentsFile->writeInt(0);

            $deletableFile = $directory->createFile('deletable');
            // write counter
            $deletableFile->writeInt(0);
        } else {
            $genFile = $directory->createFile('segments.gen');

            $genFile->writeInt((int)0xFFFFFFFE);
            // Write generation two times
            $genFile->writeLong($generation);
            $genFile->writeLong($generation);

            $segmentsFile = $directory->createFile(Lucene\Index::getSegmentFileName($generation));
            $segmentsFile->writeInt((int)0xFFFFFFFD);

            // write version (initialized by current time)
            $segmentsFile->writeLong(round(microtime(true)));

            // write name counter
            $segmentsFile->writeInt($nameCount);
            // write segment counter
            $segmentsFile->writeInt(0);
        }
    }

    /**
     * Adds a document to this index.
     *
     * @param Document $document
     */
    public function addDocument(Document $document): void
    {
        $this->currentSegment = $this->currentSegment ?? new SegmentWriter\DocumentWriter($this->directory, $this->newSegmentName());

        $this->currentSegment->addDocument($document);

        if ($this->currentSegment->count() >= $this->maxBufferedDocs) {
            $this->commit();
        }

        $this->maybeMergeSegments();

        $this->versionUpdate++;
    }

    /**
     * Get name for new segment
     *
     * @return string
     */
    private function newSegmentName(): string
    {
        Lucene\LockManager::obtainWriteLock($this->directory);

        $generation = Lucene\Index::getActualGeneration($this->directory);
        $segmentsFile = $this->directory->getFileObject(Lucene\Index::getSegmentFileName($generation), false);

        $segmentsFile->seek(12); // 12 = 4 (int, file format marker) + 8 (long, index version)
        $segmentNameCounter = $segmentsFile->readInt();

        $segmentsFile->seek(12); // 12 = 4 (int, file format marker) + 8 (long, index version)
        $segmentsFile->writeInt($segmentNameCounter + 1);

        // Flash output to guarantee that wrong value will not be loaded between unlock and
        // return (which calls $segmentsFile destructor)
        $segmentsFile->flush();

        Lucene\LockManager::releaseWriteLock($this->directory);

        return '_' . base_convert($segmentNameCounter, 10, 36);
    }

    /**
     * Commit current changes
     */
    public function commit(): void
    {
        if ($this->currentSegment === null) {
            $this->_updateSegments();
            return;
        }

        $newSegment = $this->currentSegment->close();

        if ($newSegment !== null) {
            $this->newSegments[$newSegment->getName()] = $newSegment;
        }

        $this->currentSegment = null;
    }

    /**
     * Update segments file by adding current segment to a list
     *
     * @throws RuntimeException
     * @throws InvalidFileFormatException
     */
    private function _updateSegments(): void
    {
        // Get an exclusive index lock
        Lucene\LockManager::obtainWriteLock($this->directory);

        // Write down changes for the segments
        foreach ($this->segmentInfos as $segInfo) {
            $segInfo->writeChanges();
        }


        $generation = Lucene\Index::getActualGeneration($this->directory);
        $segmentsFile = $this->directory->getFileObject(Lucene\Index::getSegmentFileName($generation), false);
        $newSegmentFile = $this->directory->createFile(Lucene\Index::getSegmentFileName(++$generation), false);

        try {
            $genFile = $this->directory->getFileObject('segments.gen', false);
        } catch (ExceptionInterface $e) {
            if (strpos($e->getMessage(), 'is not readable') !== false) {
                $genFile = $this->directory->createFile('segments.gen');
            } else {
                throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
            }
        }

        $genFile->writeInt((int)0xFFFFFFFE);
        // Write generation (first copy)
        $genFile->writeLong($generation);

        try {
            // Write format marker
            if ($this->_targetFormatVersion == Lucene\Index::FORMAT_2_1) {
                $newSegmentFile->writeInt((int)0xFFFFFFFD);
            } else if ($this->_targetFormatVersion == Lucene\Index::FORMAT_2_3) {
                $newSegmentFile->writeInt((int)0xFFFFFFFC);
            }

            // Read src file format identifier
            $format = $segmentsFile->readInt();
            if ($format == (int)0xFFFFFFFF) {
                $srcFormat = Lucene\Index::FORMAT_PRE_2_1;
            } else if ($format == (int)0xFFFFFFFD) {
                $srcFormat = Lucene\Index::FORMAT_2_1;
            } else if ($format == (int)0xFFFFFFFC) {
                $srcFormat = Lucene\Index::FORMAT_2_3;
            } else {
                throw new InvalidFileFormatException('Unsupported segments file format');
            }

            $version = $segmentsFile->readLong() + $this->versionUpdate;
            $this->versionUpdate = 0;
            $newSegmentFile->writeLong($version);

            // Write segment name counter
            $newSegmentFile->writeInt($segmentsFile->readInt());

            // Get number of segments offset
            $numOfSegmentsOffset = $newSegmentFile->tell();
            // Write dummy data (segment counter)
            $newSegmentFile->writeInt(0);

            // Read number of segemnts
            $segmentsCount = $segmentsFile->readInt();

            $segments = [];
            for ($count = 0; $count < $segmentsCount; $count++) {
                $segName = $segmentsFile->readString();
                $segSize = $segmentsFile->readInt();

                if ($srcFormat == Lucene\Index::FORMAT_PRE_2_1) {
                    // pre-2.1 index format
                    $delGen = 0;
                    $hasSingleNormFile = false;
                    $numField = (int)0xFFFFFFFF;
                    $isCompoundByte = 0;
                    $docStoreOptions = null;
                } else {
                    $delGen = $segmentsFile->readLong();

                    if ($srcFormat == Lucene\Index::FORMAT_2_3) {
                        $docStoreOffset = $segmentsFile->readInt();

                        if ($docStoreOffset != (int)0xFFFFFFFF) {
                            $docStoreSegment = $segmentsFile->readString();
                            $docStoreIsCompoundFile = $segmentsFile->readByte();

                            $docStoreOptions = ['offset'     => $docStoreOffset,
                                                'segment'    => $docStoreSegment,
                                                'isCompound' => ($docStoreIsCompoundFile == 1)];
                        } else {
                            $docStoreOptions = null;
                        }
                    } else {
                        $docStoreOptions = null;
                    }

                    $hasSingleNormFile = $segmentsFile->readByte();
                    $numField = $segmentsFile->readInt();

                    $normGens = [];
                    if ($numField != (int)0xFFFFFFFF) {
                        for ($count1 = 0; $count1 < $numField; $count1++) {
                            $normGens[] = $segmentsFile->readLong();
                        }
                    }
                    $isCompoundByte = $segmentsFile->readByte();
                }

                if (!in_array($segName, $this->_segmentsToDelete)) {
                    // Load segment if necessary
                    if (!isset($this->segmentInfos[$segName])) {
                        if ($isCompoundByte == 0xFF) {
                            // The segment is not a compound file
                            $isCompound = false;
                        } else if ($isCompoundByte == 0x00) {
                            // The status is unknown
                            $isCompound = null;
                        } else if ($isCompoundByte == 0x01) {
                            // The segment is a compound file
                            $isCompound = true;
                        }

                        $this->segmentInfos[$segName] =
                            new SegmentInfo($this->directory,
                                $segName,
                                $segSize,
                                $delGen,
                                $docStoreOptions,
                                $hasSingleNormFile,
                                $isCompound);
                    } else {
                        // Retrieve actual deletions file generation number
                        $delGen = $this->segmentInfos[$segName]->getDelGen();
                    }

                    $newSegmentFile->writeString($segName);
                    $newSegmentFile->writeInt($segSize);
                    $newSegmentFile->writeLong($delGen);
                    if ($this->_targetFormatVersion == Lucene\Index::FORMAT_2_3) {
                        if ($docStoreOptions !== null) {
                            $newSegmentFile->writeInt($docStoreOffset);
                            $newSegmentFile->writeString($docStoreSegment);
                            $newSegmentFile->writeByte($docStoreIsCompoundFile);
                        } else {
                            // Set DocStoreOffset to -1
                            $newSegmentFile->writeInt((int)0xFFFFFFFF);
                        }
                    } else if ($docStoreOptions !== null) {
                        // Release index write lock
                        Lucene\LockManager::releaseWriteLock($this->directory);

                        throw new RuntimeException('Index conversion to lower format version is not supported.');
                    }

                    $newSegmentFile->writeByte($hasSingleNormFile);
                    $newSegmentFile->writeInt($numField);
                    if ($numField != (int)0xFFFFFFFF) {
                        foreach ($normGens as $normGen) {
                            $newSegmentFile->writeLong($normGen);
                        }
                    }
                    $newSegmentFile->writeByte($isCompoundByte);

                    $segments[$segName] = $segSize;
                }
            }
            $segmentsFile->close();

            $segmentsCount = count($segments) + count($this->newSegments);

            foreach ($this->newSegments as $segName => $segmentInfo) {
                $newSegmentFile->writeString($segName);
                $newSegmentFile->writeInt($segmentInfo->count());

                // delete file generation: -1 (there is no delete file yet)
                $newSegmentFile->writeInt((int)0xFFFFFFFF);
                $newSegmentFile->writeInt((int)0xFFFFFFFF);
                if ($this->_targetFormatVersion == Lucene\Index::FORMAT_2_3) {
                    // docStoreOffset: -1 (segment doesn't use shared doc store)
                    $newSegmentFile->writeInt((int)0xFFFFFFFF);
                }
                // HasSingleNormFile
                $newSegmentFile->writeByte($segmentInfo->hasSingleNormFile());
                // NumField
                $newSegmentFile->writeInt((int)0xFFFFFFFF);
                // IsCompoundFile
                $newSegmentFile->writeByte($segmentInfo->isCompound() ? 1 : -1);

                $segments[$segmentInfo->getName()] = $segmentInfo->count();
                $this->segmentInfos[$segName] = $segmentInfo;
            }
            $this->newSegments = [];

            $newSegmentFile->seek($numOfSegmentsOffset);
            $newSegmentFile->writeInt($segmentsCount);  // Update segments count
            $newSegmentFile->close();
        } catch (Exception $e) {
            /** Restore previous index generation */
            $generation--;
            $genFile->seek(4, SEEK_SET);
            // Write generation number twice
            $genFile->writeLong($generation);
            $genFile->writeLong($generation);

            // Release index write lock
            Lucene\LockManager::releaseWriteLock($this->directory);

            // Throw the exception
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }

        // Write generation (second copy)
        $genFile->writeLong($generation);


        // Check if another update or read process is not running now
        // If yes, skip clean-up procedure
        if (Lucene\LockManager::escalateReadLock($this->directory)) {
            /**
             * Clean-up directory
             */
            $filesToDelete = [];
            $filesTypes = [];
            $filesNumbers = [];

            // list of .del files of currently used segments
            // each segment can have several generations of .del files
            // only last should not be deleted
            $delFiles = [];

            foreach ($this->directory->fileList() as $file) {
                if ($file == 'deletable') {
                    // 'deletable' file
                    $filesToDelete[] = $file;
                    $filesTypes[] = 0; // delete this file first, since it's not used starting from Lucene v2.1
                    $filesNumbers[] = 0;
                } else if ($file == 'segments') {
                    // 'segments' file
                    $filesToDelete[] = $file;
                    $filesTypes[] = 1; // second file to be deleted "zero" version of segments file (Lucene pre-2.1)
                    $filesNumbers[] = 0;
                } else if (preg_match('/^segments_[a-zA-Z0-9]+$/i', $file)) {
                    // 'segments_xxx' file
                    // Check if it's not a just created generation file
                    if ($file != Lucene\Index::getSegmentFileName($generation)) {
                        $filesToDelete[] = $file;
                        $filesTypes[] = 2;                                             // first group of files for deletions
                        $filesNumbers[] = (int)base_convert(substr($file, 9), 36, 10); // ordered by segment generation numbers
                    }
                } else if (preg_match('/(^_([a-zA-Z0-9]+))\.f\d+$/i', $file, $matches)) {
                    // one of per segment files ('<segmentname>.f<decimal_number>')
                    // Check if it's not one of the segments in the current segments set
                    if (!isset($segments[$matches[1]])) {
                        $filesToDelete[] = $file;
                        $filesTypes[] = 3;                                        // second group of files for deletions
                        $filesNumbers[] = (int)base_convert($matches[2], 36, 10); // order by segment number
                    }
                } else if (preg_match('/(^_([a-zA-Z0-9]+))(_([a-zA-Z0-9]+))\.del$/i', $file, $matches)) {
                    // one of per segment files ('<segmentname>_<del_generation>.del' where <segmentname> is '_<segment_number>')
                    // Check if it's not one of the segments in the current segments set
                    if (!isset($segments[$matches[1]])) {
                        $filesToDelete[] = $file;
                        $filesTypes[] = 3;                                        // second group of files for deletions
                        $filesNumbers[] = (int)base_convert($matches[2], 36, 10); // order by segment number
                    } else {
                        $segmentNumber = (int)base_convert($matches[2], 36, 10);
                        $delGeneration = (int)base_convert($matches[4], 36, 10);
                        if (!isset($delFiles[$segmentNumber])) {
                            $delFiles[$segmentNumber] = [];
                        }
                        $delFiles[$segmentNumber][$delGeneration] = $file;
                    }
                } else if (isset(self::$_indexExtensions[substr($file, strlen($file) - 4)])) {
                    // one of per segment files ('<segmentname>.<ext>')
                    $segmentName = substr($file, 0, -4);
                    // Check if it's not one of the segments in the current segments set
                    if (!isset($segments[$segmentName]) &&
                        ($this->currentSegment === null || $this->currentSegment->getName() != $segmentName)) {
                        $filesToDelete[] = $file;
                        $filesTypes[] = 3;                                                                               // second group of files for deletions
                        $filesNumbers[] = (int)base_convert(substr($file, 1 /* skip '_' */, -4), 36, 10);                // order by segment number
                    }
                }
            }

            $maxGenNumber = 0;
            // process .del files of currently used segments
            foreach ($delFiles as $segmentNumber => $segmentDelFiles) {
                ksort($delFiles[$segmentNumber], SORT_NUMERIC);
                array_pop($delFiles[$segmentNumber]); // remove last delete file generation from candidates for deleting

                end($delFiles[$segmentNumber]);
                $lastGenNumber = key($delFiles[$segmentNumber]);
                if ($lastGenNumber > $maxGenNumber) {
                    $maxGenNumber = $lastGenNumber;
                }
            }
            foreach ($delFiles as $segmentNumber => $segmentDelFiles) {
                foreach ($segmentDelFiles as $delGeneration => $file) {
                    $filesToDelete[] = $file;
                    $filesTypes[] = 4;                                                 // third group of files for deletions
                    $filesNumbers[] = $segmentNumber * $maxGenNumber + $delGeneration; // order by <segment_number>,<del_generation> pair
                }
            }

            // Reorder files for deleting
            array_multisort($filesTypes, SORT_ASC, SORT_NUMERIC,
                $filesNumbers, SORT_ASC, SORT_NUMERIC,
                $filesToDelete, SORT_ASC, SORT_STRING);

            foreach ($filesToDelete as $file) {
                try {
                    /** Skip shared docstore segments deleting */
                    /** @todo Process '.cfx' files to check if them are already unused */
                    if (substr($file, strlen($file) - 4) != '.cfx') {
                        $this->directory->deleteFile($file);
                    }
                } catch (ExceptionInterface $e) {
                    if (strpos($e->getMessage(), 'Can\'t delete file') === false) {
                        // That's not "file is under processing or already deleted" exception
                        // Pass it through
                        throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
                    }
                }
            }

            // Return read lock into the previous state
            Lucene\LockManager::deEscalateReadLock($this->directory);
        } else {
            // Only release resources if another index reader is running now
            foreach ($this->_segmentsToDelete as $segName) {
                foreach (self::$_indexExtensions as $ext) {
                    $this->directory->purgeFile($segName . $ext);
                }
            }
        }

        // Clean-up _segmentsToDelete container
        $this->_segmentsToDelete = [];


        // Release index write lock
        Lucene\LockManager::releaseWriteLock($this->directory);

        // Remove unused segments from segments list
        foreach ($this->segmentInfos as $segName => $segmentInfo) {
            if (!isset($segments[$segName])) {
                unset($this->segmentInfos[$segName]);
            }
        }
    }

    /**
     * Merge segments if necessary
     */
    private function maybeMergeSegments(): void
    {
        if (Lucene\LockManager::obtainOptimizationLock($this->directory) === false) {
            return;
        }

        if (!$this->_hasAnythingToMerge()) {
            Lucene\LockManager::releaseOptimizationLock($this->directory);
            return;
        }

        // Update segments list to be sure all segments are not merged yet by another process
        //
        // Segment merging functionality is concentrated in this class and surrounded
        // by optimization lock obtaining/releasing.
        // _updateSegments() refreshes segments list from the latest index generation.
        // So only new segments can be added to the index while we are merging some already existing
        // segments.
        // Newly added segments will be also included into the index by the _updateSegments() call
        // either by another process or by the current process with the commit() call at the end of _mergeSegments() method.
        // That's guaranteed by the serialisation of _updateSegments() execution using exclusive locks.
        $this->_updateSegments();

        // Perform standard auto-optimization procedure
        $segmentSizes = [];
        foreach ($this->segmentInfos as $segName => $segmentInfo) {
            $segmentSizes[$segName] = $segmentInfo->count();
        }

        $mergePool = [];
        $poolSize = 0;
        $sizeToMerge = $this->maxBufferedDocs;
        asort($segmentSizes, SORT_NUMERIC);
        foreach ($segmentSizes as $segName => $size) {
            // Check, if segment comes into a new merging block
            while ($size >= $sizeToMerge) {
                // Merge previous block if it's large enough
                if ($poolSize >= $sizeToMerge) {
                    $this->_mergeSegments($mergePool);
                }
                $mergePool = [];
                $poolSize = 0;

                $sizeToMerge *= $this->mergeFactor;

                if ($sizeToMerge > $this->maxMergeDocs) {
                    Lucene\LockManager::releaseOptimizationLock($this->directory);
                    return;
                }
            }

            $mergePool[] = $this->segmentInfos[$segName];
            $poolSize += $size;
        }

        if ($poolSize >= $sizeToMerge) {
            $this->_mergeSegments($mergePool);
        }

        Lucene\LockManager::releaseOptimizationLock($this->directory);
    }

    /**
     * Check if we have anything to merge
     *
     * @return boolean
     */
    private function _hasAnythingToMerge(): bool
    {
        $segmentSizes = [];
        foreach ($this->segmentInfos as $segName => $segmentInfo) {
            $segmentSizes[$segName] = $segmentInfo->count();
        }

        $mergePool = [];
        $poolSize = 0;
        $sizeToMerge = $this->maxBufferedDocs;
        asort($segmentSizes, SORT_NUMERIC);
        foreach ($segmentSizes as $segName => $size) {
            // Check, if segment comes into a new merging block
            while ($size >= $sizeToMerge) {
                // Merge previous block if it's large enough
                if ($poolSize >= $sizeToMerge) {
                    return true;
                }
                $mergePool = [];
                $poolSize = 0;

                $sizeToMerge *= $this->mergeFactor;

                if ($sizeToMerge > $this->maxMergeDocs) {
                    return false;
                }
            }

            $mergePool[] = $this->segmentInfos[$segName];
            $poolSize += $size;
        }

        return $poolSize >= $sizeToMerge;
    }

    /**
     * Merge specified segments
     *
     * $segments is an array of SegmentInfo objects
     *
     * @param array $segments
     */
    private function _mergeSegments($segments): void
    {
        $newName = $this->newSegmentName();

        $merger = new SegmentMerger($this->directory,
            $newName);
        foreach ($segments as $segmentInfo) {
            $merger->addSource($segmentInfo);
            $this->_segmentsToDelete[$segmentInfo->getName()] = $segmentInfo->getName();
        }

        $newSegment = $merger->merge();
        if ($newSegment !== null) {
            $this->newSegments[$newSegment->getName()] = $newSegment;
        }

        $this->commit();
    }

    /**
     * Merges the provided indexes into this index.
     *
     * @param array $readers
     *
     * @return void
     */
    public function addIndexes($readers): void
    {
        /**
         * @todo implementation
         */
    }

    /**
     * Merges all segments together into new one
     *
     * Returns true on success and false if another optimization or auto-optimization process
     * is running now
     *
     * @return boolean
     */
    public function optimize(): bool
    {
        if (Lucene\LockManager::obtainOptimizationLock($this->directory) === false) {
            return false;
        }

        // Update segments list to be sure all segments are not merged yet by another process
        //
        // Segment merging functionality is concentrated in this class and surrounded
        // by optimization lock obtaining/releasing.
        // _updateSegments() refreshes segments list from the latest index generation.
        // So only new segments can be added to the index while we are merging some already existing
        // segments.
        // Newly added segments will be also included into the index by the _updateSegments() call
        // either by another process or by the current process with the commit() call at the end of _mergeSegments() method.
        // That's guaranteed by the serialisation of _updateSegments() execution using exclusive locks.
        $this->_updateSegments();

        $this->_mergeSegments($this->segmentInfos);

        Lucene\LockManager::releaseOptimizationLock($this->directory);

        return true;
    }
}
