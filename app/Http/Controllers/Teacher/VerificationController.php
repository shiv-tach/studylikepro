<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\TeacherDocumentRequest;
use App\Models\TeacherVerificationDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class VerificationController extends Controller
{
    /**
     * Show the verification documents page.
     */
    public function index(Request $request): View|RedirectResponse
    {
        $profile = $request->user()->teacherProfile;

        if (! $profile?->isComplete()) {
            return redirect()
                ->route('teacher.profile')
                ->with('status', 'complete-your-profile');
        }

        return view('teacher.verification', [
            'profile' => $profile->load('documents'),
            'canEditDocuments' => $profile->verification_status->allowsDocumentEditing(),
            'documentTypes' => config('studylikepro.verification_document_types'),
        ]);
    }

    /**
     * Upload a verification document.
     */
    public function store(TeacherDocumentRequest $request): RedirectResponse
    {
        $profile = $request->user()->teacherProfile;

        if (! $profile?->isComplete()) {
            return redirect()
                ->route('teacher.profile')
                ->with('status', 'complete-your-profile');
        }

        if (! $profile->verification_status->allowsDocumentEditing()) {
            return redirect()
                ->route('teacher.verification')
                ->with('status', 'documents-locked');
        }

        $file = $request->file('document');

        $profile->documents()->create([
            'type' => $request->validated('type'),
            'original_name' => $file->getClientOriginalName(),
            'path' => $file->store('verification-documents/'.$profile->id, 'local'),
        ]);

        return redirect()
            ->route('teacher.verification')
            ->with('status', 'document-uploaded');
    }

    /**
     * Remove a verification document.
     */
    public function destroy(TeacherVerificationDocument $document): RedirectResponse
    {
        Gate::authorize('delete', $document);

        Storage::disk('local')->delete($document->path);
        $document->delete();

        return redirect()
            ->route('teacher.verification')
            ->with('status', 'document-removed');
    }

    /**
     * Submit the application for admin review.
     */
    public function submit(Request $request): RedirectResponse
    {
        $profile = $request->user()->teacherProfile;

        if (! $profile?->isComplete()) {
            return redirect()
                ->route('teacher.profile')
                ->with('status', 'complete-your-profile');
        }

        if (! $profile->verification_status->allowsDocumentEditing()) {
            return redirect()
                ->route('teacher.verification')
                ->with('status', 'verification-locked');
        }

        $request->validate([
            'agree' => ['accepted'],
        ], [
            'agree.accepted' => __('Please accept the terms before submitting for review.'),
        ]);

        if (! $profile->documents()->where('type', 'id_proof')->exists()) {
            return back()->withErrors([
                'document' => 'Upload your government ID before submitting for review.',
            ]);
        }

        $profile->update([
            'verification_status' => VerificationStatus::Pending,
            'submitted_at' => now(),
            'verification_notes' => null,
            'agreement_accepted_at' => $profile->agreement_accepted_at ?? now(),
            'agreement_version' => (string) config('studylikepro.support.policy_version'),
        ]);

        return redirect()
            ->route('teacher.verification')
            ->with('status', 'submitted-for-review');
    }
}
