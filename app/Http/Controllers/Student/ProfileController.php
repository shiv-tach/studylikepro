<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Concerns\StoresAvatar;
use App\Http\Controllers\Controller;
use App\Http\Requests\StudentProfileRequest;
use App\Services\CatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    use StoresAvatar;

    public function __construct(private readonly CatalogService $catalog) {}

    /**
     * Show the learning profile. New students are sent through the onboarding
     * wizard first; this page is the place to change those answers later.
     */
    public function edit(Request $request): View|RedirectResponse
    {
        $profile = $request->user()->studentProfile;

        if (! $profile?->isComplete()) {
            return redirect()->route('student.onboarding.show');
        }

        return view('student.profile', [
            'profile' => $profile,
            'levels' => $this->catalog->levels(),
        ]);
    }

    /**
     * Update the learning profile of an existing student.
     */
    public function update(StudentProfileRequest $request): RedirectResponse
    {
        $user = $request->user();

        $profile = $user->studentProfile()->firstOrNew([]);
        $profile->fill($request->safe()->except('avatar'));
        $profile->timezone = config('studylikepro.default_display_timezone');
        $profile->save();

        $user->setRelation('studentProfile', $profile);

        $this->updateAvatar($request, $user);

        return redirect()
            ->route('student.profile')
            ->with('status', 'profile-updated');
    }
}
