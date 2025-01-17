<?php


namespace Sajya\Lucene\Search;

use Sajya\Lucene\Exception\InvalidArgumentException;

/**
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage Search
 */
class QueryToken
{
    /**
     * Token types.
     */
    public const TT_WORD = 0;              // Word
    public const TT_PHRASE = 1;            // Phrase (one or several quoted words)
    public const TT_FIELD = 2;             // Field name in 'field:word', field:<phrase> or field:(<subquery>) pairs
    public const TT_FIELD_INDICATOR = 3;   // ':'
    public const TT_REQUIRED = 4;          // '+'
    public const TT_PROHIBITED = 5;        // '-'
    public const TT_FUZZY_PROX_MARK = 6;   // '~'
    public const TT_BOOSTING_MARK = 7;     // '^'
    public const TT_RANGE_INCL_START = 8;  // '['
    public const TT_RANGE_INCL_END = 9;    // ']'
    public const TT_RANGE_EXCL_START = 10; // '{'
    public const TT_RANGE_EXCL_END = 11;   // '}'
    public const TT_SUBQUERY_START = 12;   // '('
    public const TT_SUBQUERY_END = 13;     // ')'
    public const TT_AND_LEXEME = 14;       // 'AND' or 'and'
    public const TT_OR_LEXEME = 15;        // 'OR'  or 'or'
    public const TT_NOT_LEXEME = 16;       // 'NOT' or 'not'
    public const TT_TO_LEXEME = 17;        // 'TO'  or 'to'
    public const TT_NUMBER = 18;           // Number, like: 10, 0.8, .64, ....
    /**
     * TokenCategories
     */
    public const TC_WORD = 0;
    public const TC_PHRASE = 1;           // Word
    public const TC_NUMBER = 2;           // Phrase (one or several quoted words)
    public const TC_SYNTAX_ELEMENT = 3;   // Nubers, which are used with syntax elements. Ex. roam~0.8
    /**
     * Token type.
     *
     * @var integer
     */
    public $type;   // +  -  ( )  [ ]  { }  !  ||  && ~ ^
    /**
     * Token text.
     *
     * @var integer
     */
    public $text;
    /**
     * Token position within query.
     *
     * @var integer
     */
    public $position;

    /**
     * IndexReader constructor needs token type and token text as a parameters.
     *
     * @param integer $tokenCategory
     * @param string  $tokText
     * @param integer $position
     *
     * @throws InvalidArgumentException
     */
    public function __construct($tokenCategory, $tokenText, $position)
    {
        $this->text = $tokenText;
        $this->position = $position + 1; // Start from 1

        switch ($tokenCategory) {
            case self::TC_WORD:
                if (strtolower($tokenText) == 'and') {
                    $this->type = self::TT_AND_LEXEME;
                } else if (strtolower($tokenText) == 'or') {
                    $this->type = self::TT_OR_LEXEME;
                } else if (strtolower($tokenText) == 'not') {
                    $this->type = self::TT_NOT_LEXEME;
                } else if (strtolower($tokenText) == 'to') {
                    $this->type = self::TT_TO_LEXEME;
                } else {
                    $this->type = self::TT_WORD;
                }
                break;

            case self::TC_PHRASE:
                $this->type = self::TT_PHRASE;
                break;

            case self::TC_NUMBER:
                $this->type = self::TT_NUMBER;
                break;

            case self::TC_SYNTAX_ELEMENT:
                switch ($tokenText) {
                    case ':':
                        $this->type = self::TT_FIELD_INDICATOR;
                        break;

                    case '+':
                        $this->type = self::TT_REQUIRED;
                        break;

                    case '-':
                        $this->type = self::TT_PROHIBITED;
                        break;

                    case '~':
                        $this->type = self::TT_FUZZY_PROX_MARK;
                        break;

                    case '^':
                        $this->type = self::TT_BOOSTING_MARK;
                        break;

                    case '[':
                        $this->type = self::TT_RANGE_INCL_START;
                        break;

                    case ']':
                        $this->type = self::TT_RANGE_INCL_END;
                        break;

                    case '{':
                        $this->type = self::TT_RANGE_EXCL_START;
                        break;

                    case '}':
                        $this->type = self::TT_RANGE_EXCL_END;
                        break;

                    case '(':
                        $this->type = self::TT_SUBQUERY_START;
                        break;

                    case ')':
                        $this->type = self::TT_SUBQUERY_END;
                        break;

                    case '!':
                        $this->type = self::TT_NOT_LEXEME;
                        break;

                    case '&&':
                        $this->type = self::TT_AND_LEXEME;
                        break;

                    case '||':
                        $this->type = self::TT_OR_LEXEME;
                        break;

                    default:
                        throw new InvalidArgumentException(
                            'Unrecognized query syntax lexeme: \'' . $tokenText . '\''
                        );
                }
                break;

            case self::TC_NUMBER:
                $this->type = self::TT_NUMBER;

            default:
                throw new InvalidArgumentException(
                    'Unrecognized lexeme type: \'' . $tokenCategory . '\''
                );
        }
    }

    /**
     * Returns all possible lexeme types.
     * It's used for syntax analyzer state machine initialization
     *
     * @return array
     */
    public static function getTypes(): array
    {
        return [self::TT_WORD,
                self::TT_PHRASE,
                self::TT_FIELD,
                self::TT_FIELD_INDICATOR,
                self::TT_REQUIRED,
                self::TT_PROHIBITED,
                self::TT_FUZZY_PROX_MARK,
                self::TT_BOOSTING_MARK,
                self::TT_RANGE_INCL_START,
                self::TT_RANGE_INCL_END,
                self::TT_RANGE_EXCL_START,
                self::TT_RANGE_EXCL_END,
                self::TT_SUBQUERY_START,
                self::TT_SUBQUERY_END,
                self::TT_AND_LEXEME,
                self::TT_OR_LEXEME,
                self::TT_NOT_LEXEME,
                self::TT_TO_LEXEME,
                self::TT_NUMBER,
        ];
    }
}
