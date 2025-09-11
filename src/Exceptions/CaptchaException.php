<?php

namespace SnipifyDev\LaravelCaptcha\Exceptions;

use Exception;

/**
 * Base captcha exception class
 */
class CaptchaException extends Exception
{
    /**
     * The captcha response data
     *
     * @var array
     */
    protected array $responseData = [];

    /**
     * The captcha score (for v3)
     *
     * @var float|null
     */
    protected ?float $score = null;

    /**
     * The captcha action
     *
     * @var string|null
     */
    protected ?string $action = null;

    /**
     * Create a new captcha exception
     *
     * @param string $message
     * @param int $code
     * @param Exception|null $previous
     * @param array $responseData
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Exception $previous = null,
        array $responseData = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->responseData = $responseData;
    }

    /**
     * Get the response data
     *
     * @return array
     */
    public function getResponseData(): array
    {
        return $this->responseData;
    }

    /**
     * Set the response data
     *
     * @param array $responseData
     * @return $this
     */
    public function setResponseData(array $responseData): self
    {
        $this->responseData = $responseData;
        return $this;
    }

    /**
     * Get the captcha score
     *
     * @return float|null
     */
    public function getScore(): ?float
    {
        return $this->score;
    }

    /**
     * Set the captcha score
     *
     * @param float|null $score
     * @return $this
     */
    public function setScore(?float $score): self
    {
        $this->score = $score;
        return $this;
    }

    /**
     * Get the captcha action
     *
     * @return string|null
     */
    public function getAction(): ?string
    {
        return $this->action;
    }

    /**
     * Set the captcha action
     *
     * @param string|null $action
     * @return $this
     */
    public function setAction(?string $action): self
    {
        $this->action = $action;
        return $this;
    }

    /**
     * Create exception from response
     *
     * @param array $response
     * @param string $message
     * @return static
     */
    public static function fromResponse(array $response, string $message = ''): self
    {
        $exception = new static($message ?: 'Captcha verification failed');
        $exception->setResponseData($response);
        
        if (isset($response['score'])) {
            $exception->setScore($response['score']);
        }
        
        if (isset($response['action'])) {
            $exception->setAction($response['action']);
        }
        
        return $exception;
    }

    /**
     * Get context data for logging
     *
     * @return array
     */
    public function getContext(): array
    {
        $context = [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'response_data' => $this->responseData,
        ];

        if ($this->score !== null) {
            $context['score'] = $this->score;
        }

        if ($this->action !== null) {
            $context['action'] = $this->action;
        }

        return $context;
    }
}