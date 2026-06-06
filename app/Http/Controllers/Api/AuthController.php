<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Memory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:100',
            'email'    => 'required|email|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => $request->password,
        ]);

        // Create empty memory record
        Memory::create(['user_id' => $user->id]);

        $token = $user->createToken('nia-token')->plainTextToken;

        return response()->json([
            'user'  => $this->userResource($user),
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The credentials you provided are incorrect.'],
            ]);
        }

        // Revoke old tokens
        $user->tokens()->delete();

        $token = $user->createToken('nia-token')->plainTextToken;

        return response()->json([
            'user'  => $this->userResource($user),
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $this->userResource($request->user())]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $request->validate([
            'name'             => 'sometimes|string|max:100',
            'age'              => 'sometimes|integer|min:1|max:120',
            'marital_status'   => 'sometimes|string',
            'occupation'       => 'sometimes|string',
            'whatsapp_number'  => 'sometimes|string',
            'onboarded'        => 'sometimes|boolean',
        ]);

        $user->update($request->only([
            'name','age','marital_status','occupation','whatsapp_number','onboarded'
        ]));

        return response()->json(['user' => $this->userResource($user)]);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password'         => 'required|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Current password is incorrect'], 422);
        }

        $user->update(['password' => $request->password]);
        return response()->json(['message' => 'Password updated successfully']);
    }

    private function userResource(User $user): array
    {
        return [
            'id'               => $user->id,
            'name'             => $user->name,
            'email'            => $user->email,
            'age'              => $user->age,
            'marital_status'   => $user->marital_status,
            'occupation'       => $user->occupation,
            'whatsapp_number'  => $user->whatsapp_number,
            'plan'             => $user->plan,
            'is_pro'           => $user->isPro(),
            'is_admin'         => $user->is_admin,
            'onboarded'        => $user->onboarded,
            'messages_remaining' => $user->messagesRemaining(),
            'pro_expires_at'   => $user->pro_expires_at,
            'created_at'       => $user->created_at,
        ];
    }
}
