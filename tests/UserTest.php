<?php

/*
 * The MIT License
 *
 * Copyright 2018 Austrian Centre for Digital Humanities.
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

/**
 * Description of UserTest
 *
 * @author zozlak
 */
class UserTest extends \PHPUnit\Framework\TestCase {

    static private string $saveDir      = 'build';
    static private string $connSettings = 'pgsql: host=127.0.0.1 port=5432 user=postgres password=postgres';
    static private PDO $pdo;
    static private int $docId;

    static public function setUpBeforeClass(): void {
        self::$pdo = new PDO(self::$connSettings);
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$pdo->beginTransaction();
        self::$pdo->query("TRUNCATE documents CASCADE");
        self::$pdo->query("TRUNCATE users CASCADE");

        $doc         = new Document(self::$pdo);
        $doc->loadFile('tests/testtext.xml', 'tests/testtext-schema.xml', 'test');
        $doc->save(self::$saveDir);
        self::$docId = $doc->getId();
    }

    public static function tearDownAfterClass(): void {
        self::$pdo->rollback();
        unlink(self::$saveDir . '/' . self::$docId . '.xml');
    }

    protected function setUp(): void {
        parent::setUp();
    }

    protected function tearDown(): void {
        parent::tearDown();
    }

    public function testUsers(): void {
        $u = new User(self::$pdo, self::$docId);

        $this->assertEquals([], $u->getUsers());

        $u->setRole('aaa', User::ROLE_OWNER);
        $aaa = (object) ['userId' => 'aaa', 'role' => User::ROLE_OWNER, 'name' => null];
        $this->assertEquals([$aaa], $u->getUsers());
        $this->assertEquals($aaa, $u->getUser('aaa'));
        $this->assertEquals(true, $u->isOwner('aaa'));
        $this->assertEquals(true, $u->isEditor('aaa'));
        $this->assertEquals(false, $u->isEditor('aaa', true));
        $this->assertEquals(true, $u->isViewer('aaa'));
        $this->assertEquals(false, $u->isViewer('aaa', true));

        $u->setRole('bbb', User::ROLE_EDITOR);
        $bbb = (object) ['userId' => 'bbb', 'role' => User::ROLE_EDITOR, 'name' => null];
        $this->assertEquals([$aaa, $bbb], $u->getUsers());
        $this->assertEquals($bbb, $u->getUser('bbb'));
        $this->assertEquals(false, $u->isOwner('bbb'));
        $this->assertEquals(true, $u->isEditor('bbb'));
        $this->assertEquals(true, $u->isEditor('bbb', true));
        $this->assertEquals(true, $u->isViewer('bbb'));
        $this->assertEquals(false, $u->isViewer('bbb', true));

        $u->setRole('ccc', User::ROLE_VIEWER, 'xxx');
        $ccc = (object) ['userId' => 'ccc', 'role' => User::ROLE_VIEWER, 'name' => 'xxx'];
        $this->assertEquals([$aaa, $bbb, $ccc], $u->getUsers());
        $this->assertEquals($ccc, $u->getUser('ccc'));
        $this->assertEquals(false, $u->isOwner('ccc'));
        $this->assertEquals(false, $u->isEditor('ccc'));
        $this->assertEquals(false, $u->isEditor('ccc', true));
        $this->assertEquals(true, $u->isViewer('ccc'));
        $this->assertEquals(true, $u->isViewer('ccc', true));
        
        $u->setRole('bbb', User::ROLE_NONE, 'zzz');
        $bbb2 = (object) ['userId' => 'bbb', 'role' => User::ROLE_NONE, 'name' => 'zzz'];
        $this->assertEquals([$aaa, $bbb2, $ccc], $u->getUsers());
        $this->assertEquals($bbb2, $u->getUser('bbb'));
        $this->assertEquals(false, $u->isOwner('bbb'));
        $this->assertEquals(false, $u->isEditor('bbb'));
        $this->assertEquals(false, $u->isEditor('bbb', true));
        $this->assertEquals(false, $u->isViewer('bbb'));
        $this->assertEquals(false, $u->isViewer('bbb', true));

        $this->assertEquals(false, $u->isOwner('unknownUser'));
        $this->assertEquals(false, $u->isEditor('unknownUser'));
        $this->assertEquals(false, $u->isViewer('unknownUser'));
    }

    public function testAtLeastOneOwner(): void {
        $u = new User(self::$pdo, self::$docId);

        $this->expectException(\RuntimeException::class);
        $u->setRole('aaa', User::ROLE_NONE);
    }

    public function testRoleParam(): void {
        $u = new User(self::$pdo, self::$docId);

        $this->expectException(\BadMethodCallException::class);
        $u->setRole('aaa', 'xxx');
    }

    public function testNoUser(): void {
        $u = new User(self::$pdo, self::$docId);

        $this->expectException(\BadMethodCallException::class);
        $u->getUser('zzz');
    }
}
