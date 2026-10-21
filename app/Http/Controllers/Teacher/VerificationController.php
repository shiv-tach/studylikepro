<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\TeacherDocumentRequest;
use App\Models\TeacherProfile;
use App\Models\TeacherVerificationDocument;
use Illuminate\Http\JsonResponse;
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
            // The row the page rings and labels "just uploaded" after a redirect.
            'highlightDocumentId' => $request->session()->get('uploaded_document_id'),
        ]);
    }

    /**
     * Upload a verification document.
     */
    public function store(TeacherDocumentRequest $request): RedirectResponse|JsonResponse
    {
        $profile = $request->user()->teacherProfile;

        if (! $profile?->isComplete()) {
            return redirect()
                ->route('teacher.profile')
                ->with('status', 'complete-your-profile');
        }

        if (! $profile->verification_status->allowsDocumentEditing()) {
            if ($request->wantsJson()) {
                return self::lockedResponse();
            }

            return redirect()
                ->route('teacher.verification')
                ->with('status', 'documents-locked');
        }

        $file = $request->file('document');

        $document = $profile->documents()->create([
            'type' => $request->validated('type'),
            'original_name' => $file->getClientOriginalName(),
            'path' => $file->store('verification-documents/'.$profile->id, 'local'),
        ]);

        // Uploads save as a draft; the page can confirm straight away when the
        // request accepts JSON, and the redirect carries the same news otherwise.
        if ($request->wantsJson()) {
            return response()->json([
                'document' => self::documentPayload($document),
                'notices' => [[
                    'tone' => 'emerald',
                    'title' => __('Document uploaded'),
                    'message' => self::uploadedNotice($profile),
                ]],
            ]);
        }

        return redirect()
            ->route('teacher.verification')
            ->with('status', 'document-uploaded')
            ->with('uploaded_document_id', $document->id);
    }

    /**
     * Remove a verification document.
     */
    public function destroy(Request $request, TeacherVerificationDocument $document): RedirectResponse|JsonResponse
    {
        Gate::authorize('delete', $document);

        $label = $document->typeLabel();

        Storage::disk('local')->delete($document->path);
        $document->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'removedId' => $document->id,
                'notices' => [[
                    'tone' => 'slate',
                    'title' => __('Document removed'),
                    'message' => __(':type is no longer on file. You can upload it again before you submit.', ['type' => $label]),
                ]],
            ]);
        }

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

    /**
     * One document in the shape the Vue page renders and appends.
     *
     * @return array<string, mixed>
     */
    private static function documentPayload(TeacherVerificationDocument $document): array
    {
        return [
            'id' => $document->id,
            'type' => $document->type,
            'typeLabel' => $document->typeLabel(),
            'originalName' => $document->original_name,
            'uploadedAt' => $document->created_at?->format('d M Y'),
            'isRequired' => $document->type === 'id_proof',
            'destroyUrl' => route('teacher.verification.documents.destroy', $document),
        ];
    }

    /**
     * What the teacher should do next, said inside the upload's confirmation.
     */
    private static function uploadedNotice(TeacherProfile $profile): string
    {
        return $profile->documents()->where('type', 'id_proof')->exists()
            ? __('That was the document we needed — submit your application below.')
            : __('Your government ID is still needed before you can submit.');
    }

    private static function lockedResponse(): JsonResponse
    {
        return response()->json([
            'notices' => [[
                'tone' => 'amber',
                'title' => __('Documents locked'),
                'message' => __('Your documents are locked while the application is under review.'),
            ]],
        ], 409);
    }
}
