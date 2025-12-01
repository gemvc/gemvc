<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use Gemvc\Database\Schema;
use Gemvc\Database\UniqueConstraint;
use Gemvc\Database\ForeignKeyConstraint;
use Gemvc\Database\IndexConstraint;
use Gemvc\Database\PrimaryKeyConstraint;
use Gemvc\Database\AutoIncrementConstraint;
use Gemvc\Database\CheckConstraint;
use Gemvc\Database\FulltextConstraint;

class SchemaTest extends TestCase
{
    public function testUniqueConstraintSingleColumn(): void
    {
        $constraint = Schema::unique('email');
        $this->assertInstanceOf(UniqueConstraint::class, $constraint);
        $this->assertEquals('unique', $constraint->getType());
        $this->assertEquals('email', $constraint->getColumns());
        
        $array = $constraint->toArray();
        $this->assertEquals('unique', $array['type']);
        $this->assertEquals('email', $array['columns']);
    }
    
    public function testUniqueConstraintComposite(): void
    {
        $constraint = Schema::unique(['username', 'email']);
        $this->assertInstanceOf(UniqueConstraint::class, $constraint);
        $this->assertEquals(['username', 'email'], $constraint->getColumns());
        
        $array = $constraint->toArray();
        $this->assertEquals(['username', 'email'], $array['columns']);
    }
    
    public function testUniqueConstraintWithName(): void
    {
        $constraint = Schema::unique('email')->name('unique_email');
        $this->assertEquals('unique_email', $constraint->getName());
        
        $array = $constraint->toArray();
        $this->assertEquals('unique_email', $array['name']);
    }
    
    public function testForeignKeyConstraint(): void
    {
        $constraint = Schema::foreignKey('user_id', 'users.id');
        $this->assertInstanceOf(ForeignKeyConstraint::class, $constraint);
        $this->assertEquals('foreign_key', $constraint->getType());
        $this->assertEquals('user_id', $constraint->getColumns());
        $this->assertEquals('users.id', $constraint->getReferences());
        
        $array = $constraint->toArray();
        $this->assertEquals('foreign_key', $array['type']);
        $this->assertEquals('user_id', $array['column']);
        $this->assertEquals('users.id', $array['references']);
    }
    
    public function testForeignKeyOnDeleteCascade(): void
    {
        $constraint = Schema::foreignKey('user_id', 'users.id')->onDeleteCascade();
        $this->assertEquals('CASCADE', $constraint->getOnDelete());
        
        $array = $constraint->toArray();
        $this->assertEquals('CASCADE', $array['on_delete']);
    }
    
    public function testForeignKeyOnDeleteRestrict(): void
    {
        $constraint = Schema::foreignKey('user_id', 'users.id')->onDeleteRestrict();
        $this->assertEquals('RESTRICT', $constraint->getOnDelete());
    }
    
    public function testForeignKeyOnDeleteSetNull(): void
    {
        $constraint = Schema::foreignKey('user_id', 'users.id')->onDeleteSetNull();
        $this->assertEquals('SET_NULL', $constraint->getOnDelete());
    }
    
    public function testForeignKeyOnUpdateCascade(): void
    {
        $constraint = Schema::foreignKey('user_id', 'users.id')->onUpdateCascade();
        $this->assertEquals('CASCADE', $constraint->getOnUpdate());
    }
    
    public function testIndexConstraintSingleColumn(): void
    {
        $constraint = Schema::index('email');
        $this->assertInstanceOf(IndexConstraint::class, $constraint);
        $this->assertEquals('index', $constraint->getType());
        $this->assertEquals('email', $constraint->getColumns());
        $this->assertFalse($constraint->isUnique());
    }
    
    public function testIndexConstraintComposite(): void
    {
        $constraint = Schema::index(['name', 'is_active']);
        $this->assertEquals(['name', 'is_active'], $constraint->getColumns());
    }
    
    public function testIndexConstraintUnique(): void
    {
        $constraint = Schema::index('email')->unique();
        $this->assertTrue($constraint->isUnique());
        
        $array = $constraint->toArray();
        $this->assertTrue($array['unique']);
    }
    
    public function testPrimaryKeyConstraint(): void
    {
        $constraint = Schema::primary('id');
        $this->assertInstanceOf(PrimaryKeyConstraint::class, $constraint);
        $this->assertEquals('primary', $constraint->getType());
        $this->assertEquals('id', $constraint->getColumns());
    }
    
