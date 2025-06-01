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
 * Description of ImportExportWorkflowTest
 *
 * @author zozlak
 */
class ImportExportWorkflowTest extends \PHPUnit\Framework\TestCase {

    static private $saveDir      = 'build';
    static private $connSettings = 'pgsql: host=127.0.0.1 port=5432 user=postgres password=postgres';
    static private $pdo;
    static private $validInPlace = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>' . "\n" .
        '<TEI xmlns="http://www.tei-c.org/ns/1.0" xmlns:foo="http://foo"><!--sample comment--><teiHeader><fileDesc><titleStmt><title>testtext</title></titleStmt><publicationStmt><p/></publicationStmt><sourceDesc/></fileDesc></teiHeader><text><body>' .
        '<w xmlns="http://www.tei-c.org/ns/1.0" xmlns:foo="http://foo" id="w1" lemma="aaa">Hello<type>bbb</type><txml>k<l>m</l>o<foo:n>p</foo:n>r</txml></w>' .
        '<w xmlns="http://www.tei-c.org/ns/1.0" id="w2" lemma="ccc">World<type>ddd</type><txml>a<b>c</b>d</txml></w>' .
        '<w xmlns="http://www.tei-c.org/ns/1.0" id="w3" lemma="eee">!<type>fff</type><txml>k<l>m</l>n</txml></w>' .
        '</body></text></TEI>';
    static private $validFull    = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>' . "\n" .
        '<TEI xmlns="http://www.tei-c.org/ns/1.0" xmlns:foo="http://foo"><!--sample comment--><teiHeader><fileDesc><titleStmt><title>testtext</title></titleStmt><publicationStmt><p/></publicationStmt><sourceDesc/></fileDesc></teiHeader><text><body>' .
        '<w xmlns="http://www.tei-c.org/ns/1.0" xmlns:foo="http://foo" id="w1" lemma="Hello">Hello<type>NE<fs type="tokeneditor"><f name="user"><string>test</string></f><f name="date"><string>%DATE</string></f><f name="property_xpath"><string>./tei:type</string></f><f name="value"><string>bbb</string></f></fs></type><txml>a<foo:b>c</foo:b>d<fs type="tokeneditor"><f name="user"><string>test</string></f><f name="date"><string>%DATE</string></f><f name="property_xpath"><string>./tei:txml</string></f><f xmlns:foo="http://foo" xmlns="http://www.tei-c.org/ns/1.0" name="value">k<l>m</l>o<foo:n>p</foo:n>r</f></fs></txml><fs type="tokeneditor"><f name="user"><string>test</string></f><f name="date"><string>%DATE</string></f><f name="property_xpath"><string>@lemma</string></f><f name="value"><string>aaa</string></f></fs></w>' .
        '<w xmlns="http://www.tei-c.org/ns/1.0" id="w2" lemma="World">World<type>NN<fs type="tokeneditor"><f name="user"><string>test</string></f><f name="date"><string>%DATE</string></f><f name="property_xpath"><string>./tei:type</string></f><f name="value"><string>ddd</string></f></fs></type><txml>a<b>c</b>d</txml><fs type="tokeneditor"><f name="user"><string>test</string></f><f name="date"><string>%DATE</string></f><f name="property_xpath"><string>@lemma</string></f><f name="value"><string>ccc</string></f></fs></w>' .
        '<w xmlns="http://www.tei-c.org/ns/1.0" id="w3" lemma="!">!<type>$.<fs type="tokeneditor"><f name="user"><string>test</string></f><f name="date"><string>%DATE</string></f><f name="property_xpath"><string>./tei:type</string></f><f name="value"><string>fff</string></f></fs></type><txml>a<b>c</b>d<fs type="tokeneditor"><f name="user"><string>test</string></f><f name="date"><string>%DATE</string></f><f name="property_xpath"><string>./tei:txml</string></f><f xmlns="http://www.tei-c.org/ns/1.0" name="value">k<l>m</l>n</f></fs></txml><fs type="tokeneditor"><f name="user"><string>test</string></f><f name="date"><string>%DATE</string></f><f name="property_xpath"><string>@lemma</string></f><f name="value"><string>eee</string></f></fs></w>' .
        '</body></text></TEI>';
    static private $date;
    private $docsToClean         = [];

