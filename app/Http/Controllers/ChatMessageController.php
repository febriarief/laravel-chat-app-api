<?php

namespace App\Http\Controllers;

use App\Events\NewMessageSent;
use App\Http\Requests\GetMessageRequest;
use App\Http\Requests\StoreMessageRequest;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ChatMessageController extends Controller
{
    /**
     * Get list of messages.
     *
     * @param GetMessageRequest $request
     * @return JsonResponse
     */
    public function index(GetMessageRequest $request): JsonResponse
    {
        $input = $request->validated();
        $pageSize = $input['page_size'] ?? 20;
        $messages = ChatMessage::where('chat_id', $input['chat_id'])
            ->with('user')
            ->latest('created_at')
            ->simplePaginate($pageSize, ['*'], 'page', $input['page']);

        return $this->success(data: $messages->getCollection());
    }

    /**
     * Store new chat.
     *
     * @param StoreMessageRequest $request
     * @return JsonResponse
     */
    public function store(StoreMessageRequest $request): JsonResponse
    {
        $input = $request->validated();
        $input['user_id'] = $request->user()->id;

        try {
            DB::beginTransaction();

            $chatMessage = ChatMessage::create($input);
            $chatMessage->load('user');

            broadcast(new NewMessageSent($chatMessage))->toOthers();

            $user = $request->user();
            $userId = $user->id;

            $chat = Chat::where('id', $chatMessage->chat_id)
                ->with(['participants' => function($query) use($userId) {
                    $query->where('user_id', '!=', $userId);
                }])
                ->first();

            if (count($chat->participants) > 0) {
                $friendId = $chat->participants[0]->id;
                $friend = User::find($friendId);
                $friend->sendNewMessageNotification([
                    'messageData' => [
                        'senderName' => $user->email, //$user->name,
                        'message' => $chatMessage->message,
                        'chat_id' => $chatMessage->chat_id
                    ]
                ]);
            }

            DB::commit();

            return $this->success(data: $chatMessage);

        } catch(\Exception $e) {
            DB::rollBack();
            return $this->error(message: $e->getMessage(), statusCode: 500);
        }
    }
}
