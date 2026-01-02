**Full Changelog**: https://github.com/gemvc/gemvc/compare/5.2.5...5.3.0
# GEMVC Framework - Release Notes

## Version 5.3.0 - Server Monitoring Dashboard

**Release Date**: 2026-01-03  
**Type**: Minor Release (Backward Compatible)

---

##  Overview

This release introduces a **Server Monitoring Dashboard** in the Developer Assistant, providing real-time visualization of server resources including RAM, CPU, network, and database metrics. The monitoring system features interactive charts, Docker container metrics, and a user-friendly interface for tracking system performance.

---

##  New Features

### 📊 Server Monitoring Dashboard

A complete real-time monitoring solution integrated into the Developer Assistant SPA, providing visual insights into server performance.

#### **Key Features**:

1. **Real-Time Charts** (6 interactive charts):
   - **RAM Usage** - System memory usage with used/total/free metrics
   - **Docker Container RAM** - Container memory limits and PHP memory usage
   - **Docker Container CPU** - Container CPU usage with throttling detection
   - **CPU Usage** - System CPU usage with load averages and core count
   - **Network Bandwidth** - Network traffic (received/sent bytes)
   - **Database Latency** - Database query latency (min/max/average)

2. **Interactive Controls**:
   - Configurable refresh intervals (2s, 3s, 5s, 10s, or custom)
   - Pause/Resume functionality
   - Manual refresh button
   - Preferences saved to localStorage

3. **Database Connections Table**:
   - Expandable table showing active database connections
   - Process list with connection details (ID, User, Host, DB, Command, Time, State, Info)
   - Real-time connection count display

4. **Smart Features**:
   - Page Visibility API integration (pauses when browser tab is hidden)
   - Automatic canvas resizing on window resize
   - Color-coded charts (green <70%, orange 70-90%, red >90%)
   - Circular buffer (60 data points) for efficient memory usage
   - Smooth chart rendering with HTML5 Canvas

#### **Access**:
- URL: `/index/developer#monitoring`
- Environment: Development only (`APP_ENV=dev`)
- Authentication: Requires `['developer','admin']` roles

#### **Technical Implementation**:
- **Frontend**: Self-contained JavaScript module (`monitoring.js`)
- **Backend**: Uses existing `/api/GemvcMonitoring/*` endpoints
- **Architecture**: Client-side rendering with server-side data fetching
- **Performance**: Parallel API calls for all metrics
- **Compatibility**: Works with all webserver types (Apache, Nginx, OpenSwoole)

### 🐳 Docker Container Metrics

Enhanced monitoring capabilities specifically for Docker environments:

- **Docker Container RAM** (`/api/GemvcMonitoring/dockerRam`):
  - Container memory limits and usage
  - PHP memory consumption within container
  - Memory usage percentage calculation

- **Docker Container CPU** (`/api/GemvcMonitoring/dockerCpu`):
  - Container CPU usage percentage
  - Assigned CPU cores detection
  - CPU throttling detection (warns when >95%)
  - Cgroup-based CPU metrics

---

## 🔄 Changes

### Developer Assistant SPA

- **New Monitoring Page** - Added complete monitoring dashboard
- **Navigation** - Added "Monitoring" link to Developer Assistant menu
- **Module System** - Integrated monitoring JavaScript module with proper cleanup

### GemvcMonitoring API

- **Docker RAM Endpoint** - Returns container memory metrics
- **Docker CPU Endpoint** - Returns container CPU metrics with throttling detection

### Monitoring JavaScript Module

- **Chart Rendering** - HTML5 Canvas-based line charts
- **Data Management** - Circular buffer for efficient data storage
- **Event Handling** - Proper event listener management with cleanup
- **Error Handling** - Graceful degradation when metrics unavailable

---

## 🐛 Bug Fixes

- **Database Latency Chart** - Changed line color to consistent orange for better visibility

---

## 📚 Documentation Updates

- Added monitoring page documentation
- Updated Developer Assistant feature list
- Documented Docker container metrics endpoints

---

## 🔒 Security

- **No security vulnerabilities** reported in this release
- All existing security features maintained (90% automatic security)
- Monitoring endpoints require proper authentication (`['developer','admin']` roles)
- Monitoring page only accessible in development environment

---

## ⚙️ Configuration

No configuration changes required. The monitoring system uses existing GemvcMonitoring API endpoints.

---

## 🚀 Performance

- **Efficient Data Storage** - Circular buffer limits memory usage to 60 data points per chart
- **Parallel API Calls** - All metrics fetched simultaneously for faster updates
- **Smart Pausing** - Automatically pauses when browser tab is hidden
- **Canvas Optimization** - Efficient chart rendering with proper dimension management

---

## 🧪 Testing

- All monitoring charts verified and functional
- Docker container metrics tested in Docker environments
- Refresh interval changes tested
- Pause/resume functionality verified
- Database connections table tested
- Page visibility API integration verified

---

## 🔄 Migration Guide

### From 5.2.5 to 5.3.0

This release is **fully backward compatible**. No action required.

**What's New**:
- New monitoring dashboard in Developer Assistant
- Enhanced Docker container metrics
- No breaking changes to existing APIs

**Benefits**:
- Real-time server monitoring
- Visual performance insights
- Docker container metrics
- Better debugging capabilities

**Optional Usage**:
- Access monitoring at `/index/developer#monitoring`
- Configure refresh intervals as needed
- Use pause/resume for detailed analysis

---

## 🙏 Acknowledgments

Special thanks to the community for feedback and feature requests that led to this comprehensive monitoring solution.

---

## 📝 Full Changelog

For detailed changes, see [CHANGELOG_5.2.5.md](CHANGELOG_5.2.5.md).

---

## 🔗 Links

- **Documentation**: https://gemvc.de
- **GitHub**: https://github.com/gemvc/gemvc
- **Issues**: https://github.com/gemvc/gemvc/issues

---

**Upgrade Command**:
```bash
composer update gemvc/library
```

**Breaking Changes**: None  
**Deprecations**: None  
**Minimum PHP Version**: 8.2+  
**Recommended PHP Version**: 8.4+

---

## [Gemvc PHP Framework built for Microservices](https://gemvc.de)
### Made with ❤️ by Ali Khorsandfard

