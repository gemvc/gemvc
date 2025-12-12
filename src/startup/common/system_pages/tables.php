<?php
/**
 * Tables Layer Management Page Content
 * 
 * This template provides the content for the tables layer management page.
 * The HTML structure, header, and footer are handled by index.php.
 * 
 * @var string $baseUrl The base URL of the application
 * @var string $apiBaseUrl The base URL for API endpoints
 * @var string $webserverType The detected webserver type ('apache', 'nginx', 'swoole')
 * @var string $webserverName The detected webserver name (Apache, Nginx, or OpenSwoole)
 * @var string $templateDir The directory path where this template is located
 * @var array<int, array<string, mixed>> $tableClasses List of table classes with migration status
 * @var int $totalTables Total number of table classes
 * @var array<string, mixed> $relationships Foreign key relationships for schema diagram
 */

// Security check: Defense-in-depth (already protected by index.php, but extra safety)
if (($_ENV['APP_ENV'] ?? '') !== 'dev') {
    http_response_code(404);
    exit('Not Found');
}
?>
<div class="mb-10">
    <div class="flex items-center justify-between mb-4">
        <form method="POST" class="inline m-0 p-0">
            <input type="hidden" name="set_page" value="developer-welcome">
            <button type="submit" class="text-base font-medium transition-colors text-gray-600 hover:text-gemvc-green flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                <span>Back to Home</span>
            </button>
        </form>
    </div>
    <div class="text-center">
        <h1 class="text-5xl font-bold text-gemvc-green mb-2.5 tracking-tight">Tables Layer</h1>
        <p class="text-lg font-normal text-gray-600">Manage your database table classes and migrations</p>
    </div>
</div>

<!-- Tables List -->
<div class="mb-8">
    <h2 class="text-2xl font-semibold text-gray-800 mb-4 border-b-2 border-gemvc-green pb-2.5">Table Classes (<?php echo $totalTables; ?>)</h2>
    <?php if (!empty($error ?? '')): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-5 rounded mb-4">
            <p class="text-red-800 m-0"><strong>Error:</strong> <?php echo htmlspecialchars($error); ?></p>
        </div>
    <?php endif; ?>
    <?php if (empty($tableClasses)): ?>
        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-5 rounded">
            <p class="text-yellow-800 m-0">No table classes found. Create your first table class to get started!</p>
            <?php if (!empty($error ?? '')): ?>
                <p class="text-red-600 text-sm mt-2">Debug: <?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="bg-white border border-gray-200 rounded-lg shadow-md overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Table Class</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Database Table</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Foreign Keys</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($tableClasses as $table): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($table['className']); ?></div>
                                <?php if (!empty($table['description'])): ?>
                                    <div class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($table['description']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <code class="text-sm font-mono text-gray-800"><?php echo htmlspecialchars($table['tableName']); ?></code>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($table['isMigrated']): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        ✓ Migrated
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        ⚠ Not Migrated
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php if (!empty($table['foreignKeys'])): ?>
                                    <div class="flex flex-wrap gap-1">
                                        <?php foreach ($table['foreignKeys'] as $fk): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800" title="<?php echo htmlspecialchars($fk['column_name']); ?> → <?php echo htmlspecialchars($fk['referenced_table']); ?>">
                                                <?php echo htmlspecialchars($fk['referenced_table']); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400">None</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <?php if ($table['isMigrated']): ?>
                                    <button onclick="migrateTable('<?php echo htmlspecialchars($table['className']); ?>', true)" 
                                        class="migrate-btn-<?php echo htmlspecialchars($table['className']); ?> text-gemvc-green hover:text-gemvc-green-dark font-medium">
                                        Update
                                    </button>
                                <?php else: ?>
                                    <button onclick="migrateTable('<?php echo htmlspecialchars($table['className']); ?>', false)" 
                                        class="migrate-btn-<?php echo htmlspecialchars($table['className']); ?> text-gemvc-green hover:text-gemvc-green-dark font-medium">
                                        Migrate
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Schema Diagram -->
<?php if (!empty($relationships)): ?>
    <div class="mb-8">
        <h2 class="text-2xl font-semibold text-gray-800 mb-4 border-b-2 border-gemvc-green pb-2.5">Database Schema Relationships</h2>
        <div class="bg-white border border-gray-200 rounded-lg shadow-md p-6">
            <div id="schemaDiagram" class="relative" style="min-height: 400px;">
                <!-- Schema diagram will be rendered here by JavaScript -->
                <svg id="schemaSvg" width="100%" height="100%" style="min-height: 400px;">
                    <!-- Relationships will be drawn here -->
                </svg>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
// Migration function will be wired up in SPA JavaScript
window.migrateTable = function(tableClassName, isUpdate) {
    // This will be implemented in spa.html
    console.log('Migrate table:', tableClassName, isUpdate);
};
</script>

