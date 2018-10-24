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
    static private $connSettings = 'pgsql: dbname=tokeneditor';
    static private $pdo;
    static private $validInPlace = <<<RES
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<TEI xmlns="http://www.tei-c.org/ns/1.0"><!--sample comment--><teiHeader><fileDesc><titleStmt><title>testtext</title></titleStmt><publicationStmt><p/></publicationStmt><sourceDesc/></fileDesc></teiHeader><text><body><w xmlns="http://www.tei-c.org/ns/1.0" id="w1" lemma="aaa">Hello<type>bbb</type></w><w xmlns="http://www.tei-c.org/ns/1.0" id="w2" lemma="ccc">World<type>ddd</type></w><w xmlns="http://www.tei-c.org/ns/1.0" id="w3" lemma="eee">!<type>fff</type></w></body></text></TEI>
RES;
    static private $validFull    = <<<RES
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<TEI xmlns="http://www.tei-c.org/ns/1.0"><!--sample comment--><teiHeader><fileDesc><titleStmt><title>testtext</title></titleStmt><publicationStmt><p/></publicationStmt><sourceDesc/></fileDesc></teiHeader><text><body><w xmlns="http://www.tei-c.org/ns/1.0" id="w1" lemma="Hello">Hello<type>NE<fs type="tokeneditor"><f name="user"><string>test</string></f><f name="date"><string>%DATE</string></f><f name="property_xpath"><string>./tei:type</string></f><f name="value"><string>bbb</string></f></fs></type><fs type="tokeneditor"><f name="user"><string>test</string></f><f name="date"><string>%DATE</string></f><f name="property_xpath"><string>@lemma</string></f><f name="value"><string>aaa</string></f></fs></w><w xmlns="http://www.tei-c.org/ns/1.0" id="w2" lemma="World">World<type>NN<fs type="tokeneditor"><f name="user"><string>test</string></f><f name="date"><string>%DATE</string></f><f name="property_xpath"><string>./tei:type</string></f><f name="value"><string>ddd</string></f></fs></type><fs type="tokeneditor"><f name="user"><string>test</string></f><f name="date"><string>%DATE</string></f><f name="property_xpath"><string>@lemma</string></f><f name="value"><string>ccc</string></f></fs></w><w xmlns="http://www.tei-c.org/ns/1.0" id="w3" lemma="!">!<type>$.<fs type="tokeneditor"><f name="user"><string>test</string></f><f name="date"><string>%DATE</string></f><f name="property_xpath"><string>./tei:type</string></f><f name="value"><string>fff</string></f></fs></type><fs type="tokeneditor"><f name="user"><string>test</string></f><f name="date"><string>%DATE</string></f><f name="property_xpath"><string>@lemma</string></f><f name="value"><string>eee</string></f></fs></w></body></text></TEI>
RES;
    static private $date;
    private $docsToClean         = array();

    static public function setUpBeforeClass() {
        self::$pdo       = new \PDO(self::$connSettings);
        self::$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        self::$pdo->beginTransaction();
        self::$pdo->query("TRUNCATE documents CASCADE");
        self::$pdo->query("TRUNCATE users CASCADE");
        self::$pdo->query("INSERT INTO users VALUES ('test')");
        self::$date      = self::$pdo->query("SELECT now()::date")->fetchColumn();
        self::$validFull = str_replace('%DATE', self::$date, self::$validFull);
    }

    public static function tearDownAfterClass() {
        self::$pdo->rollback();
        if (file_exists('tmp.xml')) {
            unlink('tmp.xml');
        }
    }

    protected function setUp() {
        parent::setUp();
    }

    protected function tearDown() {
        parent::tearDown();
        foreach ($this->docsToClean as $i) {
            unlink(self::$saveDir . '/' . $i . '.xml');
        }
    }

    protected function insertValues($docId) {
        $query = self::$pdo->prepare("INSERT INTO documents_users VALUES (?, 'test', 'owner')");
        $query->execute(array($docId));
        $query = self::$pdo->prepare("
			INSERT INTO values (document_id, property_xpath, token_id, user_id, value, date) 
			SELECT document_id, property_xpath, token_id, 'test', ?, now() FROM orig_values WHERE document_id = ? AND property_xpath = ? AND token_id = ?
		");
        $query->execute(array('aaa', $docId, '@lemma', 1));
        $query->execute(array('bbb', $docId, './tei:type', 1));
        $query->execute(array('ccc', $docId, '@lemma', 2));
        $query->execute(array('ddd', $docId, './tei:type', 2));
        $query->execute(array('eee', $docId, '@lemma', 3));
        $query->execute(array('fff', $docId, './tei:type', 3));
    }

    protected function checkImport(int $docId, int $count = 9) {
        $query = self::$pdo->prepare("SELECT count(*) FROM orig_values WHERE document_id = ?");
        $query->execute(array($docId));
        $this->assertEquals($count, $query->fetch(\PDO::FETCH_COLUMN));
    }

    public function testDefaultInPlace() {
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

    public function testDefaultFull() {
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

    public function testXMLReader() {
        $doc                 = new Document(self::$pdo);
        $doc->loadFile('tests/testtext.xml', 'tests/testtext-schema.xml', 'test', Document::XML_READER);
        $doc->save(self::$saveDir);
        $docId               = $doc->getId();
        $this->docsToClean[] = $docId;

        $this->checkImport($docId);
        $this->insertValues($docId);

        $doc = new Document(self::$pdo);
        $doc->loadDb($docId, Document::XML_READER);
        $this->assertEquals(trim(self::$validInPlace), trim($doc->export(true)));
    }

    /* Does not work in Travis, as Travis Postgresql does not include parent nodes namespaces in the xpath() results (sic!) */

    public function testPDO() {
        $doc                 = new Document(self::$pdo);
        $doc->loadFile('tests/testtext.xml', 'tests/testtext-schema.xml', 'test', Document::PDO);
        $doc->save(self::$saveDir);
        $docId               = $doc->getId();
        $this->docsToClean[] = $docId;

        $this->checkImport($docId);
        $this->insertValues($docId);

        $doc = new Document(self::$pdo);
        $doc->loadDb($docId);
        $this->assertEquals(trim(self::$validInPlace), trim($doc->export(true)));
    }

    public function testDOMDocument() {
        $doc                 = new Document(self::$pdo);
        $doc->loadFile('tests/testtext.xml', 'tests/testtext-schema.xml', 'test', Document::DOM_DOCUMENT);
        $doc->save(self::$saveDir);
        $docId               = $doc->getId();
        $this->docsToClean[] = $docId;

        $this->checkImport($docId);
        $this->insertValues($docId);

        $doc   = new Document(self::$pdo);
        $doc->loadDb($docId, Document::DOM_DOCUMENT);
        $valid = trim(str_replace('<w xmlns="http://www.tei-c.org/ns/1.0"', '<w', self::$validInPlace));
        $this->assertEquals($valid, trim($doc->export(true)));
    }

    public function testCsvExport() {
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
        $doc->exportCsv($file);
        $this->assertEquals('tokenId,token,lemma,type
1,Hello<type>NE</type>,aaa,bbb
2,World<type>NN</type>,ccc,ddd
3,!<type>$.</type>,eee,fff
', file_get_contents($file));
    }

    public function testCsvExportOptional() {
        $doc                 = new Document(self::$pdo);
        $doc->loadFile('tests/testtext_optional.xml', 'tests/testtext-schema.xml', 'test');
        $doc->save(self::$saveDir);
        $docId               = $doc->getId();
        $this->docsToClean[] = $docId;

        $this->checkImport($docId, 8);
        $this->insertValues($docId);

        $doc                 = new Document(self::$pdo);
        $doc->loadDb($docId);
        $this->docsToClean[] = 'csv';
        $file                = self::$saveDir . '/csv.xml';
        $doc->exportCsv($file);
        $this->assertEquals('tokenId,token,lemma,type
1,Hello<type>NE</type>,aaa,bbb
2,World<type>NN</type>,ccc,ddd
3,!<type>$.</type>,,fff
', file_get_contents($file));
    }

}
