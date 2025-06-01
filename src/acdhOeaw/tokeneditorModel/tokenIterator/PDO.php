<?php

/**
 * The MIT License
 *
 * Copyright 2016 Austrian Centre for Digital Humanities.
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

namespace acdhOeaw\tokeneditorModel\tokenIterator;

use BadMethodCallException;
use DOMDocument;
use acdhOeaw\tokeneditorModel\Document;
use acdhOeaw\tokeneditorModel\Token;

/**
 * Token iterator class using relational database backend.
 * On Postgresql it is very fast but memory ineficient (like every DOM parser).
 * 
 *
 * @author zozlak
 */
class PDO extends TokenIterator {

    private \PDO $pdo;
    private int $id;
    private $results;

    public function __construct(string $xmlPath, Document $document) {
        parent::__construct($xmlPath, $document);
        $this->pdo = $this->document->getPdo();

        $this->id = $this->pdo->
            query("SELECT nextval('import_tmp_seq')")->
            fetchColumn();

        $query = $this->pdo->prepare("INSERT INTO import_tmp VALUES (?, ?)");
        $query->execute(array($this->id, preg_replace('/^[^<]*/', '', file_get_contents($this->xmlPath))));
    }

    /**
     * 
     */
    public function __destruct() {
        $query = $this->pdo->prepare("DELETE FROM import_tmp WHERE id = ?");
        $query->execute(array($this->id));
    }

    public function next(): void {
        $this->pos++;
        $this->token = $this->results->fetch(\PDO::FETCH_COLUMN);
        if ($this->token !== false) {
            $tokenDom    = new DOMDocument();
            $tokenDom->loadXml($this->token);
            $this->token = new Token($tokenDom->documentElement, $this->document);
        }
    }

    public function rewind(): void {
        $param = array($this->document->getSchema()->getTokenXPath());

        $ns = array();
        foreach ($this->document->getSchema()->getNs() as $prefix => $namespace) {
            $ns[]    = 'array[?, ?]';
            $param[] = $prefix;
            $param[] = $namespace;
        }
        $ns = implode(',', $ns);
        if ($ns != '') {
            $ns = ', array[' . $ns . ']';
        }

        $param[] = $this->id;

        $this->results = $this->pdo->prepare("SELECT unnest(xpath(?, xml" . $ns . ")) FROM import_tmp WHERE id = ?");
        $this->results->execute($param);
        $this->pos     = -1;
        $this->next();
    }

    public function export(string | null $path = null): string | null {
        throw new BadMethodCallException('export() is not not implemented for this TokenIterator class');
    }

    public function replaceToken(Token $new): void {
        throw new BadMethodCallException('replaceToken() is not not implemented for this TokenIterator class');
    }

}
