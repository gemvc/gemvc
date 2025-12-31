<?php

namespace Gemvc\Http;

use Gemvc\Core\Apm\ApmFactory;
use Gemvc\Core\Apm\ApmInterface;
use Gemvc\Helper\ProjectHelper;

/**
 * this class is responsible for creating consistently formatted structured JSON responses to use in API
 */
class JsonResponse implements ResponseInterface
{
    public string|false $json_response;
    public int $response_code;
    public string $message;
    public ?int $count;
    public ?string $service_message;
    public mixed $data;
    
    /**
     * APM span reference (set automatically by Response static methods when called from Model)
     * Properties starting with _ are ignored in CRUD operations, so this won't affect database operations
     * 
     * @var array<string, mixed>
     */
    public array $_apm_span = [];
    
    /**
     * Model name for APM (set automatically)
     * 
     * @var string|null
     */
    public ?string $_apm_model_name = null;
    
    /**
     * Method name for APM (set automatically)
     * 
     * @var string|null
     */
    public ?string $_apm_method_name = null;

    public function __construct()
    {
        $this->data = null;
    }

    public function create(int $responseCode,mixed $data ,?	int $count = null ,?string $service_message = null):JsonResponse
    {
        $this->response_code = $responseCode;
        
        $this->message = $this->setHttpMessage($responseCode);

        $this->count = $count;
        $this->service_message = $service_message;
        $this->data = $data;
        $this->json_response = json_encode($this, JSON_PRETTY_PRINT);
        if(!$this->json_response) {
            $this->response_code = 500;
            $this->message = 'internal error';
            $this->count = 0;
            $this->service_message = 'failure in creating json response in Gemvc/JsonResponse .please check data payload';
            $this->json_response = json_encode($this);
        }
        return $this;
    }

    public function success(mixed $data ,?int $count = null ,?string $service_message = null):JsonResponse
    {
        return $this->create(200, $data, $count, $service_message);
    }

    public function updated(mixed $data ,?int $count = null,?string $service_message = null):JsonResponse
    {
        return $this->create(209, $data, $count, $service_message);
    }

    public function created(mixed $data ,?int $count = null,?string $service_message = null):JsonResponse
    {
        return $this->create(201, $data, $count, $service_message);
    }

    public function successButNoContentToShow(mixed $data ,?int $count = null,?string $service_message= null):JsonResponse
    {
        return $this->create(204, $data, $count, $service_message);
    }
    
    public function deleted(mixed $data ,?int $count = null,?string $service_message = null):JsonResponse
    {
        return $this->create(210, $data, $count, $service_message);
    }
    public function unauthorized(?string $service_message = null):JsonResponse
    {
        return $this->create(401, null, null, $service_message);
    }
    public function forbidden(?string $service_message = null):JsonResponse
    {
        return $this->create(403, null, null, $service_message);
    }
    public function notFound(?string $service_message = null):JsonResponse
    {
        return $this->create(404, null, null, $service_message);
    }

    public function internalError(?string $service_message = null ):JsonResponse
    {
        return $this->create(500, null, null, $service_message);
    }

    public function unknownError(mixed $data, ?string $service_message = null):JsonResponse
    {
        return $this->create(0, $data, null, $service_message);
    }

    public function notAcceptable(?string $service_message = null):JsonResponse
    {
        return $this->create(406, null, null, $service_message);
    }

    public function conflict(?string $service_message = null):JsonResponse
    {
        return $this->create(409, null, null, $service_message);
    }

    public function unsupportedMediaType(?string $service_message = null):JsonResponse
    {
        return $this->create(415, null, null, $service_message);
    }

    public function unprocessableEntity(?string $service_message = null):JsonResponse
    {
        return $this->create(422, null, null, $service_message);
    }
    
