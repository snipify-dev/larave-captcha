<?php

namespace SnipifyDev\LaravelCaptcha\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use SnipifyDev\LaravelCaptcha\Facades\Captcha;
use SnipifyDev\LaravelCaptcha\Exceptions\CaptchaValidationException;

/**
 * Verify Captcha Middleware
 * 
 * Middleware to verify captcha before allowing request to proceed
 */
class VerifyCaptcha
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @param string $action
     * @param float|null $threshold
     * @param string $field
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $action = 'default', ?string $threshold = null, string $field = 'captcha_token')
    {
        // Skip verification if captcha is disabled
        if (!Captcha::isEnabled($action)) {
            return $next($request);
        }

        // Skip verification for GET requests by default
        if ($request->isMethod('GET') && !config('captcha.middleware.verify_get_requests', false)) {
            return $next($request);
        }

        try {
            $this->verifyCaptcha($request, $action, $threshold, $field);
            return $next($request);
        } catch (CaptchaValidationException $e) {
            return $this->handleCaptchaFailure($request, $e, $action);
        } catch (\Exception $e) {
            return $this->handleUnexpectedError($request, $e, $action);
        }
    }

    /**
     * Verify the captcha token
     *
     * @param Request $request
     * @param string $action
     * @param string|null $threshold
     * @param string $field
     * @throws CaptchaValidationException
     */
    protected function verifyCaptcha(Request $request, string $action, ?string $threshold, string $field): void
    {
        $token = $this->extractToken($request, $field);
        
        if (empty($token)) {
            throw new CaptchaValidationException(
                config('captcha.errors.messages.required', 'The captcha field is required.')
            );
        }

        $thresholdValue = $threshold ? (float) $threshold : null;
        
        if (!Captcha::verify($token, $action, $thresholdValue)) {
            throw new CaptchaValidationException(
                config('captcha.errors.messages.invalid', 'The captcha verification failed.')
            );
        }
    }

    /**
     * Extract captcha token from request
     *
     * @param Request $request
     * @param string $field
     * @return string|null
     */
    protected function extractToken(Request $request, string $field): ?string
    {
        // Try different possible field names
        $possibleFields = [
            $field,
            'captcha_token',
            'captcha',
            'recaptcha_token',
            'recaptcha',
            'g-recaptcha-response', // v2 default field name
        ];

        foreach ($possibleFields as $fieldName) {
            $token = $request->input($fieldName);
            if (!empty($token)) {
                return $token;
            }
        }

        return null;
    }

    /**
     * Handle captcha validation failure
     *
     * @param Request $request
     * @param CaptchaValidationException $e
     * @param string $action
     * @return Response
     */
    protected function handleCaptchaFailure(Request $request, CaptchaValidationException $e, string $action): Response
    {
        // Log the failure if configured
        if (config('captcha.errors.log_errors', true)) {
            logger()->log(
                config('captcha.errors.log_level', 'warning'),
                'Captcha middleware validation failed',
                [
                    'action' => $action,
                    'url' => $request->fullUrl(),
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'score' => $e->getScore(),
                    'message' => $e->getMessage(),
                ]
            );
        }

        // Handle API requests
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'Captcha verification failed.',
                'errors' => [
                    'captcha' => [$e->getMessage()]
                ],
                'captcha_error' => true,
            ], 422);
        }

        // Handle web requests
        if ($request->ajax()) {
            return response()->json([
                'error' => $e->getMessage(),
                'captcha_error' => true,
            ], 422);
        }

        // Redirect back with error for regular form submissions
        throw ValidationException::withMessages([
            'captcha_token' => [$e->getMessage()],
        ]);
    }

    /**
     * Handle unexpected errors
     *
     * @param Request $request
     * @param \Exception $e
     * @param string $action
     * @return Response
     */
    protected function handleUnexpectedError(Request $request, \Exception $e, string $action): Response
    {
        // Log the error
        logger()->error('Captcha middleware unexpected error', [
            'action' => $action,
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'exception' => $e,
        ]);

        $message = config('captcha.errors.messages.network_error', 'Unable to verify captcha due to network error.');

        // Handle API requests
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'Captcha verification error.',
                'errors' => [
                    'captcha' => [$message]
                ],
                'captcha_error' => true,
            ], 500);
        }

        // Handle AJAX requests
        if ($request->ajax()) {
            return response()->json([
                'error' => $message,
                'captcha_error' => true,
            ], 500);
        }

        // For regular requests, redirect back with error
        return redirect()->back()
            ->withInput($request->except(['captcha_token', 'captcha', 'g-recaptcha-response']))
            ->withErrors(['captcha_token' => $message]);
    }
}