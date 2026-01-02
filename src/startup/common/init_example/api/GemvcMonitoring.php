<?php
namespace App\Api;

use App\Controller\GemvcMonitoringController;
use Gemvc\Core\ApiService;
use Gemvc\Http\Request;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\Response;

class GemvcMonitoring extends ApiService
{
    /**
     * Constructor
     * 
     * @param Request $request The HTTP request object
     */
    public function __construct(Request $request)
    {
        parent::__construct($request);
    }

    /**
     * Get RAM Metrics
     * 
     * @return JsonResponse
     * @http GET
     * @description Get server RAM/memory usage metrics
     * @hidden
     * @example /api/GemvcMonitoring/ram
     */
    public function ram(): JsonResponse
    {
        // Authentication check
        if (!$this->request->auth(['developer','admin'])) {
            return Response::unauthorized('Authentication required');
        }
        
        return (new GemvcMonitoringController($this->request))->ram();
    }

    public function dockerRam(): JsonResponse
    {
        // Authentication check
        if (!$this->request->auth(['developer','admin'])) {
            return Response::unauthorized('Authentication required');
        }
        
        return (new GemvcMonitoringController($this->request))->dockerRam();
    }

    public function dockerCpu(): JsonResponse
    {
        // Authentication check
        if (!$this->request->auth(['developer','admin'])) {
            return Response::unauthorized('Authentication required');
        }
        
        return (new GemvcMonitoringController($this->request))->dockerCpu();
    }

    /**
     * Get CPU Metrics
     * 
     * @return JsonResponse
     * @http GET
     * @description Get server CPU usage and load metrics
     * @hidden
     * @example /api/GemvcMonitoring/cpu
     */
    public function cpu(): JsonResponse
    {
        // Authentication check
        if (!$this->request->auth(['developer','admin'])) {
            return Response::unauthorized('Authentication required');
        }
        
        return (new GemvcMonitoringController($this->request))->cpu();
    }

    /**
     * Get Network Metrics
     * 
     * @return JsonResponse
     * @http GET
     * @description Get network interface statistics
     * @hidden
     * @example /api/GemvcMonitoring/network
     */
    public function network(): JsonResponse
    {
        // Authentication check
        if (!$this->request->auth(['developer','admin'])) {
            return Response::unauthorized('Authentication required');
        }
        
        return (new GemvcMonitoringController($this->request))->network();
    }

    /**
     * Get Database Connections
     * 
     * @return JsonResponse
     * @http GET
     * @description Get active database connections and process list
     * @hidden
     * @example /api/GemvcMonitoring/databaseConnections
     */
    public function databaseConnections(): JsonResponse
    {
        // Authentication check
        if (!$this->request->auth(['developer','admin'])) {
            return Response::unauthorized('Authentication required');
        }
        
        return (new GemvcMonitoringController($this->request))->databaseConnections();
    }

    /**
     * Get Database Pool Statistics
     * 
     * @return JsonResponse
     * @http GET
     * @description Get database connection pool statistics
     * @hidden
     * @example /api/GemvcMonitoring/databasePool
     */
    public function databasePool(): JsonResponse
    {
        // Authentication check
        if (!$this->request->auth(['developer','admin'])) {
            return Response::unauthorized('Authentication required');
        }
        
        return (new GemvcMonitoringController($this->request))->databasePool();
    }

    /**
     * Get Database Latency
     * 
     * @return JsonResponse
     * @http GET
     * @description Measure database round-trip latency
     * @hidden
     * @example /api/GemvcMonitoring/databaseLatency
     */
    public function databaseLatency(): JsonResponse
    {
        // Authentication check
        if (!$this->request->auth(['developer','admin'])) {
            return Response::unauthorized('Authentication required');
        }
        
        return (new GemvcMonitoringController($this->request))->databaseLatency();
    }
}

