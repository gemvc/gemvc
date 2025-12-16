<?php
namespace Gemvc\Core;

class GemvcError {
    public string $message;
    public int $http_code;
    public ?string $file;
    public ?int $line;

    public function __construct(string $message, int $http_code, ?string $file, ?int $line)
    {
        $this->message = $message;
        $this->http_code = $http_code;
        $this->file = $file;
        $this->line = $line;
    }

}