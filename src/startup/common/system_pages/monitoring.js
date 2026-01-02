// monitoring.js - Server Monitoring Module for GEMVC Developer Assistant SPA
(function() {
    'use strict';
    
    // Module state
    let monitoringInterval = null;
    let refreshInterval = 2000; // Default 2 seconds
    let isPaused = false;
    let currentApiBase = null; // Store API_BASE for use in module
    let eventListeners = []; // Track event listeners for cleanup
    let visibilityHandler = null; // Page visibility handler
    let chartData = {
        ram: [],
        dockerRam: [],
        dockerCpu: [],
        cpu: [],
        network: [],
        latency: []
    };
    const MAX_DATA_POINTS = 60; // Circular buffer limit
    
    // Helper functions
    function formatBytes(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }
    
    function formatPercentage(value) {
        return value.toFixed(1) + '%';
    }
    
    function formatTime(seconds) {
        if (seconds < 60) return seconds + 's';
        if (seconds < 3600) return Math.floor(seconds / 60) + 'm ' + (seconds % 60) + 's';
        return Math.floor(seconds / 3600) + 'h ' + Math.floor((seconds % 3600) / 60) + 'm';
    }
    
    // Initialize canvas dimensions
    function initializeCanvasDimensions() {
        const canvases = ['ramChart', 'dockerRamChart', 'dockerCpuChart', 'cpuChart', 'networkChart', 'latencyChart'];
        canvases.forEach(id => {
            const canvas = document.getElementById(id);
            if (canvas) {
                const container = canvas.parentElement;
                if (container) {
                    const containerWidth = container.clientWidth;
                    if (containerWidth > 0) {
                        canvas.width = containerWidth - 32; // Account for padding
                        canvas.height = 200;
                    } else {
                        // Fallback if container width not available
                        canvas.width = 400;
                        canvas.height = 200;
                    }
                }
            }
        });
    }
    
    // Chart rendering functions
    function drawLineChart(canvas, dataPoints, label, color, maxValue, unit = '%') {
        if (!canvas) return;
        
        // Ensure canvas has valid dimensions
        if (canvas.width === 0 || canvas.height === 0) {
            const container = canvas.parentElement;
            if (container && container.clientWidth > 0) {
                canvas.width = container.clientWidth - 32;
                canvas.height = 200;
            } else {
                canvas.width = 400;
                canvas.height = 200;
            }
        }
        
        const ctx = canvas.getContext('2d');
        const width = canvas.width;
        const height = canvas.height;
        const padding = 40;
        const chartWidth = width - (padding * 2);
        const chartHeight = height - (padding * 2);
        
        // Clear canvas
        ctx.clearRect(0, 0, width, height);
        
        if (dataPoints.length === 0) return;
        
        // Draw grid
        ctx.strokeStyle = '#e5e7eb';
        ctx.lineWidth = 1;
        // Horizontal grid lines
        for (let i = 0; i <= 5; i++) {
            const y = padding + (chartHeight / 5) * i;
            ctx.beginPath();
            ctx.moveTo(padding, y);
            ctx.lineTo(width - padding, y);
            ctx.stroke();
        }
        
        // Draw line
        ctx.strokeStyle = color;
        ctx.lineWidth = 2;
        ctx.beginPath();
        
        const currentValue = parseFloat(dataPoints[dataPoints.length - 1]) || 0;
        const normalizedMax = maxValue || Math.max(...dataPoints.map(v => parseFloat(v) || 0), 100);
        
        if (dataPoints.length === 1) {
            // Single point - draw a horizontal line
            const x = padding + (chartWidth / 2);
            const y = padding + chartHeight - ((currentValue / normalizedMax) * chartHeight);
            ctx.moveTo(x, y);
            ctx.lineTo(x, y);
        } else {
            // Multiple points - draw line chart
            const stepX = chartWidth / (dataPoints.length - 1);
            dataPoints.forEach((value, index) => {
                const numValue = parseFloat(value) || 0;
                const x = padding + (stepX * index);
                const y = padding + chartHeight - ((numValue / normalizedMax) * chartHeight);
                
                if (index === 0) {
                    ctx.moveTo(x, y);
                } else {
                    ctx.lineTo(x, y);
                }
            });
        }
        
        ctx.stroke();
        
        // Draw point at current value
        if (dataPoints.length > 0) {
            const pointX = dataPoints.length === 1 
                ? padding + (chartWidth / 2)
                : padding + (chartWidth / (dataPoints.length - 1)) * (dataPoints.length - 1);
            const numCurrentValue = parseFloat(currentValue) || 0;
            const pointY = padding + chartHeight - ((numCurrentValue / normalizedMax) * chartHeight);
            ctx.fillStyle = color;
            ctx.beginPath();
            ctx.arc(pointX, pointY, 4, 0, 2 * Math.PI);
            ctx.fill();
        }
        
        // Draw current value text
        ctx.fillStyle = color;
        ctx.font = 'bold 14px Inter';
        ctx.fillText(`${label}: ${currentValue.toFixed(1)}${unit}`, padding, 20);
    }
    
    // Interval management
    function setRefreshInterval(seconds) {
        if (seconds < 1000) seconds = 1000; // Minimum 1 second
        refreshInterval = seconds;
        
        // Save to localStorage
        localStorage.setItem('monitoring_refresh_interval', seconds.toString());
        
        // Restart monitoring with new interval
        if (monitoringInterval && currentApiBase) {
            stopMonitoring();
            startMonitoring(currentApiBase, refreshInterval);
        }
    }
    
    function loadSavedInterval() {
        const saved = localStorage.getItem('monitoring_refresh_interval');
        if (saved) {
            const interval = parseInt(saved, 10);
            if (interval >= 1000) {
                refreshInterval = interval;
            }
        }
    }
    
    function startMonitoring(apiBase, interval) {
        if (monitoringInterval) {
            clearInterval(monitoringInterval);
        }
        
        // Initial load
        updateMonitoringData(apiBase);
        
        // Set interval
        monitoringInterval = setInterval(() => {
            if (!isPaused) {
                updateMonitoringData(apiBase);
            }
        }, interval);
    }
    
    function stopMonitoring() {
        if (monitoringInterval) {
            clearInterval(monitoringInterval);
            monitoringInterval = null;
        }
    }
    
    function pauseMonitoring() {
        isPaused = true;
        const pauseBtn = document.getElementById('pauseBtn');
        if (pauseBtn) {
            pauseBtn.textContent = '▶️ Resume';
        }
    }
    
    function resumeMonitoring() {
        isPaused = false;
        const pauseBtn = document.getElementById('pauseBtn');
        if (pauseBtn) {
            pauseBtn.textContent = '⏸️ Pause';
        }
        // Immediate update when resuming
        if (currentApiBase) {
            updateMonitoringData(currentApiBase);
        }
    }
    
    // Interval selector setup
    function setupIntervalSelector() {
        const selector = document.getElementById('refreshInterval');
        const customInput = document.getElementById('customInterval');
        
        if (!selector || !customInput) return;
        
        // Load saved preference
        loadSavedInterval();
        if (refreshInterval === 2000) selector.value = '2000';
        else if (refreshInterval === 3000) selector.value = '3000';
        else if (refreshInterval === 5000) selector.value = '5000';
        else if (refreshInterval === 10000) selector.value = '10000';
        else {
            selector.value = 'custom';
            customInput.classList.remove('hidden');
            customInput.value = refreshInterval;
        }
        
        const selectorHandler = (e) => {
            if (e.target.value === 'custom') {
                customInput.classList.remove('hidden');
                if (customInput.value) {
                    setRefreshInterval(parseInt(customInput.value, 10));
                }
            } else {
                customInput.classList.add('hidden');
                setRefreshInterval(parseInt(e.target.value, 10));
            }
        };
        
        const customInputHandler = (e) => {
            const value = parseInt(e.target.value, 10);
            if (value >= 1000) {
                setRefreshInterval(value);
            }
        };
        
        selector.addEventListener('change', selectorHandler);
        customInput.addEventListener('change', customInputHandler);
        
        // Track listeners for cleanup
        eventListeners.push(
            {element: selector, event: 'change', handler: selectorHandler},
            {element: customInput, event: 'change', handler: customInputHandler}
        );
    }
    
    // Data fetching and updating
    async function updateMonitoringData(apiBase) {
        try {
            // Ensure canvas dimensions are initialized
            initializeCanvasDimensions();
            
            // Fetch all metrics in parallel
            const [ramRes, dockerRamRes, dockerCpuRes, cpuRes, networkRes, latencyRes, connectionsRes] = await Promise.all([
                fetch(`${apiBase}/GemvcMonitoring/ram`).catch(() => null),
                fetch(`${apiBase}/GemvcMonitoring/dockerRam`).catch(() => null),
                fetch(`${apiBase}/GemvcMonitoring/dockerCpu`).catch(() => null),
                fetch(`${apiBase}/GemvcMonitoring/cpu`).catch(() => null),
                fetch(`${apiBase}/GemvcMonitoring/network`).catch(() => null),
                fetch(`${apiBase}/GemvcMonitoring/databaseLatency`).catch(() => null),
                fetch(`${apiBase}/GemvcMonitoring/databaseConnections`).catch(() => null)
            ]);
            
            // Process RAM data
            if (ramRes && ramRes.ok) {
                const ramData = await ramRes.json();
                // API returns { response_code: 200, data: {...} } structure
                if (ramData && ramData.data) {
                    const usagePercent = ramData.data.system_usage_percent !== null && ramData.data.system_usage_percent !== undefined
                        ? parseFloat(ramData.data.system_usage_percent) || 0
                        : 0;
                    chartData.ram.push(usagePercent);
                    if (chartData.ram.length > MAX_DATA_POINTS) {
                        chartData.ram.shift();
                    }
                    
                    const ramCanvas = document.getElementById('ramChart');
                    if (ramCanvas) {
                        // Ensure canvas dimensions are set
                        if (ramCanvas.width === 0 || ramCanvas.height === 0) {
                            initializeCanvasDimensions();
                        }
                        const color = usagePercent < 70 ? '#10b981' : usagePercent < 90 ? '#f59e0b' : '#ef4444';
                        drawLineChart(ramCanvas, chartData.ram, 'RAM', color, 100, '%');
                    } else {
                        console.warn('RAM canvas not found');
                    }
                    
                    const ramStats = document.getElementById('ramStats');
                    if (ramStats) {
                        const total = formatBytes(ramData.data.system_total || 0);
                        const used = formatBytes(ramData.data.system_used || 0);
                        const free = formatBytes(ramData.data.system_free || 0);
                        ramStats.textContent = `${formatPercentage(usagePercent)} | Used: ${used} / Total: ${total} | Free: ${free}`;
                    }
                } else {
                    console.warn('RAM data format invalid or missing data:', ramData);
                }
            }
            
            // Process Docker Container RAM data
            if (dockerRamRes && dockerRamRes.ok) {
                const dockerRamData = await dockerRamRes.json();
                // API returns { response_code: 200, data: {...} } structure
                if (dockerRamData && dockerRamData.data) {
                    const usagePercent = dockerRamData.data.container_usage_percent !== null && dockerRamData.data.container_usage_percent !== undefined
                        ? parseFloat(dockerRamData.data.container_usage_percent) || 0
                        : 0;
                    chartData.dockerRam.push(usagePercent);
                    if (chartData.dockerRam.length > MAX_DATA_POINTS) {
                        chartData.dockerRam.shift();
                    }
                    
                    const dockerRamCanvas = document.getElementById('dockerRamChart');
                    if (dockerRamCanvas) {
                        // Ensure canvas dimensions are set
                        if (dockerRamCanvas.width === 0 || dockerRamCanvas.height === 0) {
                            initializeCanvasDimensions();
                        }
                        const color = usagePercent < 70 ? '#10b981' : usagePercent < 90 ? '#f59e0b' : '#ef4444';
                        drawLineChart(dockerRamCanvas, chartData.dockerRam, 'Docker RAM', color, 100, '%');
                    }
                    
                    const dockerRamStats = document.getElementById('dockerRamStats');
                    if (dockerRamStats) {
                        const containerUsed = dockerRamData.data.container_used_mb !== null && dockerRamData.data.container_used_mb !== undefined
                            ? parseFloat(dockerRamData.data.container_used_mb) || 0
                            : 0;
                        const containerTotal = dockerRamData.data.container_total_mb;
                        const containerTotalStr = (containerTotal === 'No Limit' || containerTotal === null || containerTotal === undefined)
                            ? 'No Limit'
                            : formatBytes(parseFloat(containerTotal) * 1024 * 1024);
                        const containerUsedStr = containerUsed > 0 ? formatBytes(containerUsed * 1024 * 1024) : '0 B';
                        const phpCurrent = dockerRamData.data.php_current_mb !== null && dockerRamData.data.php_current_mb !== undefined
                            ? parseFloat(dockerRamData.data.php_current_mb) || 0
                            : 0;
                        const phpPeak = dockerRamData.data.php_peak_mb !== null && dockerRamData.data.php_peak_mb !== undefined
                            ? parseFloat(dockerRamData.data.php_peak_mb) || 0
                            : 0;
                        const usageDisplay = usagePercent > 0 ? formatPercentage(usagePercent) : 'N/A';
                        dockerRamStats.textContent = `${usageDisplay} | Container: ${containerUsedStr} / ${containerTotalStr} | PHP: ${phpCurrent.toFixed(2)} MB (Peak: ${phpPeak.toFixed(2)} MB)`;
                    }
                }
            }
            
            // Process Docker Container CPU data
            if (dockerCpuRes && dockerCpuRes.ok) {
                const dockerCpuData = await dockerCpuRes.json();
                // API returns { response_code: 200, data: {...} } structure
                if (dockerCpuData && dockerCpuData.data) {
                    const available = dockerCpuData.data.available !== false;
                    if (available) {
                        const cpuPercent = dockerCpuData.data.container_cpu_percent !== null && dockerCpuData.data.container_cpu_percent !== undefined
                            ? parseFloat(dockerCpuData.data.container_cpu_percent) || 0
                            : 0;
                        chartData.dockerCpu.push(cpuPercent);
                        if (chartData.dockerCpu.length > MAX_DATA_POINTS) {
                            chartData.dockerCpu.shift();
                        }
                        
                        const dockerCpuCanvas = document.getElementById('dockerCpuChart');
                        if (dockerCpuCanvas) {
                            // Ensure canvas dimensions are set
                            if (dockerCpuCanvas.width === 0 || dockerCpuCanvas.height === 0) {
                                initializeCanvasDimensions();
                            }
                            const color = cpuPercent < 70 ? '#10b981' : cpuPercent < 90 ? '#f59e0b' : '#ef4444';
                            drawLineChart(dockerCpuCanvas, chartData.dockerCpu, 'Docker CPU', color, 100, '%');
                        }
                        
                        const dockerCpuStats = document.getElementById('dockerCpuStats');
                        if (dockerCpuStats) {
                            const assignedCores = dockerCpuData.data.assigned_cores !== null && dockerCpuData.data.assigned_cores !== undefined
                                ? parseFloat(dockerCpuData.data.assigned_cores) || 0
                                : 0;
                            const isThrottled = dockerCpuData.data.is_throttled === true;
                            const throttledText = isThrottled ? ' ⚠️ Throttled' : '';
                            dockerCpuStats.textContent = `${formatPercentage(cpuPercent)} | Cores: ${assignedCores.toFixed(2)}${throttledText}`;
                        }
                    } else {
                        // Docker CPU metrics not available (not in Docker or cgroup not accessible)
                        const dockerCpuStats = document.getElementById('dockerCpuStats');
                        if (dockerCpuStats) {
                            dockerCpuStats.textContent = 'Not available (not in Docker container or cgroup not accessible)';
                        }
                    }
                }
            }
            
            // Process CPU data
            if (cpuRes && cpuRes.ok) {
                const cpuData = await cpuRes.json();
                // API returns { response_code: 200, data: {...} } structure
                if (cpuData && cpuData.data) {
                    const usage = cpuData.data.usage !== null && cpuData.data.usage !== undefined
                        ? parseFloat(cpuData.data.usage) || 0
                        : 0;
                    chartData.cpu.push(usage);
                    if (chartData.cpu.length > MAX_DATA_POINTS) {
                        chartData.cpu.shift();
                    }
                    
                    const cpuCanvas = document.getElementById('cpuChart');
                    if (cpuCanvas) {
                        // Ensure canvas dimensions are set
                        if (cpuCanvas.width === 0 || cpuCanvas.height === 0) {
                            initializeCanvasDimensions();
                        }
                        const color = usage < 70 ? '#10b981' : usage < 90 ? '#f59e0b' : '#ef4444';
                        drawLineChart(cpuCanvas, chartData.cpu, 'CPU', color, 100, '%');
                    }
                    
                    const cpuStats = document.getElementById('cpuStats');
                    if (cpuStats) {
                        const cores = cpuData.data.cores || 0;
                        const load = cpuData.data.load_average || [];
                        const load1m = load && load.length > 0 ? parseFloat(load[0]) || 0 : 0;
                        const load5m = load && load.length > 1 ? parseFloat(load[1]) || 0 : 0;
                        const load15m = load && load.length > 2 ? parseFloat(load[2]) || 0 : 0;
                        cpuStats.textContent = `${formatPercentage(usage)} | Cores: ${cores} | Load: ${load1m.toFixed(2)} (1m), ${load5m.toFixed(2)} (5m), ${load15m.toFixed(2)} (15m)`;
                    }
                }
            }
            
            // Process Network data
            if (networkRes && networkRes.ok) {
                const networkData = await networkRes.json();
                // API returns { response_code: 200, data: {...} } structure
                if (networkData && networkData.data) {
                    const totals = networkData.data.totals || networkData.data || {};
                    const bytesReceived = totals.bytes_received !== null && totals.bytes_received !== undefined
                        ? parseFloat(totals.bytes_received) || 0
                        : 0;
                    const bytesSent = totals.bytes_sent !== null && totals.bytes_sent !== undefined
                        ? parseFloat(totals.bytes_sent) || 0
                        : 0;
                    
                    // Calculate total MB (cumulative bytes converted to MB)
                    const totalBytes = bytesReceived + bytesSent;
                    const totalMB = totalBytes / 1024 / 1024;
                    chartData.network.push(totalMB); // Convert to MB
                    if (chartData.network.length > MAX_DATA_POINTS) {
                        chartData.network.shift();
                    }
                    
                    const networkCanvas = document.getElementById('networkChart');
                    if (networkCanvas) {
                        // Ensure canvas dimensions are set
                        if (networkCanvas.width === 0 || networkCanvas.height === 0) {
                            initializeCanvasDimensions();
                        }
                        // Calculate max value for network chart (use max of data or a reasonable default)
                        const networkMax = chartData.network.length > 0 
                            ? Math.max(...chartData.network.map(v => parseFloat(v) || 0), 100) 
                            : 100;
                        drawLineChart(networkCanvas, chartData.network, 'Network', '#3b82f6', networkMax, ' MB');
                    }
                    
                    const networkStats = document.getElementById('networkStats');
                    if (networkStats) {
                        const received = formatBytes(parseFloat(bytesReceived) || 0);
                        const sent = formatBytes(parseFloat(bytesSent) || 0);
                        networkStats.textContent = `Received: ${received} | Sent: ${sent}`;
                    }
                }
            }
            
            // Process Database Latency data
            if (latencyRes && latencyRes.ok) {
                const latencyData = await latencyRes.json();
                // API returns { response_code: 200, data: {...} } structure
                if (latencyData && latencyData.data) {
                    const latency = latencyData.data.average_latency_ms !== null && latencyData.data.average_latency_ms !== undefined
                        ? parseFloat(latencyData.data.average_latency_ms) || 0
                        : (latencyData.data.latency_ms !== null && latencyData.data.latency_ms !== undefined
                            ? parseFloat(latencyData.data.latency_ms) || 0
                            : 0);
                    chartData.latency.push(latency);
                    if (chartData.latency.length > MAX_DATA_POINTS) {
                        chartData.latency.shift();
                    }
                    
                    const latencyCanvas = document.getElementById('latencyChart');
                    if (latencyCanvas) {
                        // Ensure canvas dimensions are set
                        if (latencyCanvas.width === 0 || latencyCanvas.height === 0) {
                            initializeCanvasDimensions();
                        }
                        // Calculate max value for latency chart
                        const latencyMax = chartData.latency.length > 0 
                            ? Math.max(...chartData.latency.map(v => parseFloat(v) || 0), 100) 
                            : 100;
                        const color = '#f59e0b'; // Orange color
                        drawLineChart(latencyCanvas, chartData.latency, 'DB Latency', color, latencyMax, 'ms');
                    }
                    
                    const latencyStats = document.getElementById('latencyStats');
                    if (latencyStats) {
                        const min = parseFloat(latencyData.data.min_latency_ms) || 0;
                        const max = parseFloat(latencyData.data.max_latency_ms) || 0;
                        latencyStats.textContent = `Avg: ${latency.toFixed(2)}ms | Min: ${min.toFixed(2)}ms | Max: ${max.toFixed(2)}ms`;
                    }
                }
            }
            
            // Process Database Connections data
            if (connectionsRes && connectionsRes.ok) {
                const connectionsData = await connectionsRes.json();
                // API returns { response_code: 200, data: {...} } structure
                if (connectionsData && connectionsData.data) {
                    const processList = connectionsData.data.process_list || [];
                    const activeConnections = connectionsData.data.active_connections || 0;
                    
                    // Update connection count
                    const connectionCount = document.getElementById('connectionCount');
                    if (connectionCount) {
                        connectionCount.textContent = activeConnections;
                    }
                    
                    // Update connections table
                    const connectionsBody = document.getElementById('connectionsBody');
                    if (connectionsBody) {
                        connectionsBody.innerHTML = '';
                        processList.forEach(process => {
                            const row = document.createElement('tr');
                            row.className = 'hover:bg-gray-50';
                            row.innerHTML = `
                                <td class="px-4 py-2 border">${process.Id || '-'}</td>
                                <td class="px-4 py-2 border">${process.User || '-'}</td>
                                <td class="px-4 py-2 border">${process.Host || '-'}</td>
                                <td class="px-4 py-2 border">${process.db || '-'}</td>
                                <td class="px-4 py-2 border">${process.Command || '-'}</td>
                                <td class="px-4 py-2 border">${process.Time || 0}</td>
                                <td class="px-4 py-2 border">${process.State || '-'}</td>
                                <td class="px-4 py-2 border">${(process.Info || '-').substring(0, 50)}</td>
                            `;
                            connectionsBody.appendChild(row);
                        });
                    }
                }
            }
            
            // Update last update time
            const lastUpdateEl = document.getElementById('lastUpdate');
            if (lastUpdateEl) {
                lastUpdateEl.textContent = new Date().toLocaleTimeString();
            }
        } catch (error) {
            console.error('Error fetching monitoring data:', error);
            const lastUpdateEl = document.getElementById('lastUpdate');
            if (lastUpdateEl) {
                lastUpdateEl.textContent = 'Error: ' + error.message;
            }
        }
    }
    
    // Main render function - now just initializes monitoring on existing HTML
    function renderMonitoring(apiBase) {
        // Store API base for module use
        currentApiBase = apiBase;
        
        // Cleanup any existing monitoring first
        cleanupMonitoring();
        
        // Load saved interval preference
        loadSavedInterval();
        
        // Setup interval selector
        setupIntervalSelector();
        
        // Setup pause/resume button
        const pauseBtn = document.getElementById('pauseBtn');
        if (pauseBtn) {
            const pauseHandler = () => {
                if (isPaused) {
                    resumeMonitoring();
                } else {
                    pauseMonitoring();
                }
            };
            pauseBtn.addEventListener('click', pauseHandler);
            eventListeners.push({element: pauseBtn, event: 'click', handler: pauseHandler});
        }
        
        // Setup refresh now button
        const refreshNowBtn = document.getElementById('refreshNowBtn');
        if (refreshNowBtn) {
            const refreshNowHandler = () => {
                if (currentApiBase) {
                    updateMonitoringData(currentApiBase);
                }
            };
            refreshNowBtn.addEventListener('click', refreshNowHandler);
            eventListeners.push({element: refreshNowBtn, event: 'click', handler: refreshNowHandler});
        }
        
        // Setup connections toggle
        const connectionsToggle = document.getElementById('connectionsToggle');
        const connectionsTable = document.getElementById('connectionsTable');
        if (connectionsToggle && connectionsTable) {
            const toggleHandler = () => {
                connectionsTable.classList.toggle('hidden');
                const isHidden = connectionsTable.classList.contains('hidden');
                const countEl = document.getElementById('connectionCount');
                const count = countEl ? countEl.textContent : '0';
                connectionsToggle.innerHTML = `Database Connections (${count} active) ${isHidden ? '▼' : '▲'}`;
            };
            connectionsToggle.addEventListener('click', toggleHandler);
            eventListeners.push({element: connectionsToggle, event: 'click', handler: toggleHandler});
        }
        
        // Setup canvas resize handlers
        const canvases = ['ramChart', 'dockerRamChart', 'dockerCpuChart', 'cpuChart', 'networkChart', 'latencyChart'];
        const resizeHandler = () => {
            // Redraw all charts on window resize
            if (currentApiBase) {
                // Update canvas dimensions
                canvases.forEach(id => {
                    const canvas = document.getElementById(id);
                    if (canvas) {
                        const container = canvas.parentElement;
                        if (container) {
                            canvas.width = container.clientWidth - 32; // Account for padding
                            canvas.height = 200;
                        }
                    }
                });
                updateMonitoringData(currentApiBase);
            }
        };
        window.addEventListener('resize', resizeHandler);
        eventListeners.push({element: window, event: 'resize', handler: resizeHandler});
        
        // Setup Page Visibility API - pause when tab is hidden
        visibilityHandler = () => {
            if (document.hidden) {
                if (!isPaused) {
                    pauseMonitoring();
                }
            } else {
                if (isPaused) {
                    resumeMonitoring();
                }
            }
        };
        document.addEventListener('visibilitychange', visibilityHandler);
        
        // Initialize canvas dimensions immediately
        initializeCanvasDimensions();
        
        // Also initialize after a short delay to ensure container is fully rendered
        setTimeout(() => {
            initializeCanvasDimensions();
            // Start monitoring after canvas is initialized
            startMonitoring(apiBase, refreshInterval);
        }, 50);
    }
    
    // Cleanup function
    function cleanupMonitoring() {
        // Stop monitoring interval
        stopMonitoring();
        
        // Remove all event listeners
        eventListeners.forEach(({element, event, handler}) => {
            element.removeEventListener(event, handler);
        });
        eventListeners = [];
        
        // Remove page visibility handler
        if (visibilityHandler) {
            document.removeEventListener('visibilitychange', visibilityHandler);
            visibilityHandler = null;
        }
        
        // Clear chart data
        chartData = {
            ram: [],
            dockerRam: [],
            dockerCpu: [],
            cpu: [],
            network: [],
            latency: []
        };
        
        // Reset state
        isPaused = false;
        currentApiBase = null;
    }
    
    // Export to global scope for spa.php to use
    window.MonitoringModule = {
        render: renderMonitoring,
        cleanup: cleanupMonitoring,
        setInterval: setRefreshInterval,
        pause: pauseMonitoring,
        resume: resumeMonitoring
    };
    
    // Debug: verify module is loaded
    console.log('MonitoringModule loaded:', typeof window.MonitoringModule);
})();

