<?php


namespace Sajya\Lucene\Index;

use Sajya\Lucene;

/**
 * A Term represents a word from text.  This is the unit of search.  It is
 * composed of two elements, the text of the word, as a string, and the name of
 * the field that the text occured in, an interned string.
 *
 * Note that terms may represent more than words from text fields, but also
 * things like dates, email addresses, urls, etc.
 *
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage Index
 */
class Term
{
    /**
     * Field name or field number (depending from context)
     *
     * @var mixed
     */
    public $field;

    /**
     * Term value
     *
     * @var string
     */
    public $text;


    /**
     * Object constructor
     */
    public function __construct($text, $field = null)
    {
        $this->field = $field ?? Lucene\Lucene::getDefaultSearchField();
        $this->text = $text;
    }

    /**
     * Get term prefix
     *
     * @param string  $str
     * @param integer $length
     *
     * @return string
     */
    public static function getPrefix($str, $length): string
    {
        /**
         * @todo !!!!!!! use mb_string or iconv functions if they are available
         */
        $prefixBytes = 0;
        $prefixChars = 0;
        while (isset($str[$prefixBytes]) && $prefixChars < $length) {
            $charBytes = 1;
            if ((ord($str[$prefixBytes]) & 0xC0) == 0xC0) {
                $charBytes++;
                if (ord($str[$prefixBytes]) & 0x20) {
                    $charBytes++;
                    if (ord($str[$prefixBytes]) & 0x10) {
                        $charBytes++;
                    }
                }
            }

            if (!isset($str[$prefixBytes + $charBytes - 1])) {
                // wrong character
                break;
            }

            $prefixChars++;
            $prefixBytes += $charBytes;
        }

        return substr($str, 0, $prefixBytes);
    }

    /**
     * Get UTF-8 string length
     *
     * @param string $str
     *
     * @return string
     */
    public static function getLength($str): string
    {
        $bytes = 0;
        $chars = 0;
        while ($bytes < strlen($str)) {
            $charBytes = 1;
            if ((ord($str[$bytes]) & 0xC0) == 0xC0) {
                $charBytes++;
                if (ord($str[$bytes]) & 0x20) {
                    $charBytes++;
                    if (ord($str[$bytes]) & 0x10) {
                        $charBytes++;
                    }
                }
            }

            if ($bytes + $charBytes > strlen($str)) {
                // wrong character
                break;
            }

            $chars++;
            $bytes += $charBytes;
        }

        return $chars;
    }

    /**
     * Returns term key
     *
     * @return string
     */
    public function key(): string
    {
        return $this->field . chr(0) . $this->text;
    }
}
