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

/**
 * Description of Token
 *
 * @author zozlak
 */
class Token {

    /**
     *
     * @var \PDOStatement
     */
    private static $valuesQuery;

    /**
     *
     * @var \PDOStatement
     */
    private static $origValuesQuery;

    /**
     *
     * @var \DomElement
     */
    private $dom;

    /**
     *
     * @var acdhOeaw\tokeneditor\Document
     */
    private $document;
    private $tokenId;
    private $properties        = [];
    private $invalidProperties = [];

    /**
     * 
     * @param \DOMElement $dom
     * @param \acdhOeaw\tokeneditor\Document $document
     * @throws \LengthException
     */
    public function __construct(\DOMElement $dom, Document $document) {
        $this->dom      = $dom;
        $this->document = $document;
        $this->tokenId  = $this->document->generateTokenId();

        $xpath = new \DOMXPath($dom->ownerDocument);
        foreach ($this->document->getSchema()->getNs() as $prefix => $ns) {
            $xpath->registerNamespace($prefix, $ns);
        }

        foreach ($this->document->getSchema() as $prop) {
            try {
                $value = $xpath->query($prop->getXPath(), $this->dom);
                if ($value->length === 1) {
                    $this->properties[$prop->getXPath()] = $value->item(0);
                } else if ($value->length !== 0 || !$prop->getOptional()) {
                    throw new \LengthException('property not found or many properties found');
                }
            } catch (\LengthException $e) {
                $this->invalidProperties[$prop->getXPath()] = $e->getMessage();
            }
        }
    }

    /**
     * 
     * @param \DOMNode $node
     * @return string
     */
    private function innerXml(\DOMNode $node) {
        $out = '';
        for ($i = 0; $i < $node->childNodes->length; $i++) {
            $out .= $node->ownerDocument->saveXML($node->childNodes->item($i));
        }
        return $out;
    }

    /**
     * 
     * @throws \RuntimeException
     */
    public function save() {
        if (count($this->invalidProperties) > 0) {
            throw new \RuntimeException("at least one property wasn't found");
        }

        $pdo   = $this->document->getPdo();
        $docId = $this->document->getId();

        $query = $pdo->prepare("INSERT INTO tokens (document_id, token_id) VALUES (?, ?)");
        $query->execute([$docId, $this->tokenId]);

        $query = $pdo->prepare("INSERT INTO orig_values (document_id, token_id, property_xpath, value) VALUES (?, ?, ?, ?)");
        foreach ($this->properties as $xpath => $prop) {
            $value = '';
            if ($prop) {
                $value = isset($prop->value) ? $prop->value : $this->innerXml($prop);
            }
            $query->execute([$docId, $this->tokenId, $xpath, $value]);
        }
    }

    /**
     * 
     * @return boolean
     */
    public function update() {
        $this->checkValuesQuery();

        foreach ($this->properties as $xpath => $prop) {
            self::$valuesQuery->execute([$this->document->getId(), $xpath, $this->tokenId]);
            $value = self::$valuesQuery->fetch(\PDO::FETCH_OBJ);
            if ($value !== false) {
                if (isset($prop->value)) {
                    $prop->value = $value->value;
                } else {
                    $prop->nodeValue = $value->value;
                }
            }
        }

        return $this->updateDocument();
    }

    /**
     * 
     * @return boolean
     */
    public function enrich() {
        $this->checkValuesQuery();

        foreach ($this->properties as $xpath => $prop) {
            self::$valuesQuery->execute([$this->document->getId(), $xpath, $this->tokenId]);
            while ($value = self::$valuesQuery->fetch(\PDO::FETCH_OBJ)) {
                $user = $this->createTeiFeature('user', $value->user_id);
                $date = $this->createTeiFeature('date', $value->date);
                $xpth = $this->createTeiFeature('property_xpath', $xpath);
                $val  = $this->createTeiFeature('value', $value->value);
                $fs   = $this->createTeiFeatureSet();
                $fs->appendChild($user);
                $fs->appendChild($date);
                $fs->appendChild($xpth);
                $fs->appendChild($val);
                if ($prop->nodeType !== XML_ELEMENT_NODE) {
                    $prop->parentNode->appendChild($fs);
                } else {
                    $prop->appendChild($fs);
                }
            }
        }

        return $this->updateDocument();
    }

    public function exportCsv($csvFile, string $delimiter = ',') {
        $this->checkValuesQuery();

        $values = [$this->tokenId];
        foreach ($this->properties as $xpath => $prop) {
            self::$valuesQuery->execute([$this->document->getId(), $xpath, $this->tokenId]);
            $userValue = self::$valuesQuery->fetch(\PDO::FETCH_OBJ);
            if ($userValue) {
                $values[] = $userValue->value;
            } else {
                self::$origValuesQuery->execute([$this->document->getId(), $xpath,
                    $this->tokenId]);
                $values[] = self::$origValuesQuery->fetch(\PDO::FETCH_OBJ)->value;
            }
        }
        fputcsv($csvFile, $values, $delimiter);
    }

    /**
     * 
     * @return \DOMNode
     */
    public function getNode() {
        return $this->dom;
    }

    /**
     * 
     * @return int
     */
    public function getId() {
        return $this->tokenId;
    }

    /**
     * 
     */
    private function checkValuesQuery() {
        if (self::$valuesQuery === null) {
            self::$valuesQuery = $this->document->getPdo()->
                prepare("SELECT user_id, value, date FROM values WHERE (document_id, property_xpath, token_id) = (?, ?, ?) ORDER BY date DESC");
        }
        if (self::$origValuesQuery === null) {
            self::$origValuesQuery = $this->document->getPdo()->
                prepare("SELECT value FROM orig_values WHERE (document_id, property_xpath, token_id) = (?, ?, ?)");
        }
    }

    /**
     * 
     */
    private function updateDocument() {
        $this->document->getTokenIterator()->replaceToken($this);
    }

    /**
     * 
     * @return \DOMNode
     */
    private function createTeiFeatureSet() {
        $doc = $this->dom->ownerDocument;

        $type        = $doc->createAttribute('type');
        $type->value = 'tokeneditor';

        $fs = $doc->createElement('fs');
        $fs->appendChild($type);

        return($fs);
    }

    /**
     * 
     * @param string $name
     * @param string $value
     * @return \DOMNode
     */
    private function createTeiFeature(string $name, string $value) {
        $doc = $this->dom->ownerDocument;

        $fn        = $doc->createAttribute('name');
        $fn->value = $name;

        $v = $doc->createElement('string', $value);

        $f = $doc->createElement('f');
        $f->appendChild($fn);
        $f->appendChild($v);

        return $f;
    }

}
