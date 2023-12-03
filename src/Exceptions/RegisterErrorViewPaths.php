<?php

namespace Mollsoft\WebTelegramBot\Exceptions;

use Illuminate\Support\Facades\View;

class RegisterErrorViewPaths
{
    /**
     * Register the error view paths.
     *
     * @return void
     */
    public function __invoke()
    {
        View::replaceNamespace(
            'errors',
            collect(config('view.paths'))->map(function ($path) {
                return "{$path}/telegraph/errors";
            })->all()
        );
    }
}