    public function testPrimaryKeyConstraintComposite(): void
    {
        $constraint = Schema::primary(['id', 'tenant_id']);
        $this->assertEquals(['id', 'tenant_id'], $constraint->getColumns());
    }
    
    public function testAutoIncrementConstraint(): void
    {
        $constraint = Schema::autoIncrement('id');
        $this->assertInstanceOf(AutoIncrementConstraint::class, $constraint);
        $this->assertEquals('auto_increment', $constraint->getType());
        $this->assertEquals('id', $constraint->getColumns());
    }
    
    public function testCheckConstraint(): void
    {
        $constraint = Schema::check('age >= 18');
        $this->assertInstanceOf(CheckConstraint::class, $constraint);
        $this->assertEquals('check', $constraint->getType());
        $this->assertEquals('age >= 18', $constraint->getExpression());
        
        $array = $constraint->toArray();
        $this->assertEquals('check', $array['type']);
        $this->assertEquals('age >= 18', $array['expression']);
    }
    
    public function testCheckConstraintWithName(): void
    {
        $constraint = Schema::check('age >= 18')->name('valid_age');
        $this->assertEquals('valid_age', $constraint->getName());
    }
    
    public function testFulltextConstraint(): void
    {
        $constraint = Schema::fulltext(['name', 'description']);
        $this->assertInstanceOf(FulltextConstraint::class, $constraint);
        $this->assertEquals('fulltext', $constraint->getType());
        $this->assertEquals(['name', 'description'], $constraint->getColumns());
    }
    
    public function testFulltextConstraintSingleColumn(): void
    {
        $constraint = Schema::fulltext('content');
        $this->assertEquals('content', $constraint->getColumns());
    }
    
    // ==========================================
    // PrimaryKeyConstraint - Extended Coverage
    // ==========================================
    
    public function testPrimaryKeyConstraintToArray(): void
    {
        $constraint = Schema::primary('id');
        $array = $constraint->toArray();
        
        $this->assertIsArray($array);
        $this->assertEquals('primary', $array['type']);
        $this->assertEquals('id', $array['columns']);
        $this->assertNull($array['name']);
    }
    
    public function testPrimaryKeyConstraintWithName(): void
    {
        $constraint = Schema::primary('id')->name('pk_users');
        $this->assertEquals('pk_users', $constraint->getName());
        
        $array = $constraint->toArray();
        $this->assertEquals('pk_users', $array['name']);
    }
    
    public function testPrimaryKeyConstraintCompositeToArray(): void
    {
        $constraint = Schema::primary(['id', 'tenant_id'])->name('pk_composite');
        $array = $constraint->toArray();
        
        $this->assertEquals('primary', $array['type']);
        $this->assertEquals(['id', 'tenant_id'], $array['columns']);
        $this->assertEquals('pk_composite', $array['name']);
    }
    
    public function testPrimaryKeyConstraintNameReturnsStatic(): void
    {
        $constraint = Schema::primary('id');
        $result = $constraint->name('pk_test');
        
        $this->assertInstanceOf(PrimaryKeyConstraint::class, $result);
        $this->assertSame($constraint, $result); // Fluent interface returns same instance
    }
    
    // ==========================================
    // AutoIncrementConstraint - Extended Coverage
    // ==========================================
    
    public function testAutoIncrementConstraintToArray(): void
    {
        $constraint = Schema::autoIncrement('id');
        $array = $constraint->toArray();
        
        $this->assertIsArray($array);
        $this->assertEquals('auto_increment', $array['type']);
        $this->assertEquals('id', $array['column']);
        $this->assertNull($array['name']);
    }
    
    public function testAutoIncrementConstraintWithName(): void
    {
        $constraint = Schema::autoIncrement('id')->name('ai_id');
        $this->assertEquals('ai_id', $constraint->getName());
        
        $array = $constraint->toArray();
        $this->assertEquals('ai_id', $array['name']);
    }
    
    public function testAutoIncrementConstraintNameReturnsStatic(): void
    {
        $constraint = Schema::autoIncrement('id');
        $result = $constraint->name('ai_test');
        
        $this->assertInstanceOf(AutoIncrementConstraint::class, $result);
        $this->assertSame($constraint, $result);
    }
    
    // ==========================================
    // FulltextConstraint - Extended Coverage
    // ==========================================
    
    public function testFulltextConstraintToArray(): void
    {
        $constraint = Schema::fulltext(['name', 'description']);
        $array = $constraint->toArray();
        
        $this->assertIsArray($array);
        $this->assertEquals('fulltext', $array['type']);
        $this->assertEquals(['name', 'description'], $array['columns']);
        $this->assertNull($array['name']);
    }
    
