<?php
/**
 * Developer Welcome Page Content
 * 
 * This template provides the content for the developer welcome page.
 * The HTML structure, header, and footer are handled by index.php.
 * 
 * @var string $baseUrl The base URL of the application
 * @var string $apiBaseUrl The base URL for API endpoints
 * @var string $webserverType The detected webserver type ('apache', 'nginx', 'swoole')
 * @var string $webserverName The detected webserver name (Apache, Nginx, or OpenSwoole)
 * @var string $templateDir The directory path where this template is located
 * @var string $gemvcLogoUrl The base64 encoded GEMVC logo (if available)
 * @var string $webserverLogoUrl The base64 encoded webserver logo (if available)
 */

// Security check: Defense-in-depth (already protected by index.php, but extra safety)
if (($_ENV['APP_ENV'] ?? '') !== 'dev') {
    http_response_code(404);
    exit('Not Found');
}

// Check database connectivity (presentation logic)
$databaseReady = false;
try {
    $dbManager = \Gemvc\Database\DatabaseManagerFactory::getManager();
    $connection = $dbManager->getConnection();
    if ($connection !== null) {
        // Try a simple query to verify database is accessible
        $connection->query('SELECT 1');
        $databaseReady = true;
    }
} catch (\Exception $e) {
    // Database not accessible
    $databaseReady = false;
}
?>

<div class="text-center mb-10">
    <?php if ($gemvcLogoUrl || $webserverLogoUrl): ?>
        <div class="flex items-center justify-center gap-5 mb-5 flex-wrap">
            <?php if ($gemvcLogoUrl): ?>
                <img class="max-w-[50px] h-auto" src="<?php echo htmlspecialchars($gemvcLogoUrl); ?>"
                    alt="GEMVC Framework" />
            <?php endif; ?>
            <?php if ($gemvcLogoUrl && $webserverLogoUrl): ?>
                <span class="text-2xl font-semibold text-gemvc-green">+</span>
            <?php endif; ?>
            <?php if ($webserverLogoUrl): ?>
                <img class="max-w-[100px] h-auto" src="<?php echo htmlspecialchars($webserverLogoUrl); ?>"
                    alt="<?php echo htmlspecialchars($webserverName); ?>" />
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if ($webserverType === 'swoole'): ?>
        <div class="bg-gradient-to-r from-green-600 to-blue-800 p-4 rounded-lg mb-6">
            <p class="text-white font-semibold text-lg m-0">
                Congratulations! you made the Best Choice for Modern PHP Development!<br>
                GEMVC is Built Specially for openSwoole!<br>
            </p>
        </div>
    <?php endif; ?>
    <h1 class="text-5xl font-bold text-gemvc-green mb-2.5 tracking-tight">GEMVC Framework</h1>
    <p class="text-lg font-normal text-gray-600">Development Server Running</p>
</div>

<div class="bg-green-50 border-l-4 border-gemvc-green p-5 mb-8 rounded">
    <strong class="text-gemvc-green">✓ Server Status:</strong> Running successfully on
    <strong><?php echo htmlspecialchars($baseUrl); ?></strong><br>
    <?php if ($databaseReady): ?>
        <strong class="text-gemvc-green">✓ Database:</strong> Database is connected and accessible<br>
    <?php else: ?>
        <strong class="text-gemvc-green">⚠ Database Status:</strong> Database not initialized<br>
    <?php endif; ?>
    <strong class="text-gemvc-green">✓ CurrentWebserver: </strong><?php echo htmlspecialchars($webserverName); ?>
</div>

