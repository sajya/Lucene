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
        $this->_fdxFile = $this->_directory->createFile($this->_name . '.fdx');
        $this->_fdtFile = $this->_directory->createFile($this->_name . '.fdt');

        $this->_files[] = $this->_name . '.fdx';
        $this->_files[] = $this->_name . '.fdt';
    }

    public function addNorm($fieldName, $normVector): void
    {
        if (isset($this->_norms[$fieldName])) {
            $this->_norms[$fieldName] .= $normVector;
        } else {
            $this->_norms[$fieldName] = $normVector;
        }
    }

    /**
     * Close segment, write it to disk and return segment info
     *
     * @return SegmentInfo
     */
    public function close()
    {
        if ($this->_docCount == 0) {
            return null;
        }

        $this->_dumpFNM();
        $this->_generateCFS();

        return new SegmentInfo($this->_directory,
            $this->_name,
            $this->_docCount,
            -1,
            null,
            true,
            true);
    }
}

