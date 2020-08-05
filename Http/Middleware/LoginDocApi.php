<?php

namespace App\Http\Middleware;

use App\Model\User;
use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Support\Facades\Auth;

class LoginDocApi
{
   

    public function handle($request, Closure $next)
    {
        
        if(Auth::user() != null && Auth::user()->hasRole('PITO_ADMIN'))
            return $next($request);
        return abort(403,"Permission denied");

    }
}
