<?php


namespace Sajya\Lucene\Analysis\TokenFilter;

use Sajya\Lucene\Analysis\Token;
use Sajya\Lucene\Exception\InvalidArgumentException;
use Sajya\Lucene\Exception\RuntimeException;

/**
 * Token filter that removes stop words. These words must be provided as array (set), example:
 * $stopwords = array('the' => 1, 'an' => '1');
 *
 * We do recommend to provide all words in lowercase and concatenate this class after the lowercase filter.
 *
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage Analysis
 */
class StopWords implements TokenFilterInterface
{
    /**
     * Stop Words
     *
     * @var array
     */
    private $stopSet;

    /**
     * Constructs new instance of this filter.
     *
     * @param array $stopwords array (set) of words that will be filtered out
     */
    public function __construct(array $stopwords = [])
    {
        $this->stopSet = array_flip($stopwords);
    }

    /**
     * Normalize Token or remove it (if null is returned)
     *
     * @param Token $srcToken
     *
     * @return Token
     */
    public function normalize(Token $srcToken): ?Token
    {
        if (array_key_exists($srcToken->getTermText(), $this->stopSet)) {
            return null;
        }

        return $srcToken;
    }

    /**
     * Fills stopwords set from a text file. Each line contains one stopword, lines with '#' in the first
     * column are ignored (as comments).
     *
     * You can call this method one or more times. New stopwords are always added to current set.
     *
     * @param string $filepath full path for text file with stopwords
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function loadFromFile($filepath = null): void
    {
        if (!$filepath || !file_exists($filepath)) {
            throw new InvalidArgumentException('You have to provide valid file path');
        }
        $fd = fopen($filepath, 'rb');
        if (!$fd) {
            throw new RuntimeException('Cannot open file ' . $filepath);
        }
        while (!feof($fd)) {
            $buffer = trim(fgets($fd));
            if ($buffer != '' && $buffer[0] != '#') {
                $this->stopSet[$buffer] = 1;
            }
        }
        if (!fclose($fd)) {
            throw new RuntimeException('Cannot close file ' . $filepath);
        }
    }
}
