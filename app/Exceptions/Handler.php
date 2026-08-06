<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Session\TokenMismatchException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $e
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $e)
    {
        // Handle 419 Page Expired (CSRF token mismatch) - redirect to login
        if ($e instanceof TokenMismatchException) {
            // For AJAX requests, return JSON response
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your session has expired. Please refresh the page and try again.',
                    'redirect' => route('login')
                ], 419);
            }
            
            // For regular requests, redirect to login page with message
            return redirect()->route('login')
                ->with('error', 'Your session has expired. Please login again.');
        }

        return parent::render($request, $e);
    }
}
