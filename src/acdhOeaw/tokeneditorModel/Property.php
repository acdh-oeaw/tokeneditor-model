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
use stdClass;
use SimpleXMLElement;

/**
 * Description of Property
 *
 * @author zozlak
 */
class Property {

    static public function factory(int $ord, string $name, string $xpath,
                                   string $type, bool $readOnly, bool $optional): Property {
        $xml       = "
            <property>
                <propertyName>%s</propertyName>
                <propertyXPath>%s</propertyXPath>
                <propertyType>%s</propertyType>
                %s
                %s
            </property>
        ";
        $readOnly  = $readOnly ? '<readOnly/>' : '';
        $optional  = $optional ? '<optional/>' : '';
        $xml = sprintf($xml, htmlspecialchars($name), htmlspecialchars($xpath), htmlspecialchars($type), $readOnly, $optional);
        $el  = new SimpleXMLElement($xml);
        return new Property($el, $ord);
    }

    private string $xpath;
    private string $type;
    private string $name;
    private int $ord;
    private bool $xml;
    private bool $readOnly   = false;
    private bool $optional   = false;
    private stdClass $attributes;

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

        $this->attributes = new \stdClass();
        foreach ($xml as $k => $v) {
            if (!in_array($k, ['propertyXPath', 'propertyType', 'propertyName', 'ord',
                    'readOnly', 'optional'])) {
                $this->attributes->$k = $this->parseAttributes($v);
            }
        }

        $this->parseAttributes($xml);

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

    /**
     * @return array<stdClass>|string
     */
    private function parseAttributes(SimpleXMLElement $xml): array | string {
        if ($xml->count() === 0) {
            return (string) $xml;
        }
        $ret = [];
        foreach ($xml as $k => $v) {
            $ret[] = (object) [$k => $this->parseAttributes($v)];
        }
        return $ret;
    }

    public function getXPath(): string {
        return $this->xpath;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getType(): string {
        return $this->type;
    }

    public function getOrd(): int {
        return $this->ord;
    }

    public function getReadOnly(): bool {
        return $this->readOnly;
    }

    public function getOptional(): bool {
        return $this->optional;
    }

    public function getAttribute(string $property): Property {
        if (!isset($this->attributes->$property)) {
            throw new \InvalidArgumentException('No such property');
        }
        return $this->attributes->$property;
    }

    public function getAttributes(): stdClass {
        return $this->attributes;
    }

    public function save(PDO $pdo, int $documentId): void {
        $query = $pdo->prepare("
            INSERT INTO properties (document_id, property_xpath, type_id, name, read_only, optional, ord, attributes) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $query->execute([$documentId, $this->xpath, $this->type, $this->name,
            (int) $this->readOnly, (int) $this->optional, $this->ord, json_encode($this->attributes)]);
    }

}