<div class="mb-8">
    <h2 class="text-2xl font-semibold text-gray-800 mb-4 border-b-2 border-gemvc-green pb-2.5 tracking-tight">Quick Start</h2>
    <?php if (!$databaseReady): ?>
        <div class="bg-gray-50 rounded-lg p-4 mb-2.5 flex items-center justify-between">
            <div>
                <strong class="block">Initialize Database</strong>
                <small class="text-gray-600">Initializing your database for the first time</small>
            </div>
            <code class="font-mono bg-gray-800 text-green-400 px-3 py-2 rounded text-sm">php vendor/bin/gemvc db:init</code>
        </div>
    <?php endif; ?>

    <div class="bg-gray-50 rounded-lg p-4 mb-2.5 flex items-center justify-between">
        <div>
            <strong class="block">1. Create Your API Service</strong>
            <small class="text-gray-600">Generate CRUD API service (use CamelCase, e.g., Product →
                <?php echo htmlspecialchars($apiBaseUrl); ?>/product). This command creates at once:</small>
            <ul class="mt-2 ml-5 p-0 text-gray-600 text-sm list-disc">
                <li>app/api/<span class="text-gemvc-green font-medium">Product.php</span></li>
                <li>app/controller/<span class="text-gemvc-green font-medium">ProductController.php</span></li>
                <li>app/model/<span class="text-gemvc-green font-medium">ProductModel.php</span></li>
                <li>app/table/<span class="text-gemvc-green font-medium">ProductTable.php</span></li>
            </ul>
        </div>
        <code class="font-mono bg-gray-800 text-green-400 px-3 py-2 rounded text-sm">php vendor/bin/gemvc create:crud Product</code>
    </div>

    <div class="bg-gray-50 rounded-lg p-4 mb-2.5 flex items-center justify-between">
        <div>
            <strong class="block">2. Migrate Your Table</strong>
            <?php if (!$databaseReady): ?>
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-3 my-2.5 rounded">
                    <strong class="text-yellow-800">⚠ Warning:</strong> <span class="text-yellow-800">Your database
                        is not initialized yet. Run <code class="bg-gray-100 px-1.5 py-0.5 rounded text-xs">php vendor/bin/gemvc db:init</code>
                        command first.</span>
                </div>
            <?php endif; ?>
            <small class="text-gray-600">Create or migrate your created table class to the database</small>
        </div>
        <code class="font-mono bg-gray-800 text-green-400 px-3 py-2 rounded text-sm">php vendor/bin/gemvc db:migrate ProductTable</code>
    </div>

    <div class="bg-green-500 text-white rounded-lg p-4 mb-2.5 flex items-center justify-between">
        <div>
            <strong class="block  p-2 rounded-lg">3. Visit Interactive API documentation</strong>
            <small class="text-gray-600">Verify your newly created Service input parameters in the documentation and enjoy one
                click Export to postman collection to test your API!</small>
        </div>
        <?php
        // For Swoole: use baseUrl/Index/document, for others: use apiBaseUrl/Index/document
        $docsUrl = ($webserverType === 'swoole') 
            ? $baseUrl . ':9501/index/document'
            : $apiBaseUrl . 'api/index/document';
        ?>
        <a href="<?php echo htmlspecialchars($docsUrl); ?>" 
            class="text-white no-underline font-medium transition-colors hover:text-gemvc-green-dark hover:underline"
            target="_blank">View Docs →</a>
    </div>
</div>

<div class="text-center py-10 px-5 mb-4">
    <h2 class="border-0 mb-5 text-2xl font-semibold text-gray-800">Thats it! Happy Coding!</h2>
    <div class="flex justify-center gap-5 flex-wrap mb-8">
        <a href="https://gemvc.de" target="_blank" 
            class="text-gemvc-green no-underline font-medium transition-colors hover:text-gemvc-green-dark hover:underline text-base py-2.5 px-5 border-2 border-gemvc-green rounded-lg inline-block">
            Visit gemvc.de →
        </a>
        <a href="https://github.com/gemvc/gemvc" target="_blank" 
            class="text-gemvc-green no-underline font-medium transition-colors hover:text-gemvc-green-dark hover:underline text-base py-2.5 px-5 border-2 border-gemvc-green rounded-lg inline-block flex items-center gap-2">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path fill-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" clip-rule="evenodd"></path>
            </svg>
            <span>GEMVC Repository →</span>
        </a>
        <a href="https://github.com/gemvc/gemvc/fork" target="_blank" 
            class="text-gemvc-green no-underline font-medium transition-colors hover:text-gemvc-green-dark hover:underline text-base py-2.5 px-5 border-2 border-gemvc-green rounded-lg inline-block flex items-center gap-2">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm0 8a2 2 0 0 1 2 2v4H6v-4a2 2 0 0 1 2-2zm0 8a2 2 0 0 1 2 2v4H6v-4a2 2 0 0 1 2-2zm8-16a2 2 0 0 1-2 2v4h4V3a2 2 0 0 1-2-2zm-2 8a2 2 0 0 1 2 2v4h-4v-4a2 2 0 0 1 2-2zm2 8a2 2 0 0 1-2 2v-4h4v4a2 2 0 0 1-2 2z"></path>
            </svg>
            <span>Fork GEMVC →</span>
        </a>
    </div>
</div>

<div class="bg-blue-50 border-l-4 border-yellow-400 p-5 mt-5 rounded">
    <p class="text-yellow-800 m-0">
        <strong>Note:</strong> this page is only accessible on a development environment. In production, only a standard 404
        page will be accessible for security reasons. easily switch to  production mode by setting the environment variable APP_ENV= "to any value rather than dev".
    </p>
</div>
