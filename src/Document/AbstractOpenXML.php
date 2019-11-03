<?php


namespace Sajya\Lucene\Document;

use Sajya\Lucene\Document;
use ZipArchive;

/**
 * OpenXML document.
 *
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage Document
 */
abstract class AbstractOpenXML extends Document
{
    /**
     * Xml Schema - Relationships
     *
     * @var string
     */
    public const SCHEMA_RELATIONSHIP = 'http://schemas.openxmlformats.org/package/2006/relationships';

    /**
     * Xml Schema - Office document
     *
     * @var string
     */
    public const SCHEMA_OFFICEDOCUMENT = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument';

    /**
     * Xml Schema - Core properties
     *
     * @var string
     */
    public const SCHEMA_COREPROPERTIES = 'http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties';

    /**
     * Xml Schema - Dublin Core
     *
     * @var string
     */
    public const SCHEMA_DUBLINCORE = 'http://purl.org/dc/elements/1.1/';

    /**
     * Xml Schema - Dublin Core Terms
     *
     * @var string
     */
    public const SCHEMA_DUBLINCORETERMS = 'http://purl.org/dc/terms/';

    /**
     * Extract metadata from document
     *
     * @param ZipArchive $package ZipArchive AbstractOpenXML package
     *
     * @return array    Key-value pairs containing document meta data
     */
    protected function extractMetaData(ZipArchive $package): array
    {
        // Data holders
        $coreProperties = [];

        // Prevent php from loading remote resources
        $loadEntities = libxml_disable_entity_loader(true);

        // Read relations and search for core properties
        $relations = simplexml_load_string($package->getFromName('_rels/.rels'));

        // Restore entity loader state
        libxml_disable_entity_loader($loadEntities);

        foreach ($relations->Relationship as $rel) {
            if ($rel['Type'] == self::SCHEMA_COREPROPERTIES) {
                // Found core properties! Read in contents...
                $contents = simplexml_load_string(
                    $package->getFromName(dirname($rel['Target']) . '/' . basename($rel['Target']))
                );

                foreach ($contents->children(self::SCHEMA_DUBLINCORE) as $child) {
                    $coreProperties[$child->getName()] = (string)$child;
                }
                foreach ($contents->children(self::SCHEMA_COREPROPERTIES) as $child) {
                    $coreProperties[$child->getName()] = (string)$child;
                }
                foreach ($contents->children(self::SCHEMA_DUBLINCORETERMS) as $child) {
                    $coreProperties[$child->getName()] = (string)$child;
                }
            }
        }

        return $coreProperties;
    }

    /**
     * Determine absolute zip path
     *
     * @param string $path
     *
     * @return string
     */
    protected function absoluteZipPath($path): string
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen');
        $absolutes = [];
        foreach ($parts as $part) {
            if ('.' == $part) {
                continue;
            }
            if ('..' == $part) {
                array_pop($absolutes);
            } else {
                $absolutes[] = $part;
            }
        }
        return implode('/', $absolutes);
    }
}
