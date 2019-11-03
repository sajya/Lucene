<?php


namespace Sajya\Lucene\Analysis;

/**
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage Analysis
 */
class Token
{
    /**
     * The text of the term.
     *
     * @var string
     */
    private $termText;

    /**
     * Start in source text.
     *
     * @var integer
     */
    private $startOffset;

    /**
     * End in source text
     *
     * @var integer
     */
    private $endOffset;

    /**
     * The position of this token relative to the previous Token.
     *
     * The default value is one.
     *
     * Some common uses for this are:
     * Set it to zero to put multiple terms in the same position.  This is
     * useful if, e.g., a word has multiple stems.  Searches for phrases
     * including either stem will match.  In this case, all but the first stem's
     * increment should be set to zero: the increment of the first instance
     * should be one.  Repeating a token with an increment of zero can also be
     * used to boost the scores of matches on that token.
     *
     * Set it to values greater than one to inhibit exact phrase matches.
     * If, for example, one does not want phrases to match across removed stop
     * words, then one could build a stop word filter that removes stop words and
     * also sets the increment to the number of stop words removed before each
     * non-stop word.  Then exact phrase queries will only match when the terms
     * occur with no intervening stop words.
     *
     * @var integer
     */
    private $positionIncrement = 1;

    /**
     * Object constructor
     *
     * @param string  $text
     * @param integer $start
     * @param integer $end
     */
    public function __construct($text, $start, $end)
    {
        $this->termText = $text;
        $this->startOffset = $start;
        $this->endOffset = $end;
    }

    /**
     * Returns the position increment of this Token.
     *
     * @return integer
     */
    public function getPositionIncrement(): int
    {
        return $this->positionIncrement;
    }

    /**
     * positionIncrement setter
     *
     * @param integer $positionIncrement
     */
    public function setPositionIncrement($positionIncrement): void
    {
        $this->positionIncrement = $positionIncrement;
    }

    /**
     * Returns the Token's term text.
     *
     * @return string
     */
    public function getTermText(): ?string
    {
        return $this->termText;
    }

    /**
     * Returns this Token's starting offset, the position of the first character
     * corresponding to this token in the source text.
     *
     * Note:
     * The difference between getEndOffset() and getStartOffset() may not be equal
     * to strlen(Zend_Search_Lucene_Analysis_Token::getTermText()), as the term text may have been altered
     * by a stemmer or some other filter.
     *
     * @return integer
     */
    public function getStartOffset(): int
    {
        return $this->startOffset;
    }

    /**
     * Returns this Token's ending offset, one greater than the position of the
     * last character corresponding to this token in the source text.
     *
     * @return integer
     */
    public function getEndOffset(): int
    {
        return $this->endOffset;
    }
}

