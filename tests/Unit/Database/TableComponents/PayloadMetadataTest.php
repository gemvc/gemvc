<?php

declare(strict_types=1);

namespace Tests\Unit\Database\TableComponents;

use Gemvc\Database\Table;
use Gemvc\Database\TableComponents\PayloadMetadata;
use Gemvc\Database\ViewTable;
use PHPUnit\Framework\TestCase;

final class PayloadPublicTable extends Table
{
    public int $id;
    public string $email;
    public ?string $description;
    protected string $password;
    private string $secret = 'nope';
    public string $_internal = 'skip';
    public string $display_name;

    /** @var array<string, string> */
    protected array $_type_map = [
        'id' => 'int',
        'email' => 'string',
        'description' => '?string',
        'password' => 'string',
        'display_name' => 'string',
    ];

    public function getTable(): string
    {
        return 'payload_public';
    }

    public function defineSchema(): array
    {
        return [];
    }
}

final class PayloadMissingMapTable extends Table
{
    public int $id;
    public string $name;

    public function getTable(): string
    {
        return 'payload_missing_map';
    }

    public function defineSchema(): array
    {
        return [];
    }
}

final class PayloadViewAliasTable extends ViewTable
{
    public int $user_id;
    public string $email;
    public string $role_name;

    /** @var array<string, string> */
    protected array $_type_map = [
        'user_id' => 'int',
        'email' => 'string',
        'role_name' => 'string',
    ];

    public function getTable(): string
    {
        return 'payload_view_alias';
    }

    public function defineView(): string
    {
        return 'SELECT 1 AS user_id, \'a@b.c\' AS email, \'user\' AS role_name';
    }
}

final class PayloadMetadataTest extends TestCase
{
    public function testPublicFieldsAreVisibleAndProtectedAreOmitted(): void
    {
        $names = PayloadPublicTable::payloadFieldNames();
        $this->assertSame(['id', 'email', 'description', 'display_name'], $names);
        $this->assertNotContains('password', $names);
        $this->assertNotContains('secret', $names);
        $this->assertNotContains('_internal', $names);
        $this->assertNotContains('_type_map', $names);
    }

    public function testUninitializedPublicPropertyIsListedInContract(): void
    {
        $names = PayloadPublicTable::payloadFieldNames();
        $this->assertContains('display_name', $names);
        $this->assertContains('id', $names);
        $table = new PayloadPublicTable();
        $initialized = array_keys(get_object_vars($table));
        $this->assertNotContains('display_name', $initialized);
        $this->assertNotContains('id', $initialized);
    }

    public function testNullableAndTypeMap(): void
    {
        $byName = [];
        foreach (PayloadPublicTable::payloadFields() as $field) {
            $byName[$field['name']] = $field;
        }
        $this->assertSame('int', $byName['id']['type']);
        $this->assertFalse($byName['id']['nullable']);
        $this->assertTrue($byName['description']['nullable']);
        $this->assertSame('string', $byName['description']['type']);
        $this->assertStringContainsString('string', $byName['description']['php_type']);
    }

    public function testMissingTypeMapEntryUsesPhpType(): void
    {
        $byName = [];
        foreach (PayloadMissingMapTable::payloadFields() as $field) {
            $byName[$field['name']] = $field;
        }
        $this->assertSame('int', $byName['id']['type']);
        $this->assertSame('string', $byName['name']['type']);
    }

    public function testDoesNotInstantiateTable(): void
    {
        $fields = PayloadMetadata::fields(PayloadPublicTable::class);
        $this->assertNotEmpty($fields);
        $this->assertSame($fields, PayloadPublicTable::payloadFields());
    }

    public function testViewTableAliasesArePayloadFields(): void
    {
        $names = PayloadViewAliasTable::payloadFieldNames();
        $this->assertSame(['user_id', 'email', 'role_name'], $names);
        $this->assertTrue(is_subclass_of(PayloadViewAliasTable::class, ViewTable::class));
    }

    public function testJsonEncodeOmitsProtectedFields(): void
    {
        $table = new PayloadPublicTable();
        $table->id = 1;
        $table->email = 'a@b.c';
        $table->description = null;
        $table->display_name = 'Ada';
        $json = json_encode($table);
        $this->assertIsString($json);
        $this->assertStringContainsString('email', $json);
        $this->assertStringNotContainsString('password', $json);
        $this->assertStringNotContainsString('secret', $json);
    }
}
