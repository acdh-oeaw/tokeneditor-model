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

namespace acdhOeaw\tokeneditorModel\tokenIterator;

/**
 * Description of TokenIterator
 *
 * @author zozlak
 */
abstract class TokenIterator implements \Iterator {

    /**
     *
     * @var \acdhOeaw\tokeneditor\Document
     */
    protected $document;
    protected $xmlPath;
    protected $pos;

    /**
     *
     * @var type \acdhOeaw\tokeneditor\Token
     */
    protected $token = false;

    /**
     * 
     * @param type $path
     * @param \acdhOeaw\tokeneditor\Schema $schema
     * @param \PDO $PDO
     */
    public function __construct($xmlPath, \acdhOeaw\tokeneditor\Document $document) {
        $this->xmlPath = $xmlPath;
        $this->document = $document;
    }

    /**
     * 
     * @return \acdhOeaw\tokeneditor\Token
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

    abstract public function replaceToken(\acdhOeaw\tokeneditor\Token $new);
}
