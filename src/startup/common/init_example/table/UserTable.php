<?php
/**
 * this is table layer. what so called Data access layer
 * classes in this layer shall be extended from CRUDTable or Gemvc\Core\Table ;
 * for each column in database table, you must define property in this class with same name and property type;
 */
namespace App\Table;

use Gemvc\Database\Table;
use Gemvc\Database\Schema;

/**
 * User table class for handling User database operations
 * @property int $id User's unique identifier column id in database table
 * @property string $name User's name column name in database table
 * @property string|null $description User's description column description in database table
 * @property string $password User's password column password in database table
 * @property string $created_at User's created_at column created_at in database table
 * @property string|null $updated_at User's updated_at column updated_at in database table
 * @property string $role User's role column role in database table
 * @property string $story User's story column story in database table Very Long Text
 */
class UserTable extends Table
{
    public int $id;
    public string $name;
    public string $email;
    //password is not shown in the result of the select query , but this property can be set trough setPassword() method in the UserModel class
    protected string $password;
    public string $created_at;
    public ?string $description;
    public ?string $updated_at;
    public ?string $role;
    public ?string $story;


    /**
     * Summary of type mapping for properties
     * it is used to map the properties to the database columns
     * it is used in the TableGenerator class to generate the schema for the table
     * @var array<string, string>
     */
    protected array $_type_map = [
        'id' => 'int',
        'name' => 'string',
        'email' => 'string',
        'description' => 'text',
        'password' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'role' => 'string',
        'story' => 'longText'
    ];
    /**
     * Summary of defineSchema() method
     * it is used to map the properties to the database columns
     * it is used in the TableGenerator class to generate the schema for the table
     * @return array<int, mixed>
     */
    public function defineSchema(): array 
    {
        return [
            // Primary key with auto increment
            Schema::primary('id'),
            Schema::autoIncrement('id'),
            Schema::index('email')->name('idx_email'),
            // Unique constraints
            Schema::unique('email')->name('uniq_email'),
            // Indexes for performance
            Schema::index('created_at')->name('idx_created')->timestamp(),  // Named index for timestamp column with DEFAULT CURRENT_TIMESTAMP
            Schema::index('updated_at')->name('idx_updated'),  // Named index for timestamp column
            Schema::index('role'),
            // Full-text search
            Schema::fullText(['name', 'description'])
            
        ];
    }

    public function __construct()
    {
        parent::__construct();
        $this->description = null;
        $this->updated_at = null;
        $this->created_at = '';
    }

    /**
     * @return string
     * the name of the database table
     */
    public function getTable(): string
    {
        //return the name of the table in database
        return 'users';
    }

    /**
     * @return array<static>|null
     * null or array of UserTable Objects
     */
    public function selectByName(string $name): null|array
    {
        /** @var array<static>|null $result */
        $result = $this->select()->whereLike('name', $name)->run();
        return $result;
    }

    /**
     * @return static|null
     */
    public function selectByEmail(string $email): null|static
    {
        /** @var array<static>|null $arr */
        $arr = $this->select()->whereEqual('email', $email)->limit(1)->run();
        if (is_array($arr) && isset($arr[0])) {
            return $arr[0];
        }
        return null;
    }
} 
