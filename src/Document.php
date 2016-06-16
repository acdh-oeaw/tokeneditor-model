<?php

/*
 * Copyright (C) 2015 ACDH
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace model;

/**
 * Description of Datafile
 *
 * @author zozlak
 */
class Document implements \IteratorAggregate {
	const DOM_DOCUMENT = '\model\tokenIterator\DOMDocument';
	const XML_READER = '\model\tokenIterator\XMLReader';
	const PDO = '\model\tokenIterator\PDO';

	private $path;
	private $name;
	private $schema;
	private $PDO;
	private $tokenIteratorClassName;
	private $tokenIterator;
	private $exportFlag;

	private $documentId;
	private $tokenId = 0;
	
	/**
	 * 
	 * @param type $path
	 * @param \model\Schema $schema
	 * @throws \RuntimeException
	 */
	public function __construct(\PDO $PDO) {
		$this->PDO = $PDO;
		$this->schema = new Schema($this->PDO);
	}
	
	public function loadFile($filePath, $schemaPath, $name, $iteratorClass = null){
		if(!is_file($filePath)){
			throw new \RuntimeException($filePath . ' is not a valid file');
		}
		$this->path = $filePath;
		$this->name = $name;
		$this->schema->loadFile($schemaPath);
		$this->chooseTokenIterator();
		
		if($iteratorClass === null){
			$this->chooseTokenIterator();
		}else{
			if(!in_array($iteratorClass, array(self::DOM_DOCUMENT, self::XML_READER, self::PDO))){
				throw new \InvalidArgumentException('tokenIteratorClass should be one of \model\Datafile::DOM_DOCUMENT, \model\Datafile::XML_READER or \model\Datafile::PDO');
			}
			$this->tokenIteratorClassName = $iteratorClass;
		}
	}
	
	public function loadDb($documentId, $iteratorClass = null){
		$this->documentId = $documentId;
		$this->schema->loadDb($this->documentId);
		
		$query = $this->PDO->prepare("SELECT name, save_path, hash FROM documents WHERE document_id = ?");
		$query->execute(array($this->documentId));
		$data = $query->fetch(\PDO::FETCH_OBJ);
		$this->name = $data->name;
		$this->path = $data->save_path;
		
		$hash = md5_file($this->path);
		if($hash !== $data->hash){
			throw new \RuntimeException('Raw document XML file changed since import');
		}
		
		if($iteratorClass === null){
			$this->chooseTokenIterator();
		}else{
			if(!in_array($iteratorClass, array(self::DOM_DOCUMENT, self::XML_READER))){
				throw new \InvalidArgumentException('tokenIteratorClass should be one of \model\Datafile::DOM_DOCUMENT or \model\Datafile::XML_READER');
			}
			$this->tokenIteratorClassName = $iteratorClass;
		}
	}
	
	/**
	 * 
	 * @return integer
	 */
	public function getId(){
		return $this->documentId;
	}
	
	/**
	 * 
	 * @return Schema
	 */
	public function getSchema(){
		return $this->schema;
	}
	
	/**
	 * 
	 * @return type
	 */
	public function getName(){
		return $this->name;
	}
	
	/**
	 * 
	 * @return PDO
	 */
	public function getPDO(){
		return $this->PDO;
	}
	
	/**
	 * 
	 * @return integer
	 */
	public function generateTokenId(){
		$this->tokenId++;
		return $this->tokenId;
	}

	/**
	 * 
	 * @param string $saveDir
	 * @param int $limit
	 * @param \util\ProgressBar $progressBar
	 * @param bool $skipErrors
	 * @return int number of proccessed tokens
	 */
	public function save($saveDir, $limit = 0, $progressBar = null, $skipErrors = false){
		$this->documentId = $this->PDO->
			query("SELECT nextval('document_id_seq')")->
			fetchColumn();
		
		$savePath = $saveDir . '/' . $this->documentId . '.xml';
		
		$query = $this->PDO->prepare("INSERT INTO documents (document_id, token_xpath, token_value_xpath, name, save_path, hash) VALUES (?, ?, ?, ?, ?, ?)");
		$query->execute(array($this->documentId, $this->schema->getTokenXPath(), $this->schema->getTokenValueXPath(), $this->name, $savePath, md5_file($this->path)));
		unset($query); // free memory
		
		$this->schema->save($this->documentId);
		
		$nn = 0;
		foreach($this as $n => $token){
			try{
				$token->save();
			}catch(\RuntimeException $e){
				if(!$skipErrors){
					throw $e;
				}
			}
			if($progressBar){
				$progressBar->next();
			}
			if($n > $limit && $limit > 0){
				break;
			}
			$nn = $n + 1;
		}
		
		copy($this->path, $savePath);
		return $nn;
	}
	
	/**
	 * 
	 * @param boolean $replace If true, changes will be made in-place 
	 *   (taking the most current value provided by usesrs as the right one). 
	 *   If false, review results will be provided as TEI <fs> elements
	 * @param string $path path to the file where document will be xported
	 * @param type $progressBar
	 */
	public function export($replace = false, $path = null, $progressBar = null){
		$this->exportFlag = true;
		if($replace){
			foreach($this as $token){
				$token->update();
				if($progressBar){
					$progressBar->next();
				}
			}
		}else{
			foreach($this as $token){
				$token->enrich();
				if($progressBar){
					$progressBar->next();
				}
			}
		}
		return $this->tokenIterator->export($path);
	}

	public function getIterator() {
		$this->tokenIterator = new $this->tokenIteratorClassName($this->path, $this, $this->exportFlag);
		$this->exportFlag = false;
		return $this->tokenIterator;
	}
	
	/**
	 * 
	 * @return \model\tokenIterator\TokenInterator
	 */
	public function getTokenIterator(){
		return $this->tokenIterator;
	}

	private function chooseTokenIterator() {
		try{
			new tokenIterator\XMLReader($this->path, $this);
			$this->tokenIteratorClassName = self::XML_READER;
		} catch (\RuntimeException $ex) {
			$this->tokenIteratorClassName = self::DOM_DOCUMENT;
		}
	}
}
