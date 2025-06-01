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

use BadMethodCallException;
use PDO;
use RuntimeException;
use stdClass;

/**
 * Description of User
 *
 * @author zozlak
 */
class User {

    const ROLE_NONE   = 'none';
    const ROLE_VIEWER = 'viewer';
    const ROLE_EDITOR = 'editor';
    const ROLE_OWNER  = 'owner';

    private PDO $pdo;
    private int $documentId;
    /**
     * @var array<object>
     */
    private array $users;

    public function __construct(PDO $pdo, int $documentId) {
        $this->pdo        = $pdo;
        $this->documentId = $documentId;
        $this->fetchUsers();
    }

    /**
     * Returns a list of all users connected with the document.
     * @return array<object>
     */
    public function getUsers(): array {
        return $this->users;
    }

    public function getUser(string $userId): stdClass {
        foreach ($this->users as $i) {
            if ($i->userId === $userId) {
                return $i;
            }
        }
        throw new BadMethodCallException('No such user');
    }

    /**
     * Checks if a given user is a document owner.
     * @param string $userId
     * @return bool
     */
    public function isOwner(string $userId): bool {
        try {
            $user = $this->getUser($userId);
            return $user->role === self::ROLE_OWNER;
        } catch (BadMethodCallException $ex) {
            return false;
        }
    }

    /**
     * Checks if a given user has (at least) editor rights.
     * @param string $userId
     * @param bool $strict when `true` it is checked if a given user is an editor,
     *   when `false` it is checked if a given user has at least editor rights
     * @return bool
     */
    public function isEditor(string $userId, bool $strict = false): bool {
        $matches = $strict ? [self::ROLE_EDITOR] : [self::ROLE_EDITOR, self::ROLE_OWNER];
        try {
            $user = $this->getUser($userId);
            return in_array($user->role, $matches);
        } catch (BadMethodCallException $ex) {
            return false;
        }
    }

    /**
     * Checks if a given user has (at least) viewer rights.
     * @param string $userId
     * @param bool $strict when `true` it is checked if a given user is a viewer,
     *   when `false` it is checked if a given user has at least viewer rights
     * @return bool
     */
    public function isViewer(string $userId, bool $strict = false): bool {
        $matches = $strict ? [self::ROLE_VIEWER] : [self::ROLE_VIEWER, self::ROLE_EDITOR,
            self::ROLE_OWNER];
        try {
            $user = $this->getUser($userId);
            return in_array($user->role, $matches);
        } catch (BadMethodCallException $ex) {
            return false;
        }
    }

    /**
     * Sets given user's role on a document.
     * 
     * If a users doesn't exist, it is automatically created.
     * @param string $userId user's identifier
     * @param string $role role - User::ROLE_NONE, User::ROLE_EDITOR or User::ROLE_OWNER
     * @param string|null $name optional alias for the $userId
     */
    public function setRole(string $userId, string $role, string | null $name = null): void {
        if (!in_array($role, [self::ROLE_NONE, self::ROLE_VIEWER, self::ROLE_EDITOR,
                self::ROLE_OWNER])) {
            throw new BadMethodCallException('Bad role parameter value', 400);
        }

        $query = $this->pdo->prepare("
            INSERT INTO users (user_id, name) VALUES (?, ?)
            ON CONFLICT (user_id) " . (strlen((string) $name) > 0 ? "DO UPDATE SET name = EXCLUDED.name" : "DO NOTHING")
        );
        $query->execute([$userId, $name]);

        $query = $this->pdo->prepare("
            INSERT INTO documents_users (document_id, user_id, role) VALUES (?, ?, ?)
            ON CONFLICT (document_id, user_id) DO UPDATE SET role = EXCLUDED.role
        ");
        $query->execute([$this->documentId, $userId, $role]);

        $this->fetchUsers();

        $owners = 0;
        foreach ($this->users as $i) {
            $owners += $i->role === self::ROLE_OWNER;
        }
        if ($owners === 0) {
            throw new RuntimeException('Can not revoke privileges from the last document owner', 400);
        }
    }

    private function fetchUsers(): void {
        $query       = $this->pdo->prepare('
            SELECT user_id AS "userId", role, name 
            FROM 
                documents_users 
                JOIN users USING (user_id)
            WHERE document_id = ?
            ORDER BY user_id
        ');
        $query->execute([$this->documentId]);
        $this->users = $query->fetchAll(PDO::FETCH_OBJ);
    }

}
