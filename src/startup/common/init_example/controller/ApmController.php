<?php
namespace App\Controller;

use App\Model\ApmModel;
use Gemvc\Core\Controller;
use Gemvc\Http\Request;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\Response;

class ApmController extends Controller
{
    public function __construct(Request $request)
    {
        parent::__construct($request);
    }

    /**
     * Test APM Provider
     * 
     * @return JsonResponse
     */
    public function test(): JsonResponse
    {
        $model = new ApmModel();
        return $model->test();
    }

    /**
     * Test APM Provider with Error
     * 
     * @return JsonResponse
     */
    public function testError(): JsonResponse
    {
        $model = new ApmModel();
        return $model->testError();
    }

    /**
     * Get APM Status
     * 
     * @return JsonResponse
     */
    public function status(): JsonResponse
    {
        $model = new ApmModel();
        return $model->getStatus();
    }

    /**
     * Register TraceKit Service
     * 
     * @return JsonResponse
     */
    public function register(): JsonResponse
    {
        $model = new ApmModel();
        return $model->register(
            $this->request->post['email'],
            $this->request->post['organization_name'] ?? null,
            $this->request->post['source'] ?? 'gemvc'
        );
    }

    /**
     * Verify TraceKit Email Code
     * 
     * @return JsonResponse
     */
    public function verify(): JsonResponse
    {
        $model = new ApmModel();
        return $model->verify(
            $this->request->post['session_id'],
            $this->request->post['code']
        );
    }

    /**
     * Send APM Heartbeat
     * 
     * @return JsonResponse
     */
    public function heartbeat(): JsonResponse
    {
        $model = new ApmModel();
        $status = $this->request->post['status'] ?? 'healthy';
        $metadata = $this->request->post['metadata'] ?? [];
        return $model->sendHeartbeat($status, $metadata);
    }

    /**
     * Get APM Metrics
     * 
     * @return JsonResponse
     */
    public function metrics(): JsonResponse
    {
        $model = new ApmModel();
        $window = $this->request->get['window'] ?? '15m';
        return $model->getMetrics($window);
    }

    /**
     * Get APM Alerts Summary
     * 
     * @return JsonResponse
     */
    public function alertsSummary(): JsonResponse
    {
        $model = new ApmModel();
        return $model->getAlertsSummary();
    }

    /**
     * Get APM Active Alerts
     * 
     * @return JsonResponse
     */
    public function activeAlerts(): JsonResponse
    {
        $model = new ApmModel();
        $limit = isset($this->request->get['limit']) ? (int)$this->request->get['limit'] : 50;
        $limit = max(1, min(100, $limit));
        return $model->getActiveAlerts($limit);
    }

    /**
     * Get APM Subscription
     * 
     * @return JsonResponse
     */
    public function subscription(): JsonResponse
    {
        $model = new ApmModel();
        return $model->getSubscription();
    }

    /**
     * List APM Plans
     * 
     * @return JsonResponse
     */
    public function plans(): JsonResponse
    {
        $model = new ApmModel();
        return $model->listPlans();
    }

    /**
     * List APM Webhooks
     * 
     * @return JsonResponse
     */
    public function webhooks(): JsonResponse
    {
        $model = new ApmModel();
        return $model->listWebhooks();
    }

    /**
     * Create APM Webhook
     * 
     * @return JsonResponse
     */
    public function createWebhook(): JsonResponse
    {
        $model = new ApmModel();
        $events = $this->request->post['events'];
        if (!is_array($events)) {
            return Response::badRequest('events must be an array');
        }
        $enabled = $this->request->post['enabled'] ?? true;
        return $model->createWebhook(
            $this->request->post['name'],
            $this->request->post['url'],
            $events,
            $enabled
        );
    }
}

