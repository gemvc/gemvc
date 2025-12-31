<?php
namespace Gemvc\Helper;

/**
 * Server Monitor Helper - System Resource Monitoring
 * 
 * Provides methods to collect server metrics including RAM and CPU usage.
 * Cross-platform support for Linux, Windows, and macOS.
 */
class ServerMonitorHelper
{
    /**
     * Get memory usage metrics
     * 
     * @return array<string, mixed> Memory usage information
     */
    public static function getMemoryUsage(): array
    {
        $current = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);
        
        // Get system memory info if available
        $systemTotal = null;
        $systemFree = null;
        $systemUsed = null;
        
        if (PHP_OS_FAMILY === 'Linux') {
            // Parse /proc/meminfo
            if (file_exists('/proc/meminfo')) {
                $meminfo = file_get_contents('/proc/meminfo');
                if ($meminfo !== false) {
                    preg_match('/MemTotal:\s+(\d+)\s+kB/', $meminfo, $totalMatch);
                    preg_match('/MemAvailable:\s+(\d+)\s+kB/', $meminfo, $availMatch);
                    if (isset($totalMatch[1]) && isset($availMatch[1])) {
                        $systemTotal = (int)$totalMatch[1] * 1024; // Convert to bytes
                        $systemFree = (int)$availMatch[1] * 1024;
                        $systemUsed = $systemTotal - $systemFree;
                    }
                }
            }
        } elseif (PHP_OS_FAMILY === 'Windows') {
            // Try to get system memory via WMI
            $output = @shell_exec('wmic computersystem get TotalPhysicalMemory /value 2>nul');
            if ($output !== null && is_string($output) && preg_match('/TotalPhysicalMemory=(\d+)/', $output, $matches)) {
                $systemTotal = (int)$matches[1];
            }
            
            $output = @shell_exec('wmic OS get FreePhysicalMemory /value 2>nul');
            if ($output !== null && is_string($output) && preg_match('/FreePhysicalMemory=(\d+)/', $output, $matches)) {
                $systemFree = (int)$matches[1] * 1024; // Convert to bytes
                if ($systemTotal !== null) {
                    $systemUsed = $systemTotal - $systemFree;
                }
            }
        }
        
        return [
            'php_current' => $current,
            'php_current_mb' => round($current / 1024 / 1024, 2),
            'php_peak' => $peak,
            'php_peak_mb' => round($peak / 1024 / 1024, 2),
            'system_total' => $systemTotal,
            'system_total_mb' => $systemTotal !== null ? round($systemTotal / 1024 / 1024, 2) : null,
            'system_free' => $systemFree,
            'system_free_mb' => $systemFree !== null ? round($systemFree / 1024 / 1024, 2) : null,
            'system_used' => $systemUsed,
            'system_used_mb' => $systemUsed !== null ? round($systemUsed / 1024 / 1024, 2) : null,
            'system_usage_percent' => ($systemTotal !== null && $systemUsed !== null) 
                ? round(($systemUsed / $systemTotal) * 100, 2) 
                : null,
        ];
    }

    /**
     * Get CPU load average
     * 
     * @return array<string, mixed> CPU load average (1min, 5min, 15min)
     */
    public static function getCpuLoad(): array
    {
        $loadAvg = function_exists('sys_getloadavg') ? sys_getloadavg() : null;
        
        return [
            'load_1min' => $loadAvg !== false && isset($loadAvg[0]) ? round($loadAvg[0], 2) : null,
            'load_5min' => $loadAvg !== false && isset($loadAvg[1]) ? round($loadAvg[1], 2) : null,
            'load_15min' => $loadAvg !== false && isset($loadAvg[2]) ? round($loadAvg[2], 2) : null,
            'available' => $loadAvg !== false,
        ];
    }

    /**
     * Get number of CPU cores
     * 
     * @return int Number of CPU cores
     */
    public static function getCpuCores(): int
    {
        // Try Hyperf's System class if available
        if (class_exists('\Hyperf\Support\System')) {
            return \Hyperf\Support\System::getCpuCoresNum();
        }
        
        // Try Swoole function
        if (function_exists('swoole_cpu_num')) {
            return swoole_cpu_num();
        }
        
        // Linux: Parse /proc/cpuinfo
        if (PHP_OS_FAMILY === 'Linux' && file_exists('/proc/cpuinfo')) {
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            if ($cpuinfo !== false) {
                preg_match_all('/^processor/m', $cpuinfo, $matches);
                $count = count($matches[0]);
                if ($count > 0) {
                    return $count;
                }
            }
        }
        
        // Windows: Use WMIC
        if (PHP_OS_FAMILY === 'Windows') {
            $process = @popen('wmic cpu get NumberOfCores', 'rb');
            if ($process !== false) {
                fgets($process); // Skip header
                $line = fgets($process);
                pclose($process);
                if ($line !== false) {
                    $cores = (int)trim($line);
                    if ($cores > 0) {
                        return $cores;
                    }
                }
            }
        }
        
        // macOS/BSD: Use sysctl
        if (PHP_OS_FAMILY === 'Darwin' || PHP_OS_FAMILY === 'BSD') {
            $process = @popen('sysctl -n hw.ncpu', 'rb');
            if ($process !== false) {
                $output = stream_get_contents($process);
                pclose($process);
                if ($output !== false) {
                    $cores = (int)trim($output);
                    if ($cores > 0) {
                        return $cores;
                    }
                }
            }
        }
        
        // Default fallback
        return 1;
    }

    /**
     * Get current CPU usage percentage
     * 
     * @return array<string, mixed> CPU usage information
     */
    public static function getCpuUsage(): array
    {
        $cores = self::getCpuCores();
        $loadAvg = self::getCpuLoad();
        
        // Calculate CPU usage from load average
        $usagePercent = null;
        $load1min = $loadAvg['load_1min'];
        if ($load1min !== null && is_numeric($load1min) && $cores > 0) {
            // Load average represents average number of processes in run queue
            // Divide by cores to get approximate CPU usage percentage
            $usagePercent = min(100, round(($load1min / $cores) * 100, 2));
        }
        
        // Try to get more accurate CPU usage from /proc/stat (Linux only)
        $cpuUsage = null;
        if (PHP_OS_FAMILY === 'Linux' && file_exists('/proc/stat')) {
            $stat1 = file_get_contents('/proc/stat');
            if ($stat1 !== false) {
                usleep(100000); // Wait 100ms
                $stat2 = file_get_contents('/proc/stat');
                if ($stat2 !== false) {
                    preg_match('/cpu\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/', $stat1, $match1);
                    preg_match('/cpu\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/', $stat2, $match2);
                    
                    if (count($match1) === 9 && count($match2) === 9) {
                        $idle1 = (int)$match1[4] + (int)$match1[5];
                        $total1 = array_sum(array_slice($match1, 1, 8));
                        
                        $idle2 = (int)$match2[4] + (int)$match2[5];
                        $total2 = array_sum(array_slice($match2, 1, 8));
                        
                        $idleDiff = $idle2 - $idle1;
                        $totalDiff = $total2 - $total1;
                        
                        if ($totalDiff > 0) {
                            $cpuUsage = round((1 - ($idleDiff / $totalDiff)) * 100, 2);
                        }
                    }
                }
            }
        }
        
        return [
            'cores' => $cores,
            'usage_percent' => $cpuUsage ?? $usagePercent,
            'usage_from_load' => $usagePercent,
            'load_average' => $loadAvg,
        ];
    }
}

