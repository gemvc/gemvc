<?php
/**
 * Developer Controller Layer
 * 
 * Orchestrates business logic for developer tools
 * This is the Controller layer - business logic orchestration
 */
namespace Gemvc\Core\Developer;

use Gemvc\Core\Developer\DeveloperModel;
use Gemvc\Core\Controller;
use Gemvc\Http\Request;
use Gemvc\Http\HtmlResponse;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\Response;
use Gemvc\Http\NoCors;
use Gemvc\Core\WebserverDetector;

class DeveloperController extends Controller
{
    private string $templateDir;

    public function __construct(Request $request)
    {
        parent::__construct($request);
        
        // Get template directory path (library's system_pages)
        $this->templateDir = \Gemvc\Helper\ProjectHelper::getLibrarySystemPagesPath();
        
        // Start output buffering early to prevent "headers already sent" warnings
        if (ob_get_level() === 0) {
            ob_start();
        }
        
        // Populate $_SERVER for compatibility
        if (!isset($_SERVER['REQUEST_METHOD']) && isset($request->requestMethod)) {
            $_SERVER['REQUEST_METHOD'] = $request->requestMethod;
        }
        
        // Populate $_POST, $_GET, $_FILES for compatibility
        if (empty($_POST) && !empty($request->post)) {
            $_POST = $request->post;
        }
        if (empty($_GET) && !empty($request->get)) {
            $_GET = $request->get;
        }
        if (empty($_FILES) && !empty($request->files)) {
            $_FILES = $request->files;
        }
    }

    public function devInfo(): JsonResponse
    {
        $developerModel = new DeveloperModel();
        $info = new \stdClass();
        $info->server = WebserverDetector::get();
        $info->version = \Gemvc\Helper\ProjectHelper::getVersion();
        $info->environment = \Gemvc\Helper\ProjectHelper::getAppEnv();
        $info->databaseName = $developerModel->getDatabaseName();
        $info->database = $developerModel->isDatabaseReady() ? 'ready' :  $developerModel->getError();
        $info->devAssistantUrl = $developerModel->getDevAssistantUrl();
        $info->documentationUrl = $developerModel->getDocumentationUrl();
        return Response::success($info,1,'server running');
    }

    /**
     * Handle admin login
     * 
     * @return JsonResponse
     */
    public function login(): JsonResponse
    {
        // Add CORS headers for Apache/Nginx (Swoole handles CORS at server level)
        if (WebserverDetector::get() !== 'swoole') {
            NoCors::apache();
        }
        
        // Extract password from validated POST data
        $password = isset($this->request->post['password']) && is_string($this->request->post['password'])
            ? $this->request->post['password']
            : '';
        
        $model = new DeveloperModel();
        return $model->authenticate($password);
    }

    /**
     * Return SPA HTML shell
     * 
     * @return HtmlResponse
     */
    public function app(): HtmlResponse
    {
        // Try .php first (with PHP processing), then fallback to .html
        $spaPathPhp = $this->templateDir . DIRECTORY_SEPARATOR . 'spa.php';
        $spaPathHtml = $this->templateDir . DIRECTORY_SEPARATOR . 'spa.html';
        
        if (file_exists($spaPathPhp)) {
            // Execute PHP file and capture output
            ob_start();
            include $spaPathPhp;
            $html = ob_get_clean();
            if ($html === false || $html === '') {
                return new HtmlResponse('<html><body><h1>Failed to read SPA file</h1></body></html>', 500);
            }
            return new HtmlResponse($html);
        } elseif (file_exists($spaPathHtml)) {
            // Fallback to .html if .php doesn't exist
            $html = file_get_contents($spaPathHtml);
            if ($html === false) {
                return new HtmlResponse('<html><body><h1>Failed to read SPA file</h1></body></html>', 500);
            }
            return new HtmlResponse($html);
        }
        
        // Fallback: return simple error page if SPA not found
        return new HtmlResponse('<html><body><h1>SPA file not found</h1></body></html>', 500);
    }

    /**
     * Render welcome page
     * 
     * @return JsonResponse
     */
    public function welcome(): JsonResponse
    {
        $model = new DeveloperModel();
        $data = $model->getWelcomeData();
        
        // Prepare template variables
        $variables = $this->preparePageVariables();
        $variables = array_merge($variables, $data);
        
        // Render template
        $html = $this->renderTemplate('developer-welcome', $variables);
        
        return Response::success(['html' => $html]);
    }

    /**
     * Render database page
     * 
     * @return JsonResponse
     */
    public function database(): JsonResponse
    {
        // Extract selected table from GET parameters
        $selectedTable = isset($this->request->get['table']) && is_string($this->request->get['table']) 
            ? $this->request->get['table'] 
            : null;
        
        $model = new DeveloperModel();
        $data = $model->getDatabaseData($selectedTable);
        
        // Prepare template variables
        $variables = $this->preparePageVariables();
        $variables = array_merge($variables, $data);
        
        // Render template
        $html = $this->renderTemplate('database', $variables);
        
        return Response::success(['html' => $html]);
    }

