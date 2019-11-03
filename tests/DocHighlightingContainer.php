<?php


namespace Sajya\Lucene\Test;


class DocHighlightingContainer
{
    public static function extendedHighlightingCallback($stringToHighlight, $param1, $param2): string
    {
        return '<b ' . $param1 . '>' . $stringToHighlight . '</b>' . $param2;
    }
}
