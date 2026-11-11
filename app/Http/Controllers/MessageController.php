<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Message;
use App\Models\User;
use App\Support\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** FR09 - in-app messaging between coordinators and volunteers. */
class MessageController extends Controller
{
    public function index(Request $request)
    {
        $me = $request->user()->id;

        // Latest message per conversation partner.
        $latestIds = Message::where('sender_id', $me)->orWhere('recipient_id', $me)
            ->selectRaw('MAX(id) as id')
            ->groupBy(DB::raw("CASE WHEN sender_id = $me THEN recipient_id ELSE sender_id END"))
            ->pluck('id');

        $conversations = Message::with(['sender', 'recipient', 'activity'])->whereIn('id', $latestIds)->latest('id')->get()
            ->map(function (Message $m) use ($me) {
                $partner = (int) $m->sender_id === $me ? $m->recipient : $m->sender;

                return [
                    'partner' => $partner,
                    'last' => $m,
                    'unread' => Message::where('sender_id', $partner->id)->where('recipient_id', $me)->whereNull('read_at')->count(),
                ];
            });

        return view('messages.index', [
            'conversations' => $conversations,
            'recipients' => $this->recipients($request->user()),
            'activities' => Activity::upcoming()->with('program')->get(),
        ]);
    }

    public function show(Request $request, User $user)
    {
        $me = $request->user();
        abort_if($user->id === $me->id || ! Role::allows($user->role, 'messages.use'), 404);

        Message::where('sender_id', $user->id)->where('recipient_id', $me->id)->whereNull('read_at')->update(['read_at' => now()]);

        $thread = Message::with('activity')
            ->where(fn ($q) => $q->where('sender_id', $me->id)->where('recipient_id', $user->id))
            ->orWhere(fn ($q) => $q->where('sender_id', $user->id)->where('recipient_id', $me->id))
            ->oldest('id')->get();

        return view('messages.show', [
            'partner' => $user,
            'thread' => $thread,
            'activities' => Activity::upcoming()->with('program')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $me = $request->user();
        $data = $request->validate([
            'client_uuid' => ['nullable', 'uuid'],
            'recipient_id' => ['required', Rule::exists('users', 'id')->where('is_active', true)
                ->whereIn('role', Role::ABILITIES['messages.use']), Rule::notIn([$me->id])],
            'activity_id' => ['nullable', 'exists:activities,id'],
            'body' => ['required', 'string', 'max:5000'],
        ], [
            'recipient_id.required' => 'Please choose who to send the message to.',
            'body.required' => 'Message cannot be empty.',
        ]);

        $message = ! empty($data['client_uuid']) ? Message::where('client_uuid', $data['client_uuid'])->first() : null;
        $message ??= Message::create([...$data, 'body' => trim($data['body']), 'sender_id' => $me->id]);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'reference' => 'MSG-'.$message->id, 'message' => 'Message sent']);
        }

        return redirect()->route('messages.show', $data['recipient_id'])->with('status', 'Message sent.');
    }

    private function recipients(User $me)
    {
        return User::where('is_active', true)->where('id', '!=', $me->id)
            ->whereIn('role', Role::ABILITIES['messages.use'])
            ->orderBy('role')->orderBy('name')->get();
    }
}