    /**
     * Export table
     * 
     * @return HtmlResponse
     */
    public function export(): HtmlResponse
    {
        $tableName = isset($this->request->post['table']) && is_string($this->request->post['table'])
            ? $this->request->post['table']
            : '';
        $format = isset($this->request->post['format']) && is_string($this->request->post['format'])
            ? $this->request->post['format']
            : 'csv';
        
        if (empty($tableName)) {
            return new HtmlResponse('Table name is required', 400);
        }
        
        $model = new DeveloperModel();
        
        if ($format === 'csv') {
            $result = $model->exportTableAsCsv($tableName);
            if ($model->getError()) {
                return new HtmlResponse('Export error: ' . $model->getError(), 500);
            }
            
            $content = is_string($result['content'] ?? null) ? $result['content'] : '';
            $filename = is_string($result['filename'] ?? null) ? $result['filename'] : 'export.csv';
            
            return new HtmlResponse($content, 200, [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"'
            ]);
        } elseif ($format === 'sql') {
            $result = $model->exportTableAsSql($tableName);
            if ($model->getError()) {
                return new HtmlResponse('Export error: ' . $model->getError(), 500);
            }
            
            $content = is_string($result['content'] ?? null) ? $result['content'] : '';
            $filename = is_string($result['filename'] ?? null) ? $result['filename'] : 'export.sql';
            
            return new HtmlResponse($content, 200, [
                'Content-Type' => 'text/sql; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"'
            ]);
        } else {
            return new HtmlResponse('Invalid export format. Use "csv" or "sql"', 400);
        }
    }

    /**
     * Import table data
     * 
     * @return JsonResponse
     */
    public function import(): JsonResponse
    {
        $tableName = isset($this->request->post['table']) && is_string($this->request->post['table'])
            ? $this->request->post['table']
            : '';
        $format = isset($this->request->post['format']) && is_string($this->request->post['format'])
            ? $this->request->post['format']
            : 'csv';
        
        if (empty($tableName)) {
            return Response::badRequest('Table name is required');
        }
        
        // Check if file was uploaded
        if (empty($this->request->files) || !isset($this->request->files['import_file'])) {
            return Response::badRequest('No file uploaded');
        }
        
        $file = $this->request->files['import_file'];
        if (!is_array($file) || !isset($file['error']) || !isset($file['tmp_name'])) {
            return Response::badRequest('Invalid file upload');
        }
        
        $error = is_int($file['error']) ? $file['error'] : UPLOAD_ERR_NO_FILE;
        if ($error !== UPLOAD_ERR_OK) {
            return Response::badRequest('File upload error: ' . $error);
        }
        
        $tmpName = is_string($file['tmp_name']) ? $file['tmp_name'] : '';
        if (empty($tmpName)) {
            return Response::badRequest('Invalid file path');
        }
        
        $model = new DeveloperModel();
        
        if ($format === 'csv') {
            return $model->importCsv($tableName, $tmpName);
        } elseif ($format === 'sql') {
            return $model->importSql($tableName, $tmpName);
        } else {
            return Response::badRequest('Invalid import format. Use "csv" or "sql"');
        }
    }

    /**
     * Get logo data
     * 
     * @return JsonResponse
     */
    public function logo(): JsonResponse
    {
        $gemvcLogoUrl = null;
        $gemvcLogoPath = $this->templateDir . DIRECTORY_SEPARATOR . 'gemvc_logo.svg';
        if (file_exists($gemvcLogoPath)) {
            $logoContent = file_get_contents($gemvcLogoPath);
            if ($logoContent !== false) {
                $gemvcLogoUrl = 'data:image/svg+xml;base64,' . base64_encode($logoContent);
            }
        }
        
        // Check if admin password is set
        $adminPassword = isset($_ENV['ADMIN_PASSWORD']) && is_string($_ENV['ADMIN_PASSWORD'])
            ? $_ENV['ADMIN_PASSWORD']
            : '';
        $adminPasswordSet = !empty(trim($adminPassword));
        
        return Response::success([
            'gemvcLogo' => $gemvcLogoUrl,
            'adminPasswordSet' => $adminPasswordSet
        ]);
    }

    /**
     * Check database ready status
     * 
     * @return JsonResponse
     */
    public function isDbReady(): JsonResponse
    {
        $model = new DeveloperModel();
        return $model->isDbReady();
    }

    /**
     * Initialize database
     * 
     * @return JsonResponse
     */
    public function initDatabase(): JsonResponse
    {
        $model = new DeveloperModel();
        return $model->initDatabase();
    }

    /**
     * Render services page
     * 
     * @return JsonResponse
     */
    public function services(): JsonResponse
    {
        $model = new DeveloperModel();
        $data = $model->getServicesData();
        
        // Prepare template variables
        $variables = $this->preparePageVariables();
        $variables = array_merge($variables, $data);
        
        // Render template
        $html = $this->renderTemplate('services', $variables);
        
        return Response::success(['html' => $html]);
    }

