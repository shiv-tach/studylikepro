<?php

namespace App\Contracts;

use App\Models\TutoringRequest;
use App\Support\ClassificationResult;

interface TopicClassifier
{
    /**
     * Suggest a subject and topic for a student's question.
     *
     * Implementations throw on transport/authentication failures and return a
     * failed result when the model answered but nothing could be matched.
     */
    public function classify(TutoringRequest $request): ClassificationResult;
}
