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

use SplFileObject;
use acdhOeaw\tokeneditorModel\Document;
use acdhOeaw\tokeneditorModel\Token;

/**
 * Token iterator class developed using stream XML parser (\XMLReader).
 * It is memory efficient (requires constant memory no matter XML size)
 * and very fast but (at least at the moment) can handle token XPaths
 * specyfying single node name only.
 * This is because \XMLReader does not provide any way to execute XPaths on it
 * and I was to lazy to implement more compound XPath handling. Maybe it will
 * be extended in the future.
 *
 * @author zozlak
 */
class XMLReader extends TokenIterator {

    private \XMLReader $reader;
    private SplFileObject | null $outStream = null;
    private string $tokenXPath;

    /**
     * 
     * @throws \RuntimeException
     */
    public function __construct(string $xmlPath, Document $document,
                                bool $export = false) {
        parent::__construct($xmlPath, $document);

        $this->reader = new \XMLReader();
        $tokenXPath   = $this->document->getSchema()->getTokenXPath();
        if (!preg_match('|^//[a-zA-Z0-9_:.]+$|', $tokenXPath)) {
            throw new \RuntimeException('Token XPath is too complicated for \XMLReader');
        }
        $this->tokenXPath = mb_substr($tokenXPath, 2);
        $nsPrefixPos      = mb_strpos($this->tokenXPath, ':');
        if ($nsPrefixPos !== false) {
            $prefix = mb_substr($this->tokenXPath, 0, $nsPrefixPos);
            $ns     = $this->document->getSchema()->getNs();
            if (isset($ns[$prefix])) {
                $this->tokenXPath = $ns[$prefix] . mb_substr($this->tokenXPath, $nsPrefixPos);
            }
        }

        if ($export) {
            $filename        = tempnam(sys_get_temp_dir(), '');
            $this->outStream = new SplFileObject($filename, 'w');
            $this->outStream->fwrite('<?xml version="1.0" encoding="UTF-8" standalone="no"?>' . "\n");
        }
    }

    public function __destruct() {
        if ($this->outStream) {
            unlink($this->outStream->getRealPath());
        }
    }

    public function next(): void {
        if ($this->token) {
            $tmp = $this->token->getNode()->ownerDocument->saveXML();
            $tmp = trim(str_replace('<?xml version="1.0"?>' . "\n", '', $tmp));
            $this->outStream->fwrite($tmp);
        }
        $this->pos++;
        $this->token = false;
        $firstStep   = $this->pos !== 0;
        do {
            // in first step skip previous token subtree
            $res       = $firstStep ? $this->reader->next() : $this->reader->read();
            $firstStep = false;
            $name      = null;
            if ($this->reader->nodeType === \XMLReader::ELEMENT) {
                $nsPrefixPos = mb_strpos($this->reader->name, ':');
                $name        = ($this->reader->namespaceURI ? $this->reader->namespaceURI . ':' : '') .
                    ($nsPrefixPos ? mb_substr($this->reader->name, $nsPrefixPos + 1) : $this->reader->name);
            }
            // rewrite nodes which are not tokens to the output
            if ($this->outStream && $res && $name !== $this->tokenXPath) {
                $this->writeElement();
            }
        } while ($res && $name !== $this->tokenXPath);
        if ($res) {
            $tokenDom    = new \DOMDocument();
            $tokenDom->loadXml($this->reader->readOuterXml());
            $this->token = new Token($tokenDom->documentElement, $this->document);
        }
    }

    public function rewind(): void {
        $this->reader->open($this->xmlPath);
        $this->pos = -1;
        $this->next();
    }

    /**
     * 
     * @throws \BadMethodCallException
     */
    public function export(string | null $path = null): string | null {
        if ($this->outStream === null) {
            throw new \RuntimeException('Set $export to true when calling object constructor to enable export');
        }
        $currPath        = $this->outStream->getRealPath();
        $this->outStream = null;
        if (!empty($path)) {
            rename($currPath, $path);
            return null;
        } else {
            $data = file_get_contents($currPath);
            unlink($currPath);
            return $data;
        }
    }

    public function replaceToken(Token $new): void {
        if ($new->getId() != $this->token->getId()) {
            throw new \RuntimeException('Only current token can be replaced when you are using \XMLReader token iterator');
        }
        $old = $this->token->getNode();
        $new = $old->ownerDocument->importNode($new->getNode(), true);
        $old->parentNode->replaceChild($new, $old);
    }

    /**
     * Rewrites current node to the output.
     * Used to rewrite nodes which ate not tokens.
     */
    private function writeElement(): void {
        $el = $this->getElementBeg();
        $el .= $this->getElementContent();
        $el .= $this->getElementEnd();
        $this->outStream->fwrite($el);
    }

    /**
     * Returns node beginning ('<', '<![CDATA[', etc.) for a current node.
     * Used to rewrite nodes which are not tokens to the output.
     */
    private function getElementBeg(): string {
        $beg   = '';
        $types = [\XMLReader::ELEMENT, \XMLReader::END_ELEMENT];
        $beg   .= in_array($this->reader->nodeType, $types) ? '<' : '';
        $beg   .= $this->reader->nodeType === \XMLReader::END_ELEMENT ? '/' : '';
        $beg   .= $this->reader->nodeType === \XMLReader::PI ? '<?' : '';
        $beg   .= $this->reader->nodeType === \XMLReader::COMMENT ? '<!--' : '';
        $beg   .= $this->reader->nodeType === \XMLReader::CDATA ? '<![CDATA[' : '';
        return $beg;
    }

    /**
     * Returns node ending ('>', ']]>', etc.) for a current node.
     * Used to rewrite nodes which are not tokens to the output.
     */
    private function getElementEnd(): string {
        $this->reader->moveToElement();
        $end   = '';
        $end   .= $this->reader->isEmptyElement ? '/' : '';
        $end   .= $this->reader->nodeType === \XMLReader::CDATA ? ']]>' : '';
        $end   .= $this->reader->nodeType === \XMLReader::COMMENT ? '-->' : '';
        $end   .= $this->reader->nodeType === \XMLReader::PI ? '?>' : '';
        $types = [\XMLReader::ELEMENT, \XMLReader::END_ELEMENT];
        $end   .= in_array($this->reader->nodeType, $types) ? '>' : '';
        return $end;
    }

    /**
     * Returns node content (e.g. 'prefix:tag attr="value"' or comment/cdata 
     * text) for a current node.
     * Used to rewrite nodes which are not tokens to the output.
     */
    private function getElementContent(): string {
        $str = '';

        $types = [\XMLReader::ELEMENT, \XMLReader::END_ELEMENT, \XMLReader::PI];
        if (in_array($this->reader->nodeType, $types)) {
            $str .= ($this->reader->prefix ? $this->reader->prefix . ':' : '');
            $str .= $this->reader->name;
        }
        $types = [\XMLReader::ELEMENT, \XMLReader::PI];
        if (in_array($this->reader->nodeType, $types)) {
            while ($this->reader->moveToNextAttribute()) {
                $str .= ' ';
                $str .= $this->reader->name;
                $str .= '="' . $this->reader->value . '"';
            }
        } else {
            $str .= $this->reader->value;
        }

        return $str;
    }

}
