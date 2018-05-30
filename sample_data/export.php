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
 * 
 * Sample export script utilizing import\Document class.
 * 
 * To run it:
 * - assure proper database connection settings in config.inc.php
 * - set up configuration variables in lines 38-44
 * - run script from the command line 'php export.php'
 */
require_once __DIR__ . '/../vendor/autoload.php';
new \zozlak\util\ClassLoader();
$config = new \zozlak\util\Config(__DIR__ . '/../config.ini');

// token iterator class; if you do not know it, set to NULL
$iterator   = \acdhOeaw\tokeneditorModel\Document::XML_READER;
// document id in the tokeneditor database (see the documents table)
$documentId = 742;
// replate properties values in-place or add full <fs> structures
$inPlace    = true;
// path to the export file to create
$exportPath = '../sample_data/export.xml';

###########################################################

$pdo = new \PDO($config->get('dbConn'));
$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

$pb  = new \zozlak\util\ProgressBar(null, 10);
$doc = new import\Document($pdo);
$doc->loadDb($documentId, $iterator);
$doc->export($inPlace, $exportPath, $pb);
$pb->finish();