    public function testFulltextConstraintWithName(): void
    {
        $constraint = Schema::fulltext(['name', 'description'])->name('ft_search');
        $this->assertEquals('ft_search', $constraint->getName());
        
        $array = $constraint->toArray();
        $this->assertEquals('ft_search', $array['name']);
    }
    
    public function testFulltextConstraintSingleColumnToArray(): void
    {
        $constraint = Schema::fulltext('content');
        $array = $constraint->toArray();
        
        $this->assertEquals('fulltext', $array['type']);
        $this->assertEquals('content', $array['columns']);
    }
    
    public function testFulltextConstraintNameReturnsStatic(): void
    {
        $constraint = Schema::fulltext('content');
        $result = $constraint->name('ft_test');
        
        $this->assertInstanceOf(FulltextConstraint::class, $result);
        $this->assertSame($constraint, $result);
    }
    
    // ==========================================
    // ForeignKeyConstraint - Extended Coverage
    // ==========================================
    
    public function testForeignKeyOnDeleteNoAction(): void
    {
        $constraint = Schema::foreignKey('user_id', 'users.id')->onDeleteNoAction();
        $this->assertEquals('NO_ACTION', $constraint->getOnDelete());
        
        $array = $constraint->toArray();
        $this->assertEquals('NO_ACTION', $array['on_delete']);
    }
    
    public function testForeignKeyOnUpdateRestrict(): void
    {
        $constraint = Schema::foreignKey('user_id', 'users.id')->onUpdateRestrict();
        $this->assertEquals('RESTRICT', $constraint->getOnUpdate());
        
        $array = $constraint->toArray();
        $this->assertEquals('RESTRICT', $array['on_update']);
    }
    
    public function testForeignKeyOnUpdateSetNull(): void
    {
        $constraint = Schema::foreignKey('user_id', 'users.id')->onUpdateSetNull();
        $this->assertEquals('SET_NULL', $constraint->getOnUpdate());
        
        $array = $constraint->toArray();
        $this->assertEquals('SET_NULL', $array['on_update']);
    }
    
    public function testForeignKeyOnUpdateNoAction(): void
    {
        $constraint = Schema::foreignKey('user_id', 'users.id')->onUpdateNoAction();
        $this->assertEquals('NO_ACTION', $constraint->getOnUpdate());
        
        $array = $constraint->toArray();
        $this->assertEquals('NO_ACTION', $array['on_update']);
    }
    
    public function testForeignKeyOnDeleteWithCustomAction(): void
    {
        $constraint = Schema::foreignKey('user_id', 'users.id')->onDelete('cascade');
        $this->assertEquals('CASCADE', $constraint->getOnDelete());
        
        // Test lowercase conversion
        $constraint2 = Schema::foreignKey('user_id', 'users.id')->onDelete('restrict');
        $this->assertEquals('RESTRICT', $constraint2->getOnDelete());
    }
    
    public function testForeignKeyOnUpdateWithCustomAction(): void
    {
        $constraint = Schema::foreignKey('user_id', 'users.id')->onUpdate('cascade');
        $this->assertEquals('CASCADE', $constraint->getOnUpdate());
        
        // Test lowercase conversion
        $constraint2 = Schema::foreignKey('user_id', 'users.id')->onUpdate('set_null');
        $this->assertEquals('SET_NULL', $constraint2->getOnUpdate());
    }
    
    public function testForeignKeyCombinedActions(): void
    {
        $constraint = Schema::foreignKey('user_id', 'users.id')
            ->onDeleteCascade()
            ->onUpdateSetNull();
        
        $this->assertEquals('CASCADE', $constraint->getOnDelete());
        $this->assertEquals('SET_NULL', $constraint->getOnUpdate());
        
        $array = $constraint->toArray();
        $this->assertEquals('CASCADE', $array['on_delete']);
        $this->assertEquals('SET_NULL', $array['on_update']);
    }
    
    public function testForeignKeyWithName(): void
    {
        $constraint = Schema::foreignKey('user_id', 'users.id')->name('fk_user');
        $this->assertEquals('fk_user', $constraint->getName());
        
        $array = $constraint->toArray();
        $this->assertEquals('fk_user', $array['name']);
    }
    
