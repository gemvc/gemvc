<?php
namespace App\Model;

use App\Table\DeveloperTable;
use Gemvc\Helper\ServerMonitorHelper;
use Gemvc\Helper\NetworkHelper;
use Gemvc\Database\DatabaseManagerFactory;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\Response;
use PDO;

/**
 * GEMVC Monitoring Model - Data logic layer for server monitoring
 * 
 * This model handles server monitoring operations including RAM, CPU, network,
 * and database metrics.
 */
class GemvcMonitoringModel extends DeveloperTable
{
    /**
     * Get RAM metrics
     * 
     * @return JsonResponse
     */
    public function getRamMetrics(): JsonResponse
    {
        $metrics = ServerMonitorHelper::getMemoryUsage();
        $metrics['timestamp'] = date('Y-m-d H:i:s');
        
        return Response::success($metrics, 1, 'RAM metrics retrieved successfully');
    }

    /**
     * Get CPU metrics
     * 
     * @return JsonResponse
     */
    public function getCpuMetrics(): JsonResponse
    {
        $load = ServerMonitorHelper::getCpuLoad();
        $cores = ServerMonitorHelper::getCpuCores();
        $usage = ServerMonitorHelper::getCpuUsage();
        
        $metrics = [
            'cores' => $cores,
            'load_average' => $load,
            'usage' => $usage,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
        
        return Response::success($metrics, 1, 'CPU metrics retrieved successfully');
    }

    /**
     * Get network metrics
     * 
     * @return JsonResponse
     */
    public function getNetworkMetrics(): JsonResponse
    {
        $stats = NetworkHelper::getNetworkStats();
        $stats['timestamp'] = date('Y-m-d H:i:s');
        
        return Response::success($stats, 1, 'Network metrics retrieved successfully');
    }

    /**
     * Get active database connections
     * 
     * @return JsonResponse
     */
    public function getDatabaseConnections(): JsonResponse
    {
        [$pdo, $connection, $dbManager] = $this->getPdoConnectionWithManager();
        
        try {
            $activeConnections = 0;
            $processList = [];
            $dbInfo = null;
            
            if ($pdo !== null) {
                // Get process list from MySQL
                $result = $pdo->query('SHOW PROCESSLIST');
                if ($result !== false) {
                    $processList = $result->fetchAll(PDO::FETCH_ASSOC);
                    $activeConnections = count($processList);
                }
                
                // Get connection info
                $dbNameResult = $pdo->query("SELECT DATABASE() as db_name, CONNECTION_ID() as connection_id");
                if ($dbNameResult !== false) {
                    $dbInfo = $dbNameResult->fetch(PDO::FETCH_ASSOC);
                }
            }
            
            $managerInfo = DatabaseManagerFactory::getManagerInfo();
            
            $metrics = [
                'active_connections' => $activeConnections,
                'process_list' => $processList,
                'current_connection' => $dbInfo,
                'manager_info' => [
                    'environment' => $managerInfo['environment'] ?? null,
                    'manager_class' => $managerInfo['manager_class'] ?? null,
                    'initialized' => $managerInfo['initialized'] ?? false,
                ],
                'timestamp' => date('Y-m-d H:i:s'),
            ];
            
            return Response::success($metrics, 1, 'Database connections retrieved successfully');
            
        } catch (\Exception $e) {
            return Response::internalError('Failed to get database connections: ' . $e->getMessage());
        } finally {
            $this->releaseConnection($connection, $dbManager);
        }
    }

    /**
     * Get database connection pool statistics
     * 
     * @return JsonResponse
     */
    public function getDatabasePoolStats(): JsonResponse
    {
        try {
            $managerInfo = DatabaseManagerFactory::getManagerInfo();
            $poolStats = $managerInfo['pool_stats'] ?? [];
            
            $metrics = [
                'pool_stats' => $poolStats,
                'manager_info' => [
                    'environment' => $managerInfo['environment'] ?? null,
                    'manager_class' => $managerInfo['manager_class'] ?? null,
                    'initialized' => $managerInfo['initialized'] ?? false,
                    'has_error' => $managerInfo['has_error'] ?? false,
                    'error' => $managerInfo['error'] ?? null,
                ],
            ];
            
            // Add PDO-specific config if available
            if (isset($managerInfo['pdo_config'])) {
                $metrics['pdo_config'] = $managerInfo['pdo_config'];
            }
            
            // Add Swoole-specific config if available
            if (isset($managerInfo['swoole_config'])) {
                $metrics['swoole_config'] = $managerInfo['swoole_config'];
            }
            
            $metrics['timestamp'] = date('Y-m-d H:i:s');
            
            return Response::success($metrics, 1, 'Database pool statistics retrieved successfully');
            
        } catch (\Exception $e) {
            return Response::internalError('Failed to get database pool stats: ' . $e->getMessage());
        }
    }

    /**
     * Get database latency (round-trip time)
     * 
     * @return JsonResponse
     */
    public function getDatabaseLatency(): JsonResponse
    {
        [$pdo, $connection, $dbManager] = $this->getPdoConnectionWithManager();
        
        try {
            if ($pdo === null) {
                return Response::internalError('Database connection failed');
            }
            
            // Measure latency with SELECT 1 query
            $startTime = microtime(true);
            $result = $pdo->query('SELECT 1');
            $endTime = microtime(true);
            
            $latencyMs = ($endTime - $startTime) * 1000;
            $success = ($result !== false);
            
            // Run multiple tests for average
            $latencies = [];
            $testCount = 3;
            
            for ($i = 0; $i < $testCount; $i++) {
                $testStart = microtime(true);
                $testResult = $pdo->query('SELECT 1');
                $testEnd = microtime(true);
                
                if ($testResult !== false) {
                    $latencies[] = ($testEnd - $testStart) * 1000;
                }
            }
            
            $avgLatency = count($latencies) > 0 ? array_sum($latencies) / count($latencies) : $latencyMs;
            $minLatency = count($latencies) > 0 ? min($latencies) : $latencyMs;
            $maxLatency = count($latencies) > 0 ? max($latencies) : $latencyMs;
            
            $metrics = [
                'latency_ms' => round($latencyMs, 2),
                'average_latency_ms' => round($avgLatency, 2),
                'min_latency_ms' => round($minLatency, 2),
                'max_latency_ms' => round($maxLatency, 2),
                'test_count' => $testCount,
                'success' => $success,
                'timestamp' => date('Y-m-d H:i:s'),
            ];
            
            return Response::success($metrics, 1, 'Database latency measured successfully');
            
        } catch (\Exception $e) {
            return Response::internalError('Failed to measure database latency: ' . $e->getMessage());
        } finally {
            $this->releaseConnection($connection, $dbManager);
        }
    }
}

