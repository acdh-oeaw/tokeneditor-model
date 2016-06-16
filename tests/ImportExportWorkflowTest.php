<?php

namespace model;

require_once 'util/ClassLoader.php';
new \util\ClassLoader('php/src');

/**
 * Description of ImportExportWorkflowTest
 *
 * @author zozlak
 */
class ImportExportWorkflowTest extends \PHPUnit_Framework_TestCase {
	static private $saveDir = 'php/docStorage';
	static private $connSettings = 'pgsql: dbname=tokeneditor';
	static private $PDO;
	static private $validInPlace = <<<RES
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<TEI xmlns="http://www.tei-c.org/ns/1.0"><!--sample comment--><teiHeader><fileDesc><titleStmt><title>testtext</title></titleStmt><publicationStmt><p/></publicationStmt><sourceDesc/></fileDesc></teiHeader><text><body><w xmlns="http://www.tei-c.org/ns/1.0" id="w1" lemma="aaa">Hello<type>bbb</type></w><w xmlns="http://www.tei-c.org/ns/1.0" id="w2" lemma="ccc">World<type>ddd</type></w><w xmlns="http://www.tei-c.org/ns/1.0" id="w3" lemma="eee">!<type>fff</type></w></body></text></TEI>
RES;
	static private $validFull = <<<RES
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<TEI xmlns="http://www.tei-c.org/ns/1.0"><!--sample comment--><teiHeader><fileDesc><titleStmt><title>testtext</title></titleStmt><publicationStmt><p/></publicationStmt><sourceDesc/></fileDesc></teiHeader><text><body><w xmlns="http://www.tei-c.org/ns/1.0" id="w1" lemma="Hello">Hello<type>NE<fs type="tokeneditor"><f name="user"><string>test</string></f><f name="date"><string>%DATE</string></f><f name="property_xpath"><string>./tei:type</string></f><f name="value"><string>bbb</string></f></fs></type><fs type="tokeneditor"><f name="user"><string>test</string></f><f name="date"><string>%DATE</string></f><f name="property_xpath"><string>@lemma</string></f><f name="value"><string>aaa</string></f></fs></w><w xmlns="http://www.tei-c.org/ns/1.0" id="w2" lemma="World">World<type>NN<fs type="tokeneditor"><f name="user"><string>test</string></f><f name="date"><string>%DATE</string></f><f name="property_xpath"><string>./tei:type</string></f><f name="value"><string>ddd</string></f></fs></type><fs type="tokeneditor"><f name="user"><string>test</string></f><f name="date"><string>%DATE</string></f><f name="property_xpath"><string>@lemma</string></f><f name="value"><string>ccc</string></f></fs></w><w xmlns="http://www.tei-c.org/ns/1.0" id="w3" lemma="!">!<type>$.<fs type="tokeneditor"><f name="user"><string>test</string></f><f name="date"><string>%DATE</string></f><f name="property_xpath"><string>./tei:type</string></f><f name="value"><string>fff</string></f></fs></type><fs type="tokeneditor"><f name="user"><string>test</string></f><f name="date"><string>%DATE</string></f><f name="property_xpath"><string>@lemma</string></f><f name="value"><string>eee</string></f></fs></w></body></text></TEI>
RES;

	private $docsToClean = array();
	
	static public function setUpBeforeClass() {
		self::$PDO = new \PDO(self::$connSettings);
		self::$PDO->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		self::$PDO->beginTransaction();
		self::$PDO->query("TRUNCATE documents CASCADE");
		self::$PDO->query("TRUNCATE users CASCADE");
		self::$PDO->query("INSERT INTO users VALUES ('test')");
		self::$validFull = str_replace('%DATE', date('Y-m-d'), self::$validFull);
	}
	
	public static function tearDownAfterClass(){
		self::$PDO->rollback();
		unlink('tmp.xml');
	}
	
	protected function setUp() {
		parent::setUp();
	}
	
