<?php

namespace App\Http\Controllers;

use App\Models\TeacherVerificationDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VerificationDocumentController extends Controller
{
    /**
     * Stream a private verification document to its owner or an admin.
     */
    public function show(Request $request, TeacherVerificationDocument $document): StreamedResponse
    {
        Gate::authorize('view', $document);

        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->response($document->path, $document->original_name);
    }
}