    static public function setUpBeforeClass(): void {
        self::$pdo       = new \PDO(self::$connSettings);
        self::$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        self::$pdo->beginTransaction();
        self::$pdo->query("TRUNCATE documents CASCADE");
        self::$pdo->query("TRUNCATE users CASCADE");
        self::$pdo->query("INSERT INTO users VALUES ('test')");
        self::$date      = self::$pdo->query("SELECT now()::date")->fetchColumn();
        self::$validFull = str_replace('%DATE', self::$date, self::$validFull);
    }

    public static function tearDownAfterClass(): void {
        self::$pdo->rollback();
        if (file_exists('tmp.xml')) {
            unlink('tmp.xml');
        }
    }

    protected function setUp(): void {
        parent::setUp();
    }

    protected function tearDown(): void {
        parent::tearDown();
        foreach ($this->docsToClean as $i) {
            unlink(self::$saveDir . '/' . $i . '.xml');
        }
    }

    protected function insertValues($docId): void {
        $query = self::$pdo->prepare("INSERT INTO documents_users VALUES (?, 'test', 'owner')");
        $query->execute(array($docId));
        $query = self::$pdo->prepare("
			INSERT INTO values (document_id, property_xpath, token_id, user_id, value, date) 
			SELECT document_id, property_xpath, token_id, 'test', ?, now() FROM orig_values WHERE document_id = ? AND property_xpath = ? AND token_id = ?
		");
        $query->execute(['aaa', $docId, '@lemma', 1]);
        $query->execute(['bbb', $docId, './tei:type', 1]);
        $query->execute(['k<l>m</l>o<foo:n>p</foo:n>r', $docId, './tei:txml', 1]);
        $query->execute(['ccc', $docId, '@lemma', 2]);
        $query->execute(['ddd', $docId, './tei:type', 2]);
        $query->execute(['eee', $docId, '@lemma', 3]);
        $query->execute(['fff', $docId, './tei:type', 3]);
        $query->execute(['k<l>m</l>n', $docId, './tei:txml', 3]);
    }

    protected function checkImport(int $docId, int $count = 12): void {
        $query = self::$pdo->prepare("SELECT count(*) FROM orig_values WHERE document_id = ?");
        $query->execute(array($docId));
        $this->assertEquals($count, $query->fetch(\PDO::FETCH_COLUMN));
    }

    public function testDefaultInPlace(): void {
        $doc                 = new Document(self::$pdo);
        $doc->loadFile('tests/testtext.xml', 'tests/testtext-schema.xml', 'test');
        $doc->save(self::$saveDir);
        $docId               = $doc->getId();
        $this->docsToClean[] = $docId;

        $this->checkImport($docId);
        $this->insertValues($docId);

        $doc = new Document(self::$pdo);
        $doc->loadDb($docId);
        $doc->export(true, 'tmp.xml');
        $this->assertEquals(trim(self::$validInPlace), trim(file_get_contents('tmp.xml')));
    }

    public function testDefaultFull(): void {
        $doc                 = new Document(self::$pdo);
        $doc->loadFile('tests/testtext.xml', 'tests/testtext-schema.xml', 'test');
        $doc->save(self::$saveDir);
        $docId               = $doc->getId();
        $this->docsToClean[] = $docId;

        $this->checkImport($docId);
        $this->insertValues($docId);

        $doc    = new Document(self::$pdo);
        $doc->loadDb($docId);
        $result = trim($doc->export());
        $result = preg_replace('/<string>' . self::$date . '[0-9 :.]+/', '<string>' . self::$date, $result);
        $this->assertEquals(trim(self::$validFull), $result);
    }

    public function testXMLReader(): void {
        $doc                 = new Document(self::$pdo);
        $doc->loadFile('tests/testtext.xml', 'tests/testtext-schema.xml', 'test', \XMLReader::class);
        $doc->save(self::$saveDir);
        $docId               = $doc->getId();
        $this->docsToClean[] = $docId;

        $this->checkImport($docId);
        $this->insertValues($docId);

        $doc = new Document(self::$pdo);
        $doc->loadDb($docId, \XMLReader::class);
        $this->assertEquals(trim(self::$validInPlace), trim($doc->export(true)));
    }

    public function testPDO(): void {
        $doc                 = new Document(self::$pdo);
        $doc->loadFile('tests/testtext.xml', 'tests/testtext-schema.xml', 'test', \PDO::class);
        $doc->save(self::$saveDir);
        $docId               = $doc->getId();
        $this->docsToClean[] = $docId;

        $this->checkImport($docId);
        $this->insertValues($docId);

        $doc = new Document(self::$pdo);
        $doc->loadDb($docId);
        $this->assertEquals(trim(self::$validInPlace), trim($doc->export(true)));
    }

    public function testDOMDocument(): void {
        $doc                 = new Document(self::$pdo);
        $doc->loadFile('tests/testtext.xml', 'tests/testtext-schema.xml', 'test', \DOMDocument::class);

        $doc->save(self::$saveDir);
        $docId               = $doc->getId();
        $this->docsToClean[] = $docId;

        $this->checkImport($docId);
        $this->insertValues($docId);

        $doc   = new Document(self::$pdo);
        $doc->loadDb($docId, \DOMDocument::class);
        $valid = trim(preg_replace('|<w[^>]+ id|', '<w id', self::$validInPlace));
        $this->assertEquals($valid, trim($doc->export(true)));
    }

    public function testCsvExport(): void {
        $doc                 = new Document(self::$pdo);
        $doc->loadFile('tests/testtext.xml', 'tests/testtext-schema.xml', 'test');
        $doc->save(self::$saveDir);
        $docId               = $doc->getId();
        $this->docsToClean[] = $docId;

        $this->checkImport($docId);
        $this->insertValues($docId);

        $doc                 = new Document(self::$pdo);
        $doc->loadDb($docId);
        $this->docsToClean[] = 'csv';
        $file                = self::$saveDir . '/csv.xml';
        $formatter           = new ExportCsv($file, ';');
        $doc->exportTable($formatter);
        $this->assertEquals('tokenId;token;xml;lemma;type
1;Hello<type>NE</type><txml>a<foo:b>c</foo:b>d</txml>;k<l>m</l>o<foo:n>p</foo:n>r;aaa;bbb
2;World<type>NN</type><txml>a<b>c</b>d</txml>;a<b>c</b>d;ccc;ddd
3;!<type>$.</type><txml>a<b>c</b>d</txml>;k<l>m</l>n;eee;fff
', file_get_contents($file));
    }

    public function testCsvExportOptional(): void {
        $doc                 = new Document(self::$pdo);
        $doc->loadFile('tests/testtext_optional.xml', 'tests/testtext-schema.xml', 'test');
        $doc->save(self::$saveDir);
        $docId               = $doc->getId();
        $this->docsToClean[] = $docId;

        $this->checkImport($docId, 11);
        $this->insertValues($docId);

        $doc                 = new Document(self::$pdo);
        $doc->loadDb($docId);
        $this->docsToClean[] = 'csv';
        $file                = self::$saveDir . '/csv.xml';
        $formatter           = new ExportCsv($file);
        $doc->exportTable($formatter);
        $this->assertEquals('tokenId,token,xml,lemma,type
1,Hello<type>NE</type><txml>a<foo:b>c</foo:b>d</txml>,k<l>m</l>o<foo:n>p</foo:n>r,aaa,bbb
2,World<type>NN</type><txml>a<b>c</b>d</txml>,a<b>c</b>d,ccc,ddd
3,!<type>$.</type><txml>a<b>c</b>d</txml>,k<l>m</l>n,,fff
', file_get_contents($file));
    }

    public function testJsonExportInPlace(): void {
        $doc                 = new Document(self::$pdo);
        $doc->loadFile('tests/testtext.xml', 'tests/testtext-schema.xml', 'test');
        $doc->save(self::$saveDir);
        $docId               = $doc->getId();
        $this->docsToClean[] = $docId;

        $this->checkImport($docId);
        $this->insertValues($docId);

        $doc                 = new Document(self::$pdo);
        $doc->loadDb($docId);
        $this->docsToClean[] = 'csv';
        $file                = self::$saveDir . '/csv.xml';
        $formatter           = new ExportJson($file);
        $doc->exportTable($formatter);
        $this->assertEquals(
            '[' .
            '{"tokenId":1,"token":"Hello<type>NE<\/type><txml>a<foo:b>c<\/foo:b>d<\/txml>","xml":"k<l>m<\/l>o<foo:n>p<\/foo:n>r","lemma":"aaa","type":"bbb"},' .
            '{"tokenId":2,"token":"World<type>NN<\/type><txml>a<b>c<\/b>d<\/txml>","xml":"a<b>c<\/b>d","lemma":"ccc","type":"ddd"},' .
            '{"tokenId":3,"token":"!<type>$.<\/type><txml>a<b>c<\/b>d<\/txml>","xml":"k<l>m<\/l>n","lemma":"eee","type":"fff"}' .
            ']',
            file_get_contents($file)
        );
    }

    public function testJsonExportFull(): void {
        $doc                 = new Document(self::$pdo);
        $doc->loadFile('tests/testtext.xml', 'tests/testtext-schema.xml', 'test');
        $doc->save(self::$saveDir);
        $docId               = $doc->getId();
        $this->docsToClean[] = $docId;

        $this->checkImport($docId);
        $this->insertValues($docId);

        $doc                 = new Document(self::$pdo);
        $doc->loadDb($docId);
        $this->docsToClean[] = 'csv';

        $file      = self::$saveDir . '/csv.xml';
        $formatter = new ExportJson($file);
        $doc->exportTable($formatter, false);
        $result    = file_get_contents($file);
        $result    = preg_replace('/"date":"' . self::$date . '[0-9 :.]+/', '"date":"' . self::$date, $result);

        $validFull = '[' .
            '{"tokenId":1,"token":[{"value":"Hello<type>NE<\/type><txml>a<foo:b>c<\/foo:b>d<\/txml>","date":null,"userId":null}],"xml":[{"userId":"test","value":"k<l>m<\/l>o<foo:n>p<\/foo:n>r","date":"%DATE"},{"value":"a<foo:b>c<\/foo:b>d","date":null,"userId":null}],"lemma":[{"userId":"test","value":"aaa","date":"%DATE"},{"value":"Hello","date":null,"userId":null}],"type":[{"userId":"test","value":"bbb","date":"%DATE"},{"value":"NE","date":null,"userId":null}]},' .
            '{"tokenId":2,"token":[{"value":"World<type>NN<\/type><txml>a<b>c<\/b>d<\/txml>","date":null,"userId":null}],"xml":[{"value":"a<b>c<\/b>d","date":null,"userId":null}],"lemma":[{"userId":"test","value":"ccc","date":"%DATE"},{"value":"World","date":null,"userId":null}],"type":[{"userId":"test","value":"ddd","date":"%DATE"},{"value":"NN","date":null,"userId":null}]},' .
            '{"tokenId":3,"token":[{"value":"!<type>$.<\/type><txml>a<b>c<\/b>d<\/txml>","date":null,"userId":null}],"xml":[{"userId":"test","value":"k<l>m<\/l>n","date":"%DATE"},{"value":"a<b>c<\/b>d","date":null,"userId":null}],"lemma":[{"userId":"test","value":"eee","date":"%DATE"},{"value":"!","date":null,"userId":null}],"type":[{"userId":"test","value":"fff","date":"%DATE"},{"value":"$.","date":null,"userId":null}]}' .
            ']';
        $validFull = str_replace('%DATE', self::$date, $validFull);
        $this->assertEquals($validFull, $result);
    }

}
