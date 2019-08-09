<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:6',
        ]);

        $http = new \GuzzleHttp\Client;

        try {
            $response = $http->post(config('services.passport.login_endpoint'), [
                'form_params' => [
                    'grant_type' => 'password',
                    'client_id' => config('services.passport.id'),
                    'client_secret' => config('services.passport.secret'),
                    'username' => $request->get('email'),
                    'password' => $request->get('password'),
                ],
            ]);

            return $response->getBody();
        } catch (\GuzzleHttp\Exception\BadResponseException $e) {
            $message;

            if ($e->getCode() === 400) {
                $message = 'Invalid Request. Please enter a username or a password.';
            } else if ($e->getCode() === 401) {
                $message = 'Your credentials are incorrect. Please try again';
            } else {
                $message = 'Something went wrong on the server.';
            }
            return response()->json([
                    'message' => $message,
                    'errors' => [ 'email' => [$message] ]
                ], 422);
        }
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return ['message' => 'Successfully logged out.'];;
    }

    public function user()
    {
        $user = Auth::user();
        $user->branch = $user->getBranches()->pluck('code');
        $user->action = $user->getActions()->pluck('code');
        $user->module = $user->getModules()->pluck('code');

        return $user;
    }
}
