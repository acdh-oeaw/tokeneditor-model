<?php

/*
 * The MIT License
 *
 * Copyright 2019 Austrian Centre for Digital Humanities.
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
 * Description of ExportCsv
 *
 * @author zozlak
 */
class ExportCsv implements ExportTableInterface {

    private string $delimiter;

    /**
     * @var resource|bool
     */
    private $file = false;

    public function __construct(string $path, string $delimiter = ',') {
        $this->file      = fopen($path, 'w');
        $this->delimiter = $delimiter;
    }

    public function __destruct() {
        if ($this->file !== false) {
            $this->end();
        }
    }

    public function begin(Schema $schema): void {
        $header = ['tokenId'];
        foreach ($schema as $property) {
            $header[] = $property->getName();
        }
        fputcsv($this->file, $header, $this->delimiter);
    }

    public function end(): void {
        fclose($this->file);
        $this->file = false;
    }

    public function writeRow(Token $token, bool $replace): void {
        fputcsv($this->file, $token->asArray(true), $this->delimiter);
    }

}
