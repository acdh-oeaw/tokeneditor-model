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
 * Description of DocumentTest
 *
 * @author zozlak
 */
class DocumentTest extends \PHPUnit\Framework\TestCase {

    static private $saveDir      = 'build';
    static private $connSettings = 'pgsql: dbname=tokeneditor';
    static private $pdo;

    static public function setUpBeforeClass() {
        self::$pdo = new \PDO(self::$connSettings);
        self::$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        self::$pdo->beginTransaction();
        self::$pdo->query("TRUNCATE documents CASCADE");
    }

    public static function tearDownAfterClass() {
        self::$pdo->rollback();
    }

    public function testNoSuchFile() {
        $this->expectException(\RuntimeException::class);
        $d = new Document(self::$pdo);
        $d->loadFile('no such file', __DIR__ . '/testtext-schema.xml', 'test name');
    }

    public function testWrongHash() {
        $this->expectException(\UnexpectedValueException::class);
        self::$pdo->query("INSERT INTO documents (document_id, token_xpath, name, save_path, hash) VALUES (0, '//w', 'name', 'tests/testtext.xml', 'wrong hash')");
        self::$pdo->query("INSERT INTO properties (document_id, property_xpath, type_id, name, read_only, ord) VALUES (0, '.', 'free text', 'prop name', true, 1)");
        $d = new Document(self::$pdo);
        $d->loadDb(0);
    }

    public function testWrongIteratorClass1() {
        $this->expectException(\InvalidArgumentException::class);
        $d = new Document(self::$pdo);
        $d->loadFile(__DIR__ . '/testtext.xml', __DIR__ . '/testtext-schema.xml', 'test name', 'no such class');
    }

    public function testWrongIteratorClass2() {
        $this->expectException(\InvalidArgumentException::class);
        $d   = new Document(self::$pdo);
        $d->loadFile('tests/testtext.xml', 'tests/testtext-schema.xml', 'test');
        $d->save(self::$saveDir);
        $d->loadDb($d->getId(), 'wrong class');
    }
    
    public function testPropertiesMissingInData() {
        $this->expectException(\RuntimeException::class);
        $d   = new Document(self::$pdo);
        $xml = file_get_contents(__DIR__ . '/testtext.xml');
        $xml = str_replace('<type>NN</type>', '', $xml);
        file_put_contents(self::$saveDir . '/tmp.xml', $xml);
        $d->loadFile('tests/testtext.xml', '/tmp/tmp.xml', 'test');
        $d->save(self::$saveDir);
    }

    public function testGetName() {
        $d   = new Document(self::$pdo);
        $d->loadFile('tests/testtext.xml', 'tests/testtext-schema.xml', 'test doc');
        $d->save(self::$saveDir);
        $this->assertEquals('test doc', $d->getName());
    }
}