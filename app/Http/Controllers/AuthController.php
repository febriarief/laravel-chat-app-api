<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    /**
     * Register a new user.
     *
     * @param RegisterRequest $request
     * @return JsonResponse
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $input = $request->validated();

        try {
            $input['password'] = bcrypt($input['password']);

            DB::beginTransaction();

            $user = User::create($input);
            $token = $user->createToken(User::USER_TOKEN);

            DB::commit();

            return $this->success(data: [
                'user' => $user,
                'token' => $token->plainTextToken
            ]);

        } catch(\Exception $e) {
            DB::rollBack();
            return $this->error(message: $e->getMessage(), statusCode: 500);
        }
    }

    /**
     * Login a user.
     *
     * @param LoginRequest $request
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $input = $request->validated();

        if (!Auth::attempt(['email' => $input['email'], 'password' => $input['password']])) {
            return $this->error(message: 'Combination of email and password did not match.');
        }

        try {
            DB::beginTransaction();
            $user = Auth::user();
            $token = $user->createToken(User::USER_TOKEN);

            DB::commit();

            return $this->success(data: [
                'user' => $user,
                'token' => $token->plainTextToken
            ]);

        } catch(\Exception $e) {
            DB::rollBack();
            return $this->error(message: $e->getMessage(), statusCode: 500);
        }
    }

    /**
     * Logout a user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $request->user()->currentAccessToken()->delete();
            DB::commit();
            return $this->success();
        } catch(\Exception $e) {
            DB::rollBack();
            return $this->error(message: $e->getMessage(), statusCode: 500);
        }
    }
}
