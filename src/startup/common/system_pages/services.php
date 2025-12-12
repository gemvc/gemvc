<?php
/**
 * Services Management Page Content
 * 
 * This template provides the content for the services management page.
 * The HTML structure, header, and footer are handled by index.php.
 * 
 * @var string $baseUrl The base URL of the application
 * @var string $apiBaseUrl The base URL for API endpoints
 * @var string $webserverType The detected webserver type ('apache', 'nginx', 'swoole')
 * @var string $webserverName The detected webserver name (Apache, Nginx, or OpenSwoole)
 * @var string $templateDir The directory path where this template is located
 * @var array<int, array<string, mixed>> $services List of API services with endpoints
 * @var int $totalServices Total number of services
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
        <button onclick="showCreateServiceModal()" class="bg-gemvc-green hover:bg-gemvc-green-dark text-white font-medium py-2 px-4 rounded-lg transition-colors">
            + Create New Service
        </button>
    </div>
    <div class="text-center">
        <h1 class="text-5xl font-bold text-gemvc-green mb-2.5 tracking-tight">API Services Manager</h1>
        <p class="text-lg font-normal text-gray-600">Manage your API endpoints and create new services</p>
    </div>
</div>

<!-- Create Service Modal -->
<div id="createServiceModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6 m-4">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-2xl font-bold text-gray-800">Create New Service</h2>
            <button onclick="hideCreateServiceModal()" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
        </div>
        <form id="createServiceForm" class="space-y-4">
            <div>
                <label for="serviceName" class="block text-sm font-medium text-gray-700 mb-2">Service Name (CamelCase)</label>
                <input type="text" id="serviceName" name="serviceName" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gemvc-green focus:border-transparent"
                    placeholder="Product" pattern="[A-Z][a-zA-Z0-9]*" title="Must start with uppercase letter">
                <small class="text-gray-500">e.g., Product, User, Order</small>
            </div>
            <div>
                <label for="serviceType" class="block text-sm font-medium text-gray-700 mb-2">Service Type</label>
                <select id="serviceType" name="type" required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gemvc-green focus:border-transparent">
                    <option value="crud">Full CRUD (Service + Controller + Model + Table)</option>
                    <option value="service">Service Only</option>
                    <option value="service-controller">Service + Controller</option>
                    <option value="service-model">Service + Model</option>
                </select>
            </div>
            <div id="createServiceError" class="hidden bg-red-50 border-l-4 border-red-500 p-3 rounded">
                <p class="text-red-800 text-sm" id="createServiceErrorText"></p>
            </div>
            <div class="flex gap-3">
                <button type="submit" id="createServiceButton"
                    class="flex-1 bg-gemvc-green hover:bg-gemvc-green-dark text-white font-medium py-2 px-4 rounded-lg transition-colors">
                    Create Service
                </button>
                <button type="button" onclick="hideCreateServiceModal()"
                    class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium py-2 px-4 rounded-lg transition-colors">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Services List -->
<div class="mb-8">
    <h2 class="text-2xl font-semibold text-gray-800 mb-4 border-b-2 border-gemvc-green pb-2.5">API Services (<?php echo $totalServices; ?>)</h2>
    <?php if (empty($services)): ?>
        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-5 rounded">
            <p class="text-yellow-800 m-0">No services found. Create your first service to get started!</p>
        </div>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($services as $index => $service): ?>
                <div class="bg-white border border-gray-200 rounded-lg shadow-md overflow-hidden">
                    <button 
                        type="button"
                        onclick="toggleService(<?php echo $index; ?>)"
                        class="w-full bg-gray-50 px-6 py-4 border-b border-gray-200 hover:bg-gray-100 transition-colors text-left focus:outline-none focus:ring-2 focus:ring-gemvc-green focus:ring-inset"
                        aria-expanded="false"
                        id="service-toggle-<?php echo $index; ?>">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <svg id="service-icon-<?php echo $index; ?>" class="w-5 h-5 text-gray-500 transition-transform transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h3 class="text-xl font-semibold text-gray-800">
                                            <?php echo htmlspecialchars(ucfirst($service['name'])); ?>
                                        </h3>
                                        <?php if (!empty($service['hidden']) && $service['hidden']): ?>
                                            <span class="inline-block bg-yellow-100 text-yellow-800 text-xs font-semibold px-2 py-0.5 rounded">HIDDEN</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($service['description'])): ?>
                                        <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($service['description']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="text-right flex items-center gap-3">
                                <span class="inline-block bg-gemvc-green text-white text-xs font-semibold px-4 py-1.5 rounded-full whitespace-nowrap min-w-[fit-content]">
                                    <?php echo $service['endpointCount']; ?> endpoint<?php echo $service['endpointCount'] !== 1 ? 's' : ''; ?>
                                </span>
                            </div>
                        </div>
                    </button>
                    <div 
                        id="service-content-<?php echo $index; ?>" 
                        class="hidden px-6 py-4">
                        <h4 class="text-sm font-semibold text-gray-700 uppercase tracking-wider mb-3">Endpoints</h4>
                        <div class="space-y-3">
                            <?php foreach ($service['endpoints'] as $endpoint): ?>
                                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                    <div class="flex items-center gap-8 mb-2 flex-wrap">
                                        <div class="flex items-center gap-3 flex-shrink-0">
                                            <span class="inline-block px-2 py-1 text-xs font-semibold rounded
                                                <?php 
                                                $method = strtoupper($endpoint['method'] ?? 'GET');
                                                echo match($method) {
                                                    'GET' => 'bg-blue-100 text-blue-800',
                                                    'POST' => 'bg-green-100 text-green-800',
                                                    'PUT' => 'bg-yellow-100 text-yellow-800',
                                                    'DELETE' => 'bg-red-100 text-red-800',
                                                    'PATCH' => 'bg-purple-100 text-purple-800',
                                                    default => 'bg-gray-100 text-gray-800'
                                                };
                                                ?>">
                                                <?php echo htmlspecialchars($method); ?>
                                            </span>
                                            <?php if (!empty($endpoint['hidden']) && $endpoint['hidden']): ?>
                                                <span class="inline-block bg-yellow-100 text-yellow-800 text-xs font-semibold px-2 py-0.5 rounded">HIDDEN</span>
                                            <?php endif; ?>
                                            <?php
                                            // For GET requests, append query parameters to URL if they exist
                                            $displayUrl = $endpoint['url'] ?? '';
                                            if ($method === 'GET' && !empty($endpoint['get_parameters'])) {
                                                $queryParams = [];
                                                foreach ($endpoint['get_parameters'] as $paramName => $paramInfo) {
                                                    $paramType = $paramInfo['type'] ?? 'string';
                                                    $queryParams[] = $paramName . '=' . $paramType;
                                                }
                                                if (!empty($queryParams)) {
                                                    $separator = strpos($displayUrl, '?') !== false ? '&' : '?';
                                                    $displayUrl .= $separator . implode('&', $queryParams);
                                                }
                                            }
                                            ?>
                                            <code class="text-sm font-mono text-gray-800"><?php echo htmlspecialchars($displayUrl); ?></code>
                                        </div>
                                        <?php if (!empty($endpoint['description'])): ?>
                                            <p class="text-sm  ml-6 text-gray-800 flex-shrink-0"><?php echo htmlspecialchars($endpoint['description']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php 
                                    // Show all parameters (both GET and POST) in the Parameters section
                                    $allParams = array_merge(
                                        $endpoint['get_parameters'] ?? [],
                                        $endpoint['parameters'] ?? []
                                    );
                                    ?>
                                    <?php if (!empty($allParams)): ?>
                                        <div class="mt-3">
                                            <p class="text-xs font-semibold text-gray-700 uppercase mb-2">Parameters</p>
                                            <div class="flex flex-wrap gap-2">
                                                <?php 
                                                foreach ($allParams as $paramName => $paramInfo): 
                                                    $isRequired = isset($paramInfo['required']) && $paramInfo['required'];
                                                    $paramType = $paramInfo['type'] ?? 'string';
                                                ?>
                                                    <div class="flex items-center gap-1.5 text-xs bg-white px-2 py-1 rounded border border-gray-300">
                                                        <code class="font-mono text-gray-800"><?php echo htmlspecialchars($paramName); ?></code>
                                                        <span class="text-gray-500">(<?php echo htmlspecialchars($paramType); ?>)</span>
                                                        <?php if ($isRequired): ?>
                                                            <span class="text-red-600 font-semibold">*</span>
                                                        <?php else: ?>
                                                            <span class="text-gray-400 text-[10px]">opt</span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
// Functions are defined in SPA's renderServices() function
// This script tag is kept for compatibility but functions are wired up in spa.html
</script>

