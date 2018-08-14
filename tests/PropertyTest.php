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

    public function testFactory() {
        $p = Property::factory(1, 'test name', '.', 'test type', true, ['a', 'b',
                'c']);
        $this->assertEquals('test name', $p->getName());
        $this->assertEquals('.', $p->getXPath());
    }

    public function testManyXPaths() {
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

    public function testNoXPath() {
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

    public function testManyNames() {
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

    public function testNoName() {
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

    public function testReservedName() {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("property uses a reserved name");
        $xml = "
            <property>
                <propertyName>token_id</propertyName>
                <propertyXPath>.</propertyXPath>
                <propertyType>test</propertyType>
            </property>
        ";
        $el  = new \SimpleXMLElement($xml);
        new Property($el, 0);
    }

    public function testManyTypes() {
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

    public function testNoType() {
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

}
