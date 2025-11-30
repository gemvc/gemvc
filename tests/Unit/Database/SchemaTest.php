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
}

