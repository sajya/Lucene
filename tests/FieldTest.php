<?php


namespace Sajya\Lucene\Test;

use PHPUnit\Framework\TestCase;
use Sajya\Lucene\Document;

/**
 * @category   Zend
 * @package    Zend_Search_Lucene
 * @subpackage UnitTests
 * @group      Zend_Search_Lucene
 */
class FieldTest extends TestCase
{
    public function testBinary(): void
    {
        $field = Document\Field::Binary('field', 'value');

        $this->assertEquals($field->boost, 1);
        $this->assertEquals($field->encoding, '');
        $this->assertEquals($field->isBinary, true);
        $this->assertEquals($field->isIndexed, false);
        $this->assertEquals($field->isStored, true);
        $this->assertEquals($field->isTokenized, false);

        $this->assertEquals($field->name, 'field');
        $this->assertEquals($field->value, 'value');
    }

    public function testKeyword(): void
    {
        $field = Document\Field::Keyword('field', 'value');

        $this->assertEquals($field->boost, 1);
        $this->assertEquals($field->encoding, 'UTF-8');
        $this->assertEquals($field->isBinary, false);
        $this->assertEquals($field->isIndexed, true);
        $this->assertEquals($field->isStored, true);
        $this->assertEquals($field->isTokenized, false);

        $this->assertEquals($field->name, 'field');
        $this->assertEquals($field->value, 'value');
    }

    public function testText(): void
    {
        $field = Document\Field::Text('field', 'value');

        $this->assertEquals($field->boost, 1);
        $this->assertEquals($field->encoding, 'UTF-8');
        $this->assertEquals($field->isBinary, false);
        $this->assertEquals($field->isIndexed, true);
        $this->assertEquals($field->isStored, true);
        $this->assertEquals($field->isTokenized, true);

        $this->assertEquals($field->name, 'field');
        $this->assertEquals($field->value, 'value');
    }

    public function testUnIndexed(): void
    {
        $field = Document\Field::UnIndexed('field', 'value');

        $this->assertEquals($field->boost, 1);
        $this->assertEquals($field->encoding, 'UTF-8');
        $this->assertEquals($field->isBinary, false);
        $this->assertEquals($field->isIndexed, false);
        $this->assertEquals($field->isStored, true);
        $this->assertEquals($field->isTokenized, false);

        $this->assertEquals($field->name, 'field');
        $this->assertEquals($field->value, 'value');
    }

    public function testUnStored(): void
    {
        $field = Document\Field::UnStored('field', 'value');

        $this->assertEquals($field->boost, 1);
        $this->assertEquals($field->encoding, 'UTF-8');
        $this->assertEquals($field->isBinary, false);
        $this->assertEquals($field->isIndexed, true);
        $this->assertEquals($field->isStored, false);
        $this->assertEquals($field->isTokenized, true);

        $this->assertEquals($field->name, 'field');
        $this->assertEquals($field->value, 'value');
    }

    public function testEncoding(): void
    {
        // forcing filter to UTF-8
        $utf8text = iconv('UTF-8', 'UTF-8', 'Words with umlauts: åãü...');

        $iso8859_1 = iconv('UTF-8', 'ISO-8859-1', $utf8text);
        $field = Document\Field::Text('field', $iso8859_1, 'ISO-8859-1');

        $this->assertEquals($field->encoding, 'ISO-8859-1');

        $this->assertEquals($field->name, 'field');
        $this->assertEquals($field->value, $iso8859_1);
        $this->assertEquals($field->getUtf8Value(), $utf8text);
    }
}

