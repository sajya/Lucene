<?php


namespace Sajya\Lucene\Index\SegmentWriter;

use Sajya\Lucene\Index\SegmentInfo;
use Sajya\Lucene\Storage\Directory;

/**
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage Index
 */
class StreamWriter extends AbstractSegmentWriter
{
    /**
     * Object constructor.
     *
     * @param Directory\DirectoryInterface $directory
     * @param string                       $name
     */
    public function __construct(Directory\DirectoryInterface $directory, $name)
    {
        parent::__construct($directory, $name);
    }


    /**
     * Create stored fields files and open them for write
     */
    public function createStoredFieldsFiles(): void
    {
        $this->fdxFile = $this->directory->createFile($this->name . '.fdx');
        $this->fdtFile = $this->directory->createFile($this->name . '.fdt');

        $this->files[] = $this->name . '.fdx';
        $this->files[] = $this->name . '.fdt';
    }

    public function addNorm($fieldName, $normVector): void
    {
        if (isset($this->norms[$fieldName])) {
            $this->norms[$fieldName] .= $normVector;
        } else {
            $this->norms[$fieldName] = $normVector;
        }
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
        $this->_generateCFS();

        return new SegmentInfo($this->directory,
            $this->name,
            $this->docCount,
            -1,
            null,
            true,
            true);
    }
}

