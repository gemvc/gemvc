<?php
// Load environment and construct API base URL
try {
    \Gemvc\Helper\ProjectHelper::loadEnv();
    $apiBaseUrl = \Gemvc\Helper\ProjectHelper::getApiBaseUrl();
} catch (\Exception $e) {
    // Fallback to default if env can't be loaded - but still try to read port from HTTP_HOST or env
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) && is_string($_SERVER['HTTP_HOST'])
        ? $_SERVER['HTTP_HOST']
        : 'localhost';
    
    // Extract port from HTTP_HOST if it exists (e.g., localhost:82)
    $detectedPort = null;
    if (preg_match('/:(\d+)$/', $host, $matches)) {
        $detectedPort = (int) $matches[1];
        $host = preg_replace('/:\d+$/', '', $host);
    }
    
    // Use detected port from HTTP_HOST if available, otherwise use env variable
    if ($detectedPort !== null) {
        $port = $detectedPort;
    } else {
        $portEnv = $_ENV['APP_ENV_PUBLIC_SERVER_PORT'] ?? '80';
        $port = is_numeric($portEnv) ? (int) $portEnv : 80;
    }
    $portDisplay = ($port !== 80 && $port !== 443) ? ':' . $port : '';
    
    // Get API sub URL from env (matches ProjectHelper::getApiBaseUrl logic exactly)
    // If empty, endpoints are directly on base URL (no /api prefix) - e.g., Swoole: localhost:9501/user/create
    // If set (e.g., 'api' or 'apiv2'), endpoints are on base URL + sub URL - e.g., localhost:9501/api/user/create
    $apiSubUrl = isset($_ENV['APP_ENV_API_DEFAULT_SUB_URL']) && is_string($_ENV['APP_ENV_API_DEFAULT_SUB_URL'])
        ? trim(trim($_ENV['APP_ENV_API_DEFAULT_SUB_URL'], '\'"'), '/')
        : '';
    $apiSubUrl = $apiSubUrl !== '' ? '/' . $apiSubUrl : '';
    
    $apiBaseUrl = rtrim($protocol . '://' . $host . $portDisplay . $apiSubUrl, '/');
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>GEMVC Framework - Development Server</title>
        <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars($apiBaseUrl, ENT_QUOTES, 'UTF-8'); ?>/index/favicon">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = {
                theme: {
                    extend: {
                        fontFamily: {
                            sans: [
                                'Inter',
                                '-apple-system',
                                'BlinkMacSystemFont',
                                'Segoe UI',
                                'Roboto',
                                'Helvetica Neue',
                                'Arial',
                                'sans-serif'
                            ]
                        },
                        colors: {
                            gemvc: {
                                green: '#10b981',
                                'green-dark': '#059669'
                            }
                        }
                    }
                }
            }
        </script>
        <style>
            body {
                -webkit-font-smoothing: antialiased;
                -moz-osx-font-smoothing: grayscale;
            }
        </style>
    </head>
    <body
        class="min-h-screen bg-gradient-to-br from-gemvc-green to-gemvc-green-dark font-sans font-normal pt-20 pb-24">
        <!-- Navigation Bar -->
        <nav id="navbar" class="fixed top-0 left-0 right-0 w-full bg-white shadow-md z-50 hidden">
            <div class="max-w-6xl mx-auto px-10 py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-6">
                        <a id="logoLink" href="#" class="inline-flex items-center p-0 m-0 bg-transparent border-0 cursor-pointer no-underline">
                            <img id="gemvcLogo" class="h-8 w-auto" src="" alt="GEMVC Framework"/>
                        </a>
                        <a href="#" data-route="welcome" class="nav-link text-base font-medium transition-colors no-underline text-gray-600 hover:text-gemvc-green">Home</a>
                        <a href="#" data-route="services" class="nav-link text-base font-medium transition-colors no-underline text-gray-600 hover:text-gemvc-green">Services</a>
                        <a href="#" data-route="tables" class="nav-link text-base font-medium transition-colors no-underline text-gray-600 hover:text-gemvc-green">Tables Layer</a>
                        <a href="#" data-route="database" class="nav-link text-base font-medium transition-colors no-underline text-gray-600 hover:text-gemvc-green">Database</a>
                    </div>
                    <div class="flex items-center gap-4">
                        <button onclick="logout()" class="text-base font-medium transition-colors text-gray-600 hover:text-red-600 bg-transparent border-0 cursor-pointer">Logout</button>
                        <a class="bg-yellow-200 p-1 font-medium   rounded-md " href="https://buymeacoffee.com/gemvc"><span>Buy Me a Coffee </span></a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content Area -->
        <div id="app" class="flex items-center justify-center p-5">
            <div class="bg-white rounded-xl shadow-2xl max-w-6xl w-full p-10">
                <div id="content">Loading...</div>
            </div>
        </div>

        <!-- Footer -->
        <footer id="footer" class="fixed bottom-0 left-0 right-0 w-full bg-gray-900 border-t border-gray-700 shadow-md z-50 hidden">
            <div class="max-w-6xl mx-auto px-10 py-4">
                <div class="flex flex-col items-center gap-3">
                    <div class="flex items-center justify-center gap-6 flex-wrap">
                        <a href="https://gemvc.de" target="_blank" class="text-gray-300 hover:text-gemvc-green no-underline font-medium transition-colors text-sm">
                            gemvc.de
                        </a>
                        <span class="text-gray-600">|</span>
                        <a href="https://github.com/gemvc/gemvc/fork" target="_blank" class="text-gray-300 hover:text-gemvc-green no-underline font-medium transition-colors text-sm">
                            Fork on GitHub
                        </a>
                        <span class="text-gray-600">|</span>
                        <a href="https://github.com/gemvc/gemvc" target="_blank" class="text-gray-300 hover:text-gemvc-green no-underline font-medium transition-colors text-sm flex items-center gap-1">
                            <svg class="w-4 h-4" fill="currentColor" viewbox="0 0 24 24" aria-hidden="true">
                                <path fill-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" clip-rule="evenodd"></path>
                            </svg>
                            <span>GEMVC on GitHub</span>
                        </a>
                    </div>
                    <div class="text-xs text-gray-400 text-center">
                        Made with ❤️ |
                        <a href="https://opensource.org/licenses/MIT" target="_blank" class="text-gray-400 hover:text-gemvc-green transition-colors">MIT License</a>
                        | Forever Free
                    </div>
                </div>
            </div>
        </footer>

        <script>
            // SPA Router and Application
            (function () { 
                // API Base URL - injected from PHP (.env configuration)
                const API_BASE = <?php echo json_encode($apiBaseUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
                console.log('API Base URL (from .env):', API_BASE);

                let currentRoute = 'login';
                let token = localStorage.getItem('gemvc_admin_token');

                // JWT Token Management
                const originalFetch = window.fetch;
                window.fetch = function (url, options = {}) {
                    // Initialize options if not provided
                    options = options || {};
                    
                    // Always get the latest token from localStorage (source of truth)
                    const currentToken = localStorage.getItem('gemvc_admin_token');
                    if (currentToken) {
                        // Handle Headers object or plain object
                        if (options.headers instanceof Headers) {
                            // If it's a Headers object, check if Authorization is already set
                            if (!options.headers.has('Authorization') && !options.headers.has('authorization')) {
                                options.headers.set('Authorization', 'Bearer ' + currentToken);
                            }
                        } else {
                            // If it's a plain object or undefined, create/use plain object
                            options.headers = options.headers || {};
                            if (!options.headers['Authorization'] && !options.headers['authorization']) {
                                options.headers['Authorization'] = 'Bearer ' + currentToken;
                            }
                        }
                    }
                    return originalFetch(url, options);
                };

                // Router
                function getRoute() {
                    const hash = window.location.hash.slice(1) || 'login';
                    return hash;
                }

                function setRoute(route) {
                    window.location.hash = route;
                    currentRoute = route;
                }

                async function loadPage(route) {
                    try { // Check authentication for protected routes
                        if (route !== 'login' && ! token) {
                            setRoute('login');
                            return;
                        }

                        // Show/hide navbar and footer
                        const navbar = document.getElementById('navbar');
                        const footer = document.getElementById('footer');
                        if (route === 'login') {
                            navbar.classList.add('hidden');
                            footer.classList.add('hidden');
                        } else {
                            navbar.classList.remove('hidden');
                            footer.classList.remove('hidden');
                            updateNavbar(route);
                        }

                        // Load page content
                        const content = document.getElementById('content');
                        content.innerHTML = '<div class="text-center">Loading...</div>';

                        switch (route) {
                            case 'login':
                                await renderLogin();
                                break;
                            case 'welcome':
                                await renderWelcome();
                                break;
                            case 'services':
                                await renderServices();
                                break;
                            case 'tables':
                                await renderTables();
                                break;
                            case 'database':
                                await renderDatabase();
                                break;
                            default: content.innerHTML = '<div class="text-center text-red-600">Page not found</div>';
                        }
                    } catch (error) {
                        console.error('Error loading page:', error);
                        document.getElementById('content').innerHTML = '<div class="text-center text-red-600">Error loading page: ' + error.message + '</div>';
                    }
                }

                function updateNavbar(activeRoute) {
                    document.querySelectorAll('.nav-link').forEach(link => {
                        const route = link.getAttribute('data-route');
                        if (route === activeRoute) {
                            link.classList.add('text-gemvc-green', 'border-b-2', 'border-gemvc-green', 'pb-1');
                            link.classList.remove('text-gray-600');
                        } else {
                            link.classList.remove('text-gemvc-green', 'border-b-2', 'border-gemvc-green', 'pb-1');
                            link.classList.add('text-gray-600');
                        }
                    });
                }

                async function renderLogin() { // Change content area styling for login page
                    const appDiv = document.getElementById('app');
                    appDiv.className = 'flex items-center justify-center min-h-screen p-5';
                    const contentWrapper = appDiv.querySelector('div');
                    contentWrapper.className = 'bg-white rounded-xl shadow-2xl max-w-md w-full p-10';

                    const content = document.getElementById('content');
                    content.innerHTML = `
                    <div class="text-center mb-8">
                        <img id="loginLogo" src="" alt="GEMVC Logo" class="h-16 mx-auto mb-4 hidden">
                        <h1 class="text-3xl font-bold text-gemvc-green mb-2 tracking-tight">Developer Login</h1>
                        <p class="text-gray-600"></p>
                    </div>
                    <div id="loginError" class="hidden bg-red-50 border-l-4 border-red-500 p-4 rounded mb-6">
                        <p class="text-red-800 text-sm" id="loginErrorText"></p>
                    </div>
                    <form id="loginForm" class="space-y-6">
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                            <input type="email" id="email" name="email" required autofocus
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gemvc-green focus:border-transparent transition-colors"
                                placeholder="Enter your email">
                        </div>
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                            <input type="password" id="password" name="password" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gemvc-green focus:border-transparent transition-colors"
                                placeholder="Enter your password">
                        </div>
                        <button type="submit" id="loginButton"
                            class="w-full bg-gemvc-green hover:bg-gemvc-green-dark text-white font-medium py-3 px-4 rounded-lg transition-colors shadow-md hover:shadow-lg">
                            Login
                        </button>
                    </form>
                    <div id="adminPasswordHint" class="mt-6 text-center">
                        <p class="">
                             
                            <a href="${API_BASE}/User/create" class="text-gemvc-green no-underline font-medium transition-colors hover:text-gemvc-green-dark hover:underline cursor-pointer">Create Admin User</a>
                        </p>
                    </div>
                `;

                    // Load logo
                    const logoResponse = await fetch(API_BASE + '/index/logo');
                    if (logoResponse.ok) {
                        const logoData = await logoResponse.json();
                        if (logoData.data && logoData.data.gemvcLogo) {
                            document.getElementById('loginLogo').src = logoData.data.gemvcLogo;
                            document.getElementById('loginLogo').classList.remove('hidden');
                        }
                    }

                    // Handle login form
                    document.getElementById('loginForm').addEventListener('submit', async (e) => {
                        e.preventDefault();
                        const errorDiv = document.getElementById('loginError');
                        const errorText = document.getElementById('loginErrorText');
                        const loginButton = document.getElementById('loginButton');

                        errorDiv.classList.add('hidden');
                        loginButton.disabled = true;
                        loginButton.textContent = 'Logging in...';

                        try {
                            const email = document.getElementById('email').value;
                            const password = document.getElementById('password').value;

                            const response = await fetch(API_BASE + '/User/login', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({
                                    email: email,
                                    password: password
                                })
                            });

                            const data = await response.json();

                            if (response.ok && data.data) {
                                // Store login token (prefer login_token, fallback to access_token)
                                const loginToken = data.data.login_token || data.data.access_token;
                                if (loginToken) {
                                    token = loginToken;
                                    localStorage.setItem('gemvc_admin_token', token);
                                    // Ensure token is stored before navigation
                                    token = localStorage.getItem('gemvc_admin_token');
                                    if (token) {
                                        setRoute('welcome');
                                    } else {
                                        throw new Error('Failed to store authentication token');
                                    }
                                } else {
                                    throw new Error('No authentication token in response');
                                }
                            } else {
                                // Show error message
                                const errorMessage = data.service_message || data.message || 'Login failed. Please check your email and password.';
                                errorText.textContent = errorMessage;
                                errorDiv.classList.remove('hidden');
                                loginButton.disabled = false;
                                loginButton.textContent = 'Login';
                            }
                        } catch (error) {
                            errorText.textContent = 'Network error. Please try again.';
                            errorDiv.classList.remove('hidden');
                            loginButton.disabled = false;
                            loginButton.textContent = 'Login';
                        }
                    });
                }

                async function renderWelcome() { // Reset content area styling for normal pages
                    const appDiv = document.getElementById('app');
                    appDiv.className = 'flex items-center justify-center p-5';
                    const contentWrapper = appDiv.querySelector('div');
                    contentWrapper.className = 'bg-white rounded-xl shadow-2xl max-w-6xl w-full p-10';

                    // Get token from localStorage
                    const currentToken = localStorage.getItem('gemvc_admin_token');
                    const headers = {
                        'Content-Type': 'application/json'
                    };
                    if (currentToken) {
                        headers['Authorization'] = 'Bearer ' + currentToken;
                    }

                    // Fetch welcome page HTML and database status in parallel
                    const [welcomeResponse, dbStatusResponse] = await Promise.all([
                        fetch(API_BASE + '/index/welcome', { headers: headers }),
                        fetch(API_BASE + '/index/isDbReady', { headers: headers })
                    ]);

                    if (! welcomeResponse.ok) {
                        if (welcomeResponse.status === 401) {
                            setRoute('login');
                            return;
                        }
                        throw new Error('Failed to load welcome page');
                    }

                    const welcomeData = await welcomeResponse.json();
                    const content = document.getElementById('content');
                    content.innerHTML = welcomeData.data.html || '<div>Welcome page content</div>';

                    // Update database status based on API response
                    let databaseReady = false;
                    if (dbStatusResponse.ok) {
                        const dbStatusData = await dbStatusResponse.json();
                        databaseReady = dbStatusData.data && dbStatusData.data.databaseReady === true;
                    }

                    // Update database status display in the rendered HTML
                    const statusBox = content.querySelector('.bg-green-50');
                    if (statusBox) {
                        // Find database status by searching for strong elements with Database text
                        const allStrongs = statusBox.querySelectorAll('strong');
                        allStrongs.forEach(strong => {
                            const text = strong.textContent || '';
                            if (text.includes('Database')) {
                                // Found database status line - replace it
                                const parent = strong.parentElement;
                                if (parent) {
                                    // Get the full line HTML
                                    let lineHTML = parent.innerHTML;
                                    // Replace the database status part
                                    if (databaseReady) {
                                        lineHTML = lineHTML.replace(
                                            /<strong[^>]*class="[^"]*"[^>]*>.*?Database.*?<\/strong>[^<]*<br>/i,
                                            '<strong class="text-gemvc-green">✓ Database:</strong> Database is connected and accessible<br>'
                                        );
                                    } else {
                                        lineHTML = lineHTML.replace(
                                            /<strong[^>]*class="[^"]*"[^>]*>.*?Database.*?<\/strong>[^<]*<br>/i,
                                            '<strong class="text-red-500">⚠ Database Status:</strong> Database not initialized<br>'
                                        );
                                    }
                                    parent.innerHTML = lineHTML;
                                }
                            }
                        });
                    }

                    // Convert "Initialize Database" section to clickable button
                    const allSections = content.querySelectorAll('.bg-gray-50');
                    allSections.forEach(section => {
                        const codeElement = section.querySelector('code');
                        if (codeElement && codeElement.textContent.includes('db:init')) {
                            if (databaseReady) {
                                section.style.display = 'none';
                            } else {
                                section.style.display = 'block';
                                // Replace code element with clickable button
                                const button = document.createElement('button');
                                button.className = 'bg-gemvc-green hover:bg-gemvc-green-dark text-white font-medium py-2 px-4 rounded transition-colors';
                                button.textContent = 'Initialize Database';
                                button.onclick = async () => {
                                    button.disabled = true;
                                    button.textContent = 'Initializing...';
                                    
                                    try {
                                        const initResponse = await fetch(API_BASE + '/index/initDatabase', {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/json',
                                                'Authorization': 'Bearer ' + token
                                            }
                                        });
                                        
                                        const initData = await initResponse.json();
                                        
                                        if (initResponse.ok && initData.response_code === 200) {
                                            // Success - reload the welcome page to show updated status
                                            await renderWelcome();
                                        } else {
                                            alert('Failed to initialize database: ' + (initData.service_message || initData.message || 'Unknown error'));
                                            button.disabled = false;
                                            button.textContent = 'Initialize Database';
                                        }
                                    } catch (error) {
                                        console.error('Error initializing database:', error);
                                        alert('Failed to initialize database: ' + error.message);
                                        button.disabled = false;
                                        button.textContent = 'Initialize Database';
                                    }
                                };
                                
                                // Replace the code element with button
                                const codeParent = codeElement.parentElement;
                                if (codeParent) {
                                    codeParent.replaceChild(button, codeElement);
                                }
                            }
                        }
                    });

                    // Show/hide warning in migrate section
                    const migrateWarning = content.querySelector('.bg-yellow-50');
                    if (migrateWarning) {
                        if (databaseReady) {
                            migrateWarning.style.display = 'none';
                        } else {
                            migrateWarning.style.display = 'block';
                        }
                    }

                    // Convert "View Database" button to SPA navigation
                    const dbButton = content.querySelector('form[method="POST"] button');
                    if (dbButton) {
                        dbButton.type = 'button';
                        dbButton.onclick = () => setRoute('database');
                        dbButton.closest('form').remove();
                        const dbCard = dbButton.closest('.bg-gray-50');
                        if (dbCard) {
                            const link = document.createElement('a');
                            link.href = '#';
                            link.className = 'text-gemvc-green no-underline font-medium transition-colors hover:text-gemvc-green-dark hover:underline cursor-pointer';
                            link.textContent = 'View Database →';
                            link.onclick = (e) => {
                                e.preventDefault();
                                setRoute('database');
                            };
                            dbCard.querySelector('div').parentNode.replaceChild(link, dbButton.parentNode);
                        }
                    }

                    // Wire up "Create Service" button to navigate to services page
                    const createServiceButton = content.querySelector('a[data-route="services"]');
                    if (createServiceButton) {
                        createServiceButton.onclick = (e) => {
                            e.preventDefault();
                            setRoute('services');
                        };
                    }

                    // Wire up "Migrate Table" button to navigate to tables page
                    const migrateTableButton = content.querySelector('a[data-route="tables"]');
                    if (migrateTableButton) {
                        migrateTableButton.onclick = (e) => {
                            e.preventDefault();
                            setRoute('tables');
                        };
                    }
                }

                // AbortController for canceling in-flight database requests
                let databaseRequestController = null;
                let databaseRequestTimeout = null;

                async function renderDatabase(tableName = null) { // Reset content area styling for normal pages
                    // Cancel any in-flight request
                    if (databaseRequestController) {
                        databaseRequestController.abort();
                    }
                    if (databaseRequestTimeout) {
                        clearTimeout(databaseRequestTimeout);
                    }

                    // Debounce rapid requests (wait 150ms before making request)
                    return new Promise((resolve) => {
                        databaseRequestTimeout = setTimeout(async () => {
                            const appDiv = document.getElementById('app');
                            appDiv.className = 'flex items-center justify-center p-5';
                            const contentWrapper = appDiv.querySelector('div');
                            contentWrapper.className = 'bg-white rounded-xl shadow-2xl max-w-6xl w-full p-10';

                            // Create new AbortController for this request
                            databaseRequestController = new AbortController();

                            try {
                                // Build URL with table parameter if provided
                                let url = API_BASE + '/index/database/';
                                if (tableName) {
                                    url += '?table=' + encodeURIComponent(tableName);
                                }

                                const response = await fetch(url, {
                                    signal: databaseRequestController.signal
                                });
                                
                                if (! response.ok) {
                                    if (response.status === 401) {
                                        setRoute('login');
                                        resolve();
                                        return;
                                    }
                                    throw new Error('Failed to load database page');
                                }

                                const data = await response.json();
                                const content = document.getElementById('content');
                                content.innerHTML = data.data.html || '<div>Database page content</div>';
                                
                                // Convert "Back to Home" button to SPA navigation
                                const backButton = content.querySelector('form[method="POST"] button');
                                if (backButton && backButton.textContent.includes('Back')) {
                                    backButton.type = 'button';
                                    backButton.onclick = () => setRoute('welcome');
                                    backButton.closest('form').remove();
                                }

                                // Convert "View Structure" buttons to SPA navigation
                                // Convert NodeList to Array to avoid issues with DOM mutations
                                Array.from(content.querySelectorAll('form[method="POST"]')).forEach(form => {
                                    const setTableInput = form.querySelector('input[name="set_table"]');
                                    if (setTableInput) {
                                        const tableName = setTableInput.value;
                                        const button = form.querySelector('button[type="submit"]');
                                        if (button && button.textContent.includes('View Structure')) { // Extract button from form before removing form
                                            const parent = form.parentNode;
                                            if (parent) {
                                                button.type = 'button';
                                                button.onclick = async (e) => {
                                                    e.preventDefault();
                                                    e.stopPropagation();
                                                    // Reload database page with selected table
                                                    await renderDatabase(tableName);
                                                };
                                                // Replace form with button
                                                parent.replaceChild(button, form);
                                            }
                                        }
                                    }
                                });
                                
                                // Convert export buttons to AJAX
                                // Convert NodeList to Array to avoid issues with DOM mutations
                                Array.from(content.querySelectorAll('form[method="POST"]')).forEach(form => {
                                    const exportTableInput = form.querySelector('input[name="export_table"]');
                                    const exportFormatInput = form.querySelector('input[name="export_format"]');
                                    if (exportTableInput && exportFormatInput) {
                                        const button = form.querySelector('button[type="submit"]');
                                        if (button) { // Extract button from form before removing form
                                            const parent = form.parentNode;
                                            button.type = 'button';
                                            button.onclick = async () => {
                                                const tableName = exportTableInput.value;
                                                const format = exportFormatInput.value;

                                                try {
                                                    const exportResponse = await fetch(API_BASE + '/index/export', {
                                                        method: 'POST',
                                                        headers: {
                                                            'Content-Type': 'application/json'
                                                        },
                                                        body: JSON.stringify(
                                                            {table: tableName, format: format}
                                                        )
                                                    });
                                                    
                                                    if (exportResponse.ok) {
                                                        const blob = await exportResponse.blob();
                                                        const url = window.URL.createObjectURL(blob);
                                                        const a = document.createElement('a');
                                                        a.href = url;
                                                        a.download = tableName + '_' + new Date().toISOString().slice(0, 10) + '.' + (format === 'csv' ? 'csv' : 'sql');
                                                        document.body.appendChild(a);
                                                        a.click();
                                                        document.body.removeChild(a);
                                                        window.URL.revokeObjectURL(url);
                                                    } else {
                                                        alert('Export failed');
                                                    }
                                                } catch (error) {
                                                    console.error('Export error:', error);
                                                    alert('Export failed: ' + error.message);
                                                }
                                            };
                                            // Replace form with button
                                            parent.replaceChild(button, form);
                                        }
                                    }
                                });

                                // Convert import form to AJAX
                                const importForm = content.querySelector('form[enctype="multipart/form-data"]');
                                if (importForm) {
                                    importForm.addEventListener('submit', async (e) => {
                                        e.preventDefault();
                                        const formData = new FormData(importForm);
                                        const importTableInput = importForm.querySelector('input[name="import_table"]');
                                        const importFormatInput = importForm.querySelector('select[name="import_format"]');
                                        
                                        if (!importTableInput || !importFormatInput) {
                                            alert('Invalid import form');
                                            return;
                                        }
                                        
                                        const tableName = importTableInput.value;
                                        const format = importFormatInput.value;
                                        
                                        try {
                                            const importResponse = await fetch(API_BASE + '/index/import', {
                                                method: 'POST',
                                                body: formData
                                            });
                                            
                                            const importData = await importResponse.json();
                                            
                                            if (importResponse.ok && importData.response_code === 200) {
                                                alert('Import successful: ' + (importData.data?.message || 'Data imported'));
                                                // Reload database page to show updated data
                                                await renderDatabase(tableName);
                                            } else {
                                                alert('Import failed: ' + (importData.data?.error || importData.service_message || 'Unknown error'));
                                            }
                                        } catch (error) {
                                            console.error('Import error:', error);
                                            alert('Import failed: ' + error.message);
                                        }
                                    });
                                }
                                
                                // Clear controller after successful request
                                databaseRequestController = null;
                                resolve();
                            } catch (error) {
                                // Ignore abort errors (user switched tables)
                                if (error.name === 'AbortError') {
                                    console.log('Database request cancelled');
                                    resolve();
                                    return;
                                }
                                console.error('Error loading database page:', error);
                                const content = document.getElementById('content');
                                content.innerHTML = '<div class="text-center text-red-600">Error loading database page: ' + error.message + '</div>';
                                databaseRequestController = null;
                                resolve();
                            }
                        }, 150); // 150ms debounce
                    });
                }

                async function renderServices() {
                    // Reset content area styling for normal pages
                    const appDiv = document.getElementById('app');
                    appDiv.className = 'flex items-center justify-center p-5';
                    const contentWrapper = appDiv.querySelector('div');
                    contentWrapper.className = 'bg-white rounded-xl shadow-2xl max-w-6xl w-full p-10';

                    const response = await fetch(API_BASE + '/index/services');
                    if (!response.ok) {
                        if (response.status === 401) {
                            setRoute('login');
                            return;
                        }
                        throw new Error('Failed to load services page');
                    }

                    const data = await response.json();
                    const content = document.getElementById('content');
                    content.innerHTML = data.data.html || '<div>Services page content</div>';

                    // Convert "Back to Home" button to SPA navigation
                    const backButton = content.querySelector('form[method="POST"] button');
                    if (backButton && backButton.textContent.includes('Back')) {
                        backButton.type = 'button';
                        backButton.onclick = () => setRoute('welcome');
                        backButton.closest('form').remove();
                    }

                    // Handle create service form submission
                    const createServiceForm = document.getElementById('createServiceForm');
                    if (createServiceForm) {
                        createServiceForm.addEventListener('submit', async (e) => {
                            e.preventDefault();
                            
                            const formData = new FormData(createServiceForm);
                            const serviceName = formData.get('serviceName');
                            const type = formData.get('type');
                            
                            const errorDiv = document.getElementById('createServiceError');
                            const errorText = document.getElementById('createServiceErrorText');
                            const submitButton = document.getElementById('createServiceButton');
                            
                            if (!serviceName || !type) {
                                if (errorDiv && errorText) {
                                    errorText.textContent = 'Please fill in all fields';
                                    errorDiv.classList.remove('hidden');
                                }
                                return;
                            }
                            
                            // Disable button and show loading
                            if (submitButton) {
                                submitButton.disabled = true;
                                submitButton.textContent = 'Creating...';
                            }
                            
                            if (errorDiv) {
                                errorDiv.classList.add('hidden');
                            }
                            
                            try {
                                const createResponse = await fetch(API_BASE + '/index/createService', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Authorization': 'Bearer ' + token
                                    },
                                    body: JSON.stringify({
                                        serviceName: serviceName,
                                        type: type
                                    })
                                });
                                
                                const createData = await createResponse.json();
                                
                                if (createResponse.ok && createData.response_code === 200) {
                                    // Success - hide modal and reload services page
                                    hideCreateServiceModal();
                                    await renderServices();
                                } else {
                                    // Show error
                                    if (errorDiv && errorText) {
                                        errorText.textContent = createData.data?.error || createData.service_message || 'Failed to create service';
                                        errorDiv.classList.remove('hidden');
                                    }
                                }
                            } catch (error) {
                                console.error('Error creating service:', error);
                                if (errorDiv && errorText) {
                                    errorText.textContent = 'Failed to create service: ' + error.message;
                                    errorDiv.classList.remove('hidden');
                                }
                            } finally {
                                if (submitButton) {
                                    submitButton.disabled = false;
                                    submitButton.textContent = 'Create Service';
                                }
                            }
                        });
                    }
                    
                    // Wire up modal functions
                    window.showCreateServiceModal = function() {
                        const modal = document.getElementById('createServiceModal');
                        if (modal) {
                            modal.classList.remove('hidden');
                        }
                    };
                    
                    window.hideCreateServiceModal = function() {
                        const modal = document.getElementById('createServiceModal');
                        if (modal) {
                            modal.classList.add('hidden');
                            const form = document.getElementById('createServiceForm');
                            if (form) {
                                form.reset();
                            }
                            const errorDiv = document.getElementById('createServiceError');
                            if (errorDiv) {
                                errorDiv.classList.add('hidden');
                            }
                        }
                    };
                    
                    // Wire up accordion toggle function
                    window.toggleService = function(index) {
                        const content = document.getElementById('service-content-' + index);
                        const icon = document.getElementById('service-icon-' + index);
                        const button = document.getElementById('service-toggle-' + index);
                        
                        if (content && icon && button) {
                            const isExpanded = !content.classList.contains('hidden');
                            
                            if (isExpanded) {
                                // Collapse
                                content.classList.add('hidden');
                                icon.classList.remove('rotate-90');
                                button.setAttribute('aria-expanded', 'false');
                            } else {
                                // Expand
                                content.classList.remove('hidden');
                                icon.classList.add('rotate-90');
                                button.setAttribute('aria-expanded', 'true');
                            }
                        }
                    };
                }

                async function renderTables() {
                    // Reset content area styling for normal pages
                    const appDiv = document.getElementById('app');
                    appDiv.className = 'flex items-center justify-center p-5';
                    const contentWrapper = appDiv.querySelector('div');
                    contentWrapper.className = 'bg-white rounded-xl shadow-2xl max-w-6xl w-full p-10';

                    const response = await fetch(API_BASE + '/index/tables');
                    if (!response.ok) {
                        if (response.status === 401) {
                            setRoute('login');
                            return;
                        }
                        throw new Error('Failed to load tables page');
                    }

                    const data = await response.json();
                    const content = document.getElementById('content');
                    content.innerHTML = data.data.html || '<div>Tables page content</div>';

                    // Convert "Back to Home" button to SPA navigation
                    const backButton = content.querySelector('form[method="POST"] button');
                    if (backButton && backButton.textContent.includes('Back')) {
                        backButton.type = 'button';
                        backButton.onclick = () => setRoute('welcome');
                        backButton.closest('form').remove();
                    }

                    // Wire up migration buttons
                    window.migrateTable = async function(tableClassName, isUpdate) {
                        const button = document.querySelector('.migrate-btn-' + tableClassName);
                        if (!button) return;
                        
                        const originalText = button.textContent;
                        button.disabled = true;
                        button.textContent = isUpdate ? 'Updating...' : 'Migrating...';
                        
                        try {
                            const migrateResponse = await fetch(API_BASE + '/index/migrateTable', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Authorization': 'Bearer ' + token
                                },
                                body: JSON.stringify({
                                    tableClassName: tableClassName
                                })
                            });
                            
                            const migrateData = await migrateResponse.json();
                            
                            if (migrateResponse.ok && migrateData.response_code === 200) {
                                alert(migrateData.data?.message || 'Table migrated successfully!');
                                // Reload tables page to show updated status
                                await renderTables();
                            } else {
                                alert('Migration failed: ' + (migrateData.data?.error || migrateData.service_message || 'Unknown error'));
                                button.disabled = false;
                                button.textContent = originalText;
                            }
                        } catch (error) {
                            console.error('Error migrating table:', error);
                            alert('Failed to migrate table: ' + error.message);
                            button.disabled = false;
                            button.textContent = originalText;
                        }
                    };

                    // Render schema diagram if relationships exist
                    const schemaSvg = document.getElementById('schemaSvg');
                    if (schemaSvg && data.data && data.data.relationships) {
                        renderSchemaDiagram(schemaSvg, data.data.relationships, data.data.tableClasses);
                    }
                }

                function renderSchemaDiagram(svg, relationships, tableClasses) {
                    // Simple schema diagram rendering
                    const tables = {};
                    tableClasses.forEach(table => {
                        if (table.isMigrated) {
                            tables[table.tableName] = table;
                        }
                    });

                    // Clear existing content
                    svg.innerHTML = '';
                    
                    // Add arrow marker definition
                    const defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
                    const marker = document.createElementNS('http://www.w3.org/2000/svg', 'marker');
                    marker.setAttribute('id', 'arrowhead');
                    marker.setAttribute('markerWidth', '10');
                    marker.setAttribute('markerHeight', '10');
                    marker.setAttribute('refX', '9');
                    marker.setAttribute('refY', '3');
                    marker.setAttribute('orient', 'auto');
                    const polygon = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
                    polygon.setAttribute('points', '0 0, 10 3, 0 6');
                    polygon.setAttribute('fill', '#3b82f6');
                    marker.appendChild(polygon);
                    defs.appendChild(marker);
                    svg.appendChild(defs);
                    
                    let y = 30;
                    let x = 20;
                    const boxWidth = 200;
                    const boxHeight = 60;
                    const spacing = 250;
                    let row = 0;
                    let col = 0;
                    const colsPerRow = 3;

                    Object.keys(relationships).forEach((tableName, index) => {
                        if (col >= colsPerRow) {
                            col = 0;
                            row++;
                        }
                        
                        x = 20 + (col * spacing);
                        y = 30 + (row * (boxHeight + 80));
                        
                        // Draw table box
                        const rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
                        rect.setAttribute('x', x);
                        rect.setAttribute('y', y);
                        rect.setAttribute('width', boxWidth);
                        rect.setAttribute('height', boxHeight);
                        rect.setAttribute('fill', '#f3f4f6');
                        rect.setAttribute('stroke', '#10b981');
                        rect.setAttribute('stroke-width', '2');
                        rect.setAttribute('rx', '5');
                        svg.appendChild(rect);
                        
                        // Table name
                        const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                        text.setAttribute('x', x + 10);
                        text.setAttribute('y', y + 25);
                        text.setAttribute('font-family', 'monospace');
                        text.setAttribute('font-size', '14');
                        text.setAttribute('font-weight', 'bold');
                        text.setAttribute('fill', '#1f2937');
                        text.textContent = tableName;
                        svg.appendChild(text);
                        
                        // Foreign keys count
                        const fkText = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                        fkText.setAttribute('x', x + 10);
                        fkText.setAttribute('y', y + 45);
                        fkText.setAttribute('font-family', 'monospace');
                        fkText.setAttribute('font-size', '10');
                        fkText.setAttribute('fill', '#6b7280');
                        const fkCount = relationships[tableName].length;
                        fkText.textContent = fkCount + ' foreign key' + (fkCount !== 1 ? 's' : '');
                        svg.appendChild(fkText);
                        
                        col++;
                    });
                    
                    // Update SVG size
                    const totalHeight = 30 + ((row + 1) * (boxHeight + 80));
                    svg.setAttribute('height', totalHeight.toString());
                }

                window.logout = function () {
                    token = null;
                    localStorage.removeItem('gemvc_admin_token');
                    setRoute('login');
                };

                // Navigation links
                document.addEventListener('click', (e) => {
                    if (e.target.matches('.nav-link')) {
                        e.preventDefault();
                        const route = e.target.getAttribute('data-route');
                        setRoute(route);
                    }
                    // Logo link navigation
                    if (e.target.closest('#logoLink')) {
                        e.preventDefault();
                        setRoute('welcome');
                    }
                });

                // Hash change handler
                window.addEventListener('hashchange', () => {
                    loadPage(getRoute());
                });

                // Load navbar logo
                async function loadNavbarLogo() {
                    try {
                        const logoResponse = await fetch(API_BASE + '/index/logo');
                        if (logoResponse.ok) {
                            const logoData = await logoResponse.json();
                            if (logoData.data && logoData.data.gemvcLogo) {
                                const logoImg = document.getElementById('gemvcLogo');
                                logoImg.src = logoData.data.gemvcLogo;
                                logoImg.style.display = 'block';
                            }
                        }
                    } catch (error) {
                        console.error('Error loading navbar logo:', error);
                    }
                }

                // Initial load
                loadPage(getRoute());
                loadNavbarLogo();
            })();
        </script>
    </body>
</html>
