<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /** POST /api/login */
    public function login(LoginRequest $request): UserResource
    {
        $request->authenticate();

        // New session ID after login prevents session-fixation attacks.
        $request->session()->regenerate();

        return new UserResource($request->user()->load('role'));
    }

    /** POST /api/logout */
    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    /** GET /api/user */
    public function user(Request $request): UserResource
    {
        return new UserResource($request->user()->load('role'));
    }
}
