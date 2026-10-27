/**
 * Marketing copy for the landing page.
 *
 * Keeping the wording out of the components makes the sections purely
 * presentational and leaves one obvious place to review, translate or later
 * replace with CMS/database driven content.
 */

export const trustPoints = [
    'Free to join',
    'Verified teachers only',
    'Secure LKR payments',
    'Chat, reminders & reviews',
];

export const stats = [
    { value: 'Grades 6–13', label: 'Sri Lankan curriculum, O/L through A/L' },
    { value: 'Verified only', label: 'Every tutor is reviewed before their first lesson' },
    { value: 'Live 1-on-1', label: 'A private lesson room — never a crowded class' },
    { value: 'LKR pricing', label: 'Clear rates, receipts and a published refund policy' },
];

// Marquee strip beneath the hero — the syllabus the catalog actually covers.
export const boards = [
    'Grades 6–13',
    'O/L',
    'A/L',
    'Mathematics',
    'Science',
    'English',
    'Sinhala',
    'Tamil',
    'ICT',
    'Commerce',
    'Physics',
    'Chemistry',
    'Biology',
    'Accounting',
    'Economics',
];

export const levels = [
    {
        key: 'ol',
        name: 'O/L',
        grades: 'Grades 6–11',
        blurb: 'The full Ordinary Level catalog: core subjects plus one subject from each optional basket.',
        subjects: [
            { name: 'Mathematics', icon: '🔢' },
            { name: 'Science', icon: '🔬' },
            { name: 'English', icon: '📖' },
            { name: 'Sinhala', icon: '📜' },
            { name: 'History', icon: '🏛️' },
            { name: 'ICT', icon: '💻' },
            { name: 'Geography', icon: '🌍' },
            { name: 'Commerce', icon: '💼' },
            { name: 'Art', icon: '🎨' },
            { name: 'Music', icon: '🎹' },
            { name: 'Drama & Theatre', icon: '🎭' },
            { name: 'Health & Physical Education', icon: '🏃' },
        ],
    },
    {
        key: 'al',
        name: 'A/L',
        grades: 'Grades 12–13',
        blurb: 'Advanced Level streams — science, commerce and technology — with grade-by-grade lesson lists.',
        subjects: [
            { name: 'Combined Mathematics', icon: '🔢' },
            { name: 'Physics', icon: '⚛️' },
            { name: 'Chemistry', icon: '🧪' },
            { name: 'Biology', icon: '🧬' },
            { name: 'Accounting', icon: '📊' },
            { name: 'Economics', icon: '📈' },
            { name: 'Business Studies', icon: '💼' },
            { name: 'ICT', icon: '💻' },
        ],
    },
];

export const steps = [
    {
        icon: 'book',
        title: 'Choose your subject & lesson',
        summary: 'Browse the catalog for your own grade.',
        detail: 'O/L and A/L are separate catalogs, so the subjects and lessons you see always match the grade on your profile.',
    },
    {
        icon: 'users',
        title: 'Compare verified teachers',
        summary: 'Only teachers who teach that lesson show up.',
        detail: 'Filter by subject, lesson and availability. Every tutor on Studylikepro is onboarded by invitation, submits verification documents and is approved by our team before they can accept a single lesson.',
    },
    {
        icon: 'calendar',
        title: 'Choose a real time slot',
        summary: 'Book only the times a teacher has actually published.',
        detail: 'Your teacher publishes availability in advance, so the slots you see are the slots you can book — no back-and-forth messages to agree on a time.',
    },
    {
        icon: 'card',
        title: 'Pay securely in rupees',
        summary: 'Transparent pricing with a receipt for every lesson.',
        detail: 'Checkout shows the lesson rate, the platform booking fee and the total before you pay. Payments are processed by our gateway and every lesson gets a receipt.',
    },
    {
        icon: 'video',
        title: 'Join the live lesson',
        summary: 'A private 1-on-1 video room, opened from your dashboard.',
        detail: 'When it is time, open the lesson from your dashboard and join the private room. Chat with your teacher around the lesson and get reminders so you never miss one.',
    },
    {
        icon: 'star',
        title: 'Rate your teacher',
        summary: 'Your review helps the next student choose well.',
        detail: 'After the lesson, rate your teacher and leave a review. Ratings stay on the profile so quality stays visible, and any problem can be raised as a dispute from your lesson history.',
    },
];

