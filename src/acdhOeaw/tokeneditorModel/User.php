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
use BadMethodCallException;

/**
 * Description of User
 *
 * @author zozlak
 */
class User {

    const ROLE_NONE   = 'none';
    const ROLE_EDITOR = 'editor';
    const ROLE_OWNER  = 'owner';

    /**
     *
     * @var PDO
     */
    private $pdo;
    private $documentId;
    private $users;

    /**
     * 
     * @param PDO $pdo
     * @param int $documentId
     */
    public function __construct(PDO $pdo, int $documentId) {
        $this->pdo        = $pdo;
        $this->documentId = $documentId;
        $this->fetchUsers();
    }

    /**
     * Returns a list of all users connected with the document.
     * @return array
     */
    public function getUsers(): array {
        return $this->users;
    }

    /**
     * Checks if a given user is a document owner.
     * @param string $userId
     * @return bool
     */
    public function isOwner(string $userId): bool {
        return isset($this->users[$userId]) && $this->users[$userId]->role === 'owner';
    }

    /**
     * Checks if a given user has (at least) editor rights.
     * @param string $userId
     * @param bool $strict when `true` it is checked if a given user is an editor,
     *   when `false` it is checked if a given user has at least editor rights
     * @return bool
     */
    public function isEditor(string $userId, bool $strict = false): bool {
        $matches = $strict ? ['editor'] : ['editor', 'owner'];
        return isset($this->users[$userId]) && in_array($this->users[$userId]->role, $matches);
    }

    /**
     * Sets given user's role on a document.
     * 
     * If a users doesn't exist, it is automatically created.
     * @param string $userId user's identifier
     * @param string $role role - User::ROLE_NONE, User::ROLE_EDITOR or User::ROLE_OWNER
     * @param string $name optional alias for the $userId
     */
    public function setRole(string $userId, string $role, string $name = null) {
        if (!in_array($role, [self::ROLE_NONE, self::ROLE_EDITOR, self::ROLE_OWNER])) {
            throw new BadMethodCallException('Bad role specified', 400);
        }

        $query = $this->pdo->prepare("
            INSERT INTO users (user_id, name) VALUES (?, ?)
            ON CONFLICT (user_id) " . (strlen($name) > 0 ? "DO UPDATE" : "DO NOTHING")
        );
        $query->execute([$userId, $name]);

        $query = $this->pdo->prepare("
            INSERT INTO documents_users (document_id, user_id, role) VALUES (?, ?, ?)
            ON CONFLICT (document_id, user_id) DO UPDATE
        ");
        $query->execute([$this->documentId, $userId, $role]);

        $this->fetchUsers();
    }

    private function fetchUsers() {
        $query       = $this->pdo->prepare("SELECT user_id, role FROM documents_users WHERE document_id = ?");
        $query->execute([$this->documentId]);
        $this->users = $query->fetchAll(PDO::FETCH_OBJ);
    }

}
