<?php

namespace App\Support;

use App\Models\TeacherProfile;
use Illuminate\Support\MessageBag;

/**
 * The island behind the verification page: the checklist, the document types,
 * the subject picker and every server-written message.
 */
final class VerificationStepConfig
{
    /**
     * @param  array<string, string>  $documentTypes
     * @param  list<array<string, mixed>>  $subjectGroups
     * @param  list<int>  $selectedSubjectIds
     * @return array<string, mixed>
     */
    public static function build(
        TeacherProfile $profile,
        MessageBag $errors,
        array $documentTypes,
        array $subjectGroups,
        array $selectedSubjectIds,
        string $themePreset = 'classic',
        ?int $highlightDocumentId = null,
    ): array {
        $status = $profile->verification_status;
        $hasIdProof = $profile->documents->contains('type', 'id_proof');
        $verificationSubmitted = (bool) $profile->hasSubmittedVerification();
        $hasAvailability = TeacherOnboarding::hasAvailability($profile);

        $typeHints = [
            'id_proof' => __('A passport, national ID card or driving licence — this is the one we need before you can submit.'),
            'degree' => __('Your degree or diploma certificate.'),
            'certificate' => __('A teaching qualification or training certificate.'),
            'government_proof' => __('A school letter, appointment letter or staff ID that shows your government teaching post. Optional — it earns the badge on your profile card.'),
            'other' => __('Anything else that supports your application.'),
        ];

        // Each type gets its own upload slot, so the icon travels with it and the
        // no-JS twin can draw the same list. Multiple files per type are allowed
        // (a two-page ID scan is common), which the section heading explains.
        $typeIcons = [
            'id_proof' => 'shield',
            'degree' => 'graduation',
            'certificate' => 'check-circle',
            'government_proof' => 'shield',
            'other' => 'folder',
        ];

        $documentTypesPayload = collect($documentTypes)
            ->map(fn (string $label, string $value) => [
                'value' => $value,
                'label' => $label,
                'hint' => $typeHints[$value] ?? '',
                'required' => $value === 'id_proof',
                'icon' => $typeIcons[$value] ?? 'folder',
                'multiple' => true,
            ])
            ->values();

        $uploadedTypes = $profile->documents->pluck('type')->all();
        $selectedType = collect(array_keys($documentTypes))
            ->first(fn (string $type) => ! in_array($type, $uploadedTypes, true)) ?? 'id_proof';

        // A rejected upload redirects back with the old `type`, so the slot that
        // failed can carry the server's message instead of a loose page alert.
        $failedType = old('type');
        $failedType = is_string($failedType) && array_key_exists($failedType, $documentTypes)
            ? $failedType
            : null;

        // Acceptance is stored on the profile when the application is submitted,
        // so the checklist keeps showing it as done afterwards. A failed submit
        // keeps whatever the teacher actually ticked, error included.
        $agreementAccepted = session()->hasOldInput()
            ? (bool) old('agree')
            : $profile->hasAcceptedAgreement();

        $notices = self::notices($profile, $hasIdProof);

        return [
            'appName' => config('app.name', 'Studylikepro'),
            'csrfToken' => csrf_token(),
            'theme' => ['preset' => $themePreset],
            'status' => [
                'current' => $status->value,
                'label' => $status->label(),
            ],
            'profile' => [
                'isComplete' => (bool) $profile->isComplete(),
                'hasSubmittedVerification' => $verificationSubmitted,
                'hasAvailability' => $hasAvailability,
                'submittedAt' => display_datetime($profile->submitted_at),
                'verifiedAt' => display_datetime($profile->verified_at),
                'notes' => $profile->verification_notes,
            ],
            'canEditDocuments' => $status->allowsDocumentEditing(),
            'documentTypes' => $documentTypesPayload,
            'documents' => $profile->documents->map(fn ($document) => [
                'id' => $document->id,
                'type' => $document->type,
                'typeLabel' => $document->typeLabel(),
                'originalName' => $document->original_name,
                'uploadedAt' => $document->created_at?->format('d M Y'),
                'isRequired' => $document->type === 'id_proof',
                'destroyUrl' => route('teacher.verification.documents.destroy', $document),
            ])->values(),
            'selectedType' => $selectedType,
            'subjectGroups' => $subjectGroups,
            'selectedSubjectIds' => $selectedSubjectIds,
            'highlightDocumentId' => $highlightDocumentId,
            'limits' => [
                'accept' => 'application/pdf,image/jpeg,image/png,image/webp',
                'maxBytes' => 5 * 1024 * 1024,
                'maxSize' => '5 MB',
                'extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'webp'],
            ],
            'values' => [
                'agree' => $agreementAccepted,
            ],
            // Laravel owns the wording of every message; the app only decides
            // where it is shown, so the two never drift apart. `byType` tells the
            // matching upload slot which file failed after a full-page bounce.
            'errors' => [
                'type' => $errors->get('type'),
                'document' => $errors->get('document'),
                'agree' => $errors->get('agree'),
                'subjects' => $errors->get('subjects'),
                'byType' => $failedType === null ? null : [
                    'type' => $failedType,
                    'messages' => array_values(array_merge($errors->get('type'), $errors->get('document'))),
                ],
            ],
            'notices' => $notices,
            'steps' => TeacherOnboarding::steps($profile),
            'urls' => [
                'upload' => route('teacher.verification.documents.store'),
                'submit' => route('teacher.verification.submit'),
                'subjects' => route('teacher.verification.subjects.store'),
                'terms' => route('legal.terms'),
                'privacy' => route('legal.privacy'),
                'dashboard' => route('teacher.dashboard'),
                'profile' => route('teacher.profile'),
                'availability' => route('teacher.availability.index'),
                'back' => null,
                'submitted' => route('teacher.approval.status'),
            ],
            'copy' => self::copy(),
        ];
    }