export const features = [
    {
        icon: 'shield-check',
        title: 'Verified teachers only',
        description: 'Tutors are invited, document-checked and approved by our team before they can accept a lesson.',
    },
    {
        icon: 'search',
        title: 'Grade-aware teacher search',
        description: 'Filter verified teachers by the subject, lesson and grade you need — only tutors who actually teach it appear.',
    },
    {
        icon: 'layers',
        title: 'Grade-aware catalog',
        description: 'O/L and A/L are separate catalogs with lesson lists built grade by grade, so Grade 6 never sees A/L work.',
    },
    {
        icon: 'calendar',
        title: 'Real availability',
        description: 'Book straight from a teacher’s published calendar — before school, after school or evening, in Sri Lanka time.',
    },
    {
        icon: 'wallet',
        title: 'Transparent payments',
        description: 'Lesson rate, booking fee and total are shown before checkout. Receipts, refunds and disputes are handled in-app.',
    },
    {
        icon: 'chat',
        title: 'Chat, reminders & reviews',
        description: 'Message your teacher around the lesson, get timely reminders, and rate every lesson you take.',
    },
];

// Illustrative layout of a tutor profile — not a real teacher.
export const profilePreview = {
    headline: 'O/L Mathematics & Science',
    grades: 'Grades 6–11',
    rate: 'Per-grade rates set by the teacher',
    availability: 'Availability published week by week',
    lessons: 'Subjects and lessons inherited from the curriculum',
    rating: 'Star ratings and reviews from completed lessons',
};

export const profileHighlights = [
    { icon: 'shield-check', label: 'Verification documents approved by our team' },
    { icon: 'book', label: 'Subjects and lessons inherited from the curriculum' },
    { icon: 'clock', label: 'Weekly availability with per-grade pricing' },
    { icon: 'star', label: 'Star ratings and written reviews from lessons' },
];

// Sample copy — replace with real student reviews before launch.
export const testimonials = [
    {
        quote: 'I pick my lesson, see which teachers actually teach it, and book a slot that fits my week. No more guessing which topic to search.',
        name: 'Student',
        meta: 'O/L · Grade 10',
    },
    {
        quote: 'Lessons appear on our calendar and the reminder means we are never late. The teacher knows exactly where we are in the syllabus.',
        name: 'Parent',
        meta: 'O/L · Grade 8',
    },
    {
        quote: 'Pricing was clear before I paid and the receipt was available straight away. The refund policy was right there in writing.',
        name: 'Student',
        meta: 'A/L · Grade 12',
    },
];

export const faqs = [
    {
        question: 'How do I find the right teacher?',
        answer: 'Browse the catalog for your grade, open the subject and lesson you need, and compare the verified teachers who teach it. Each profile shows their availability, rates and reviews, and you can book one of their published slots directly.',
    },
    {
        question: 'Who is allowed to teach on Studylikepro?',
        answer: 'Teachers join by invitation only. They complete their profile, submit verification documents and are reviewed by our team before their profile can accept a lesson. Students only ever see approved teachers.',
    },
    {
        question: 'Which grades and subjects are covered?',
        answer: 'The catalog follows the Sri Lankan structure: O/L (Grades 6–11) with its optional subject baskets, and A/L (Grades 12–13). Every subject carries a lesson list for each grade it runs in.',
    },
    {
        question: 'How do payments and refunds work?',
        answer: 'Lesson prices are in Sri Lankan rupees, and checkout shows the lesson rate, the booking fee and the total before you pay. Every lesson produces a receipt, and cancellations follow the published refund policy.',
    },
    {
        question: 'How is the lesson delivered?',
        answer: 'Lessons run 1-on-1 in a private video room that you open from your dashboard at the scheduled time. You can chat with your teacher around the lesson and you will get reminders before it starts.',
    },
    {
        question: 'What happens if something goes wrong?',
        answer: 'Report the issue from your lesson history. Bookings, payments and marking orders can all be disputed in-app, and our team reviews disputes and refunds against the recorded lesson details.',
    },
];
