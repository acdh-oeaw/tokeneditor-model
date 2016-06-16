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
 * Description of TokenIterator
 *
 * @author zozlak
 */
abstract class TokenIterator implements \Iterator {
	/**
	 *
	 * @var \model\Document
	 */
	protected $document;
	protected $xmlPath;
	protected $pos;
	/**
	 *
	 * @var type \model\Token
	 */
	protected $token = false;

	/**
	 * 
	 * @param type $path
	 * @param \model\Schema $schema
	 * @param \PDO $PDO
	 */
	public function __construct($xmlPath, \model\Document $document){
		$this->xmlPath = $xmlPath;
		$this->document = $document;
	}
	
	/**
	 * 
	 * @return model\Token
	 */
	public function current() {
		return $this->token;
	}
	
	/**
	 * 
	 * @return int
	 */
	public function key() {
		return $this->pos;
	}

	/**
	 * 
	 * @return boolean
	 */
	public function valid() {
		return $this->token !== false;
	}
	
	abstract public function export($path);
	abstract public function replaceToken(\model\Token $new);
}