    /**
     * The flash messages that become toasts after a redirect.
     *
     * @return list<array{tone: string, title: string, message: string}>
     */
    private static function notices(TeacherProfile $profile, bool $hasIdProof): array
    {
        $notices = [];

        if (session('status') === 'profile-completed') {
            $notices[] = [
                'tone' => 'emerald',
                'title' => __('Profile saved'),
                'message' => __('Your teaching profile is saved. Set your weekly availability, then upload your documents.'),
            ];
        } elseif (session('status') === 'availability-saved') {
            $notices[] = [
                'tone' => 'emerald',
                'title' => __('Weekly availability saved'),
                'message' => __('Your week is set. Upload your documents below, then submit them for review.'),
            ];
        } elseif (session('status') === 'complete-your-availability') {
            $notices[] = [
                'tone' => 'amber',
                'title' => __('One step left'),
                'message' => __('Set your weekly availability before submitting your documents.'),
            ];
        } elseif (session('status') === 'complete-your-verification') {
            $notices[] = [
                'tone' => 'amber',
                'title' => __('One step left'),
                'message' => __('Finish your verification to unlock your dashboard.'),
            ];
        } elseif (session('status') === 'document-uploaded') {
            $notices[] = [
                'tone' => 'emerald',
                'title' => __('Document uploaded'),
                'message' => $hasIdProof
                    ? __('That was the document we needed — submit your application below.')
                    : __('Your government ID is still needed before you can submit.'),
            ];
        } elseif (session('status') === 'document-removed') {
            $notices[] = [
                'tone' => 'slate',
                'title' => __('Document removed'),
                'message' => __('You can upload it again before you submit.'),
            ];
        } elseif (session('status') === 'subjects-saved') {
            $notices[] = [
                'tone' => 'emerald',
                'title' => __('Subjects saved'),
                'message' => __('Our team approves your subjects together with your application.'),
            ];
        } elseif (session('status') === 'submitted-for-review') {
            $notices[] = [
                'tone' => 'emerald',
                'title' => __('Application submitted'),
                'message' => __('Our team will review your documents — usually within 1–2 business days.'),
            ];
        } elseif (session('status') === 'documents-locked') {
            $notices[] = [
                'tone' => 'amber',
                'title' => __('Documents locked'),
                'message' => __('Your documents are locked while the application is under review.'),
            ];
        } elseif (session('status') === 'verification-locked') {
            $notices[] = [
                'tone' => 'amber',
                'title' => __('Already submitted'),
                'message' => __('Your application is already with our team. You can upload new documents only if we ask for changes.'),
            ];
        }

        return $notices;
    }

