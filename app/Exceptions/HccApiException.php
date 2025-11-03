<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Client\Response;

class HccApiException extends Exception
{
    protected ?Response $response;
    protected array $context;

    public function __construct(
        string $message,
        ?Response $response = null,
        array $context = [],
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->response = $response;
        $this->context = $context;
    }

    public function getResponse(): ?Response
    {
        return $this->response;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function isAuthError(): bool
    {
        return $this->response && in_array($this->response->status(), [401, 403]);
    }

    public function getDetailedMessage(): string
    {
        $details = [
            'message' => $this->getMessage(),
            'context' => $this->context,
        ];

        if ($this->response) {
            $details['status'] = $this->response->status();
            $details['body'] = $this->response->body();
        }

        return json_encode($details, JSON_PRETTY_PRINT);
    }
}







