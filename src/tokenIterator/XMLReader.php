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
 * Token iterator class developed using stream XML parser (XMLReader).
 * It is memory efficient (requires constant memory no matter XML size)
 * and very fast but (at least at the moment) can handle token XPaths
 * specyfying single node name only.
 * This is because XMLReader does not provide any way to execute XPaths on it
 * and I was to lazy to implement more compound XPath handling. Maybe it will
 * be extended in the future.
 *
 * @author zozlak
 */
class XMLReader extends TokenIterator {
	private $reader;
	private $outStream;
	
	/**
	 * 
	 * @param type $xmlPath
	 * @param \model\Document $document
	 * @param type $export
	 * @throws \RuntimeException
	 */
	public function __construct($xmlPath, \model\Document $document, $export = false){
		parent::__construct($xmlPath, $document);

		$this->reader = new \XMLReader();
		$tokenXPath = $this->document->getSchema()->getTokenXPath();
		if(!preg_match('|^//[a-zA-Z0-9_:.]+$|', $tokenXPath)){
			throw new \RuntimeException('Token XPath is too complicated for XMLReader');
		}
		$this->tokenXPath = mb_substr($tokenXPath, 2);
		$nsPrefixPos = mb_strpos($this->tokenXPath, ':');
		if($nsPrefixPos !== false){
			$prefix = mb_substr($this->tokenXPath, 0, $nsPrefixPos);
			$ns = $this->document->getSchema()->getNs();
			if(isset($ns[$prefix])){
				$this->tokenXPath = $ns[$prefix] . mb_substr($this->tokenXPath, $nsPrefixPos);
			}
		}
		
		if($export){
			$filename = tempnam(sys_get_temp_dir(), '');
			$this->outStream = new \SplFileObject($filename, 'w');
			$this->outStream->fwrite('<?xml version="1.0" encoding="UTF-8" standalone="no"?>' . "\n");
		}
	}
	
	public function __destruct() {
		if($this->outStream){
			$filename = $this->outStream->getRealPath();
			$this->outStream = null;
			unlink($filename);
		}
	}


	/**
	 * 
	 */
	public function next() {
		if($this->outStream && $this->token){
			$tmp = $this->token->getNode()->ownerDocument->saveXML();
			$tmp = trim(str_replace('<?xml version="1.0"?>' . "\n", '', $tmp));
			$this->outStream->fwrite($tmp);
		}
		$this->pos++;
		$this->token = false;
		$firstStep = $this->pos !== 0;
		do{
			// in first step skip previous token subtree
			$res = $firstStep ? $this->reader->next() : $this->reader->read();
			$firstStep = false;
			$name = null;
			if($this->reader->nodeType === \XMLReader::ELEMENT){
				$nsPrefixPos = mb_strpos($this->reader->name, ':');
				$name = 
					($this->reader->namespaceURI ? $this->reader->namespaceURI . ':' : '') .
					($nsPrefixPos ? mb_substr($this->reader->name, $nsPrefixPos + 1) : $this->reader->name);
			}
			// rewrite nodes which are not tokens to the output
			if($this->outStream && $res && $name !== $this->tokenXPath){
				$this->writeElement();
			}
		}while($res && $name !== $this->tokenXPath);
		if($res){
			$tokenDom = new \DOMDocument();
			$tokenDom->loadXml($this->reader->readOuterXml());
			$this->token = new \model\Token($tokenDom->documentElement, $this->document);
		}
	}
	
	/**
	 * 
	 */
	public function rewind() {
		$this->reader->open($this->xmlPath);
		$this->pos = -1;
		$this->next();
	}
	
	/**
	 * 
	 * @param type $path
	 * @throws \BadMethodCallException
	 */
	public function export($path) {
		if(!$this->outStream){
			throw new \RuntimeException('Set $export to true when calling object constructor to enable export');
		}
		$currPath = $this->outStream->getRealPath();
		$this->outStream = null;
		if($path != ''){
			rename($currPath, $path);
		}else{
			$data = file_get_contents($currPath);
			unlink($currPath);
			return $data;
		}
	}

	/**
	 * 
	 * @param \model\Token $new
	 */
	public function replaceToken(\model\Token $new){
		if($new->getId() != $this->token->getId()){
			throw new \RuntimeException('Only current token can be replaced when you are using XMLReader token iterator');
		}
		$old = $this->token->getNode();
		$new = $old->ownerDocument->importNode($new->getNode(), true);
		$old->parentNode->replaceChild($new, $old);
	}

	/**
	 * Rewrites current node to the output.
	 * Used to rewrite nodes which ate not tokens.
	 */
	private function writeElement(){
		$el = $this->getElementBeg();
		$el .=  $this->getElementContent();
		$el .= $this->getElementEnd();
		$this->outStream->fwrite($el);
	}

	/**
	 * Returns node beginning ('<', '<![CDATA[', etc.) for a current node.
	 * Used to rewrite nodes which are not tokens to the output.
	 * @return string
	 */
	private function getElementBeg(){
		$beg = '';
		$types = array(
			\XMLReader::ELEMENT, 
			\XMLReader::END_ELEMENT
		);
		$beg .= in_array($this->reader->nodeType, $types) ? '<' : '';
		$beg .= $this->reader->nodeType === \XMLReader::END_ELEMENT ? '/' : '';
		$beg .= $this->reader->nodeType === \XMLReader::PI ? '<?' : '';
		$beg .= $this->reader->nodeType === \XMLReader::COMMENT ? '<!--' : '';
		$beg .= $this->reader->nodeType === \XMLReader::CDATA ? '<![CDATA[' : '';
		return $beg;
	}
	
	/**
	 * Returns node ending ('>', ']]>', etc.) for a current node.
	 * Used to rewrite nodes which are not tokens to the output.
	 * @return string
	 */
	private function getElementEnd(){
		$this->reader->moveToElement();
		$end = '';
		$end .= $this->reader->isEmptyElement ? '/' : '';
		$end .= $this->reader->nodeType === \XMLReader::CDATA ? ']]>' : '';
		$end .= $this->reader->nodeType === \XMLReader::COMMENT ? '-->' : '';
		$end .= $this->reader->nodeType === \XMLReader::PI ? '?>' : '';
		$types = array(
			\XMLReader::ELEMENT, 
			\XMLReader::END_ELEMENT,
		);
		$end .= in_array($this->reader->nodeType, $types) ? '>' : '';
		return $end;
	}

	/**
	 * Returns node content (e.g. 'prefix:tag attr="value"' or comment/cdata 
	 * text) for a current node.
	 * Used to rewrite nodes which are not tokens to the output.
	 * @return string
	 */
	private function getElementContent(){
		$str = '';
		
		$types = array(
			\XMLReader::ELEMENT, 
			\XMLReader::END_ELEMENT,
			\XMLReader::PI
		);
		if(in_array($this->reader->nodeType, $types)){
			$str .= ($this->reader->prefix ? $this->reader->prefix . ':' : '');
			$str .= $this->reader->name;
		}
		$types = array(
			\XMLReader::ELEMENT, 
			\XMLReader::PI
		);
		if(in_array($this->reader->nodeType, $types)){
			while($this->reader->moveToNextAttribute()){
				$str .= ' ';
				$str .= $this->reader->name;
				$str .= '="' . $this->reader->value . '"';
			}
		}else{
			$str .= $this->reader->value;
		}
		
		return $str;
	}
}