    /**
     * @return array<string, mixed>
     */
    private static function copy(): array
    {
        return [
            'toastLabel' => __('Notifications'),
            'toastDismiss' => __('Dismiss notification'),
            'errorTitle' => __('Please check the following:'),
            'stepsTitle' => __('Teacher setup'),
            'stepsCompleted' => __(':completed of :total steps completed'),
            'stepLabel' => __('Step :number'),
            'stepStatusDone' => __('Completed'),
            'stepStatusCurrent' => __('In progress'),
            'stepStatusUpNext' => __('Up next'),
            'stepStatusLocked' => __('Locked'),
            'statusTitles' => [
                'draft' => __('Get verified'),
                'pending' => __('Application under review'),
                'approved' => __('You are verified'),
                'rejected' => __('We need a bit more from you'),
            ],
            'statusBodies' => [
                'draft' => __('Upload your documents below, then submit your application. Verification keeps quality high and builds student trust.'),
                'pending' => __('Thanks! We received your application on :submitted. Our team reviews documents within 1–2 business days, and you will be notified when your profile is approved.'),
                'approved' => __('Your profile was approved on :verified. Verified teachers appear in student search and can be booked for live lessons.'),
                'rejected' => __('Our team could not approve the application yet. Please address the notes below, update your documents, and resubmit.'),
            ],
            'reviewerNotes' => __('What our team said'),
            'checklistTitle' => __('What we need'),
            'checklistHint' => __('Everything submission waits for, updated as you go.'),
            'checklistProgress' => __('Progress'),
            'checklistDone' => __('Done'),
            'checklistTodo' => __('To do'),
            'checklistTip' => __('Upload one clear file per document. You can add more than one file of the same type.'),
            'checklistLocked' => __('Your documents are locked while the application is under review.'),
            'reqProfile' => __('Teaching profile'),
            'reqProfileHint' => __('Saved.'),
            'reqId' => __('Government ID'),
            'reqIdHintDone' => __('On file and ready for review.'),
            'reqIdHintMissing' => __('Upload your ID to unlock submission.'),
            'reqIdAction' => __('Upload now'),
            'reqSubjects' => __('Subjects you will teach'),
            'reqSubjectsHintDone' => __('At least one subject selected.'),
            'reqSubjectsHintMissing' => __('Choose at least one subject before submitting.'),
            'reqSubjectsAction' => __('Choose now'),
            'reqTerms' => __('Terms and policies accepted'),
            'reqTermsHintDone' => __('Accepted.'),
            'reqTermsHintMissing' => __('Tick the box on the submit card.'),
            'documentsTitle' => __('Your documents'),
            'documentsHint' => __('Everything our team will review. Open the document type you want to add — the upload starts on its own.'),
            'documentsCount' => __(':count on file'),
            'documentsEmptyTitle' => __('No documents uploaded yet'),
            'documentsEmptyHint' => __('Start with your government ID — it is the one we need before you can submit.'),
            'requiredDocument' => __('Required'),
            'optionalDocument' => __('Optional'),
            'uploadedDocument' => __('Uploaded'),
            'justUploaded' => __('Just uploaded'),
            'uploadedOn' => __('Uploaded :date'),
            'removeDocument' => __('Remove :type'),
            'uploadTitle' => __('Upload a document'),
            'uploadHint' => __('Files are saved as a draft right away — nothing reaches our team until you submit below.'),
            'uploadTypeLabel' => __('What are you uploading?'),
            'uploadFileLabel' => __('Choose the file'),
            'fileChoose' => __('Choose a file'),
            'fileDropHint' => __('or drag and drop it onto this box'),
            'fileTapHint' => __('Tap to take a photo or choose a file'),
            'fileTypes' => __('PDF, JPG, PNG or WebP · up to 5 MB'),
            'fileReady' => __('ready to upload'),
            'fileChange' => __('Change'),
            'fileTypeError' => __('That file type is not accepted. Use a PDF, JPG, PNG or WebP.'),
            'fileSizeError' => __('That file is larger than :limit. Try a smaller scan.'),
            'fileRequired' => __('Choose a file first.'),
            'uploadButton' => __('Upload document'),
            'uploadBusy' => __('Uploading…'),
            'uploadSuccess' => __('Saved to your documents.'),
            'uploadFailed' => __('That upload did not go through. Please try again.'),
            'uploadDraftNote' => __('Saved as a draft — not sent for review yet.'),
            // One upload slot per document type: a file starts uploading the
            // moment it is chosen, so every state needs a word of its own.
            'uploadAutoNote' => __('Uploading starts the moment you choose a file.'),
            'uploadAddAnother' => __('Add another file'),
            'uploadRetry' => __('Try again'),
            'uploadCancel' => __('Cancel'),
            'uploadCancelled' => __('Upload cancelled.'),
            'uploadProgress' => __('Uploading… :percent%'),
            'uploadSlotEmpty' => __('No file yet'),
            'uploadSlotUploading' => __('Uploading'),
            'uploadSlotInvalid' => __('Not uploaded'),
            'uploadFailedShort' => __('Upload failed'),
            'uploadRequiredHint' => __('Needed before you can submit.'),
            'uploadOptionalHint' => __('Optional — it still helps our team.'),
            'uploadStartHere' => __('Start here'),
            'submitBlockedUpload' => __('Wait for your upload to finish before submitting.'),
            'subjectsTitle' => __('What will you teach?'),
            'subjectsHint' => __('Pick every subject you can teach — you can choose from more than one level. Your selection goes to our team with your documents and is approved together with your application.'),
            'subjectsLockedHint' => __('Your subjects are locked while the application is under review.'),
            'subjectsSearch' => __('Search subjects'),
            'subjectsSelected' => __(':count selected'),
            'subjectsSelectAll' => __('Select all'),
            'subjectsShowAll' => __('Show all :count'),
            'subjectsShowLess' => __('Show fewer'),
            'subjectsClear' => __('Clear'),
            'subjectsEmpty' => __('No subjects match your search.'),
            'subjectsSaveFailed' => __('Your subjects could not be saved. Please try again.'),
            'submitTitle' => __('Submit for review'),
            'submitHint' => __('Nothing reaches our team until you submit. After that your documents are locked while we review — usually 1–2 business days.'),
            // The three stages are shown above the button so "submit" never reads
            // as "finished": it is the start of the review, not the end of setup.
            'submitStages' => [
                ['title' => __('Submit'), 'body' => __('Send your documents and subjects to our team.')],
                ['title' => __('Review'), 'body' => __('An admin checks them — usually 1–2 business days.')],
                ['title' => __('Go live'), 'body' => __('Approved teachers appear in student search and can be booked.')],
            ],
            'termsAcknowledge' => __('I understand my profile stays hidden from students until it is approved.'),
            'termsLead' => __('I have read and accept the'),
            'termsLink' => __('teacher terms'),
            'termsAnd' => __('and the'),
            'privacyLink' => __('privacy policy'),
            'termsTail' => __(', including the commission and cancellation rules.'),
            'submitButton' => __('Submit for review'),
            'submitReady' => __('All requirements met — you can submit now.'),
            'submitReadyShort' => __('Ready to submit'),
            'submitBlockedId' => __('Upload your government ID above to enable submission.'),
            'submitBlockedSubjects' => __('Choose at least one subject you will teach to enable submission.'),
            'submitBlockedTerms' => __('Tick the terms box to enable submission.'),
            'confirmTitle' => __('Submit your application for review?'),
            'confirmBody' => __('Your documents will be locked while our team reviews them, so please make sure they are clear and correct first. Submitting does not make you visible yet — an admin approves your profile first, usually within 1–2 business days.'),
            'confirmYes' => __('Yes, submit for review'),
            'confirmNo' => __('Not yet'),
            'confirmClose' => __('Close'),
            'removeTitle' => __('Remove this document?'),
            'removeBody' => __('You can upload it again at any time before you submit for review.'),
            'removeConfirm' => __('Remove document'),
            'removeCancel' => __('Keep it'),
            'removeFailed' => __('That document could not be removed. Please try again.'),
            'completeTitle' => __('You are live'),
            'completeBody' => __('Your profile is approved. Students can find you in search and book your open slots.'),
            'completeCta' => __('Go to dashboard'),
            'reviewTitle' => __('Application in review'),
            'reviewBody' => __('Your documents are with our team. You are not visible to students yet — we will notify you as soon as your profile is approved.'),
            'reviewCta' => __('View approval status'),
        ];
    }
}
