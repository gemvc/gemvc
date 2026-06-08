<?php

namespace Gemvc\CLI;

/**
 * Core CLI command metadata (shipped with gemvc/library).
 * Development commands (create:*, admin:*, db:init, etc.) live in gemvc/cli-dev.
 */
class CommandCategories
{
    public const CATEGORIES = [
        'Project Management' => [
            'init' => 'Initialize a new GEMVC project with server configuration (Apache/Swoole/Nginx)',
        ],
        'Database (production)' => [
            'db:migrate' => 'Create or update a specific table (db:migrate TableClassName)',
        ],
    ];

    public static function getCommandClass(string $command): string
    {
        $commandMappings = [
            'init' => 'InitProject',
            'db:migrate' => 'DbMigrate',
        ];

        return $commandMappings[$command] ?? '';
    }

    public static function getCategory(string $command): string
    {
        foreach (self::CATEGORIES as $category => $commands) {
            if (isset($commands[$command])) {
                return $category;
            }
        }
        return 'Other';
    }

    public static function getDescription(string $command): string
    {
        foreach (self::CATEGORIES as $commands) {
            if (isset($commands[$command])) {
                return $commands[$command];
            }
        }
        return '';
    }

    /**
     * @return array<string, string|array<string>>
     */
    public static function getExamples(): array
    {
        return [
            'init' => 'vendor/bin/gemvc init',
            'db:migrate' => 'vendor/bin/gemvc db:migrate UserTable',
        ];
    }

    /**
     * Dev commands from gemvc/cli-dev when installed (require-dev).
     *
     * @return array<string, string>
     */
    public static function getDevCommandHints(): array
    {
        return [
            'create:service' => 'Create a new service (gemvc/cli-dev)',
            'create:controller' => 'Create a new controller (gemvc/cli-dev)',
            'create:model' => 'Create a new model (gemvc/cli-dev)',
            'create:table' => 'Create a new table class (gemvc/cli-dev)',
            'create:crud' => 'Create full CRUD stack (gemvc/cli-dev)',
            'db:init' => 'Initialize database (gemvc/cli-dev)',
            'db:list' => 'List database tables (gemvc/cli-dev)',
            'db:describe' => 'Describe table structure (gemvc/cli-dev)',
            'db:drop' => 'Drop a table (gemvc/cli-dev)',
            'db:unique' => 'Add unique constraint (gemvc/cli-dev)',
            'admin:setpassword' => 'Set dev admin password (gemvc/cli-dev)',
            'admin:setadmin' => 'Create first admin user (gemvc/cli-dev)',
        ];
    }
}
