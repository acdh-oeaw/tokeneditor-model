<?php

/*
 * The MIT License
 *
 * Copyright 2018 zozlak.
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
 * Description of PropertyTest
 *
 * @author zozlak
 */
class PropertyTest extends \PHPUnit\Framework\TestCase {

    public function testFactory(): void {
        $p = Property::factory(1, 'test name', '.', 'test type', true, true);
        $this->assertEquals('test name', $p->getName());
        $this->assertEquals('.', $p->getXPath());
    }

    public function testManyXPaths(): void {
        $this->expectException(\LengthException::class);
        $this->expectExceptionMessage("exactly one propertyXPath has to be provided");
        $xml = "
            <property>
                <propertyName>test</propertyName>
                <propertyXPath>.</propertyXPath>
                <propertyXPath>.</propertyXPath>
                <propertyType>test</propertyType>
            </property>
        ";
        $el  = new \SimpleXMLElement($xml);
        new Property($el, 0);
    }

    public function testNoXPath(): void {
        $this->expectException(\LengthException::class);
        $this->expectExceptionMessage("exactly one propertyXPath has to be provided");
        $xml = "
            <property>
                <propertyName>test</propertyName>
                <propertyType>test</propertyType>
            </property>
        ";
        $el  = new \SimpleXMLElement($xml);
        new Property($el, 0);
    }

    public function testManyNames(): void {
        $this->expectException(\LengthException::class);
        $this->expectExceptionMessage("exactly one propertyName has to be provided");
        $xml = "
            <property>
                <propertyName>test</propertyName>
                <propertyName>test</propertyName>
                <propertyXPath>.</propertyXPath>
                <propertyType>test</propertyType>
            </property>
        ";
        $el  = new \SimpleXMLElement($xml);
        new Property($el, 0);
    }

    public function testNoName(): void {
        $this->expectException(\LengthException::class);
        $this->expectExceptionMessage("exactly one propertyName has to be provided");
        $xml = "
            <property>
                <propertyXPath>.</propertyXPath>
                <propertyType>test</propertyType>
            </property>
        ";
        $el  = new \SimpleXMLElement($xml);
        new Property($el, 0);
    }

    public function testReservedName(): void {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("property uses a reserved name");
        $xml = "
            <property>
                <propertyName>tokenId</propertyName>
                <propertyXPath>.</propertyXPath>
                <propertyType>test</propertyType>
            </property>
        ";
        $el  = new \SimpleXMLElement($xml);
        new Property($el, 0);
    }

    public function testManyTypes(): void {
        $this->expectException(\LengthException::class);
        $this->expectExceptionMessage("exactly one propertyType has to be provided");
        $xml = "
            <property>
                <propertyName>test</propertyName>
                <propertyXPath>.</propertyXPath>
                <propertyType>test</propertyType>
                <propertyType>test</propertyType>
            </property>
        ";
        $el  = new \SimpleXMLElement($xml);
        new Property($el, 0);
    }

    public function testNoType(): void {
        $this->expectException(\LengthException::class);
        $this->expectExceptionMessage("exactly one propertyType has to be provided");
        $xml = "
            <property>
                <propertyName>test</propertyName>
                <propertyXPath>.</propertyXPath>
            </property>
        ";
        $el  = new \SimpleXMLElement($xml);
        new Property($el, 0);
    }

    public function testGetters(): void {
        $xml   = "
            <property>
                <propertyName>test name</propertyName>
                <propertyXPath>.</propertyXPath>
                <propertyType>test type</propertyType>
                <readOnly/>
                <propertyValues>
                    <value>a</value>
                    <value>b</value>
                </propertyValues>
                <apiUrl>http://some.url</apiUrl>
            </property>
        ";
        $el    = new \SimpleXMLElement($xml);
        $p     = new Property($el, 0);
        $this->assertEquals('test name', $p->getName());
        $this->assertEquals('.', $p->getXPath());
        $this->assertEquals('test type', $p->getType());
        $this->assertEquals(true, $p->getReadOnly());
        $this->assertEquals(false, $p->getOptional());
        $this->assertEquals(0, $p->getOrd());
        $this->assertEquals([(object) ['value' => 'a'], (object) ['value' => 'b']], $p->getAttribute('propertyValues'));
        $this->assertEquals('http://some.url', $p->getAttribute('apiUrl'));
        $props = (object) [
                'apiUrl'         => 'http://some.url',
                'propertyValues' => [(object) ['value' => 'a'], (object) ['value' => 'b']],
        ];
        $this->assertEquals($props, $p->getAttributes());

        $xml = "
            <property>
                <propertyName>test name</propertyName>
                <propertyXPath>.</propertyXPath>
                <propertyType>test type</propertyType>
                <optional/>
            </property>
        ";
        $el  = new \SimpleXMLElement($xml);
        $p   = new Property($el, 0);
        $this->assertEquals('test name', $p->getName());
        $this->assertEquals('.', $p->getXPath());
        $this->assertEquals('test type', $p->getType());
        $this->assertEquals(false, $p->getReadOnly());
        $this->assertEquals(true, $p->getOptional());
        $this->assertEquals(0, $p->getOrd());
        $this->assertEquals(new \stdClass(), $p->getAttributes());
        $this->expectException('InvalidArgumentException');
        $p->getAttribute('propertyValues');
    }

}
