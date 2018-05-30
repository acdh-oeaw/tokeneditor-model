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
 * Sample import script utilizing model\Document class.
 * 
 * To run it:
 * - assure proper database connection settings in config.ini
 * - set up configuration variables in lines 37-49
 * - run script from the command line 'php import.php'
 */
require_once __DIR__ . '/../vendor/autoload.php';
new \zozlak\util\ClassLoader();
$config = new \zozlak\util\Config(__DIR__ . '/../config.ini');

// token iterator class; if you do not know it, set to NULL
$iterator   = \acdhOeaw\tokeneditorModel\Document::DOM_DOCUMENT;
// if processed data should be stored in the database
$save       = false;
// allows to limit number of processed tokens (put 0 to process all)
$limit      = 0;
// path to the XML file describing schema
$schemaPath = __DIR__ . '/marmot-schema.xml';
// path to the XML file with data
$dataPath   = __DIR__ . '/marmot.xml';
// path to the directory where imported XMLs are stored
$saveDir    = 'docStorage';
// simply skip broken tokens (true) or break the import on first broken error (false)
$skipErrors = true;

###########################################################

$pdo = new \PDO($config->get('db'));
$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
$pdo->beginTransaction();

$name = basename($name);
$pb   = new \zozlak\util\ProgressBar(null, 10);

$doc = new \acdhOeaw\tokeneditorModel\Document($pdo);
$doc->loadFile($dataPath, $schemaPath, $name, $iterator);
$doc->save($saveDir, $limit, $pb, $skipErrors);

if ($save) {
    $pdo->commit();
}
$pb->finish();
