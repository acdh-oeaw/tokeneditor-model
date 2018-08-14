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

/**
 * Description of SchemaTest
 *
 * @author zozlak
 */
class SchemaTest extends \PHPUnit\Framework\TestCase {

    static private $connSettings = 'pgsql: dbname=tokeneditor';
    static private $pdo;
    static private $xml;

    static public function setUpBeforeClass() {
        self::$pdo = new \PDO(self::$connSettings);
        self::$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        self::$pdo->beginTransaction();
        self::$xml = file_get_contents(__DIR__ . '/testtext-schema.xml');
    }

    public static function tearDownAfterClass() {
        self::$pdo->rollback();
    }

    public function testLoadNoFile() {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("no such file is not a valid file");
        $s = new Schema(self::$pdo);
        $s->loadFile('no such file');
    }

    public function testNoTokenXPath() {
        $this->expectException(\LengthException::class);
        $this->expectExceptionMessage("exactly one tokenXPath has to be provided");
        $s = new Schema(self::$pdo);
        $s->loadXML(str_replace('<tokenXPath>//tei:w</tokenXPath>', '', self::$xml));
    }

    public function testManyTokenXPaths() {
        $this->expectException(\LengthException::class);
        $this->expectExceptionMessage("exactly one tokenXPath has to be provided");
        $s = new Schema(self::$pdo);
        $s->loadXML(str_replace('<tokenXPath>//tei:w</tokenXPath>', '<tokenXPath>//tei:w</tokenXPath><tokenXPath>//tei:w</tokenXPath>', self::$xml));
    }

    public function testManyTokenValueXPaths() {
        $this->expectException(\LengthException::class);
        $this->expectExceptionMessage("exactly one tokenValueXPath has to be provided");
        $s = new Schema(self::$pdo);
        $s->loadXML(str_replace('<tokenValueXPath>.</tokenValueXPath>', '<tokenValueXPath>.</tokenValueXPath><tokenValueXPath>.</tokenValueXPath>', self::$xml));
    }

    public function testNoProperties() {
        $this->expectException(\LengthException::class);
        $this->expectExceptionMessage("no token properties defined");
        $s = new Schema(self::$pdo);
        $s->loadXML(str_replace('properties', 'aaa', self::$xml));
    }

    public function testDuplicateProperties() {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("property names are not unique");
        $s = new Schema(self::$pdo);
        $s->loadXML(preg_replace('|<propertyName>[a-zA-Z]+</propertyName>|', '<propertyName>lemma</propertyName>', self::$xml));
    }
}
