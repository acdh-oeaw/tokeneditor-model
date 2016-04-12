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


namespace model\tokenIterator;

/**
 * Basic token iterator class using DOM parser (DOMDocument).
 * It is memory inefficient as every DOM parser but quite fast (at least as long 
 * Token class constuctor supports passing Token XML as a DOMElement object; if 
 * conversion to string was required, it would be very slow).
 *
 * @author zozlak
 */
class DOMDocument extends TokenIterator {
	private $dom;
	private $tokens;
	
	/**
	 * 
	 * @param type $path
	 */
	public function __construct($xmlPath, \model\Document $document) {
		parent::__construct($xmlPath, $document);
	}
	
	/**
	 * 
	 */
	public function next() {
		$this->token = false;
		$this->pos++;
		if($this->pos < $this->tokens->length){
			$doc = new \DOMDocument();
			$tokenNode = $doc->importNode($this->tokens->item($this->pos), true);
			$this->token = new \model\Token($tokenNode, $this->document);
		}
	}

	/**
	 * 
	 */
	public function rewind() {
		$this->dom = new \DOMDocument();
		$this->dom->preserveWhiteSpace = false;
		$this->dom->LoadXML(file_get_contents($this->xmlPath));
		$xpath = new \DOMXPath($this->dom);
		foreach($this->document->getSchema()->getNs() as $prefix => $ns){
			$xpath->registerNamespace($prefix, $ns);
		}
		$this->tokens = $xpath->query($this->document->getSchema()->getTokenXPath());
		$this->pos = -1;
		$this->next();
	}
	
	/**
	 * 
	 * @param \model\Token $new
	 */
	public function replaceToken(\model\Token $new){
		$old = $this->tokens->item($new->getId() - 1);
		$new = $this->dom->importNode($new->getNode(), true);
		$old->parentNode->replaceChild($new, $old);
	}
	
	/**
	 * 
	 * @param string $path
	 * @return string
	 */
	public function export($path){
		if($path != ''){
			$this->dom->save($path);
		}else{
			return $this->dom->saveXML();
		}
	}
}
