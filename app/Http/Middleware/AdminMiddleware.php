<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware {
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response {

        if (Auth::check() && (Auth::user()->role === 'Admin')) {
            return $next($request);
        }else{
            session()->flash('t-error', 'You are not authorized to access this page.');
            return redirect()->route('login');
        }

    }
}
