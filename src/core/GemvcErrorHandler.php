<?php
namespace Gemvc\Core;

use Gemvc\Core\GemvcError;
use Gemvc\Http\JsonResponse;
use Gemvc\Helper\ProjectHelper;

class GEMVCErrorHandler {

    /**
     * Handle and display errors as JSON response
     * 
     * @param array<GemvcError> $errors Array of error objects to handle
     * @return void
     */
    public static function handleErrors(array $errors) {
        if(empty($errors)) {
            // No errors to handle - return early
            return;
        }
        
        $response = new JsonResponse();
        
        if(count($errors) === 1) {
            $error = $errors[0];
            $msg = self::handleErrorMessage($error);
            $response->create($error->http_code, $msg, null, $error->message);
            $response->show();
        }
        else {
            $std = new \stdClass();
            $std->errors = [];
            foreach($errors as $error) {
                $std->errors[] = $error->message;
            }
            $httpCode = self::determineHttpCodeForMultipleErrors($errors);
            $response->create($httpCode, $std, count($errors), 'multiple errors occurred');
            $response->show();
        }
    }

    /**
     * Format error message for development environment (includes file and line)
     * 
     * @param GemvcError $error The error object
     * @return string Formatted error message with file and line information
     */
    public static function handleDevErrorMessage(GemvcError $error) {
        $fileInfo = ($error->file && $error->line) 
            ? " in {$error->file} on line {$error->line}" 
            : "";
        return $error->message . $fileInfo;
    }

    /**
     * Format error message for production environment (message only)
     * 
     * @param GemvcError $error The error object
     * @return string Error message without file/line details
     */
    public static function handleProductionErrorMessage(GemvcError $error) {
        return $error->message;
    }

    /**
     * Get formatted error message based on environment
     * 
     * @param GemvcError $error The error object
     * @return string Formatted error message (dev includes file/line, prod is message only)
     */
    public static function handleErrorMessage(GemvcError $error) {
        if (ProjectHelper::isDevEnvironment()) {
            return self::handleDevErrorMessage($error);
        }
        return self::handleProductionErrorMessage($error);
    }

    /**
     * Determine the appropriate HTTP status code for multiple errors
     * Uses the most severe error code (500 > 422 > 404 > 400)
     * 
     * @param array<GemvcError> $errors
     * @return int HTTP status code
     */
    private static function determineHttpCodeForMultipleErrors(array $errors): int {
        if(empty($errors)) {
            return 500; // Fallback if somehow empty
        }

        // Priority order: 500 > 422 > 404 > 400 > others
        $priority = [
            500 => 4, // Highest priority - server errors
            422 => 3, // Validation errors
            404 => 2, // Not found
            400 => 1, // Bad request
        ];

        $maxPriority = 0;
        $selectedCode = 422; // Default fallback

        foreach($errors as $error) {
            $code = $error->http_code;
            $codePriority = $priority[$code] ?? 0;
            
            if($codePriority > $maxPriority) {
                $maxPriority = $codePriority;
                $selectedCode = $code;
            }
        }

        return $selectedCode;
    }
}