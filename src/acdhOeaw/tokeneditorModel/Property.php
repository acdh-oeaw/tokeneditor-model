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

use LengthException;
use PDO;
use SimpleXMLElement;

/**
 * Description of Property
 *
 * @author zozlak
 */
class Property {

    static public function factory(int $ord, string $name, string $xpath,
                                   string $type, bool $readOnly, bool $optional,
                                   array $values = []): Property {
        $xml       = "
            <property>
                <propertyName>%s</propertyName>
                <propertyXPath>%s</propertyXPath>
                <propertyType>%s</propertyType>
                %s
                %s
                %s
            </property>
        ";
        $readOnly  = $readOnly ? '<readOnly/>' : '';
        $optional  = $optional ? '<optional/>' : '';
        $valuesXml = '';
        if (count($values) > 0) {
            $valuesXml = '<propertyValues>';
            foreach ($values as $i) {
                $valuesXml .= '<value>' . htmlspecialchars($i) . '</value>';
            }
            $valuesXml .= '</propertyValues>';
        }
        $xml = sprintf($xml, htmlspecialchars($name), htmlspecialchars($xpath), htmlspecialchars($type), $readOnly, $optional, $valuesXml);
        $el  = new SimpleXMLElement($xml);
        return new Property($el, $ord);
    }

    private $xpath;
    private $type;
    private $name;
    private $ord;
    private $readOnly   = false;
    private $optional   = false;
    private $properties = null;

    /**
     * 
     * @param SimpleXMLElement $xml
     * @param int $ord
     * @throws LengthException
     */
    public function __construct(SimpleXMLElement $xml, int $ord) {
        $this->ord = $ord;

        if (!isset($xml->propertyXPath) || count($xml->propertyXPath) != 1) {
            throw new LengthException('exactly one propertyXPath has to be provided');
        }
        $this->xpath = (string) $xml->propertyXPath[0];

        if (!isset($xml->propertyType) || count($xml->propertyType) != 1) {
            throw new LengthException('exactly one propertyType has to be provided');
        }
        $this->type = (string) $xml->propertyType;

        if (!isset($xml->propertyName) || count($xml->propertyName) != 1) {
            throw new LengthException('exactly one propertyName has to be provided');
        }
        $this->name = (string) $xml->propertyName;
        if (in_array($this->name, ['tokenId', '_offset', '_pagesize', '_docid',
                '_order'])) {
            throw new \RuntimeException('property uses a reserved name');
        }

        $this->properties = new \stdClass();
        foreach ($xml as $k => $v) {
            if (!in_array($k, ['propertyXPath', 'propertyType', 'propertyName', 'ord',
                    'readOnly', 'optional'])) {
                $this->properties->$k = $this->parseProperties($v);
            }
        }

        $this->parseProperties($xml);

        if (isset($xml->readOnly)) {
            $this->readOnly = true;
        }

        if (isset($xml->optional)) {
            $this->optional = true;
        }

        if (isset($xml->xml)) {
            $this->xml = true;
        }
    }

    private function parseProperties(SimpleXMLElement $xml) {
        if ($xml->count() === 0) {
            return (string) $xml;
        }
        $ret = [];
        foreach ($xml as $k => $v) {
            $ret[] = (object) [$k => $this->parseProperties($v)];
        }
        return $ret;
    }

    /**
     * 
     * @return string
     */
    public function getXPath() {
        return $this->xpath;
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
     * @return string
     */
    public function getType() {
        return $this->type;
    }

    /**
     * 
     * @return int
     */
    public function getOrd() {
        return $this->ord;
    }

    /**
     * 
     * @return bool
     */
    public function getReadOnly() {
        return $this->readOnly;
    }

    /**
     * 
     * @return bool
     */
    public function getOptional() {
        return $this->optional;
    }

    /**
     * 
     * @return mixed
     */
    public function getProperty(string $property) {
        if (!isset($this->properties->$property)) {
            throw new \InvalidArgumentException('No such property');
        }
        return $this->properties->$property;
    }

    /**
     * 
     * @return mixed
     */
    public function getProperties() {
        return $this->properties;
    }
    
    /**
     * 
     * @param PDO $pdo
     * @param integer $documentId
     */
    public function save(PDO $pdo, int $documentId) {
        $query = $pdo->prepare("
            INSERT INTO properties (document_id, property_xpath, type_id, name, read_only, optional, ord, properties) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $query->execute([$documentId, $this->xpath, $this->type, $this->name,
            (int) $this->readOnly, (int) $this->optional, $this->ord, json_encode($this->properties)]);
    }

}
