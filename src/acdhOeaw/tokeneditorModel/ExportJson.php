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
 * Description of ExportJson
 *
 * @author zozlak
 */
class ExportJson implements ExportTableInterface  {
    
    private $file;
    private $n = 0;
    
    public function __construct(string $path) {
        $this->file = fopen($path, 'w');
        fwrite($this->file, '[');
    }

    public function __destruct() {
        if ($this->file) {
            $this->end();
        }
    }    
    public function begin(Schema $schema): void {
        
    }

    public function end(): void {
        fwrite($this->file, ']');
        fclose($this->file);
        $this->file = null;
    }

    public function writeRow(Token $token, bool $replace): void {
        if ($this->n > 0) {
            fwrite($this->file, ',');
        }
        fwrite($this->file, json_encode($token->asArray($replace)));
        $this->n++;
    }

}
