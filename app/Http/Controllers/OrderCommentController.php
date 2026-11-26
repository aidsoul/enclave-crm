<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreOrderCommentRequest;
use App\Models\Order;
use App\Models\OrderComment;
use App\Support\StrHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderCommentController extends Controller
{
    public function index(Request $request, Order $order): JsonResponse
    {
        $user = $request->attributes->get('user') ?? auth()->user();
        if ($user && $user->isCourier()) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $query = OrderComment::with('user')
            ->where('order_id', $order->id);

        if ($search = $request->input('search')) {
            $escaped = StrHelper::escapeLike($search);
            $query->where(function ($q) use ($escaped) {
                $q->where('body', 'ilike', "%{$escaped}%")
                    ->orWhereHas('user', function ($uq) use ($escaped) {
                        $uq->where('system_name', 'ilike', "%{$escaped}%");
                    });
            });
        }

        if ($userId = $request->input('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($authorType = $request->input('author_type')) {
            $query->where('author_type', $authorType);
        }

        $query->orderByDesc('created_at');

        if ($request->has('page') || $request->has('per_page')) {
            $perPage = min(max((int) $request->input('per_page', 10), 1), 100);
            $paginator = $query->paginate($perPage);

            return response()->json([
                'comments' => $paginator->items(),
                'total' => $paginator->total(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ]);
        }

        $comments = $query->get();

        return response()->json([
            'comments' => $comments,
            'total' => $comments->count(),
        ]);
    }

    public function store(StoreOrderCommentRequest $request, Order $order): JsonResponse
    {
        $user = auth()->user();

        $validated = $request->validated();

        $authorType = match ($user->role) {
            UserRole::OWNER => 'owner',
            UserRole::MANAGER => 'manager',
            UserRole::COURIER => 'courier',
            default => 'system',
        };

        $comment = OrderComment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'author_type' => $authorType,
            'body' => $validated['body'],
        ]);

        return response()->json(['comment' => $comment->load('user')], 201);
    }

    public function destroy(Request $request, Order $order, OrderComment|int|string $comment): JsonResponse
    {
        $commentId = $comment instanceof OrderComment ? $comment->id : (int) $comment;

        $commentModel = OrderComment::where('order_id', $order->id)
            ->where('id', $commentId)
            ->first();

        if (! $commentModel) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $commentModel->delete();

        return response()->json(['status' => 'deleted']);
    }
}
