<?php
namespace App\Controller;

use App\Model\GemvcMonitoringModel;
use Gemvc\Core\Controller;
use Gemvc\Http\Request;
use Gemvc\Http\JsonResponse;

class GemvcMonitoringController extends Controller
{
    public function __construct(Request $request)
    {
        parent::__construct($request);
    }

    /**
     * Get RAM metrics
     * 
     * @return JsonResponse
     */
    public function ram(): JsonResponse
    {
        $model = new GemvcMonitoringModel();
        return $model->getRamMetrics();
    }

    /**
     * Get CPU metrics
     * 
     * @return JsonResponse
     */
    public function cpu(): JsonResponse
    {
        $model = new GemvcMonitoringModel();
        return $model->getCpuMetrics();
    }

    /**
     * Get network metrics
     * 
     * @return JsonResponse
     */
    public function network(): JsonResponse
    {
        $model = new GemvcMonitoringModel();
        return $model->getNetworkMetrics();
    }

    /**
     * Get database connections
     * 
     * @return JsonResponse
     */
    public function databaseConnections(): JsonResponse
    {
        $model = new GemvcMonitoringModel();
        return $model->getDatabaseConnections();
    }

    /**
     * Get database pool statistics
     * 
     * @return JsonResponse
     */
    public function databasePool(): JsonResponse
    {
        $model = new GemvcMonitoringModel();
        return $model->getDatabasePoolStats();
    }

    /**
     * Get database latency
     * 
     * @return JsonResponse
     */
    public function databaseLatency(): JsonResponse
    {
        $model = new GemvcMonitoringModel();
        return $model->getDatabaseLatency();
    }
}

