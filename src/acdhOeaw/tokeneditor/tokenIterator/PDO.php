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

namespace acdhOeaw\tokeneditor\tokenIterator;

/**
 * Token iterator class using relational database backend.
 * On Postgresql it is very fast but memory ineficient (like every DOM parser).
 * 
 *
 * @author zozlak
 */
class PDO extends TokenIterator {

    private $PDO;
    private $id;
    private $results;

    /**
     * 
     * @param type $path
     * @param \acdhOeaw\tokeneditor\Schema $schema
     * @param \PDO $PDO
     */
    public function __construct($xmlPath, \acdhOeaw\tokeneditor\Document $document) {
        parent::__construct($xmlPath, $document);
        $this->PDO = $this->document->getPDO();

        $this->id = $this->PDO->
            query("SELECT nextval('import_tmp_seq')")->
            fetchColumn();

        $query = $this->PDO->prepare("INSERT INTO import_tmp VALUES (?, ?)");
//print_r(array($this->id, preg_replace('/^[^<]*/', '', file_get_contents($this->xmlPath))));
        $query->execute(array($this->id, preg_replace('/^[^<]*/', '', file_get_contents($this->xmlPath))));
print_r($this->PDO->query("select unnest(xpath('//tei:w', xml, array[array['tei', 'http://www.tei-c.org/ns/1.0']])) FROM import_tmp LIMIT 1")->fetchColumn());
    }

    /**
     * 
     */
    public function __destruct() {
        $query = $this->PDO->prepare("DELETE FROM import_tmp WHERE id = ?");
        $query->execute(array($this->id));
    }

    /**
     * 
     */
    public function next() {
        $this->pos++;
        $this->token = $this->results->fetch(\PDO::FETCH_COLUMN);
//echo($this->token."\n\n");
        if ($this->token !== false) {
            $tokenDom = new \DOMDocument();
            $tokenDom->loadXml($this->token);
            $this->token = new \acdhOeaw\tokeneditor\Token($tokenDom->documentElement, $this->document);
        }
    }

    /**
     * 
     */
    public function rewind() {
        $param = array($this->document->getSchema()->getTokenXPath());

        $ns = array();
        foreach ($this->document->getSchema()->getNs() as $prefix => $namespace) {
            $ns[] = 'array[?, ?]';
            $param[] = $prefix;
            $param[] = $namespace;
        }
        $ns = implode(',', $ns);
        if ($ns != '') {
            $ns = ', array[' . $ns . ']';
        }

        $param[] = $this->id;

        $this->results = $this->PDO->prepare("SELECT unnest(xpath(?, xml" . $ns . ")) FROM import_tmp WHERE id = ?");
//echo($this->PDO->query("SELECT xml FROM import_tmp")->fetchColumn()."\n\n");
//echo("SELECT unnest(xpath(".$param[0] . ", xml, ".$ns.")) FROM import_tmp WHERE id = ".$param[1].";\n\n");
        $this->results->execute($param);
        $this->pos = -1;
        $this->next();
    }

    /**
     * 
     * @param type $path
     * @throws \BadMethodCallException
     */
    public function export($path) {
        throw new \BadMethodCallException('export() is not not implemented for this TokenIterator class');
    }

    /**
     * 
     * @param \acdhOeaw\tokeneditor\Token $new
     * @throws \BadMethodCallException
     */
    public function replaceToken(\acdhOeaw\tokeneditor\Token $new) {
        throw new \BadMethodCallException('replaceToken() is not not implemented for this TokenIterator class');
    }

}
