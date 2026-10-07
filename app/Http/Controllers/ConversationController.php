<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportConversationRequest;
use App\Models\Conversation;
use App\Services\ConversationService;
use App\Services\DisputeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The booking-scoped chat. One page for both sides: the perspective follows the
 * signed-in user, who must be the student or the teacher of the lesson.
 */
class ConversationController extends Controller
{
    public function __construct(private readonly ConversationService $conversations) {}

    /**
     * Inbox: every lesson thread this user takes part in, newest first.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $conversations = Conversation::query()
            ->forUser($user)
            ->with(['teacherProfile.user', 'student', 'latestMessage', 'booking.subject', 'booking.lesson'])
            ->latestFirst()
            ->paginate(15);

        return view('messages.index', [
            'conversations' => $conversations,
            'unread' => $conversations->getCollection()
                ->mapWithKeys(fn (Conversation $conversation) => [$conversation->id => $conversation->unreadCountFor($user)]),
            'totalUnread' => $this->conversations->unreadCountFor($user),
        ]);
    }

    /**
     * The thread itself.
     */
    public function show(Request $request, Conversation $conversation): View
    {
        Gate::authorize('view', $conversation);

        $user = $request->user();

        $conversation->load([
            'booking.subject',
            'booking.lesson',
            'booking.teacherProfile.user',
            'student',
            'teacherProfile.user',
        ]);

        $messages = $conversation->messages()
            ->with('sender')
            ->latest('id')
            ->limit(ConversationService::PAGE_SIZE)
            ->get()
            ->reverse()
            ->values();

        $this->conversations->markRead($conversation, $user);

        return view('messages.show', [
            'conversation' => $conversation,
            'messages' => $messages,
            'isTeacher' => $conversation->teacherProfile?->user_id === $user->id,
            'counterpart' => $conversation->counterpartName($user),
            'initialPayload' => $messages->map(fn ($message) => $message->toThreadPayload($user))->values()->all(),
            'reportAlreadyOpen' => app(DisputeService::class)->hasOpenFrom($conversation, $user),
        ]);
    }

    /**
     * Post a message (optionally with a photo).
     */
    public function store(Request $request, Conversation $conversation): RedirectResponse|JsonResponse
    {
        Gate::authorize('post', $conversation);

        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:2000', 'required_without:image'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=4096,max_height=4096'],
        ]);

        $message = $this->conversations->post(
            $conversation,
            $request->user(),
            $validated['body'] ?? null,
            $request->file('image'),
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $message->toThreadPayload($request->user()),
            ], 201);
        }

        return back()->withFragment('latest');
    }

    /**
     * Everything posted after the client's cursor, polled every few seconds.
     */
    public function poll(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        return response()->json(
            $this->conversations->poll($conversation, $request->user(), $request->integer('after', 0)),
        );
    }

    /**
     * Escalate to support: opens a dispute for admin review.
     */
    public function report(ReportConversationRequest $request, Conversation $conversation): RedirectResponse
    {
        Gate::authorize('report', $conversation);

        $disputes = app(DisputeService::class);

        if ($disputes->hasOpenFrom($conversation, $request->user())) {
            return back()->with('status', 'report-already-open');
        }

        $disputes->raise(
            $request->user(),
            $request->validated('reason'),
            $request->validated('details'),
            $conversation,
        );

        return back()->with('status', 'report-received');
    }
}
