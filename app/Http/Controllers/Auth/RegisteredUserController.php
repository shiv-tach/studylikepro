<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\TeacherInvite;
use App\Models\User;
use App\Rules\SriLankanMobile;
use App\Support\PhoneNumber;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(Request $request): View
    {
        $invite = null;
        $inviteToken = null;
        $inviteError = null;

        if ($request->filled('invite')) {
            $inviteToken = (string) $request->query('invite');
            $invite = $this->resolveInvite($inviteToken);

            if ($invite === null) {
                $inviteError = 'This teacher onboarding link is invalid, expired, or already used.';
            }
        }

        return view('auth.register', [
            'invite' => $invite,
            'inviteToken' => $inviteToken,
            'inviteError' => $inviteError,
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        // Compare and store the number in its canonical local form so
        // "076 982 88 74" and "+94 76 982 8874" are the same account.
        $request->merge(['mobile' => PhoneNumber::normalize($request->input('mobile'))]);

        $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'mobile' => ['required', 'string', new SriLankanMobile, Rule::unique(User::class, 'mobile')],
            'role' => ['nullable', 'string', Rule::in([User::ROLE_STUDENT, User::ROLE_TEACHER])],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [], [
            'mobile' => __('mobile number'),
        ]);

        // Public registration always creates a student; only an invite may grant the teacher role.
        $role = $request->string('role')->toString() ?: User::ROLE_STUDENT;

        $invite = null;

        if ($role === User::ROLE_TEACHER) {
            $invite = $this->resolveInvite((string) $request->input('invite'));

            if ($invite === null) {
                throw ValidationException::withMessages([
                    'invite' => 'Teacher accounts can only be created through a valid onboarding link.',
                ]);
            }
        }

        $user = User::create([
            'first_name' => trim($request->string('first_name')->toString()),
            'last_name' => trim($request->string('last_name')->toString()),
            'email' => $request->email,
            'mobile' => $request->mobile,
            'password' => Hash::make($request->password),
        ]);

        $user->assignRole($role);

        if ($invite !== null) {
            $invite->forceFill(['consumed_at' => now()])->save();
        }

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }

    /**
     * Find a usable invite for the given plaintext token, or null.
     */
    private function resolveInvite(?string $token): ?TeacherInvite
    {
        if (blank($token)) {
            return null;
        }

        $invite = TeacherInvite::query()
            ->where('token', hash('sha256', $token))
            ->first();

        return $invite?->isUsable() ? $invite : null;
    }
}
