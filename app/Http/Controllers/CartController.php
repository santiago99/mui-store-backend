<?php

namespace App\Http\Controllers;

use App\Http\Requests\MergeCartRequest;
use App\Http\Requests\StoreCartItemRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Http\Resources\CartItemResource;
use App\Models\CartItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    /**
     * Display a listing of cart items for the authenticated user.
     */
    public function index(): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = Auth::user();
        $cartItems = $user
            ->cartItems()
            ->with('product.category')
            ->get();

        return CartItemResource::collection($cartItems);
    }

    /**
     * Store a newly created cart item in storage.
     */
    public function store(StoreCartItemRequest $request): CartItemResource
    {
        /** @var User $user */
        $user = Auth::user();
        $validated = $request->validated();

        // Check if product already exists in cart
        $existingCartItem = $user->cartItems()
            ->where('product_id', $validated['product_id'])
            ->first();

        if ($existingCartItem) {
            // Update quantity instead of creating new item
            $existingCartItem->update([
                'quantity' => $existingCartItem->quantity + $validated['quantity'],
            ]);
            $existingCartItem->load('product.category');

            return new CartItemResource($existingCartItem);
        }

        // Create new cart item
        $cartItem = $user->cartItems()->create($validated);
        $cartItem->load('product.category');

        return new CartItemResource($cartItem);
    }

    /**
     * Update the specified cart item in storage.
     */
    public function update(UpdateCartItemRequest $request, CartItem $cartItem): CartItemResource
    {
        // Ensure the cart item belongs to the authenticated user
        if ($cartItem->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access to cart item');
        }

        $cartItem->update($request->validated());
        $cartItem->load('product.category');

        return new CartItemResource($cartItem);
    }

    /**
     * Remove the specified cart item from storage.
     */
    public function destroy(CartItem $cartItem): JsonResponse
    {
        // Ensure the cart item belongs to the authenticated user
        if ($cartItem->user_id !== Auth::id()) {
            abort(403, 'Unauthorized access to cart item');
        }

        $cartItem->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Merge localStorage cart with database cart.
     */
    public function merge(MergeCartRequest $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = Auth::user();
        $validated = $request->validated();

        foreach ($validated['items'] as $item) {
            $existingCartItem = $user->cartItems()
                ->where('product_id', $item['product_id'])
                ->first();

            if ($existingCartItem) {
                // Add quantities together
                $existingCartItem->update([
                    'quantity' => $existingCartItem->quantity + $item['quantity'],
                ]);
            } else {
                // Create new cart item
                $user->cartItems()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ]);
            }
        }

        // Return updated cart
        $cartItems = $user->cartItems()
            ->with('product.category')
            ->get();

        return CartItemResource::collection($cartItems);
    }
}
