@php
    // /profile is rendered by resources/js/profile. Blade keeps the account
    // values, the email-verification state, every validation bag and the route
    // map, then hands them over as a JSON island, so no field name, message or
    // URL is hardcoded in Vue.
    $user = Auth::user();

    // The re-send notice only makes sense while the address is still unverified,
    // which is the same condition Breeze's partial used.
    $showsVerificationNotice = $user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail
        && ! $user->hasVerifiedEmail();

    $profilePayload = [
        'appName' => config('app.name', 'Studylikepro'),
        'csrfToken' => csrf_token(),
        'theme' => ['preset' => $userTheme['preset'] ?? 'classic'],
        'user' => [
            'name' => $user->name,
            'email' => $user->email,
            'initials' => Str::upper(Str::substr($user->name, 0, 2)),
            'avatarUrl' => $user->avatarUrl(),
        ],
        // A rejected save comes back through old(), so the form reopens with the
        // values the user just submitted rather than the stored ones.
        'values' => [
            'name' => old('name', $user->name),
            'email' => old('email', $user->email),
        ],
        'errors' => [
            'name' => $errors->get('name'),
            'email' => $errors->get('email'),
        ],
        // The password form validates into its own bag, so the keys are renamed
        // to match the fields the Vue card renders.
        'passwordErrors' => [
            'currentPassword' => $errors->updatePassword->get('current_password'),
            'password' => $errors->updatePassword->get('password'),
            'passwordConfirmation' => $errors->updatePassword->get('password_confirmation'),
        ],
        'deleteErrors' => $errors->userDeletion->get('password'),
        'status' => [
            'profileUpdated' => session('status') === 'profile-updated',
            'passwordUpdated' => session('status') === 'password-updated',
            'verificationLinkSent' => session('status') === 'verification-link-sent',
        ],
        'showsVerificationNotice' => $showsVerificationNotice,
        // A rejected delete redirects back with the bag already populated, so the
        // confirmation dialog has to reopen on its own.
        'deleteDialogOpen' => $errors->userDeletion->isNotEmpty(),
        'copy' => [
            'saved' => __('Saved successfully.'),
            'saving' => __('Saving...'),
            'save' => __('Save'),
            'infoTitle' => __('Profile Information'),
            'infoHint' => __("Update your account's profile information and email address."),
            'nameLabel' => __('Name'),
            'emailLabel' => __('Email'),
            'unverifiedNotice' => __('Your email address is unverified.'),
            'resendVerification' => __('Click here to re-send the verification email.'),
            'verificationSent' => __('A new verification link has been sent to your email address.'),
            'passwordTitle' => __('Update Password'),
            'passwordHint' => __('Ensure your account is using a long, random password to stay secure.'),
            'currentPasswordLabel' => __('Current Password'),
            'newPasswordLabel' => __('New Password'),
            'confirmPasswordLabel' => __('Confirm Password'),
            'deleteTitle' => __('Delete Account'),
            'deleteHint' => __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.'),
            'delete' => __('Delete Account'),
            'deleteErrorHint' => __('Please enter your password to confirm.'),
            'deleteConfirmTitle' => __('Are you sure you want to delete your account?'),
            'deleteConfirmBody' => __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.'),
            'passwordLabel' => __('Password'),
            'cancel' => __('Cancel'),
        ],
        'urls' => [
            'update' => route('profile.update'),
            'password' => route('password.update'),
            'destroy' => route('profile.destroy'),
            'verificationSend' => route('verification.send'),
            'settings' => route('settings.index'),
        ],
    ];

    // JSON_UNESCAPED_SLASHES keeps the URLs readable; the HEX flags keep the
    // island safe inside a <script> tag.
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;

    $fieldClasses = 'mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('settings.index') }}" class="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-300" title="{{ __('Back to Settings') }}">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">
                {{ __('Profile Settings') }}
            </h2>
        </div>
    </x-slot>

    <script type="application/json" id="profile-config">@json($profilePayload, $jsonFlags)</script>

    <div id="profile-app"></div>

    {{-- Fallback for a browser without JavaScript: the same three forms, with the
         same field names, posted to the same routes. --}}
    <noscript>
        <div class="mx-auto max-w-5xl space-y-8">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ __('Profile Information') }}</h2>

                <form method="POST" action="{{ route('profile.update') }}" class="mt-6 max-w-xl space-y-6">
                    @csrf
                    @method('PATCH')

                    <div>
                        <x-input-label for="name" :value="__('Name')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autocomplete="name" />
                        <x-input-error class="mt-2" :messages="$errors->get('name')" />
                    </div>

                    <div>
                        <x-input-label for="email" :value="__('Email')" />
                        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
                        <x-input-error class="mt-2" :messages="$errors->get('email')" />
                    </div>

                    <x-primary-button>{{ __('Save') }}</x-primary-button>
                </form>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ __('Update Password') }}</h2>

                <form method="POST" action="{{ route('password.update') }}" class="mt-6 max-w-xl space-y-6">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="update_password_current_password" :value="__('Current Password')" />
                        <x-text-input id="update_password_current_password" name="current_password" type="password" class="mt-1 block w-full" autocomplete="current-password" />
                        <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="update_password_password" :value="__('New Password')" />
                        <x-text-input id="update_password_password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                        <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="update_password_password_confirmation" :value="__('Confirm Password')" />
                        <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                        <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
                    </div>

                    <x-primary-button>{{ __('Save') }}</x-primary-button>
                </form>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ __('Delete Account') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}</p>

                <form method="POST" action="{{ route('profile.destroy') }}" class="mt-6 max-w-xl space-y-6">
                    @csrf
                    @method('DELETE')

                    <div>
                        <x-input-label for="delete_password" :value="__('Password')" />
                        <x-text-input id="delete_password" name="password" type="password" class="mt-1 block w-full" placeholder="{{ __('Password') }}" />
                        <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
                    </div>

                    <x-danger-button>{{ __('Delete Account') }}</x-danger-button>
                </form>
            </div>
        </div>
    </noscript>

    @vite('resources/js/profile/main.js')
</x-app-layout>
