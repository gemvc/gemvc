<?php
/**
 * Database Management Page Content
 * 
 * This template provides the content for the database management page.
 * The HTML structure, header, and footer are handled by index.php.
 * 
 * @var string $baseUrl The base URL of the application
 * @var string $apiBaseUrl The base URL for API endpoints
 * @var string $webserverType The detected webserver type ('apache', 'nginx', 'swoole')
 * @var string $webserverName The detected webserver name (Apache, Nginx, or OpenSwoole)
 * @var string $templateDir The directory path where this template is located
 */

// Security check: Defense-in-depth (already protected by index.php, but extra safety)
if (($_ENV['APP_ENV'] ?? '') !== 'dev') {
    http_response_code(404);
    exit('Not Found');
}

// Check database connectivity and get connection
$databaseReady = false;
$connection = null;
$tables = [];
// Get selected table from variable (passed from API) or default to null
$selectedTable = $selectedTable ?? null;
$tableStructure = null;
$tableRelationships = null;

try {
    $dbManager = \Gemvc\Database\DatabaseManagerFactory::getManager();
    $connection = $dbManager->getConnection();
    if ($connection !== null) {
        // Get the underlying PDO connection
        $pdo = $connection->getConnection();
        if ($pdo instanceof \PDO) {
            $pdo->query('SELECT 1');
            $databaseReady = true;

            // Get database name
            $dbName = $pdo->query("SELECT DATABASE() as db_name")->fetch(\PDO::FETCH_ASSOC)['db_name'] ?? 'unknown';

            // Get all tables
            $tablesResult = $pdo->query("
                SELECT TABLE_NAME, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH, TABLE_COMMENT
                FROM INFORMATION_SCHEMA.TABLES 
                WHERE TABLE_SCHEMA = " . $pdo->quote($dbName) . " 
                AND TABLE_TYPE = 'BASE TABLE'
                ORDER BY TABLE_NAME
            ");
            $tables = $tablesResult->fetchAll(\PDO::FETCH_ASSOC);

                // If a table is selected, get its structure and relationships
                if ($selectedTable && $databaseReady) {
            // Get table structure (columns)
            $columnsResult = $pdo->query("
                SELECT 
                    COLUMN_NAME,
                    COLUMN_TYPE,
                    IS_NULLABLE,
                    COLUMN_KEY,
                    COLUMN_DEFAULT,
                    EXTRA,
                    COLUMN_COMMENT
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = " . $pdo->quote($dbName) . " 
                AND TABLE_NAME = " . $pdo->quote($selectedTable) . "
                ORDER BY ORDINAL_POSITION
            ");
            $tableStructure = $columnsResult->fetchAll(\PDO::FETCH_ASSOC);

            // Get foreign key relationships
            $fkResult = $pdo->query("
                SELECT 
                    kcu.CONSTRAINT_NAME,
                    kcu.COLUMN_NAME,
                    kcu.REFERENCED_TABLE_NAME,
                    kcu.REFERENCED_COLUMN_NAME,
                    rc.UPDATE_RULE,
                    rc.DELETE_RULE
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
                INNER JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc
                    ON kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
                    AND kcu.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA
                WHERE kcu.TABLE_SCHEMA = " . $pdo->quote($dbName) . "
                    AND kcu.TABLE_NAME = " . $pdo->quote($selectedTable) . "
                    AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
            ");
                $tableRelationships = $fkResult->fetchAll(\PDO::FETCH_ASSOC);
            }
        }
    }
} catch (\Exception $e) {
    $databaseReady = false;
    $errorMessage = $e->getMessage();
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
        <h1 class="text-5xl font-bold text-gemvc-green mb-2.5 tracking-tight">Database Manager</h1>
        <p class="text-lg font-normal text-gray-600">view your database and tables structure</p>
    </div>
</div>

<?php if (!$databaseReady): ?>
    <div class="bg-red-50 border-l-4 border-red-500 p-5 rounded mb-5">
        <p class="text-red-800 m-0">
            <strong>Error:</strong> Database connection failed. Please check your database configuration.
            <?php if (isset($errorMessage)): ?>
                <br><small><?php echo htmlspecialchars($errorMessage); ?></small>
            <?php endif; ?>
        </p>
    </div>
<?php else: ?>
    <!-- Tables List -->
    <div class="mb-8">
        <h2 class="text-2xl font-semibold text-gray-800 mb-4 border-b-2 border-gemvc-green pb-2.5">Database Tables</h2>
        <?php if (empty($tables)): ?>
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-5 rounded">
                <p class="text-yellow-800 m-0">No tables found in the database.</p>
            </div>
        <?php else: ?>
            <div class="bg-gray-50 rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Table
                                Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Rows</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Size</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Comment
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($tables as $table): ?>
                            <tr
                                class="hover:bg-gray-50 <?php echo $selectedTable === $table['TABLE_NAME'] ? 'bg-green-50' : ''; ?>">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($table['TABLE_NAME']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-600"><?php echo number_format($table['TABLE_ROWS'] ?? 0); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-600">
                                        <?php
                                        $size = ($table['DATA_LENGTH'] ?? 0) + ($table['INDEX_LENGTH'] ?? 0);
                                        echo $size > 0 ? number_format($size / 1024, 2) . ' KB' : '0 KB';
                                        ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-600">
                                        <?php echo htmlspecialchars($table['TABLE_COMMENT'] ?? '-'); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <form method="POST" class="inline m-0 p-0">
                                        <input type="hidden" name="set_table"
                                            value="<?php echo htmlspecialchars($table['TABLE_NAME']); ?>">
                                        <button type="submit"
                                            class="text-gemvc-green hover:text-gemvc-green-dark font-medium text-sm transition-colors bg-transparent border-0 cursor-pointer p-0">
                                            View Structure →
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Separator -->
    <?php if ($selectedTable && $tableStructure): ?>
        <hr class="my-10 border-t-2 border-gray-300">
    <?php endif; ?>

    <!-- Table Structure -->
    <?php if ($selectedTable && $tableStructure): ?>
        <div class="mb-8 bg-green-50 rounded-lg p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-2xl font-semibold text-gray-800 border-b-2 border-gemvc-green pb-2.5">
                    Table Structure: <span class="text-gemvc-green"><?php echo htmlspecialchars($selectedTable); ?></span>
                </h2>
                <div class="flex items-center gap-3">
                    <!-- Export Buttons -->
                    <div class="flex items-center gap-2">
                        <form method="POST" class="inline m-0 p-0">
                            <input type="hidden" name="export_table" value="<?php echo htmlspecialchars($selectedTable); ?>">
                            <input type="hidden" name="export_format" value="csv">
                            <button type="submit"
                                class="bg-gemvc-green hover:bg-gemvc-green-dark text-white text-xs font-medium px-3 py-1.5 rounded transition-colors">
                                Export CSV
                            </button>
                        </form>
                        <form method="POST" class="inline m-0 p-0">
                            <input type="hidden" name="export_table" value="<?php echo htmlspecialchars($selectedTable); ?>">
                            <input type="hidden" name="export_format" value="sql">
                            <button type="submit"
                                class="bg-gemvc-green hover:bg-gemvc-green-dark text-white text-xs font-medium px-3 py-1.5 rounded transition-colors">
                                Export SQL
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Import Section -->
            <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-3">Import Data</h3>
                <form method="POST" enctype="multipart/form-data" class="flex items-center gap-3">
                    <input type="hidden" name="import_table" value="<?php echo htmlspecialchars($selectedTable); ?>">
                    <input type="file" name="import_file" accept=".csv,.sql" required
                        class="text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-gemvc-green file:text-white hover:file:bg-gemvc-green-dark">
                    <select name="import_format" required class="text-sm border border-gray-300 rounded px-3 py-2">
                        <option value="csv">CSV</option>
                        <option value="sql">SQL</option>
                    </select>
                    <button type="submit"
                        class="bg-blue-500 hover:bg-blue-600 text-white text-sm font-medium px-4 py-2 rounded transition-colors">
                        Import
                    </button>
                </form>
            </div>

            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['export_error'])): ?>
                <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded mb-4">
                    <p class="text-red-800 text-sm">Export Error: <?php echo htmlspecialchars($_SESSION['export_error']); ?></p>
                </div>
                <?php unset($_SESSION['export_error']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['import_success'])): ?>
                <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded mb-4">
                    <p class="text-green-800 text-sm"><?php echo htmlspecialchars($_SESSION['import_success']); ?></p>
                </div>
                <?php unset($_SESSION['import_success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['import_error'])): ?>
                <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded mb-4">
                    <p class="text-red-800 text-sm">Import Error: <?php echo htmlspecialchars($_SESSION['import_error']); ?></p>
                </div>
                <?php unset($_SESSION['import_error']); ?>
            <?php endif; ?>

            <!-- Columns -->
            <div class="bg-gray-50 rounded-lg overflow-hidden mb-6">
                <h3 class="bg-gray-100 px-6 py-3 text-lg font-semibold text-gray-800">Columns</h3>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Column
                                Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Nullable
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Key</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Default
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Extra
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Comment
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($tableStructure as $column): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($column['COLUMN_NAME']); ?>
                                        <?php if ($column['COLUMN_KEY'] === 'PRI'): ?>
                                            <span
                                                class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">PK</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <code
                                        class="text-sm text-gray-800 bg-gray-100 px-2 py-1 rounded"><?php echo htmlspecialchars($column['COLUMN_TYPE']); ?></code>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        class="text-sm <?php echo $column['IS_NULLABLE'] === 'YES' ? 'text-orange-600' : 'text-gray-600'; ?>">
                                        <?php echo $column['IS_NULLABLE'] === 'YES' ? 'YES' : 'NO'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-600">
                                        <?php
                                        if ($column['COLUMN_KEY'] === 'PRI') {
                                            echo '<span class="text-blue-600 font-medium">PRIMARY</span>';
                                        } elseif ($column['COLUMN_KEY'] === 'UNI') {
                                            echo '<span class="text-purple-600 font-medium">UNIQUE</span>';
                                        } elseif ($column['COLUMN_KEY'] === 'MUL') {
                                            echo '<span class="text-green-600 font-medium">INDEX</span>';
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-600">
                                        <?php
                                        if ($column['COLUMN_DEFAULT'] !== null) {
                                            // Show the actual default value
                                            echo htmlspecialchars($column['COLUMN_DEFAULT']);
                                        } else {
                                            // No default set
                                            echo '<span class="text-gray-400">-</span>';
                                        }
                                        ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-600">
                                        <?php echo htmlspecialchars($column['EXTRA'] ?: '-'); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-600">
                                        <?php echo htmlspecialchars($column['COLUMN_COMMENT'] ?: '-'); ?></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Relationships -->
            <?php if (!empty($tableRelationships)): ?>
                <div class="bg-gray-50 rounded-lg overflow-hidden">
                    <h3 class="bg-gray-100 px-6 py-3 text-lg font-semibold text-gray-800">Foreign Key Relationships</h3>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Column
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">
                                    References Table</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">
                                    References Column</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">On Update
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">On Delete
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($tableRelationships as $fk): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($fk['COLUMN_NAME']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <form method="POST" class="inline m-0 p-0">
                                            <input type="hidden" name="set_table"
                                                value="<?php echo htmlspecialchars($fk['REFERENCED_TABLE_NAME']); ?>">
                                            <button type="submit"
                                                class="text-sm text-gemvc-green hover:text-gemvc-green-dark font-medium transition-colors bg-transparent border-0 cursor-pointer p-0">
                                                <?php echo htmlspecialchars($fk['REFERENCED_TABLE_NAME']); ?> →
                                            </button>
                                        </form>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-600">
                                            <?php echo htmlspecialchars($fk['REFERENCED_COLUMN_NAME']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-gray-600"><?php echo htmlspecialchars($fk['UPDATE_RULE']); ?></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-gray-600"><?php echo htmlspecialchars($fk['DELETE_RULE']); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="bg-gray-50 rounded-lg p-5">
                    <p class="text-gray-600 text-sm">No foreign key relationships found for this table.</p>
                </div>
            <?php endif; ?>
        </div>
    <?php elseif ($selectedTable): ?>
        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-5 rounded mb-5">
            <p class="text-yellow-800 m-0">Table "<?php echo htmlspecialchars($selectedTable); ?>" not found or could not be
                accessed.</p>
        </div>
    <?php endif; ?>
<?php endif; ?>