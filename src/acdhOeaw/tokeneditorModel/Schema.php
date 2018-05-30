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

/**
 * Description of Schema
 *
 * @author zozlak
 */
class Schema implements \IteratorAggregate {

    private $pdo;
    private $documentId;
    private $tokenXPath;
    private $tokenValueXPath;
    private $namespaces = [];
    private $properties = [];

    /**
     * 
     * @param PDO $pdo
     * @throws \RuntimeException
     * @throws \LengthException
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function loadFile(string $path) {
        if (!is_file($path)) {
            throw new \RuntimeException($path . ' is not a valid file');
        }
        $this->loadXML(file_get_contents($path));
    }

    public function loadXML(string $xml) {
        $dom = new \SimpleXMLElement($xml);

        if (!isset($dom->tokenXPath) || count($dom->tokenXPath) != 1) {
            throw new \LengthException('exactly one tokenXPath has to be provided');
        }
        $this->tokenXPath = $dom->tokenXPath;

        if (!isset($dom->tokenValueXPath) || count($dom->tokenValueXPath) != 1) {
            throw new \LengthException('exactly one tokenValueXPath has to be provided');
        }
        $this->tokenValueXPath = $dom->tokenValueXPath;

        if (
            !isset($dom->properties) || !isset($dom->properties->property) || count($dom->properties->property) == 0
        ) {
            throw new \LengthException('no token properties defined');
        }
        $n     = 1;
        $names = [];
        foreach ($dom->properties->property as $i) {
            $prop               = new Property($i, $n++);
            $this->properties[] = $prop;
            $names[]            = $prop->getName();
        }
        if (count($names) !== count(array_unique($names))) {
            throw new \RuntimeException('property names are not unique');
        }
        if (isset($dom->namespaces) && isset($dom->namespaces->namespace)) {
            foreach ($dom->namespaces->namespace as $i) {
                $this->namespaces[(string) $i->prefix[0]] = (string) $i->uri[0];
            }
        }
    }

    public function loadDb(int $documentId) {
        $this->documentId = $documentId;

        $schema = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><schema>';

        $schema .= '<namespaces>';
        $query  = $this->pdo->prepare("SELECT prefix, ns FROM documents_namespaces WHERE document_id = ?");
        $query->execute([$this->documentId]);
        while ($ns     = $query->fetch(PDO::FETCH_OBJ)) {
            $schema .= '<namespace><prefix>' . htmlspecialchars($ns->prefix) . '</prefix><uri>' . htmlspecialchars($ns->ns) . '</uri></namespace>';
        }
        $schema .= '</namespaces>';

        $query  = $this->pdo->prepare("SELECT token_xpath, token_value_xpath FROM documents WHERE document_id = ?");
        $query->execute([$this->documentId]);
        $data   = $query->fetch(PDO::FETCH_OBJ);
        $schema .= '<tokenXPath>' . htmlspecialchars($data->token_xpath) . '</tokenXPath>';
        $schema .= '<tokenValueXPath>' . htmlspecialchars($data->token_value_xpath) . '</tokenValueXPath>';

        $schema      .= '<properties>';
        $query       = $this->pdo->prepare("SELECT property_xpath, type_id, name, read_only FROM properties WHERE document_id = ? ORDER BY ord");
        $valuesQuery = $this->pdo->prepare("SELECT value FROM dict_values WHERE (document_id, property_xpath) = (?, ?)");
        $query->execute([$this->documentId]);
        while ($prop        = $query->fetch(PDO::FETCH_OBJ)) {
            $schema .= '<property>';
            $schema .= '<propertyName>' . htmlspecialchars($prop->name) . '</propertyName>';
            $schema .= '<propertyXPath>' . htmlspecialchars($prop->property_xpath) . '</propertyXPath>';
            $schema .= '<propertyType>' . htmlspecialchars($prop->type_id) . '</propertyType>';

            if ($prop->read_only) {
                $schema .= '<readOnly/>';
            }

            $valuesQuery->execute([$this->documentId, $prop->property_xpath]);
            $values = $valuesQuery->fetchAll(PDO::FETCH_COLUMN);
            if (count($values) > 0) {
                $schema .= '<propertyValues>';
                foreach ($values as $v) {
                    $schema .= '<value>' . htmlspecialchars($v) . '</value>';
                }
                $schema .= '</propertyValues>';
            }
            $schema .= '</property>';
        }
        $schema .= '</properties>';

        $schema .= '</schema>';

        $this->loadXML($schema);
    }

    /**
     * 
     * @return string
     */
    public function getTokenXPath() {
        return (string) $this->tokenXPath;
    }

    /**
     * 
     * @return string
     */
    public function getTokenValueXPath() {
        return (string) $this->tokenValueXPath;
    }

    /**
     * 
     * @return array
     */
    public function getNs() {
        return $this->namespaces;
    }

    /**
     * 
     * @return \ArrayIterator
     */
    public function getIterator() {
        return new \ArrayIterator($this->properties);
    }

    /**
     * 
     * @param PDO $pdo
     * @param type $datafileId
     */
    public function save(int $documentId) {
        $query = $this->pdo->prepare("INSERT INTO documents_namespaces (document_id, prefix, ns) VALUES (?, ?, ?)");
        foreach ($this->getNs() as $prefix => $ns) {
            $query->execute([$documentId, $prefix, $ns]);
        }

        foreach ($this->properties as $prop) {
            $prop->save($this->pdo, $documentId);
        }
    }

}
