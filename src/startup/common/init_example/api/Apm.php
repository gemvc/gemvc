<?php
namespace App\Api;

use Gemvc\Core\Apm\ApmController;
use Gemvc\Core\ApiService;
use Gemvc\Http\Request;
use Gemvc\Http\JsonResponse;
use Gemvc\Http\Response;
/**
 * this @hidden is used to hide the API from the public documentation
 * @hidden
 */
class Apm extends ApiService
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
     * Test APM Provider - Test endpoint for APM tracing
     * 
     * @return JsonResponse
     * @http GET
     * @description Test APM provider implementation with nested spans
     * @example /api/Apm/test
     */
    public function test(): JsonResponse
    {
        return (new ApmController($this->request))->test();
    }

    /**
     * Test APM Provider with Error - Test exception tracing
     * 
     * @return JsonResponse
     * @http GET
     * @description Test APM provider error/exception tracing
     * @example /api/Apm/testError
     */
    public function testError(): JsonResponse
    {
        return new ApmController($this->request)->testError();
    }

    /**
     * Get APM Status
     * 
     * @return JsonResponse
     * @http GET
     * @description Get current APM provider status and configuration
     * @example /api/Apm/status
     */
    public function status(): JsonResponse
    {
        return (new ApmController($this->request))->status();
    }

    /**
     * Debug APM Payload - Shows what payload would be sent
     * 
     * @return JsonResponse
     * @http GET
     * @description Debug endpoint to inspect APM trace payload structure
     * @example /api/Apm/debugPayload
     */
    public function debugPayload(): JsonResponse
    {
        return (new ApmController($this->request))->debugPayload();
    }

    /**
     * Register APM Service (Provider-agnostic)
     * 
     * @return JsonResponse
     * @http POST
     * @description Register a new service in APM provider (requires email verification)
     * @requires gemvc/apm-{provider} (e.g., gemvc/apm-tracekit)
     * @example /api/Apm/register
     */
    public function register(): JsonResponse
    {
        if (!$this->request->definePostSchema([
            'email' => 'email',
            '?organization_name' => 'string',
            '?source' => 'string',
        ])) {
            return $this->request->returnResponse();
        }

        return (new ApmController($this->request))->register();
    }

    /**
     * Verify APM Email Code (Provider-agnostic)
     * 
     * @return JsonResponse
     * @http POST
     * @description Verify email code and get API key
     * @requires gemvc/apm-{provider} (e.g., gemvc/apm-tracekit)
     * @example /api/Apm/verify
     */
    public function verify(): JsonResponse
    {
        $this->parseJsonPostData();
        
        if (empty($this->request->post)) {
            return Response::badRequest('POST data is required. Make sure Content-Type is application/json and body contains JSON data.');
        }

        if (!$this->request->definePostSchema([
            'session_id' => 'string',
            'code' => 'string',
        ])) {
            return $this->request->returnResponse();
        }

        return (new ApmController($this->request))->verify();
    }

    /**
     * Send APM Heartbeat
     * 
     * @return JsonResponse
     * @http POST
     * @description Send health heartbeat to APM provider
     * @example /api/Apm/heartbeat
     */
    public function heartbeat(): JsonResponse
    {
        $this->parseJsonPostData();

        if (!$this->request->definePostSchema([
            '?status' => 'string',
            '?metadata' => 'json',
        ])) {
            return $this->request->returnResponse();
        }

        return (new ApmController($this->request))->heartbeat();
    }

    /**
     * Get APM Service Metrics
     * 
     * @return JsonResponse
     * @http GET
     * @description Get service metrics (latency, error rate, throughput)
     * @example /api/Apm/metrics
     */
    public function metrics(): JsonResponse
    {
        return (new ApmController($this->request))->metrics();
    }

    /**
     * Get APM Alerts Summary
     * 
     * @return JsonResponse
     * @http GET
     * @description Get alerts summary
     * @example /api/Apm/alertsSummary
     */
    public function alertsSummary(): JsonResponse
    {
        return (new ApmController($this->request))->alertsSummary();
    }

    /**
     * Get APM Active Alerts
     * 
     * @return JsonResponse
     * @http GET
     * @description Get active alerts
     * @example /api/Apm/activeAlerts
     */
    public function activeAlerts(): JsonResponse
    {
        return (new ApmController($this->request))->activeAlerts();
    }

    /**
     * Get APM Subscription Info
     * 
     * @return JsonResponse
     * @http GET
     * @description Get current subscription and usage info
     * @example /api/Apm/subscription
     */
    public function subscription(): JsonResponse
    {
        return (new ApmController($this->request))->subscription();
    }

    /**
     * List APM Available Plans
     * 
     * @return JsonResponse
     * @http GET
     * @description List available subscription plans
     * @example /api/Apm/plans
     */
    public function plans(): JsonResponse
    {
        return (new ApmController($this->request))->plans();
    }

    /**
     * List APM Webhooks
     * 
     * @return JsonResponse
     * @http GET
     * @description List all webhooks
     * @example /api/Apm/webhooks
     */
    public function webhooks(): JsonResponse
    {
        return (new ApmController($this->request))->webhooks();
    }

    /**
     * Create APM Webhook
     * 
     * @return JsonResponse
     * @http POST
     * @description Create a new webhook
     * @example /api/Apm/createWebhook
     */
    public function createWebhook(): JsonResponse
    {
        $this->parseJsonPostData();

        if (!$this->request->definePostSchema([
            'name' => 'string',
            'url' => 'url',
            'events' => 'json',
            '?enabled' => 'boolean',
        ])) {
            return $this->request->returnResponse();
        }

        return (new ApmController($this->request))->createWebhook();
    }
}

