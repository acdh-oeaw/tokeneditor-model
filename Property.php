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
 * Description of Property
 *
 * @author zozlak
 */
class Property {
	private $xpath;
	private $type;
	private $name;
	private $ord;
	private $readOnly = false;
	private $values = array();
	
	/**
	 * 
	 * @param \SimpleXMLElement $xml
	 * @throws \LengthException
	 */
	public function __construct(\SimpleXMLElement $xml, $ord) {
		$this->ord = $ord;
		
		if(!isset($xml->propertyXPath) || count($xml->propertyXPath) != 1){
			throw new \LengthException('exactly one propertyXPath has to be provided');
		}
		$this->xpath = (string)$xml->propertyXPath[0];
		
		if(!isset($xml->propertyType) || count($xml->propertyType) != 1){
			throw new \LengthException('exactly one propertyType has to be provided');
		}
		$this->type = (string)$xml->propertyType;

		if(!isset($xml->propertyName) || count($xml->propertyName) != 1){
			throw new \LengthException('exactly one propertyName has to be provided');
		}
		$this->name = (string)$xml->propertyName;
		if(in_array($this->name, array('token_id', 'token', '_offset', '_pagesize', '_docid'))){
			throw new \RuntimeException('property uses a reserved name');
		}
		
		if(isset($xml->propertyValues) && isset($xml->propertyValues->value)){
			$this->values = $xml->propertyValues->value;
		}
		
		if(isset($xml->readOnly)){
			$this->readOnly = true;
		}
	}

	/**
	 * 
	 * @return string
	 */
	public function getXPath(){
		return $this->xpath;
	}

	/**
	 * 
	 * @return string
	 */
	public function getName(){
		return $this->name;
	}
	
	/**
	 * 
	 * @param \PDO $PDO
	 * @param type $documentId
	 */
	public function save(\PDO $PDO, $documentId){
		$query = $PDO->prepare("INSERT INTO properties (document_id, property_xpath, type_id, name, ord, read_only) VALUES (?, ?, ?, ?, ?, ?)");
		$query->execute(array($documentId, $this->xpath, $this->type, $this->name, $this->ord, (int)$this->readOnly));
		
		$query = $PDO->prepare("INSERT INTO dict_values (document_id, property_xpath, value) VALUES (?, ?, ?)");
		foreach($this->values as $v){
			$query->execute(array($documentId, $this->xpath, $v));
		}
	}
}
