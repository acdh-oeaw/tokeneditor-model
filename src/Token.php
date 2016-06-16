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
 * Description of Token
 *
 * @author zozlak
 */
class Token {
	/**
	 *
	 * @var \PDOStatement
	 */
	private static $valuesQuery = null;
	
	/**
	 *
	 * @var \DomElement
	 */
	private $dom;
	/**
	 *
	 * @var Document
	 */
	private $document;
	private $tokenId;
	/**
	 *
	 * @var type \DOMElement
	 */
	private $value;
	private $properties = array();
	private $invalidProperties = array();
	
	/**
	 * 
	 * @param \DOMElement $dom
	 * @param \model\Document $document
	 * @throws \LengthException
	 */
	public function __construct(\DOMElement $dom, Document $document){
		$this->dom = $dom;
		$this->document = $document;
		$this->tokenId = $this->document->generateTokenId();
		
		$xpath = new \DOMXPath($dom->ownerDocument);
		foreach($this->document->getSchema()->getNs() as $prefix => $ns){
			$xpath->registerNamespace($prefix, $ns);
		}
		
		$valueXPath = $this->document->getSchema()->getTokenValueXPath();
		if($valueXPath == ''){
			$this->value = $this->dom->nodeValue;
		}else{
			$value = $xpath->query($valueXPath, $this->dom);
			if($value->length != 1){
				throw new \LengthException('token has no value or more then one value');
			}else{
				$value = $value->item(0);
				$this->value = isset($value->value) ? $value->value : $this->innerXml($value);
			}
		}
		
		foreach($this->document->getSchema() as $prop){
			try{
				$value = $xpath->query($prop->getXPath(), $this->dom);
				if($value->length !== 1){
					throw new \LengthException('property not found or many properties found');
				}
				
				$this->properties[$prop->getXPath()] = $value->item(0);
			}catch (\LengthException $e){
				$this->invalidProperties[$prop->getXPath()] = $e->getMessage();
			}
		}
	}
	
	/**
	 * 
	 * @param \DOMNode $node
	 * @return string
	 */
	private function innerXml(\DOMNode $node){
		$out = '';
		for($i = 0; $i < $node->childNodes->length; $i++){
			$out .= $node->ownerDocument->saveXML($node->childNodes->item($i));
		}
		return $out;
	}
	
	/**
	 * 
	 * @throws \RuntimeException
	 */
	public function save(){
		if(count($this->invalidProperties) > 0){
			throw new \RuntimeException("at least one property wasn't found");
		}
		
		$PDO = $this->document->getPDO();
		$docId = $this->document->getId();
		
		$query = $PDO->prepare("INSERT INTO tokens (document_id, token_id, value) VALUES (?, ?, ?)");
		$query->execute(array($docId, $this->tokenId, $this->value));
		
		$query = $PDO->prepare("INSERT INTO orig_values (document_id, token_id, property_xpath, value) VALUES (?, ?, ?, ?)");
		foreach ($this->properties as $xpath => $prop){
			$value = '';
			if($prop){
				$value = isset($prop->value) ? $prop->value : $this->innerXml($prop);
			}
			$query->execute(array($docId, $this->tokenId, $xpath, $value));
		}
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public function update(){
		$this->checkValuesQuery();
		
		foreach($this->properties as $xpath => $prop){
			self::$valuesQuery->execute(array($this->document->getId(), $xpath, $this->tokenId));
			$value = self::$valuesQuery->fetch(\PDO::FETCH_OBJ);
			if($value !== false){
				if(isset($prop->value)){
					$prop->value = $value->value;
				}else{
					$prop->nodeValue = $value->value;
				}
			}
		}
		
		return $this->updateDocument();
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public function enrich(){
		$this->checkValuesQuery();
		
		foreach($this->properties as $xpath => $prop){
			self::$valuesQuery->execute(array($this->document->getId(), $xpath, $this->tokenId));
			while($value = self::$valuesQuery->fetch(\PDO::FETCH_OBJ)){
				$user  = $this->createTeiFeature('user', $value->user_id);
				$date  = $this->createTeiFeature('date', $value->date);
				$xpth  = $this->createTeiFeature('property_xpath', $xpath);
				$val   = $this->createTeiFeature('value', $value->value);
				$fs    = $this->createTeiFeatureSet();
				$fs->appendChild($user);
				$fs->appendChild($date);
				$fs->appendChild($xpth);
				$fs->appendChild($val);
				if($prop->nodeType !== XML_ELEMENT_NODE){
					$prop->parentNode->appendChild($fs);
				}else{
					$prop->appendChild($fs);
				}
			}
		}
		
		return $this->updateDocument();
	}

	/**
	 * 
	 * @return \DOMNode
	 */
	public function getNode(){
		return $this->dom;
	}
	
	/**
	 * 
	 * @return int
	 */
	public function getId(){
		return $this->tokenId;
	}
	
	/**
	 * 
	 */
	private function checkValuesQuery(){
		if(self::$valuesQuery === null){
			self::$valuesQuery = $this->document->getPDO()->
				prepare("SELECT user_id, value, date FROM values WHERE (document_id, property_xpath, token_id) = (?, ?, ?) ORDER BY date DESC");
		}
	}
	
	/**
	 * 
	 */
	private function updateDocument(){
		$this->document->getTokenIterator()->replaceToken($this);
	}
	
	/**
	 * 
	 * @return \DOMNode
	 */
	private function createTeiFeatureSet(){
		$doc = $this->dom->ownerDocument;
		
		$type = $doc->createAttribute('type');
		$type->value = 'tokeneditor';

		$fs = $doc->createElement('fs');
		$fs->appendChild($type);
		
		return($fs);
	}
	
	/**
	 * 
	 * @param string $name
	 * @param string $value
	 * @return \DOMNode
	 */
	private function createTeiFeature($name, $value){
		$doc = $this->dom->ownerDocument;
		
		$fn = $doc->createAttribute('name');
		$fn->value = $name;
		
		$v = $doc->createElement('string', $value);
		
		$f = $doc->createElement('f');
		$f->appendChild($fn);
		$f->appendChild($v);
		
		return $f;
	}
}
