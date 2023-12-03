<?php

namespace Mollsoft\WebTelegramBot\Exceptions;

use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;
use Throwable;

class ExceptionHandler extends Handler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $e)
    {
        if ($e instanceof RedirectException) {
            return $e->redirect;
        }

        $response = parent::render($request, $e);

        if (($response instanceof Response) && mb_strpos($response->content(), '<message>') === false) {
            $html = '<message><p>Error 500</p></message>';
            if (View::exists('telegraph.errors.500')) {
                $html = view('telegraph.errors.500', [
                    'required' => $response,
                    'exception' => $e
                ])->render();
            }
            $response->setContent($html);
        }

        return $response;
    }

    protected function registerErrorViewPaths(): void
    {
        (new RegisterErrorViewPaths)();
    }
}
