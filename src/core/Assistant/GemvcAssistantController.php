<?php
namespace Gemvc\Core\Assistant;

use Gemvc\Core\Assistant\GemvcAssistantModel;
use Gemvc\Core\Developer\DeveloperController;
use Gemvc\Core\Controller;
use Gemvc\Http\Request;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\HtmlResponse;
use Gemvc\Http\Response;

class GemvcAssistantController extends Controller
{
    public function __construct(Request $request)
    {
        parent::__construct($request);
    }

    /**
     * Export Table
     * 
     * @return HtmlResponse
     */
    public function export(): HtmlResponse
    {
        $model = new GemvcAssistantModel();
        $table = isset($this->request->post['table']) && is_string($this->request->post['table']) ? $this->request->post['table'] : '';
        $format = isset($this->request->post['format']) && is_string($this->request->post['format']) ? $this->request->post['format'] : 'csv';
        return $model->export($table, $format);
    }

    /**
     * Import Table
     * 
     * @return JsonResponse
     */
    public function import(): JsonResponse
    {
        $model = new GemvcAssistantModel();
        $table = isset($this->request->post['table']) && is_string($this->request->post['table']) ? $this->request->post['table'] : '';
        $format = isset($this->request->post['format']) && is_string($this->request->post['format']) ? $this->request->post['format'] : 'csv';
        $file = null;
        if (isset($this->request->files['import_file']) && is_array($this->request->files['import_file'])) {
            // Ensure array is properly typed as array<string, mixed>
            $fileArray = [];
            foreach ($this->request->files['import_file'] as $key => $value) {
                if (is_string($key)) {
                    $fileArray[$key] = $value;
                }
            }
            $file = $fileArray;
        }
        
        return $model->import($table, $format, $file);
    }

    /**
     * Database Management Page Data
     * 
     * @return JsonResponse
     */
    public function database(): JsonResponse
    {
        // Use DeveloperController for template rendering
        $developerController = new DeveloperController($this->request);
        return $developerController->database();
    }

    /**
     * Get API Configuration
     * 
     * @return JsonResponse
     */
    public function config(): JsonResponse
    {
        $model = new GemvcAssistantModel();
        return $model->getConfig();
    }

    /**
     * Check Database Ready Status
     * 
     * @return JsonResponse
     */
    public function isDbReady(): JsonResponse
    {
        $model = new GemvcAssistantModel();
        return $model->isDbReady();
    }

    /**
     * Initialize Database
     * 
     * @return JsonResponse
     */
    public function initDatabase(): JsonResponse
    {
        $model = new GemvcAssistantModel();
        return $model->initDatabase();
    }

    /**
     * Services Management Page Data
     * 
     * @return JsonResponse
     */
    public function services(): JsonResponse
    {
        // Use DeveloperController for template rendering
        $developerController = new DeveloperController($this->request);
        return $developerController->services();
    }

    /**
     * Create New Service
     * 
     * @return JsonResponse
     */
    public function createService(): JsonResponse
    {
        $model = new GemvcAssistantModel();
        $serviceName = isset($this->request->post['serviceName']) && is_string($this->request->post['serviceName']) ? $this->request->post['serviceName'] : '';
        $type = isset($this->request->post['type']) && is_string($this->request->post['type']) ? $this->request->post['type'] : 'crud';
        return $model->createService($serviceName, $type);
    }

    /**
     * Tables Layer Management Page Data
     * 
     * @return JsonResponse
     */
    public function tables(): JsonResponse
    {
        // Use DeveloperController for template rendering
        $developerController = new DeveloperController($this->request);
        return $developerController->tables();
    }

    /**
     * Migrate or Update Table
     * 
     * @return JsonResponse
     */
    public function migrateTable(): JsonResponse
    {
        $model = new GemvcAssistantModel();
        $tableClassName = isset($this->request->post['tableClassName']) && is_string($this->request->post['tableClassName']) ? $this->request->post['tableClassName'] : '';
        return $model->migrateTable($tableClassName);
    }
}

