<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** Mobil kayıt: her zaman 'user' (sakin) rolüyle açılır */
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:120', 'unique:users,email'],
            'password' => ['required', Password::min(8)],
            'phone' => ['nullable', 'string', 'max:30'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);
        $user = User::create(collect($data)->except('device_name')->all());

        return response()->json(['token' => $user->createToken($data['device_name'])->plainTextToken, 'user' => $this->profile($user)], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);
        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'E-posta veya şifre hatalı.']);
        }
        $user->forceFill(['last_login_at' => now()])->save();

        return response()->json(['token' => $user->createToken($data['device_name'])->plainTextToken, 'user' => $this->profile($user)]);
    }

    public function me(Request $request)
    {
        return response()->json($this->profile($request->user()));
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['success' => true]);
    }

    /** Profil + bağlı olduğu daireler (mobilin hangi siteye bakacağını bilmesi için) */
    private function profile(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'residencies' => $user->memberships()->with('apartment:id,name,code', 'unit.block')->get()->map(fn ($m) => [
                'apartment_id' => $m->apartment_id,
                'apartment_name' => $m->apartment->name,
                'unit_id' => $m->unit_id,
                'unit' => $m->unit->label(),
                'member_id' => $m->id,
                'type' => $m->type,
            ])->values(),
        ];
    }
}