    public function testForeignKeyFluentInterfaceReturnsStatic(): void
    {
        $constraint = Schema::foreignKey('user_id', 'users.id');
        
        $result1 = $constraint->onDeleteCascade();
        $this->assertInstanceOf(ForeignKeyConstraint::class, $result1);
        $this->assertSame($constraint, $result1);
        
        $result2 = $constraint->onUpdateRestrict();
        $this->assertInstanceOf(ForeignKeyConstraint::class, $result2);
        $this->assertSame($constraint, $result2);
        
        $result3 = $constraint->name('fk_test');
        $this->assertInstanceOf(ForeignKeyConstraint::class, $result3);
        $this->assertSame($constraint, $result3);
    }
    
    public function testForeignKeyDefaultActions(): void
    {
        $constraint = Schema::foreignKey('user_id', 'users.id');
        
        // Default should be RESTRICT for both
        $this->assertEquals('RESTRICT', $constraint->getOnDelete());
        $this->assertEquals('RESTRICT', $constraint->getOnUpdate());
        
        $array = $constraint->toArray();
        $this->assertEquals('RESTRICT', $array['on_delete']);
        $this->assertEquals('RESTRICT', $array['on_update']);
    }
    
    // ==========================================
    // IndexConstraint - Extended Coverage
    // ==========================================
    
    public function testIndexConstraintFluentInterfaceReturnsStatic(): void
    {
        $constraint = Schema::index('email');
        $result = $constraint->unique();
        
        $this->assertInstanceOf(IndexConstraint::class, $result);
        $this->assertSame($constraint, $result);
    }
    
    public function testIndexConstraintWithName(): void
    {
        $constraint = Schema::index('email')->name('idx_email');
        $this->assertEquals('idx_email', $constraint->getName());
        
        $array = $constraint->toArray();
        $this->assertEquals('idx_email', $array['name']);
    }
    
    public function testIndexConstraintToArrayWithAllProperties(): void
    {
        $constraint = Schema::index(['name', 'status'])
            ->unique()
            ->name('idx_composite_unique');
        
        $array = $constraint->toArray();
        
        $this->assertEquals('index', $array['type']);
        $this->assertEquals(['name', 'status'], $array['columns']);
        $this->assertTrue($array['unique']);
        $this->assertEquals('idx_composite_unique', $array['name']);
    }
    
    // ==========================================
    // UniqueConstraint - Extended Coverage
    // ==========================================
    
    public function testUniqueConstraintFluentInterfaceReturnsStatic(): void
    {
        $constraint = Schema::unique('email');
        $result = $constraint->name('uniq_email');
        
        $this->assertInstanceOf(UniqueConstraint::class, $result);
        $this->assertSame($constraint, $result);
    }
    
    // ==========================================
    // CheckConstraint - Extended Coverage
    // ==========================================
    
    public function testCheckConstraintFluentInterfaceReturnsStatic(): void
    {
        $constraint = Schema::check('age >= 18');
        $result = $constraint->name('check_age');
        
        $this->assertInstanceOf(CheckConstraint::class, $result);
        $this->assertSame($constraint, $result);
    }
    
    public function testCheckConstraintToArrayWithName(): void
    {
        $constraint = Schema::check('salary > 0')->name('check_salary');
        $array = $constraint->toArray();
        
        $this->assertEquals('check', $array['type']);
        $this->assertEquals('salary > 0', $array['expression']);
        $this->assertEquals('check_salary', $array['name']);
    }
    
    // ==========================================
    // SchemaConstraint Base Class Coverage
    // ==========================================
    
    public function testConstraintBaseClassGetType(): void
    {
        $constraint1 = Schema::unique('email');
        $this->assertEquals('unique', $constraint1->getType());
        
        $constraint2 = Schema::primary('id');
        $this->assertEquals('primary', $constraint2->getType());
        
        $constraint3 = Schema::autoIncrement('id');
        $this->assertEquals('auto_increment', $constraint3->getType());
        
        $constraint4 = Schema::fulltext('content');
        $this->assertEquals('fulltext', $constraint4->getType());
    }
    
    public function testConstraintBaseClassGetColumns(): void
    {
        $constraint1 = Schema::unique('email');
        $this->assertEquals('email', $constraint1->getColumns());
        
        $constraint2 = Schema::primary(['id', 'tenant_id']);
        $this->assertEquals(['id', 'tenant_id'], $constraint2->getColumns());
        
        $constraint3 = Schema::autoIncrement('id');
        $this->assertEquals('id', $constraint3->getColumns());
    }
    
    public function testConstraintBaseClassGetNameReturnsNull(): void
    {
        $constraint = Schema::unique('email');
        $this->assertNull($constraint->getName());
    }
}