    public function badRequest(?string $service_message = null):JsonResponse
    {
        return $this->create(400, null, null, $service_message);
    }
    public function show():void
    {
        // Automatically end Model span if it was started (from Response static methods)
        $this->endModelSpanIfStarted();
        
        header('Content-Type: application/json',true,$this->response_code);
        if(!isset($this->json_response) || $this->json_response === false)
        {
            $this->message = "error in creating json response in Gemvc/JsonResponse .please check data payload because it is false or not set";
            $this->response_code = 500;
            $this->json_response = json_encode("error in creating json response in Gemvc/JsonResponse .please check data payload because it is false or not set");
            $this->show();
            die();
        }
        $result  =  html_entity_decode($this->json_response, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if($result == false)
        {
            echo json_encode("error in creating json response in Gemvc/JsonResponse .please check data payload");
            die();
        }
        $this->json_response =  html_entity_decode($this->json_response, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        echo $this->json_response;
    }

    /**
     * @param array<string> $items
     */
    public function payloadNeedItems(array $items):void
    {
        $string ="";
        foreach ($items as $item)
        {
            $string .= $item.',';
        }
        $this->badRequest('payload need items:'.$string);
    }

    private function setHttpMessage(int $httpCode):string
    {
        switch($httpCode)
        {
        case 200: 
            return 'OK';
        case 201: 
            return 'created';
        case 204: 
            return 'no-content';
        case 209: 
            return 'updated';
        case 210: 
            return 'deleted';
        case 400: 
            return 'bad request';
        case 401: 
            return 'unauthorized';
        case 403: 
            return 'forbidden';
        case 404: 
            return 'not found';
        case 406: 
            return 'not acceptable';
        case 409: 
            return 'conflict';
        case 415: 
            return 'unsupported media type';
        case 422: 
            return 'unprocessable entity';
        case 500: 
            return 'internal error';
        default:  
            return 'unknown error';
        }
    }

    public function showSwoole(object $swooleResponse): void
    {
        // Automatically end Model span if it was started (from Response static methods)
        $this->endModelSpanIfStarted();
        
        // @phpstan-ignore-next-line
        $swooleResponse->header('Content-Type', 'application/json');
        
        // Force custom status codes to work with OpenSwoole by providing reason message
        if (in_array($this->response_code, [209, 210])) {
            $statusMessage = $this->response_code === 209 ? 'Updated' : 'Deleted';
            // @phpstan-ignore-next-line
            $swooleResponse->status($this->response_code, $statusMessage);
        } else {
            // @phpstan-ignore-next-line
            $swooleResponse->status($this->response_code);
        }
        
        // @phpstan-ignore-next-line
        $swooleResponse->end($this->json_response);
    }
    
    /**
     * Automatically end Model span if it was started (called from Response static methods)
     * 
     * This provides 100% automatic Model tracing with response body capture
     * 
     * @return void
     */
    private function endModelSpanIfStarted(): void
    {
        if (empty($this->_apm_span)) {
            return;
        }
        if (ProjectHelper::isApmEnabled() === null) {
            return;
        }
        
        // Get APM instance (can work without Request for ending spans)
        $apm = ApmFactory::create(null);
        if ($apm === null) {
            return;
        }
        
        try {
            // Determine status based on response code
            /** @phpstan-ignore-next-line */
            $status = ApmInterface::determineStatusFromHttpCode($this->response_code);
            
            // Build final attributes with response details
            $finalAttributes = [
                'http.status_code' => $this->response_code,
                'response.message' => $this->message ?? '',
                'response.service_message' => $this->service_message ?? '',
            ];
            
            // Include response body if tracing is enabled
            if ($apm->shouldTraceResponse() && $this->json_response !== false) {
                // $this->json_response is guaranteed to be string here (not false)
                /** @phpstan-ignore-next-line */
                $finalAttributes['response.body'] = ApmInterface::limitStringForTracing($this->json_response);
                $finalAttributes['response.count'] = $this->count !== null ? (string)$this->count : 'null';
            }
            
            // End the model span with all response details
            $apm->endSpan($this->_apm_span, $finalAttributes, $status);
        } catch (\Throwable $e) {
            // Silently fail - don't let APM break response sending
            if (ProjectHelper::isDevEnvironment()) {
                error_log("APM: Failed to end model span in JsonResponse: " . $e->getMessage());
            }
        }
    }

}
