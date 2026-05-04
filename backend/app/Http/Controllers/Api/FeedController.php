<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FeedItemResource;
use App\Models\FeedItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FeedController extends Controller
{
    public function index()
    {
        // Pinned first, then most recent
        $items = FeedItem::orderByDesc('is_pinned')->orderByDesc('timestamp')->get();
        return FeedItemResource::collection($items);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'type' => 'required|in:NEW_PRODUCT,NEW_SUPPLIER,STOCK_UPDATE,CUSTOMER_REQUEST,ADVERTISEMENT,NEWS',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'isPinned' => 'nullable|boolean',
            'expiryDate' => 'nullable|date',
            'metadata' => 'nullable|array',
        ]);

        // BE-6 / CRIT-4 fix: per-type role gates. Without this, any authenticated
        // user could publish fake admin announcements via /api/feed.
        $allowedTypes = match (true) {
            $user->isAdmin()    => ['NEW_PRODUCT', 'NEW_SUPPLIER', 'STOCK_UPDATE', 'CUSTOMER_REQUEST', 'ADVERTISEMENT', 'NEWS'],
            $user->isSupplier() => ['NEW_PRODUCT', 'NEW_SUPPLIER', 'STOCK_UPDATE', 'ADVERTISEMENT'],
            $user->isCustomer() || $user->isPharmacyMaster() => ['CUSTOMER_REQUEST'],
            default             => [],
        };
        if (!in_array($data['type'], $allowedTypes, true)) {
            return response()->json([
                'message' => "Your role cannot post feed items of type {$data['type']}.",
            ], 403);
        }
        // NEWS is admin-only — extra safety check (also gated by route middleware on /feed/admin-news)
        if ($data['type'] === 'NEWS' && !$user->isAdmin()) {
            return response()->json(['message' => 'Only admins can publish NEWS items.'], 403);
        }
        // Only admins may pin items.
        if (($data['isPinned'] ?? false) && !$user->isAdmin()) {
            return response()->json(['message' => 'Only admins can pin feed items.'], 403);
        }
        $item = FeedItem::create([
            'id' => (string) Str::uuid(),
            'type' => $data['type'],
            'title' => $data['title'],
            'description' => $data['description'],
            'timestamp' => now(),
            'author_id' => $user->id,
            'author_name' => $user->name,
            'author_role' => $user->role,
            'is_pinned' => (bool) ($data['isPinned'] ?? false),
            'expiry_date' => $data['expiryDate'] ?? null,
            'metadata' => $data['metadata'] ?? null,
        ]);
        return new FeedItemResource($item);
    }

    public function customerRequest(Request $request)
    {
        $user = $request->user();
        if (!$user->isCustomer() && !$user->isAdmin()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }
        $data = $request->validate(['text' => 'required|string|max:4000']);
        $item = FeedItem::create([
            'id' => (string) Str::uuid(),
            'type' => 'CUSTOMER_REQUEST',
            'title' => "Request from {$user->name}",
            'description' => $data['text'],
            'timestamp' => now(),
            'author_id' => $user->id,
            'author_name' => $user->name,
            'author_role' => $user->role,
        ]);
        return new FeedItemResource($item);
    }

    public function advertisement(Request $request)
    {
        $user = $request->user();
        if (!$user->isSupplier() && !$user->isAdmin()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }
        $data = $request->validate([
            'productId' => 'required|string|exists:products,id',
            'days' => 'required|integer|min:1|max:90',
        ]);
        $product = Product::findOrFail($data['productId']);
        $item = FeedItem::create([
            'id' => (string) Str::uuid(),
            'type' => 'ADVERTISEMENT',
            'title' => "Featured: {$product->name}",
            'description' => $product->description,
            'timestamp' => now(),
            'author_id' => $user->id,
            'author_name' => $user->name,
            'author_role' => $user->role,
            'is_pinned' => true,
            'expiry_date' => now()->addDays($data['days']),
            'metadata' => [
                'productId' => $product->id,
                'productImage' => $product->image,
                'price' => (float) $product->price,
            ],
        ]);
        return new FeedItemResource($item);
    }

    public function adminNews(Request $request)
    {
        $user = $request->user();
        if (!$user->isAdmin()) return response()->json(['message' => 'Forbidden.'], 403);
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'mediaType' => 'nullable|in:image,video,pdf',
            // Cap base64 data URLs at ~7.5MB to prevent memory blow-ups
            'mediaUrl' => 'nullable|string|max:10000000',
            'link' => 'nullable|string|max:2048',
            'attachmentName' => 'nullable|string|max:255',
        ]);
        $item = FeedItem::create([
            'id' => (string) Str::uuid(),
            'type' => 'NEWS',
            'title' => $data['title'],
            'description' => $data['content'],
            'timestamp' => now(),
            'author_id' => $user->id,
            'author_name' => $user->name,
            'author_role' => $user->role,
            'metadata' => array_filter([
                'newsUrl' => $data['link'] ?? null,
                'mediaUrl' => $data['mediaUrl'] ?? null,
                'mediaType' => $data['mediaType'] ?? null,
                'attachmentName' => $data['attachmentName'] ?? null,
            ], fn ($v) => $v !== null && $v !== ''),
        ]);
        return new FeedItemResource($item);
    }
}
