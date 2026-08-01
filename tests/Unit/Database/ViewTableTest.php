<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use Gemvc\Database\Table;
use Gemvc\Database\TableGenerator;
use Gemvc\Database\TableMigrateOrder;
use Gemvc\Database\ViewGenerator;
use Gemvc\Database\ViewTable;
use Gemvc\Database\Schema;
use PDO;
use PHPUnit\Framework\TestCase;

final class ViewUsersFixture extends Table
{
    public int $id = 0;
    public string $email = '';

    /** @var array<string, string> */
    protected array $_type_map = [
        'id' => 'int',
        'email' => 'string',
    ];

    public function getTable(): string
    {
        return 'view_users_fixture';
    }

    /** @return array<\Gemvc\Database\SchemaConstraint> */
    public function defineSchema(): array
    {
        return [];
    }
}

final class ViewRolesFixture extends Table
{
    public int $id = 0;
    public int $user_id = 0;
    public string $name = '';

    /** @var array<string, string> */
    protected array $_type_map = [
        'id' => 'int',
        'user_id' => 'int',
        'name' => 'string',
    ];

    public function getTable(): string
    {
        return 'view_roles_fixture';
    }

    /** @return array<\Gemvc\Database\SchemaConstraint> */
    public function defineSchema(): array
    {
        return [
            Schema::foreignKey('user_id', 'view_users_fixture.id'),
        ];
    }
}

final class UserAccessViewFixture extends ViewTable
{
    public int $user_id = 0;
    public string $email = '';
    public string $role_name = '';

    /** @var array<string, string> */
    protected array $_type_map = [
        'user_id' => 'int',
        'email' => 'string',
        'role_name' => 'string',
    ];

    public function getTable(): string
    {
        return 'user_access_view_fixture';
    }

    /** @return list<class-string<Table>> */
    public function viewDependsOn(): array
    {
        return [ViewUsersFixture::class, ViewRolesFixture::class];
    }

    public function defineView(): string
    {
        $u = (new ViewUsersFixture())->getTable();
        $r = (new ViewRolesFixture())->getTable();

        return <<<SQL
            SELECT
                u.id AS user_id,
                u.email,
                r.name AS role_name
            FROM {$u} u
            INNER JOIN {$r} r ON r.user_id = u.id
        SQL;
    }
}

class ViewTableTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function testRowWritesAreRejected(): void
    {
        $view = new UserAccessViewFixture();
        $this->assertNull($view->insertSingleQuery());
        $this->assertNotNull($view->getError());
        $this->assertStringContainsString('read-only', (string) $view->getError());

        $view->setError(null);
        $this->assertNull($view->updateSingleQuery());
        $this->assertNotNull($view->getError());

        $view->setError(null);
        $this->assertNull($view->deleteByIdQuery(1));
        $this->assertNotNull($view->getError());
    }

    public function testTableGeneratorRefusesViewTable(): void
    {
        $generator = new TableGenerator($this->pdo);
        $this->assertFalse($generator->createTableFromObject(new UserAccessViewFixture()));
        $this->assertStringContainsString('ViewTable', $generator->getError());
    }

    public function testViewGeneratorCreatesAndReplacesView(): void
    {
        $tableGen = new TableGenerator($this->pdo);
        $this->assertTrue($tableGen->createTableFromObject(new ViewUsersFixture()));
        $this->assertTrue($tableGen->createTableFromObject(new ViewRolesFixture()));

        $viewGen = new ViewGenerator($this->pdo);
        $view = new UserAccessViewFixture();
        $this->assertTrue($viewGen->replaceView($view), $viewGen->getError());

        $dialect = \Gemvc\Database\Dialect\DialectResolver::resolve($this->pdo);
        $this->assertTrue($dialect->viewExists($this->pdo, 'user_access_view_fixture'));

        $this->pdo->exec("INSERT INTO view_users_fixture (id, email) VALUES (1, 'a@test.com')");
        $this->pdo->exec("INSERT INTO view_roles_fixture (id, user_id, name) VALUES (1, 1, 'admin')");

        $stmt = $this->pdo->query('SELECT user_id, email, role_name FROM user_access_view_fixture');
        $this->assertNotFalse($stmt);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($row);
        $this->assertSame('a@test.com', $row['email']);
        $this->assertSame('admin', $row['role_name']);

        // Replace with slightly different definition
        $this->assertTrue($viewGen->replaceView($view), $viewGen->getError());
        $this->assertTrue($viewGen->dropView($view), $viewGen->getError());
        $this->assertFalse($dialect->viewExists($this->pdo, 'user_access_view_fixture'));
    }

    public function testMigrateOrderTablesThenViews(): void
    {
        $instances = [
            'ViewRolesFixture' => new ViewRolesFixture(),
            'UserAccessViewFixture' => new UserAccessViewFixture(),
            'ViewUsersFixture' => new ViewUsersFixture(),
        ];

        $ordered = TableMigrateOrder::order($instances);
        $userPos = array_search('ViewUsersFixture', $ordered, true);
        $rolePos = array_search('ViewRolesFixture', $ordered, true);
        $viewPos = array_search('UserAccessViewFixture', $ordered, true);

        $this->assertNotFalse($userPos);
        $this->assertNotFalse($rolePos);
        $this->assertNotFalse($viewPos);
        $this->assertLessThan($rolePos, $userPos);
        $this->assertGreaterThan($rolePos, $viewPos);
        $this->assertGreaterThan($userPos, $viewPos);
    }

    public function testDialectCreateOrReplaceViewSqlShapes(): void
    {
        $sqlite = new \Gemvc\Database\Dialect\SqliteDialect();
        $stmts = $sqlite->createOrReplaceViewSql('v', 'SELECT 1 AS x');
        $this->assertCount(2, $stmts);
        $this->assertStringContainsString('DROP VIEW', $stmts[0]);
        $this->assertStringContainsString('CREATE VIEW', $stmts[1]);

        $mysql = new \Gemvc\Database\Dialect\MysqlDialect();
        $m = $mysql->createOrReplaceViewSql('v', 'SELECT 1 AS x');
        $this->assertCount(1, $m);
        $this->assertStringContainsString('CREATE OR REPLACE VIEW', $m[0]);
    }
}
