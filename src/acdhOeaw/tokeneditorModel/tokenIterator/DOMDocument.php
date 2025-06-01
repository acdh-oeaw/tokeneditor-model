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

use acdhOeaw\tokeneditorModel\Document;
use acdhOeaw\tokeneditorModel\Token;

/**
 * Basic token iterator class using DOM parser (DOMDocument).
 * It is memory inefficient as every DOM parser but quite fast (at least as long 
 * Token class constuctor supports passing Token XML as a DOMElement object; if 
 * conversion to string was required, it would be very slow).
 *
 * @author zozlak
 */
class DOMDocument extends TokenIterator {

    private \DOMDocument $dom;
    private \DOMNodeList $tokens;

    public function __construct(string $xmlPath, Document $document) {
        parent::__construct($xmlPath, $document);
    }

    public function next(): void {
        $this->token = false;
        $this->pos++;
        if ($this->pos < $this->tokens->length) {
            $doc         = new \DOMDocument();
            $tokenNode   = $doc->importNode($this->tokens->item($this->pos), true);
            $this->token = new Token($tokenNode, $this->document);
        }
    }

    public function rewind(): void {
        $this->dom                     = new \DOMDocument();
        $this->dom->preserveWhiteSpace = false;
        $this->dom->LoadXML(file_get_contents($this->xmlPath));
        $xpath                         = new \DOMXPath($this->dom);
        foreach ($this->document->getSchema()->getNs() as $prefix => $ns) {
            $xpath->registerNamespace($prefix, $ns);
        }
        $this->tokens = $xpath->query($this->document->getSchema()->getTokenXPath());
        $this->pos    = -1;
        $this->next();
    }

    public function replaceToken(Token $new): void {
        $old = $this->tokens->item($new->getId() - 1);
        $new = $this->dom->importNode($new->getNode(), true);
        $old->parentNode->replaceChild($new, $old);
    }

    public function export(string | null $path = null): string | null {
        if ($path != '') {
            $this->dom->save($path);
            return null;
        } else {
            return $this->dom->saveXML();
        }
    }

}