    /**
     * Create a new service
     * 
     * @return JsonResponse
     */
    public function createService(): JsonResponse
    {
        // Extract service name and type from POST
        $serviceName = isset($this->request->post['serviceName']) && is_string($this->request->post['serviceName'])
            ? trim($this->request->post['serviceName'])
            : '';
        $type = isset($this->request->post['type']) && is_string($this->request->post['type'])
            ? $this->request->post['type']
            : 'crud';
        
        if (empty($serviceName)) {
            return Response::badRequest('Service name is required');
        }
        
        $model = new DeveloperModel();
        return $model->createService($serviceName, $type);
    }

    /**
     * Render tables page
     * 
     * @return JsonResponse
     */
    public function tables(): JsonResponse
    {
        $model = new DeveloperModel();
        $data = $model->getTablesData();
        
        // Prepare template variables
        $variables = $this->preparePageVariables();
        $variables = array_merge($variables, $data);
        
        // Render template
        $html = $this->renderTemplate('tables', $variables);
        
        return Response::success(['html' => $html]);
    }

    /**
     * Migrate or update a table
     * 
     * @return JsonResponse
     */
    public function migrateTable(): JsonResponse
    {
        // Extract table class name from POST
        $tableClassName = isset($this->request->post['tableClassName']) && is_string($this->request->post['tableClassName'])
            ? trim($this->request->post['tableClassName'])
            : '';
        
        if (empty($tableClassName)) {
            return Response::badRequest('Table class name is required');
        }
        
        $model = new DeveloperModel();
        return $model->migrateTable($tableClassName);
    }

    /**
     * Render template
     * 
     * @param string $pageName
     * @param array<string, mixed> $variables
     * @return string
     */
    private function renderTemplate(string $pageName, array $variables): string
    {
        extract($variables, EXTR_SKIP);
        
        // Load page content only (no layout)
        $pagePath = $this->templateDir . DIRECTORY_SEPARATOR . $pageName . '.php';
        if (file_exists($pagePath)) {
            ob_start();
            require $pagePath;
            $content = ob_get_clean();
            return is_string($content) ? $content : '';
        }
        
        // Fallback to developer-welcome
        $fallbackPath = $this->templateDir . DIRECTORY_SEPARATOR . 'developer-welcome.php';
        if (file_exists($fallbackPath)) {
            ob_start();
            require $fallbackPath;
            $content = ob_get_clean();
            return is_string($content) ? $content : '';
        }
        
        return '<div>Template not found</div>';
    }

    /**
     * Prepare common variables for page rendering
     * 
     * @return array<string, mixed>
     */
    private function preparePageVariables(): array
    {
        $webserverType = WebserverDetector::get();
        
        $apiBaseUrl = \Gemvc\Helper\ProjectHelper::getApiBaseUrl();
        $baseUrl = \Gemvc\Helper\ProjectHelper::getBaseUrl();
        $webserverName = match($webserverType) {
            'swoole' => 'OpenSwoole',
            'apache' => 'Apache',
            'nginx' => 'Nginx',
            default => ucfirst($webserverType)
        };

        // Load logos
        $gemvcLogoUrl = null;
        $gemvcLogoPath = $this->templateDir . DIRECTORY_SEPARATOR . 'gemvc_logo.svg';
        if (file_exists($gemvcLogoPath)) {
            $logoContent = file_get_contents($gemvcLogoPath);
            if ($logoContent !== false) {
                $gemvcLogoUrl = 'data:image/svg+xml;base64,' . base64_encode($logoContent);
            }
        }

        $webserverLogoUrl = null;
        if ($webserverType === 'apache') {
            $apacheLogoPath = $this->templateDir . DIRECTORY_SEPARATOR . 'apache.svg';
            if (file_exists($apacheLogoPath)) {
                $apacheLogoContent = file_get_contents($apacheLogoPath);
                if ($apacheLogoContent !== false) {
                    $webserverLogoUrl = 'data:image/svg+xml;base64,' . base64_encode($apacheLogoContent);
                }
            }
        } elseif ($webserverType === 'nginx') {
            $nginxLogoPath = $this->templateDir . DIRECTORY_SEPARATOR . 'nginx.svg';
            if (file_exists($nginxLogoPath)) {
                $nginxLogoContent = file_get_contents($nginxLogoPath);
                if ($nginxLogoContent !== false) {
                    $webserverLogoUrl = 'data:image/svg+xml;base64,' . base64_encode($nginxLogoContent);
                }
            }
        } elseif ($webserverType === 'swoole') {
            $swooleLogoPath = $this->templateDir . DIRECTORY_SEPARATOR . 'swoole.svg';
            if (file_exists($swooleLogoPath)) {
                $swooleLogoContent = file_get_contents($swooleLogoPath);
                if ($swooleLogoContent !== false) {
                    $webserverLogoUrl = 'data:image/svg+xml;base64,' . base64_encode($swooleLogoContent);
                }
            }
        }
        
        // Return all variables as array for extraction
        return [
            'baseUrl' => $baseUrl,
            'apiBaseUrl' => $apiBaseUrl,
            'webserverType' => $webserverType,
            'webserverName' => $webserverName,
            'gemvcLogoUrl' => $gemvcLogoUrl,
            'webserverLogoUrl' => $webserverLogoUrl,
            'templateDir' => $this->templateDir,
        ];
    }
}