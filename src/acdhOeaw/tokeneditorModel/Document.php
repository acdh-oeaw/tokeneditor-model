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
use acdhOeaw\tokeneditorModel\tokenIterator\TokenIterator;
use acdhOeaw\tokeneditorModel\tokenIterator\PDO as iPDO;
use acdhOeaw\tokeneditorModel\tokenIterator\DOMDocument as iDOMDocument;
use acdhOeaw\tokeneditorModel\tokenIterator\XMLReader as iXMLReader;

/**
 * Description of Datafile
 *
 * @author zozlak
 */
class Document implements \IteratorAggregate {

    const TOKEN_ITERATORS = [iPDO::class, iDOMDocument::class, iXMLReader::class];

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
     * @throws RuntimeException
     */
    public function __construct(PDO $pdo) {
        $this->pdo    = $pdo;
        $this->schema = new Schema($this->pdo);
    }

    /**
     * 
     * @throws RuntimeException
     * @throws \InvalidArgumentException
     */
    public function loadFile(string $filePath, string $schemaPath, string $name,
                             string $iteratorClass = null): void {
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
            if (!in_array($iteratorClass, self::TOKEN_ITERATORS)) {
                throw new \InvalidArgumentException('tokenIteratorClass should be one of ' . implode(', ', self::TOKEN_ITERATORS));
            }
            $this->tokenIteratorClassName = $iteratorClass;
        }
    }

    /**
     * 
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function loadDb(string $documentId, string $iteratorClass = null): void {
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
            if (!in_array($iteratorClass, self::TOKEN_ITERATORS)) {
                throw new \InvalidArgumentException('tokenIteratorClass should be one of ' . implode(', ', self::TOKEN_ITERATORS));
            }
            $this->tokenIteratorClassName = $iteratorClass;
        }
    }

    public function getId(): int {
        return $this->documentId;
    }

    public function getSchema(): Schema {
        return $this->schema;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getPdo(): PDO {
        return $this->pdo;
    }

    public function getIterator(): TokenIterator {
        $this->tokenIterator = new $this->tokenIteratorClassName($this->path, $this, $this->exportFlag);
        $this->exportFlag    = false;
        return $this->tokenIterator;
    }

    public function getTokenIterator(): TokenIterator {
        return $this->tokenIterator;
    }

    public function getXmlPath(string $saveDir): string {
        return $saveDir . '/' . $this->documentId . '.xml';
    }

    public function generateTokenId(): int {
        $this->tokenId++;
        return $this->tokenId;
    }

    public function save(string $saveDir, int $limit = 0,
                         ProgressBar $progressBar = null, $skipErrors = false): int {
        $this->documentId = $this->pdo->
            query("SELECT nextval('document_id_seq')")->
            fetchColumn();

        $savePath = $this->getXmlPath($saveDir);

        $query = $this->pdo->prepare("INSERT INTO documents (document_id, token_xpath, name, save_path, hash) VALUES (?, ?, ?, ?, ?)");
        $query->execute([$this->documentId, $this->schema->getTokenXPath(),
            $this->name, $savePath, md5_file($this->path)]);
        unset($query); // free memory

        $this->schema->save($this->documentId);

        $n = 0;
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

    public function delete(string $saveDir): void {
        $query = $this->pdo->prepare("DELETE FROM documents WHERE document_id = ?");
        $query->execute([$this->documentId]);
        $path  = $this->getXmlPath($saveDir);
        if (file_exists($path)) {
            unlink($path);
        }
    }

    /**
     * 
     * @param boolean $replace If true, changes will be made in-place 
     *   (taking the most current value provided by usesrs as the right one). 
     *   If false, review results will be provided as TEI <fs> elements
     * @param string $path path to the file where document will be exported
     */
    public function export(bool $replace = false, string $path = null,
                           ProgressBar $progressBar = null): string {
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

    /**
     * Exports data in formats providing straightforward representations of
     * tabular data like CSV, JSON, YAML, etc.
     * @param \acdhOeaw\tokeneditorModel\ExportTableInterface $formatter
     *   object responsible for output formatting (e.g. a CSV or JSON formatter)
     * @param bool $replace should only the final value be exported for every 
     *   property? (if false, a whole history of value should be exported but
     *   the actual output depends on formatter capabilities)
     * @param ProgressBar $progressBar progress bar instance - if provided,
     *   export progress is shown
     */
    public function exportTable(ExportTableInterface $formatter, bool $replace = true, ProgressBar $progressBar = null): void {
        $this->exportFlag = true;
        $formatter->begin($this->schema);
        foreach($this as $token){
            $formatter->writeRow($token, $replace);
            if ($progressBar) {
                $progressBar->next();
            }
        }
        $formatter->end();
    }
    
    private function chooseTokenIterator(): void {
        try {
            new iXMLReader($this->path, $this);
            $this->tokenIteratorClassName = iXMLReader::class;
        } catch (RuntimeException $ex) {
            $this->tokenIteratorClassName = iDOMDocument::class;
        }
    }

}
