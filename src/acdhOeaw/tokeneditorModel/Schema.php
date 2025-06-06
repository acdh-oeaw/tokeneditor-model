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

use ArrayIterator;
use PDO;

/**
 * Description of Schema
 *
 * @implements \IteratorAggregate<int, Property>
 *
 * @author zozlak
 */
class Schema implements \IteratorAggregate {

    private PDO $pdo;
    private int $documentId;
    private string $tokenXPath;
    /**
     * @var array<string, string>
     */
    private array $namespaces = [];
    /**
     * @var array<Property>
     */
    private array $properties = [];

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function loadFile(string $path): void {
        if (!is_file($path)) {
            throw new \RuntimeException($path . ' is not a valid file');
        }
        $this->loadXML(file_get_contents($path));
    }

    public function loadXML(string $xml): void {
        unset($this->tokenXPath);
        $this->properties = [];
        $this->namespaces = [];

        $dom = new \SimpleXMLElement($xml);
        $n   = 1;

        if (!isset($dom->tokenXPath) || count($dom->tokenXPath) != 1) {
            throw new \LengthException('exactly one tokenXPath has to be provided');
        }
        $this->tokenXPath = $dom->tokenXPath;

        if (isset($dom->tokenValueXPath)) {
            if (count($dom->tokenValueXPath) != 1) {
                throw new \LengthException('exactly one tokenValueXPath has to be provided');
            }
            // convert old-style schema to new format
            $this->properties[] = Property::factory($n++, 'token', (string) $dom->tokenValueXPath[0], 'free text', true, false);
        }

        if (
            !isset($dom->properties) || !isset($dom->properties->property) || count($dom->properties->property) == 0 && count($this->properties) == 0
        ) {
            throw new \LengthException('no token properties defined');
        }
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

    public function loadDb(int $documentId): string {
        $this->documentId = $documentId;

        $schema = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><schema>';

        $schema .= '<namespaces>';
        $query  = $this->pdo->prepare("SELECT prefix, ns FROM documents_namespaces WHERE document_id = ?");
        $query->execute([$this->documentId]);
        while ($ns     = $query->fetch(PDO::FETCH_OBJ)) {
            $schema .= '<namespace><prefix>' . htmlspecialchars($ns->prefix) . '</prefix><uri>' . htmlspecialchars($ns->ns) . '</uri></namespace>';
        }
        $schema .= '</namespaces>';

        $query  = $this->pdo->prepare("SELECT token_xpath FROM documents WHERE document_id = ?");
        $query->execute([$this->documentId]);
        $data   = $query->fetch(PDO::FETCH_OBJ);
        $schema .= '<tokenXPath>' . htmlspecialchars($data->token_xpath) . '</tokenXPath>';

        $schema .= '<properties>';
        $query  = $this->pdo->prepare("
            SELECT property_xpath, type_id, name, read_only, optional, attributes 
            FROM properties 
            WHERE document_id = ? 
            ORDER BY ord
        ");
        $query->execute([$this->documentId]);
        while ($prop   = $query->fetch(PDO::FETCH_OBJ)) {
            $schema .= '<property>';
            $schema .= '<propertyName>' . htmlspecialchars($prop->name) . '</propertyName>';
            $schema .= '<propertyXPath>' . htmlspecialchars($prop->property_xpath) . '</propertyXPath>';
            $schema .= '<propertyType>' . htmlspecialchars($prop->type_id) . '</propertyType>';

            if ($prop->read_only) {
                $schema .= '<readOnly/>';
            }
            if ($prop->optional) {
                $schema .= '<optional/>';
            }

            $schema .= $this->propAttrToXml(json_decode($prop->attributes));
            
            $schema .= '</property>';
        }
        $schema .= '</properties>';

        $schema .= '</schema>';

        $this->loadXML($schema);

        return($schema);
    }

    /**
     * @param object|array<string>|string $v
     */ 
    private function propAttrToXml(object | array | string $v): string {
        if (!is_object($v) && !is_array($v)) {
            return htmlspecialchars($v);
        }
        $ret = '';
        if (is_array($v)) {
            foreach ($v as $i) {
                $ret .= $this->propAttrToXml($i);
            }
        } else {
            foreach ($v as $k => $i) {
                $ret .= "<$k>" . $this->propAttrToXml($i) . "</$k>";
            }
        }
        return $ret;
    }

    public function getTokenXPath(): string {
        return (string) $this->tokenXPath;
    }

    /**
     * 
     * @return array<string>
     */
    public function getNs(): array {
        return $this->namespaces;
    }

    /**
     * 
     * @return ArrayIterator<int, Property>
     */
    public function getIterator(): ArrayIterator {
        return new ArrayIterator($this->properties);
    }

    public function save(int $documentId): void {
        $query = $this->pdo->prepare("INSERT INTO documents_namespaces (document_id, prefix, ns) VALUES (?, ?, ?)");
        foreach ($this->getNs() as $prefix => $ns) {
            $query->execute([$documentId, $prefix, $ns]);
        }

        foreach ($this->properties as $prop) {
            $prop->save($this->pdo, $documentId);
        }
    }

}
