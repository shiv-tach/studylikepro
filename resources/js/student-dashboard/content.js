/**
 * Static copy for the student dashboard. The student's own numbers, links and
 * lesson cards arrive through the `#student-dashboard-config` island; everything
 * here is identical for every student and only changes with the build plan.
 */
export const roadmapSteps = [
    {
        key: 'profile',
        title: 'Profile & onboarding',
        description: 'Add your learning details so tutors know how to help.',
        phase: 'Phase 1',
    },
    {
        key: 'subjects',
        title: 'Choose subjects & lessons',
        description: 'Pick what you want help with from the catalog.',
        phase: 'Phase 2',
    },
    {
        key: 'booking',
        title: 'Book & pay securely',
        description: 'Pick an available time and pay through the payment gateway.',
        phase: 'Phase 4–5',
    },
    {
        key: 'lesson',
        title: 'Join your live lesson',
        description: 'Meet your teacher in a private video room.',
        phase: 'Phase 7',
    },
    {
        key: 'review',
        title: 'Chat, review & history',
        description: 'Stay in touch, rate the lesson, and revisit past lessons.',
        phase: 'Phase 8–9',
    },
];

export const flowSteps = [
    { emoji: '🔎', title: 'Find the right lesson', description: 'Browse subjects and lessons for your grade.' },
    { emoji: '👨‍🏫', title: 'Match with a verified teacher', description: 'Only approved tutors appear.' },
    { emoji: '🕐', title: 'Choose a time & pay', description: 'Real availability, secure checkout.' },
    { emoji: '🎥', title: 'Live lesson, then review', description: 'Learn 1-on-1 and rate your tutor.' },
];
