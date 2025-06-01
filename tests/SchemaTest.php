<?php

/*
 * The MIT License
 *
 * Copyright 2018 Austrian Centre for Digital Humanities.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace acdhOeaw\tokeneditorModel;

use PDO;

/**
 * Description of SchemaTest
 *
 * @author zozlak
 */
class SchemaTest extends \PHPUnit\Framework\TestCase {

    static private string $connSettings = 'pgsql: host=127.0.0.1 port=5432 user=postgres password=postgres';
    static private PDO $pdo;
    static private string $xml;

    static public function setUpBeforeClass(): void {
        self::$pdo = new \PDO(self::$connSettings);
        self::$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        self::$pdo->beginTransaction();
        self::$xml = file_get_contents(__DIR__ . '/testtext-schema.xml');
    }

    public static function tearDownAfterClass(): void {
        self::$pdo->rollback();
    }

    public function testLoadNoFile(): void {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("no such file is not a valid file");
        $s = new Schema(self::$pdo);
        $s->loadFile('no such file');
    }

    public function testNoTokenXPath(): void {
        $this->expectException(\LengthException::class);
        $this->expectExceptionMessage("exactly one tokenXPath has to be provided");
        $s = new Schema(self::$pdo);
        $s->loadXML(str_replace('<tokenXPath>//tei:w</tokenXPath>', '', self::$xml));
    }

    public function testManyTokenXPaths(): void {
        $this->expectException(\LengthException::class);
        $this->expectExceptionMessage("exactly one tokenXPath has to be provided");
        $s = new Schema(self::$pdo);
        $s->loadXML(str_replace('<tokenXPath>//tei:w</tokenXPath>', '<tokenXPath>//tei:w</tokenXPath><tokenXPath>//tei:w</tokenXPath>', self::$xml));
    }

    public function testManyTokenValueXPaths(): void {
        $this->expectException(\LengthException::class);
        $this->expectExceptionMessage("exactly one tokenValueXPath has to be provided");
        $s = new Schema(self::$pdo);
        $s->loadXML(str_replace('<tokenValueXPath>.</tokenValueXPath>', '<tokenValueXPath>.</tokenValueXPath><tokenValueXPath>.</tokenValueXPath>', self::$xml));
    }

    public function testNoProperties(): void {
        $this->expectException(\LengthException::class);
        $this->expectExceptionMessage("no token properties defined");
        $s = new Schema(self::$pdo);
        $s->loadXML(str_replace('properties', 'aaa', self::$xml));
    }

    public function testDuplicateProperties(): void {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("property names are not unique");
        $s = new Schema(self::$pdo);
        $s->loadXML(preg_replace('|<propertyName>[a-zA-Z]+</propertyName>|', '<propertyName>lemma</propertyName>', self::$xml));
    }

    public function testPropertyLoadDb(): void {
        self::$pdo->query("INSERT INTO documents (document_id, token_xpath, name, save_path, hash) VALUES (1, '', '', '', '')");
        self::$pdo->query("
            INSERT INTO properties (document_id, property_xpath, type_id, name, ord, read_only, optional, attributes) 
            VALUES (1, '/foo', 'closed list', 'bar', 1, false, true, '{\"propertyValues\": [{\"value\": \"foo\"}, {\"value\": \"bar\"}], \"baseUrl\": \"https://foo.bar?a=x&b=y\"}')
        ");
        $s = new Schema(self::$pdo);
        $s->loadDb(1);
        $n = -1;
        foreach ($s as $n => $p) {
            /* @var $p Property */
            $this->assertEquals('/foo', $p->getXPath());
            $this->assertEquals('closed list', $p->getType());
            $this->assertEquals('bar', $p->getName());
            $this->assertEquals(1, $p->getOrd());
            $this->assertEquals(false, $p->getReadOnly());
            $this->assertEquals(true, $p->getOptional());

            $this->assertEquals((object) [
                    'baseUrl'        => 'https://foo.bar?a=x&b=y',
                    'propertyValues' => [
                        (object) ['value' => 'foo'],
                        (object) ['value' => 'bar']
                    ]
                ], $p->getAttributes());
        }
        $this->assertEquals(0, $n);
    }

}
