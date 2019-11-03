<?php


namespace Sajya\Lucene;

use Sajya\Lucene\Document\Field;
use Sajya\Lucene\Exception\InvalidArgumentException;

/**
 * A Document is a set of fields. Each field has a name and a textual value.
 *
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage Document
 */
class Document
{
    /**
     * Field boost factor
     * It's not stored directly in the index, but affects on normalization factor
     *
     * @var float
     */
    public $boost = 1.0;

    /**
     * Associative array \Sajya\Lucene\Document\Field objects where the keys to the
     * array are the names of the fields.
     *
     * @var array
     */
    protected $_fields = [];

    /**
     * Magic method for checking the existence of a field
     *
     * @param string $offset
     *
     * @return boolean TRUE if the field exists else FALSE
     */
    public function __isset($offset)
    {
        return in_array($offset, $this->getFieldNames());
    }

    /**
     * Return an array with the names of the fields in this document.
     *
     * @return array
     */
    public function getFieldNames(): array
    {
        return array_keys($this->_fields);
    }

    /**
     * Proxy method for getFieldValue(), provides more convenient access to
     * the string value of a field.
     *
     * @param  $offset
     *
     * @return string
     */
    public function __get($offset)
    {
        return $this->getFieldValue($offset);
    }

    /**
     * Returns the string value of a named field in this document.
     *
     * @return string
     * @see __get()
     */
    public function getFieldValue($fieldName): string
    {
        return $this->getField($fieldName)->value;
    }

    /**
     * Returns {@link \Sajya\Lucene\Document\Field} object for a named field in this document.
     *
     * @param string $fieldName
     *
     * @return Field
     * @throws InvalidArgumentException
     */
    public function getField($fieldName): Field
    {
        if (!array_key_exists($fieldName, $this->_fields)) {
            throw new InvalidArgumentException("Field name \"$fieldName\" not found in document.");
        }

        return $this->_fields[$fieldName];
    }

    /**
     * Add a field object to this document.
     *
     * @param Field $field
     *
     * @return Document
     */
    public function addField(Document\Field $field): Document
    {
        $this->_fields[$field->name] = $field;

        return $this;
    }

    /**
     * Returns the string value of a named field in UTF-8 encoding.
     *
     * @return string
     * @see __get()
     */
    public function getFieldUtf8Value($fieldName): string
    {
        return $this->getField($fieldName)->getUtf8Value();
    }
}
