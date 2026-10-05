<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;
class PasswordExpiryCheck
{

    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            // already logged-in user
            $changed = Auth::user()->password_changed_at;
            if (is_null($changed) || Carbon::parse($changed)->addDays(30)->isPast()) {
                return redirect()->route('reset.password');
            }
        }

        return $next($request);
    }
}
