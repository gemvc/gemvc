<?php
namespace Gemvc\Helper;

/**
 * Network Helper - Network Statistics Monitoring
 * 
 * Provides methods to collect network interface statistics.
 * Cross-platform support for Linux, Windows, and macOS.
 */
class NetworkHelper
{
    /**
     * Get network statistics for all interfaces
     * 
     * @return array<string, mixed> Network statistics
     */
    public static function getNetworkStats(): array
    {
        $interfaces = self::getNetworkInterfaces();
        $stats = [];
        
        foreach ($interfaces as $interface) {
            $stats[$interface] = self::getInterfaceStats($interface);
        }
        
        // Calculate totals
        $totalBytesReceived = 0;
        $totalBytesSent = 0;
        $totalPacketsReceived = 0;
        $totalPacketsSent = 0;
        
        foreach ($stats as $interfaceStats) {
            if (isset($interfaceStats['bytes_received']) && is_int($interfaceStats['bytes_received'])) {
                $totalBytesReceived += $interfaceStats['bytes_received'];
            }
            if (isset($interfaceStats['bytes_sent']) && is_int($interfaceStats['bytes_sent'])) {
                $totalBytesSent += $interfaceStats['bytes_sent'];
            }
            if (isset($interfaceStats['packets_received']) && is_int($interfaceStats['packets_received'])) {
                $totalPacketsReceived += $interfaceStats['packets_received'];
            }
            if (isset($interfaceStats['packets_sent']) && is_int($interfaceStats['packets_sent'])) {
                $totalPacketsSent += $interfaceStats['packets_sent'];
            }
        }
        
        return [
            'interfaces' => $stats,
            'totals' => [
                'bytes_received' => $totalBytesReceived,
                'bytes_received_mb' => round($totalBytesReceived / 1024 / 1024, 2),
                'bytes_sent' => $totalBytesSent,
                'bytes_sent_mb' => round($totalBytesSent / 1024 / 1024, 2),
                'packets_received' => $totalPacketsReceived,
                'packets_sent' => $totalPacketsSent,
            ],
        ];
    }

    /**
     * Get list of active network interfaces
     * 
     * @return array<string> List of interface names
     */
    public static function getNetworkInterfaces(): array
    {
        $interfaces = [];
        
        if (PHP_OS_FAMILY === 'Linux') {
            // Parse /proc/net/dev
            if (file_exists('/proc/net/dev')) {
                $content = file_get_contents('/proc/net/dev');
                if ($content !== false) {
                    $lines = explode("\n", $content);
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (empty($line) || strpos($line, 'Inter-') === 0 || strpos($line, ' face') === 0) {
                            continue;
                        }
                        if (preg_match('/^(\w+):/', $line, $matches)) {
                            $interfaces[] = $matches[1];
                        }
                    }
                }
            }
        } elseif (PHP_OS_FAMILY === 'Windows') {
            // Use ipconfig or netsh
            $output = @shell_exec('ipconfig /all 2>nul');
            if ($output !== null && is_string($output)) {
                preg_match_all('/adapter\s+([^:]+):/i', $output, $matches);
                if (!empty($matches[1])) {
                    $interfaces = array_map('trim', $matches[1]);
                }
            }
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            // macOS: Use ifconfig
            $output = @shell_exec('ifconfig -l 2>/dev/null');
            if ($output !== null && is_string($output)) {
                $trimmed = trim($output);
                if ($trimmed !== '') {
                    $interfaces = array_filter(explode(' ', $trimmed));
                }
            }
        }
        
        // Filter out loopback and virtual interfaces if needed
        $interfaces = array_filter($interfaces, function($iface) {
            return !in_array($iface, ['lo', 'lo0', 'Loopback']);
        });
        
