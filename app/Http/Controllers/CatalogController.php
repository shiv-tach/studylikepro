<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use App\Services\CatalogService;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function __construct(private readonly CatalogService $catalog) {}

    /**
     * Public subject overview.
     */
    public function index(): View
    {
        return view('catalog.index', ['subjects' => $this->catalog->subjectsWithTopicCounts()]);
    }

    /**
     * Public topics for one subject.
     */
    public function show(Subject $subject): View
    {
        abort_unless($subject->is_active, 404);

        return view('catalog.show', [
            'subject' => $subject,
            'topics' => $this->catalog->topicsFor($subject),
        ]);
    }
}
