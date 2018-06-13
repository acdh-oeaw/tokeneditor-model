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

use PDO;
use RuntimeException;
use zozlak\util\ProgressBar;

/**
 * Description of Datafile
 *
 * @author zozlak
 */
class Editor {
    
    const PDO          = '\acdhOeaw\tokeneditorModel\tokenIterator\PDO';

    private $pdo;
    private $documentId;
    private $editorId;

    /**
     * 
     * @param type $path
     * @param \acdhOeaw\tokeneditor\Schema $schema
     * @throws RuntimeException
     */
    public function __construct(PDO $pdo) {
        $this->pdo    = $pdo;
        $this->schema = new Schema($this->pdo);
    }

    /**
     * 
     * Get the Editor from the database, based on the documentID
     * 
     * @param string $documentId
     * @param string $editorId
     * @return \stdClass
     */
    public function getEditor(string $documentId, string $editorId): \stdClass {
        $this->documentId = $documentId;
        $this->editorId = $editorId;
        
        $query = $this->pdo->prepare(" select 
            du.editor, u.name, u.user_id 
            from documents_users as du
            left join users as u ON u.user_id = du.user_id
            where 
            du.document_id = ? and du.user_id = ?
        ");
        
        $query->execute([$this->documentId, $this->editorId]);
        $data = $query->fetch(PDO::FETCH_OBJ);
       
        if($data === false ){
            return $data = new \stdClass();
        }
        return $data;
        
    }
    
    /**
     * 
     * Check the user in the users table by user_id
     * 
     * @param string $userId
     * @return bool
     */
    public function checkUserinUsersTable(string $userId) : bool {
        $query = $this->pdo->prepare("
            select 
                * 
            from users
            where 
                user_id = ?
        ");
        
        $query->execute([$userId]);
        $data = $query->fetch(PDO::FETCH_OBJ);
       
        if($data === false ){
            return false;
        }
        return true;
    }
    
    /**
     * 
     * Add the user to the users table as an editor
     * 
     * @param string $userId
     * @param string $name
     * @return bool
     */
    public function addUserToDB(string $userId, string $name = null) :bool {
        
        $query = $this->pdo->prepare("
            INSERT INTO users (user_id, name) VALUES (?, ?);
        ");
        $query->execute([$userId, $name]);
        if($query->rowCount() > 0){
            return true;
        }else {
            return false;
        }
            
    }
    
    /**
     * 
     * Add the user to the document as an editor
     * 
     * @param string $documentId
     * @param string $editorId
     * @param string $name
     * @return bool
     * @throws RuntimeException
     */
    public function addEditor(string $documentId, string $editorId, string $name = null): bool {
        $this->documentId = $documentId;
        $this->editorId = $editorId;
        $this->schema->loadDb($this->documentId);
        
        //if the user is not exists, then we need to create the user
        if($this->checkUserinUsersTable($this->editorId) === false ){
            if($this->addUserToDB($this->editorId, $name) === false){
                 throw new RuntimeException('Error during the: "addUserToDB" function', 400);
            }
        }
        
        $query = $this->pdo->prepare(" 
            INSERT INTO documents_users 
                (document_id, user_id, editor)
            VALUES 
            (?, ?, 'editor');
        ");
        
        $query->execute([$this->documentId, $this->editorId]);
        
        if($query->rowCount() > 0){
            return true;
        }else {
            //if the insert wasnt successful
            return false;
        }
    }

    /**
     * 
     * @return integer
     */
    public function getId() {
        return $this->documentId;
    }

    /**
     * 
     * @return PDO
     */
    public function getPdo() {
        return $this->pdo;
    }
}