        return array_values($interfaces);
    }

    /**
     * Get statistics for a specific network interface
     * 
     * @param string $interface Interface name
     * @return array<string, mixed> Interface statistics
     */
    private static function getInterfaceStats(string $interface): array
    {
        if (PHP_OS_FAMILY === 'Linux') {
            // Parse /proc/net/dev
            if (file_exists('/proc/net/dev')) {
                $content = file_get_contents('/proc/net/dev');
                if ($content !== false) {
                    $lines = explode("\n", $content);
                    foreach ($lines as $line) {
                        if (preg_match('/^\s*' . preg_quote($interface, '/') . ':(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/', $line, $matches)) {
                            return [
                                'interface' => $interface,
                                'bytes_received' => (int)$matches[1],
                                'bytes_received_mb' => round((int)$matches[1] / 1024 / 1024, 2),
                                'packets_received' => (int)$matches[2],
                                'bytes_sent' => (int)$matches[9],
                                'bytes_sent_mb' => round((int)$matches[9] / 1024 / 1024, 2),
                                'packets_sent' => (int)$matches[10],
                                'errors_received' => (int)$matches[3],
                                'errors_sent' => (int)$matches[11],
                                'dropped_received' => (int)$matches[4],
                                'dropped_sent' => (int)$matches[12],
                            ];
                        }
                    }
                }
            }
        } elseif (PHP_OS_FAMILY === 'Windows') {
            // Use netstat or PowerShell
            $output = @shell_exec("powershell -Command \"Get-NetAdapterStatistics -Name '$interface' | Select-Object ReceivedBytes, SentBytes, ReceivedPackets, SentPackets | ConvertTo-Json\" 2>nul");
            if ($output !== null && is_string($output)) {
                $data = json_decode($output, true);
                if (is_array($data)) {
                    $bytesReceived = isset($data['ReceivedBytes']) && (is_int($data['ReceivedBytes']) || is_string($data['ReceivedBytes'])) 
                        ? (int)$data['ReceivedBytes'] 
                        : 0;
                    $bytesSent = isset($data['SentBytes']) && (is_int($data['SentBytes']) || is_string($data['SentBytes'])) 
                        ? (int)$data['SentBytes'] 
                        : 0;
                    $packetsReceived = isset($data['ReceivedPackets']) && (is_int($data['ReceivedPackets']) || is_string($data['ReceivedPackets'])) 
                        ? (int)$data['ReceivedPackets'] 
                        : 0;
                    $packetsSent = isset($data['SentPackets']) && (is_int($data['SentPackets']) || is_string($data['SentPackets'])) 
                        ? (int)$data['SentPackets'] 
                        : 0;
                    
                    return [
                        'interface' => $interface,
                        'bytes_received' => $bytesReceived,
                        'bytes_received_mb' => round($bytesReceived / 1024 / 1024, 2),
                        'packets_received' => $packetsReceived,
                        'bytes_sent' => $bytesSent,
                        'bytes_sent_mb' => round($bytesSent / 1024 / 1024, 2),
                        'packets_sent' => $packetsSent,
                    ];
                }
            }
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            // macOS: Parse ifconfig output
            $output = @shell_exec("ifconfig $interface 2>/dev/null");
            if ($output !== null && is_string($output)) {
                preg_match('/RX packets (\d+).*bytes (\d+)/s', $output, $rxMatches);
                preg_match('/TX packets (\d+).*bytes (\d+)/s', $output, $txMatches);
                
                return [
                    'interface' => $interface,
                    'bytes_received' => isset($rxMatches[2]) ? (int)$rxMatches[2] : 0,
                    'bytes_received_mb' => isset($rxMatches[2]) ? round((int)$rxMatches[2] / 1024 / 1024, 2) : 0,
                    'packets_received' => isset($rxMatches[1]) ? (int)$rxMatches[1] : 0,
                    'bytes_sent' => isset($txMatches[2]) ? (int)$txMatches[2] : 0,
                    'bytes_sent_mb' => isset($txMatches[2]) ? round((int)$txMatches[2] / 1024 / 1024, 2) : 0,
                    'packets_sent' => isset($txMatches[1]) ? (int)$txMatches[1] : 0,
                ];
            }
        }
        
        // Fallback: return empty stats
        return [
            'interface' => $interface,
            'bytes_received' => 0,
            'bytes_received_mb' => 0,
            'packets_received' => 0,
            'bytes_sent' => 0,
            'bytes_sent_mb' => 0,
            'packets_sent' => 0,
            'error' => 'Statistics not available for this interface',
        ];
    }
}

