<?php

/**
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
 * Description of TokenCollectionTest
 *
 * @author zozlak
 */
class TokenCollectionTest extends \PHPUnit\Framework\TestCase {

    static private $saveDir      = 'build';
    static private $connSettings = 'pgsql: dbname=tokeneditor';
    static private $pdo;
    static private $docId;

    static public function setUpBeforeClass() {
        self::$pdo = new \PDO(self::$connSettings);
        self::$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        self::$pdo->beginTransaction();
        self::$pdo->query("TRUNCATE documents CASCADE");
        self::$pdo->query("TRUNCATE users CASCADE");
        self::$pdo->query("INSERT INTO users VALUES ('test'), ('test2')");

        $doc         = new Document(self::$pdo);
        $doc->loadFile('tests/testtext.xml', 'tests/testtext-schema.xml', 'test');
        $doc->save(self::$saveDir);
        self::$docId = $doc->getId();

        $query = self::$pdo->prepare("INSERT INTO documents_users VALUES (?, 'test', 'owner'), (?, 'test2', 'editor')");
        $query->execute(array(self::$docId, self::$docId));
        $query = self::$pdo->prepare("
			INSERT INTO values (document_id, property_xpath, token_id, user_id, value, date) 
			SELECT document_id, property_xpath, token_id, 'test', ?, now() FROM orig_values WHERE document_id = ? AND property_xpath = ? AND token_id = ?
		");
        $query->execute(array('aaa', self::$docId, '@lemma', 1));
        $query->execute(array('bbb', self::$docId, './tei:type', 1));
        $query->execute(array('ccc', self::$docId, '@lemma', 2));
        $query->execute(array('ddd', self::$docId, './tei:type', 2));
        $query->execute(array('eee', self::$docId, '@lemma', 3));
        $query->execute(array('ddd', self::$docId, './tei:type', 3));
        $query = self::$pdo->prepare("
			INSERT INTO values (document_id, property_xpath, token_id, user_id, value, date) 
			SELECT document_id, property_xpath, token_id, 'test2', ?, '1900-01-01 00:00:00' FROM orig_values WHERE document_id = ? AND property_xpath = ? AND token_id = ?
		");
        $query->execute(array('zzz', self::$docId, './tei:type', 3));
    }

    public static function tearDownAfterClass() {
        self::$pdo->rollback();
        $path = self::$saveDir . '/' . self::$docId . '.xml';
        if (file_exists($path)) {
            unlink($path);
        }
    }

    protected function setUp() {
        parent::setUp();
    }

    protected function tearDown() {
        parent::tearDown();
    }

    public function testGetStats() {
        $collection = new TokenCollection(self::$pdo, self::$docId, 'test');

        $this->assertEquals($collection->getStats('@lemma'), '[{"value" : "aaa", "count" : 1}, {"value" : "ccc", "count" : 1}, {"value" : "eee", "count" : 1}]');

        // getStats() doesn't skips filters
        $collection->setTokenIdFilter(2);
        $collection->addFilter('@lemma', 'fff');
        $this->assertEquals('[{"value" : "aaa", "count" : 1}, {"value" : "ccc", "count" : 1}, {"value" : "eee", "count" : 1}]', $collection->getStats('@lemma'));
    }

    public function testNoFilters() {
        $collection = new TokenCollection(self::$pdo, self::$docId, 'test');
        $this->assertEquals('{"tokenCount" : 3, "data" : [{"tokenId" : "1", "token" : "Hello<type>NE</type>", "lemma" : "aaa", "type" : "bbb"}, {"tokenId" : "2", "token" : "World<type>NN</type>", "lemma" : "ccc", "type" : "ddd"}, {"tokenId" : "3", "token" : "!<type>$.</type>", "lemma" : "eee", "type" : "ddd"}]}', $collection->getData());
        $this->assertEquals('{"tokenCount" : 3, "data" : [{"tokenId" : "1"}, {"tokenId" : "2"}, {"tokenId" : "3"}]}', $collection->getTokensOnly());
    }

    public function testTokenIdFilter() {
        $collection = new TokenCollection(self::$pdo, self::$docId, 'test');
        $collection->setTokenIdFilter(2);
        $this->assertEquals($collection->getData(), '{"tokenCount" : 1, "data" : [{"tokenId" : "2", "token" : "World<type>NN</type>", "lemma" : "ccc", "type" : "ddd"}]}');
        $this->assertEquals('{"tokenCount" : 1, "data" : [{"tokenId" : "2"}]}', $collection->getTokensOnly());
    }

    public function testFilter() {
        $collection = new TokenCollection(self::$pdo, self::$docId, 'test');
        $collection->addFilter('lemma', 'eee');
        $this->assertEquals('{"tokenCount" : 1, "data" : [{"tokenId" : "3", "token" : "!<type>$.</type>", "lemma" : "eee", "type" : "ddd"}]}', $collection->getData());
        $this->assertEquals('{"tokenCount" : 1, "data" : [{"tokenId" : "3"}]}', $collection->getTokensOnly());
    }

    public function testFilterAll() {
        $collection = new TokenCollection(self::$pdo, self::$docId, 'test');
        $collection->setTokenIdFilter(3);
        $collection->addFilter('type', 'ddd');
        $this->assertEquals('{"tokenCount" : 1, "data" : [{"tokenId" : "3", "token" : "!<type>$.</type>", "lemma" : "eee", "type" : "ddd"}]}', $collection->getData());
        $this->assertEquals('{"tokenCount" : 1, "data" : [{"tokenId" : "3"}]}', $collection->getTokensOnly());
    }

    public function testFilterNone() {
        $collection = new TokenCollection(self::$pdo, self::$docId, 'test');
        $collection->setTokenIdFilter(2);
        $collection->addFilter('lemma', 'eee');
        $this->assertEquals('{"tokenCount" : 0, "data" : []}', $collection->getData());
        $this->assertEquals('{"tokenCount" : 0, "data" : []}', $collection->getTokensOnly());
    }

    public function testFilterNonExistent() {
        $collection = new TokenCollection(self::$pdo, self::$docId, 'test');
        $collection->setTokenIdFilter(3);
        $collection->addFilter('lemma', 'eee');
        $collection->addFilter('xxx', 'eee'); // non-existent property
        $this->assertEquals('{"tokenCount" : 1, "data" : [{"tokenId" : "3", "token" : "!<type>$.</type>", "lemma" : "eee", "type" : "ddd"}]}', $collection->getData());
        $this->assertEquals('{"tokenCount" : 1, "data" : [{"tokenId" : "3"}]}', $collection->getTokensOnly());
    }

    public function testSort() {
        $collection = new TokenCollection(self::$pdo, self::$docId, 'test');
        $collection->setSorting(['-type', 'lemma']);
        $this->assertEquals('{"tokenCount" : 3, "data" : [{"tokenId" : "2", "token" : "World<type>NN</type>", "lemma" : "ccc", "type" : "ddd"}, {"tokenId" : "3", "token" : "!<type>$.</type>", "lemma" : "eee", "type" : "ddd"}, {"tokenId" : "1", "token" : "Hello<type>NE</type>", "lemma" : "aaa", "type" : "bbb"}]}', $collection->getData());
        $this->assertEquals('{"tokenCount" : 3, "data" : [{"tokenId" : "2"}, {"tokenId" : "3"}, {"tokenId" : "1"}]}', $collection->getTokensOnly());
    }

    public function testFilterSort() {
        $collection = new TokenCollection(self::$pdo, self::$docId, 'test');
        $collection->addFilter('type', 'ddd');
        $collection->setSorting(['-lemma']);
        $this->assertEquals('{"tokenCount" : 2, "data" : [{"tokenId" : "3", "token" : "!<type>$.</type>", "lemma" : "eee", "type" : "ddd"}, {"tokenId" : "2", "token" : "World<type>NN</type>", "lemma" : "ccc", "type" : "ddd"}]}', $collection->getData());
        $this->assertEquals('{"tokenCount" : 2, "data" : [{"tokenId" : "3"}, {"tokenId" : "2"}]}', $collection->getTokensOnly());
    }

}
