<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * The teacher onboarding wizard's own document shell: no dashboard sidebar, a
 * small top bar and the wizard progress slot, so setup never looks like the
 * rest of the workspace.
 */
class OnboardingLayout extends Component
{
    public function render(): View
    {
        return view('layouts.onboarding');
    }
}
