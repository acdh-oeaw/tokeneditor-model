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

namespace acdhOeaw\tokeneditorModel;

use PDO;
use RuntimeException;
use zozlak\util\ProgressBar;

/**
 * Description of Datafile
 *
 * @author zozlak
 */
class Document implements \IteratorAggregate {

    const DOM_DOCUMENT = '\acdhOeaw\tokeneditorModel\tokenIterator\DOMDocument';
    const XML_READER   = '\acdhOeaw\tokeneditorModel\tokenIterator\XMLReader';
    const PDO          = '\acdhOeaw\tokeneditorModel\tokenIterator\PDO';

    private $path;
    private $name;
    private $schema;
    private $pdo;
    private $tokenIteratorClassName;
    private $tokenIterator;
    private $exportFlag;
    private $documentId;
    private $tokenId = 0;

    /**
     * 
     * @param type $path
     * @param \acdhOeaw\tokeneditor\Schema $schema
     * @throws RuntimeException
     */
    public function __construct(PDO $pdo) {
        $this->pdo    = $pdo;
        $this->schema = new Schema($this->pdo);
    }

    public function loadFile(string $filePath, string $schemaPath, string $name,
                             string $iteratorClass = null) {
        if (!is_file($filePath)) {
            throw new RuntimeException($filePath . ' is not a valid file');
        }
        $this->path = $filePath;
        $this->name = $name;
        $this->schema->loadFile($schemaPath);
        $this->chooseTokenIterator();

        if ($iteratorClass === null) {
            $this->chooseTokenIterator();
        } else {
            if (!in_array($iteratorClass, [self::DOM_DOCUMENT, self::XML_READER,
                    self::PDO])) {
                throw new \InvalidArgumentException('tokenIteratorClass should be \acdhOeaw\tokeneditorModel\Datafile::DOM_DOCUMENT, \acdhOeaw\tokeneditorModel\Datafile::PDO or \acdhOeaw\tokeneditorModel\Datafile::XML_READER');
            }
            $this->tokenIteratorClassName = $iteratorClass;
        }
    }

    public function loadDb(string $documentId, string $iteratorClass = null) {
        $this->documentId = $documentId;
        $this->schema->loadDb($this->documentId);

        $query      = $this->pdo->prepare("SELECT name, save_path, hash FROM documents WHERE document_id = ?");
        $query->execute([$this->documentId]);
        $data       = $query->fetch(PDO::FETCH_OBJ);
        $this->name = $data->name;
        $this->path = $data->save_path;

        $hash = md5_file($this->path);
        if ($hash !== $data->hash) {
            throw new \UnexpectedValueException('Raw document XML file changed since import');
        }

        if ($iteratorClass === null) {
            $this->chooseTokenIterator();
        } else {
            if (!in_array($iteratorClass, [self::DOM_DOCUMENT, self::XML_READER])) {
                throw new \InvalidArgumentException('tokenIteratorClass should be \acdhOeaw\tokeneditorModel\Datafile::DOM_DOCUMENT, \acdhOeaw\tokeneditorModel\Datafile::PDO or \acdhOeaw\tokeneditorModel\Datafile::XML_READER');
            }
            $this->tokenIteratorClassName = $iteratorClass;
        }
    }

    /**
     * 
     * @return integer
     */
    public function getId() {
        return $this->documentId;
    }

    /**
     * 
     * @return \acdhOeaw\tokeneditor\Schema
     */
    public function getSchema() {
        return $this->schema;
    }

    /**
     * 
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * 
     * @return PDO
     */
    public function getPdo() {
        return $this->pdo;
    }

    /**
     * 
     * @return integer
     */
    public function generateTokenId() {
        $this->tokenId++;
        return $this->tokenId;
    }

    /**
     * 
     * @param string $saveDir
     * @param int $limit
     * @param \zozlak\util\ProgressBar $progressBar
     * @param bool $skipErrors
     * @return int number of proccessed tokens
     */
    public function save(string $saveDir, int $limit = 0,
                         ProgressBar $progressBar = null, $skipErrors = false) {
        $this->documentId = $this->pdo->
            query("SELECT nextval('document_id_seq')")->
            fetchColumn();

        $savePath = $saveDir . '/' . $this->documentId . '.xml';

        $query = $this->pdo->prepare("INSERT INTO documents (document_id, token_xpath, name, save_path, hash) VALUES (?, ?, ?, ?, ?)");
        $query->execute([$this->documentId, $this->schema->getTokenXPath(),
            $this->name, $savePath, md5_file($this->path)]);
        unset($query); // free memory

        $this->schema->save($this->documentId);

        foreach ($this as $n => $token) {
            try {
                $token->save();
            } catch (RuntimeException $e) {
                if (!$skipErrors) {
                    throw $e;
                }
            }
            if ($progressBar) {
                $progressBar->next();
            }
            if ($n + 1 >= $limit && $limit > 0) {
                break;
            }
        }

        copy($this->path, $savePath);
        return $n + 1;
    }

    /**
     * 
     * @param boolean $replace If true, changes will be made in-place 
     *   (taking the most current value provided by usesrs as the right one). 
     *   If false, review results will be provided as TEI <fs> elements
     * @param string $path path to the file where document will be exported
     * @param type $progressBar
     */
    public function export(bool $replace = false, string $path = null,
                           ProgressBar $progressBar = null) {
        $this->exportFlag = true;
        if ($replace) {
            foreach ($this as $token) {
                $token->update();
                if ($progressBar) {
                    $progressBar->next();
                }
            }
        } else {
            foreach ($this as $token) {
                $token->enrich();
                if ($progressBar) {
                    $progressBar->next();
                }
            }
        }
        return $this->tokenIterator->export($path);
    }

    public function exportCsv(string $path = null, string $delimiter = ',',
                              ProgressBar $progressBar = null) {
        $this->exportFlag = true;

        $csvFile = fopen($path, 'w');

        $header = ['tokenId'];
        foreach ($this->schema as $property) {
            $header[] = $property->getName();
        }
        fputcsv($csvFile, $header, $delimiter);

        foreach ($this as $token) {
            $token->exportCsv($csvFile, $delimiter);
        }

        fclose($csvFile);
    }

    public function getIterator() {
        $this->tokenIterator = new $this->tokenIteratorClassName($this->path, $this, $this->exportFlag);
        $this->exportFlag    = false;
        return $this->tokenIterator;
    }

    /**
     * 
     * @return \acdhOeaw\tokeneditor\tokenIterator\TokenInterator
     */
    public function getTokenIterator() {
        return $this->tokenIterator;
    }

    private function chooseTokenIterator() {
        try {
            new tokenIterator\XMLReader($this->path, $this);
            $this->tokenIteratorClassName = self::XML_READER;
        } catch (RuntimeException $ex) {
            $this->tokenIteratorClassName = self::DOM_DOCUMENT;
        }
    }

}
