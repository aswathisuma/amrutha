<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

abstract class Controller
{
    /**
     * Redirect back with a toast. Pass $reopen to re-open a drawer/modal
     * (its partial URL) once the page reloads.
     */
    protected function done(string $message, string $type = 'ok', ?string $reopen = null, ?string $to = null): RedirectResponse
    {
        $redirect = $to ? redirect($to) : back();

        return $redirect->with('toast', [$message, $type])->with('reopen', $reopen);
    }

    /**
     * Drawer and modal content is fetched by the front end. A direct visit
     * falls back to the given page with the panel opened on load.
     */
    protected function panel(string $view, array $data, string $fallback)
    {
        if (request()->ajax()) {
            return view($view, $data);
        }

        return redirect($fallback)->with('reopen', request()->fullUrl());
    }
}
