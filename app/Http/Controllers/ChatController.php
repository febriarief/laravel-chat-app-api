<?php

namespace App\Http\Controllers;

use App\Http\Requests\GetChatRequest;
use App\Http\Requests\StoreChatRequest;
use App\Models\Chat;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    /**
     * Display a listing of user chats.
     *
     * @param GetChatRequest $request
     * @return JsonResponse
     */
    public function index(GetChatRequest $request): JsonResponse
    {
        $input = $request->validated();
        $isPrivate = 1;
        if ($request->has('is_private')) {
            $isPrivate = (int) $input['is_private'];
        }

        $chats = Chat::where('is_private', $isPrivate)
            ->hasParticipant($request->user()->id)
            ->whereHas('messages')
            ->with(['lastMessage.user', 'participants.user'])
            ->latest('updated_at')
            ->get();

        return $this->success(data: $chats);
    }

    /**
     * Store a new chat.
     *
     * @param StoreChatRequest $request
     * @return JsonResponse
     */
    public function store(StoreChatRequest $request): JsonResponse
    {
        $input = $request->validated();

        $userId = (int) $request->user()->id;
        $friendId = (int) $input['user_id'];

        if ($friendId == $userId) {
            return $this->error(message: 'You cannot create chat by your own.');
        }

        $previousChat = $this->_getPreviousChat($friendId);
        if ($previousChat) {
            return $this->success(data: $previousChat->load(['lastMessage.user', 'participants.user']));
        }

        try {
            DB::beginTransaction();

            $chat = Chat::create([
                'created_by' => $userId,
                'is_private' => 1
            ]);

            $chat->participants()->createMany([
                ['user_id' => $userId],
                ['user_id' => $friendId]
            ]);

            DB::commit();

            $chat->refresh()->load(['lastMessage.user', 'participants.user']);

            return $this->success(data: $chat);

        } catch(\Exception $e) {
            DB::rollBack();
            return $this->error(message: $e->getMessage(), statusCode: 500);
        }
    }

    private function _getPreviousChat(int $friendId)
    {
        $userId = auth()->user()->id;

        return Chat::where('is_private', 1)
            ->whereHas('participants', function($subQuery) use($userId, $friendId) {
                $subQuery->whereIn('user_id', [$userId, $friendId]);
            })
            ->first();
    }

    /**
     * Display specific chat.
     *
     * @param Chat $chat
     * @return JsonResponse
     */
    public function show(Chat $chat): JsonResponse
    {
        $chat->load(['lastMessage.user', 'participants.user']);
        return $this->success(data: $chat);
    }
}