	protected function tearDown() {
		parent::tearDown();
		foreach($this->docsToClean as $i){
			unlink(self::$saveDir . '/' . $i . '.xml');
		}
	}
	
	protected function insertValues($docId){
		$query = self::$PDO->prepare("INSERT INTO documents_users VALUES (?, 'test')");
		$query->execute(array($docId));
		$query = self::$PDO->prepare("
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

	protected function checkImport($docId){
		$query = self::$PDO->prepare("SELECT count(*) FROM orig_values WHERE document_id = ?");
		$query->execute(array($docId));
		$this->assertEquals(6, $query->fetch(\PDO::FETCH_COLUMN));
	}
	
	public function testDefaultInPlace(){
		$doc = new Document(self::$PDO);
		$doc->loadFile('sample_data/testtext.xml', 'sample_data/testtext-schema.xml', 'test');
		$doc->save(self::$saveDir);
		$docId = $doc->getId();
		$this->docsToClean[] = $docId;
		
		$this->checkImport($docId);
		$this->insertValues($docId);
		
		$doc = new Document(self::$PDO);
		$doc->loadDb($docId);
		$doc->export(true, 'tmp.xml');
		$this->assertEquals(trim(self::$validInPlace), trim(file_get_contents('tmp.xml')));
	}
	
	public function testDefaultFull(){
		$doc = new Document(self::$PDO);
		$doc->loadFile('sample_data/testtext.xml', 'sample_data/testtext-schema.xml', 'test');
		$doc->save(self::$saveDir);
		$docId = $doc->getId();
		$this->docsToClean[] = $docId;

		$this->checkImport($docId);
		$this->insertValues($docId);
		
		$doc = new Document(self::$PDO);
		$doc->loadDb($docId);
		$result = trim($doc->export());
		$date = date('Y-m-d');
		$result = preg_replace('/<string>' . $date . '[0-9 :.]+/', '<string>' . $date, $result);
		$this->assertEquals(trim(self::$validFull), $result);
	}

	public function testXMLReader(){
		$doc = new Document(self::$PDO);
		$doc->loadFile('sample_data/testtext.xml', 'sample_data/testtext-schema.xml', 'test', Document::XML_READER);
		$doc->save(self::$saveDir);
		$docId = $doc->getId();
		$this->docsToClean[] = $docId;
		
		$this->checkImport($docId);
		$this->insertValues($docId);
		
		$doc = new Document(self::$PDO);
		$doc->loadDb($docId, Document::XML_READER);
		$this->assertEquals(trim(self::$validInPlace), trim($doc->export(true)));
	}

	public function testPDO(){
		$doc = new Document(self::$PDO);
		$doc->loadFile('sample_data/testtext.xml', 'sample_data/testtext-schema.xml', 'test', Document::PDO);
		$doc->save(self::$saveDir);
		$docId = $doc->getId();
		$this->docsToClean[] = $docId;
		
		$this->checkImport($docId);
		$this->insertValues($docId);
		
		$doc = new Document(self::$PDO);
		$doc->loadDb($docId);
		$this->assertEquals(trim(self::$validInPlace), trim($doc->export(true)));
	}
	
	public function testDOMDocument(){
		$doc = new Document(self::$PDO);
		$doc->loadFile('sample_data/testtext.xml', 'sample_data/testtext-schema.xml', 'test', Document::DOM_DOCUMENT);
		$doc->save(self::$saveDir);
		$docId = $doc->getId();
		$this->docsToClean[] = $docId;
		
		$this->checkImport($docId);
		$this->insertValues($docId);
		
		$doc = new Document(self::$PDO);
		$doc->loadDb($docId, Document::DOM_DOCUMENT);
		$valid = trim(str_replace('<w xmlns="http://www.tei-c.org/ns/1.0"', '<w', self::$validInPlace));
		$this->assertEquals($valid, trim($doc->export(true)));
	}	
}
