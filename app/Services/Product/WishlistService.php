<?php

namespace App\Services\Product;

use App\Models\Wishlist;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class WishlistService
{
    /**
     * Get the user's or guest's wishlist products.
     */
    public function getWishlist(?int $userId, ?string $guestId): Collection
    {
        if (!$userId && !$guestId) {
            return collect();
        }

        $query = Wishlist::with(['product.category', 'product.location', 'product.productImages', 'product.options.pricingCategories']);

        if ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->where('guest_id', $guestId);
        }

        return $query->get()->pluck('product')->filter()->values();
    }

    /**
     * Toggle a product in the wishlist (add if not exists, remove if exists).
     * Returns true if added, false if removed.
     */
    public function toggleWishlist(?int $userId, ?string $guestId, int $productId): bool
    {
        if (!$userId && !$guestId) {
            throw new \InvalidArgumentException('Either User ID or Guest ID must be provided.');
        }

        $query = Wishlist::where('product_id', $productId);

        if ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->where('guest_id', $guestId);
        }

        $existing = $query->first();

        if ($existing) {
            $existing->delete();
            return false; // Removed
        }

        Wishlist::create([
            'user_id' => $userId,
            'guest_id' => $userId ? null : $guestId, // If user is logged in, ignore guest_id
            'product_id' => $productId,
        ]);

        return true; // Added
    }

    /**
     * Synchronize a guest's wishlist items to a registered user account.
     * Deletes duplicates if the user already has the item.
     */
    public function syncGuestToUser(int $userId, string $guestId): void
    {
        try {
            $guestItems = Wishlist::where('guest_id', $guestId)->get();

            if ($guestItems->isEmpty()) {
                return;
            }

            $userProductIds = Wishlist::where('user_id', $userId)->pluck('product_id')->toArray();

            foreach ($guestItems as $item) {
                if (in_array($item->product_id, $userProductIds)) {
                    // User already has this product in their wishlist, just delete the guest entry
                    $item->delete();
                } else {
                    // Transfer the guest entry to the user
                    $item->update([
                        'user_id' => $userId,
                        'guest_id' => null,
                    ]);
                    $userProductIds[] = $item->product_id; // Add to tracked array to prevent future duplicates in this loop
                }
            }
            
            Log::info("Synced wishlist from guest {$guestId} to user {$userId}.");
        } catch (\Exception $e) {
            Log::error("Failed to sync wishlist for user {$userId}: " . $e->getMessage());
        }
    }
}
