<?php
namespace Gemvc\Core\Assistant;

use Gemvc\Core\Developer\DeveloperModel;
use Gemvc\Helper\ProjectHelper;
use Gemvc\Core\WebserverDetector;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\HtmlResponse;
use Gemvc\Http\Response;

/**
 * GEMVC Assistant Model - Data logic layer for developer tools
 * 
 * This model handles developer/admin tools operations.
 * It wraps DeveloperModel and DeveloperController for a clean interface.
 */
class GemvcAssistantModel
{
    private DeveloperModel $developerModel;

    public function __construct()
    {
        $this->developerModel = new DeveloperModel();
    }

    /**
     * Export Table
     * 
     * @param string $tableName
     * @param string $format
     * @return HtmlResponse
     */
    public function export(string $tableName, string $format): HtmlResponse
    {
        if (empty($tableName)) {
            return new HtmlResponse('Table name is required', 400);
        }
        
        if ($format === 'csv') {
            $result = $this->developerModel->exportTableAsCsv($tableName);
            if ($this->developerModel->getError()) {
                return new HtmlResponse('Export error: ' . $this->developerModel->getError(), 500);
            }
            
            $content = is_string($result['content'] ?? null) ? $result['content'] : '';
            $filename = is_string($result['filename'] ?? null) ? $result['filename'] : 'export.csv';
            
            return new HtmlResponse($content, 200, [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"'
            ]);
        } elseif ($format === 'sql') {
            $result = $this->developerModel->exportTableAsSql($tableName);
            if ($this->developerModel->getError()) {
                return new HtmlResponse('Export error: ' . $this->developerModel->getError(), 500);
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
     * Import Table
     * 
     * @param string $tableName
     * @param string $format
     * @param array<string, mixed>|null $file
     * @return JsonResponse
     */
    public function import(string $tableName, string $format, ?array $file): JsonResponse
    {
        if (empty($tableName)) {
            return Response::badRequest('Table name is required');
        }
        
        // Check if file was uploaded
        if (empty($file) || !isset($file['error']) || !isset($file['tmp_name'])) {
            return Response::badRequest('No file uploaded');
        }
        
        $error = is_int($file['error']) ? $file['error'] : UPLOAD_ERR_NO_FILE;
        if ($error !== UPLOAD_ERR_OK) {
            return Response::badRequest('File upload error: ' . $error);
        }
        
        $tmpName = is_string($file['tmp_name']) ? $file['tmp_name'] : '';
        if (empty($tmpName)) {
            return Response::badRequest('Invalid file path');
        }
        
        if ($format === 'csv') {
            return $this->developerModel->importCsv($tableName, $tmpName);
        } elseif ($format === 'sql') {
            return $this->developerModel->importSql($tableName, $tmpName);
        } else {
            return Response::badRequest('Invalid import format. Use "csv" or "sql"');
        }
    }


    /**
     * Get API Configuration
     * 
     * @return JsonResponse
     */
    public function getConfig(): JsonResponse
    {
        ProjectHelper::loadEnv();
        $apiBaseUrl = ProjectHelper::getApiBaseUrl();
        $baseUrl = ProjectHelper::getBaseUrl();
        $webserverType = WebserverDetector::get();
        $webserverName = match($webserverType) {
            'swoole' => 'OpenSwoole',
            'apache' => 'Apache',
            'nginx' => 'Nginx',
            default => ucfirst($webserverType)
        };
        
        return Response::success([
            'apiBaseUrl' => $apiBaseUrl,
            'baseUrl' => $baseUrl,
            'webserverType' => $webserverType,
            'webserverName' => $webserverName,
            'publicServerPort' => $_ENV['APP_ENV_PUBLIC_SERVER_PORT'] ?? '80',
            'apiSubUrl' => $_ENV['APP_ENV_API_DEFAULT_SUB_URL'] ?? ''
        ]);
    }

    /**
     * Check Database Ready Status
     * 
     * @return JsonResponse
     */
    public function isDbReady(): JsonResponse
    {
        return $this->developerModel->isDbReady();
    }

    /**
     * Initialize Database
     * 
     * @return JsonResponse
     */
    public function initDatabase(): JsonResponse
    {
        return $this->developerModel->initDatabase();
    }


    /**
     * Create New Service
     * 
     * @param string $serviceName
     * @param string $type
     * @return JsonResponse
     */
    public function createService(string $serviceName, string $type): JsonResponse
    {
        if (empty(trim($serviceName))) {
            return Response::badRequest('Service name is required');
        }
        
        return $this->developerModel->createService($serviceName, $type);
    }


    /**
     * Migrate or Update Table
     * 
     * @param string $tableClassName
     * @return JsonResponse
     */
    public function migrateTable(string $tableClassName): JsonResponse
    {
        if (empty(trim($tableClassName))) {
            return Response::badRequest('Table class name is required');
        }
        
        return $this->developerModel->migrateTable($tableClassName);
    }
}

