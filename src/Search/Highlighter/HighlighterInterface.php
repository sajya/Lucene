<?php


namespace Sajya\Lucene\Search\Highlighter;

use Sajya\Lucene\Document\HTML;

/**
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage Search
 */
interface HighlighterInterface
{
    /**
     * Set document for highlighting.
     *
     * @param HTML $document
     */
    public function setDocument(HTML $document);

    /**
     * Get document for highlighting.
     *
     * @return HTML $document
     */
    public function getDocument();

    /**
     * Highlight specified words (method is invoked once per subquery)
     *
     * @param string|array $words Words to highlight. They could be organized using the array or string.
     */
    public function highlight($words);
}
