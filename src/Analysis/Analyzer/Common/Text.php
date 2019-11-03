<?php

namespace Sajya\Lucene\Analysis\Analyzer\Common;

use Sajya\Lucene\Analysis\Token;

/**
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage Analysis
 */
class Text extends AbstractCommon
{
    /**
     * Current position in a stream
     *
     * @var integer
     */
    private $position;

    /**
     * Reset token stream
     */
    public function reset()
    {
        $this->position = 0;

        if ($this->input === null) {
            return;
        }

        // convert input into ascii
        if (PHP_OS != 'AIX') {
            $this->input = iconv($this->encoding, 'ASCII//TRANSLIT', $this->input);
        }
        $this->encoding = 'ASCII';
    }

    /**
     * Tokenization stream API
     * Get next token
     * Returns null at the end of stream
     *
     * @return Token|null
     */
    public function nextToken()
    {
        if ($this->input === null) {
            return null;
        }


        do {
            if (!preg_match('/[a-zA-Z]+/', $this->input, $match, PREG_OFFSET_CAPTURE, $this->position)) {
                // It covers both cases a) there are no matches (preg_match(...) === 0)
                // b) error occured (preg_match(...) === FALSE)
                return null;
            }

            $str = $match[0][0];
            $pos = $match[0][1];
            $endpos = $pos + strlen($str);

            $this->position = $endpos;

            $token = $this->normalize(new Token($str, $pos, $endpos));
        } while ($token === null); // try again if token is skipped

        return $token;
    }
}

