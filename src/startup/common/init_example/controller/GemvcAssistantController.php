<?php
namespace App\Controller;

use App\Model\GemvcAssistantModel;
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
        return $model->export(
            $this->request->post['table'],
            $this->request->post['format']
        );
    }

    /**
     * Import Table
     * 
     * @return JsonResponse
     */
    public function import(): JsonResponse
    {
        $model = new GemvcAssistantModel();
        $table = $this->request->post['table'] ?? '';
        $format = $this->request->post['format'] ?? 'csv';
        $file = $this->request->files['import_file'] ?? null;
        
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
        $developerController = new \App\Controller\DeveloperController($this->request);
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
        $developerController = new \App\Controller\DeveloperController($this->request);
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
        return $model->createService(
            $this->request->post['serviceName'],
            $this->request->post['type']
        );
    }

    /**
     * Tables Layer Management Page Data
     * 
     * @return JsonResponse
     */
    public function tables(): JsonResponse
    {
        // Use DeveloperController for template rendering
        $developerController = new \App\Controller\DeveloperController($this->request);
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
        return $model->migrateTable($this->request->post['tableClassName']);
    }
}

