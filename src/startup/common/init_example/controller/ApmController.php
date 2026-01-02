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
     * Register APM Service
     * 
     * @return JsonResponse
     */
    public function register(): JsonResponse
    {
        $model = new ApmModel();
        $email = isset($this->request->post['email']) && is_string($this->request->post['email']) ? $this->request->post['email'] : '';
        $organizationName = isset($this->request->post['organization_name']) && is_string($this->request->post['organization_name']) ? $this->request->post['organization_name'] : null;
        $source = isset($this->request->post['source']) && is_string($this->request->post['source']) ? $this->request->post['source'] : 'gemvc';
        return $model->register($email, $organizationName, $source);
    }

    /**
     * Verify APM Email Code
     * 
     * @return JsonResponse
     */
    public function verify(): JsonResponse
    {
        $model = new ApmModel();
        $sessionId = isset($this->request->post['session_id']) && is_string($this->request->post['session_id']) ? $this->request->post['session_id'] : '';
        $code = isset($this->request->post['code']) && is_string($this->request->post['code']) ? $this->request->post['code'] : '';
        return $model->verify($sessionId, $code);
    }

    /**
     * Send APM Heartbeat
     * 
     * @return JsonResponse
     */
    public function heartbeat(): JsonResponse
    {
        $model = new ApmModel();
        $status = isset($this->request->post['status']) && is_string($this->request->post['status']) ? $this->request->post['status'] : 'healthy';
        $metadata = isset($this->request->post['metadata']) && is_array($this->request->post['metadata']) ? $this->request->post['metadata'] : [];
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
        $window = isset($this->request->get['window']) && is_string($this->request->get['window']) ? $this->request->get['window'] : '15m';
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
        $limit = 50;
        if (isset($this->request->get['limit']) && is_numeric($this->request->get['limit'])) {
            $limit = (int)$this->request->get['limit'];
        }
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
        $events = $this->request->post['events'] ?? [];
        if (!is_array($events)) {
            return Response::badRequest('events must be an array');
        }
        $eventsArray = [];
        foreach ($events as $event) {
            if (is_string($event)) {
                $eventsArray[] = $event;
            }
        }
        $name = isset($this->request->post['name']) && is_string($this->request->post['name']) ? $this->request->post['name'] : '';
        $url = isset($this->request->post['url']) && is_string($this->request->post['url']) ? $this->request->post['url'] : '';
        $enabled = isset($this->request->post['enabled']) ? (bool)$this->request->post['enabled'] : true;
        return $model->createWebhook($name, $url, $eventsArray, $enabled);
    }
}

