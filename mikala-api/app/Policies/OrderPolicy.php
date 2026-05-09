<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Determine if the user can view any orders.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view orders (filtered by their role)
        return true;
    }

    /**
     * Determine if the user can view the order.
     */
    public function view(User $user, Order $order): bool
    {
        // Internal staff can view any order
        if (in_array($user->role, ['manajemen', 'customer_care', 'finance'])) {
            return true;
        }

        // Klien can view own orders
        if ($user->role === 'klien' && $user->klien) {
            return $order->klien_id === $user->klien->id;
        }

        // Mitra can view assigned orders
        if ($user->role === 'mitra' && $user->mitra) {
            return $order->mitra_id === $user->mitra->id;
        }

        return false;
    }

    /**
     * Determine if the user can create orders.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, [
            'manajemen',
            'customer_care'
        ]);
    }

    /**
     * Determine if the user can update the order.
     */
    public function update(User $user, Order $order): bool
    {
        // Only internal staff can update orders
        return in_array($user->role, [
            'manajemen',
            'customer_care'
        ]);
    }

    /**
     * Determine if the user can delete the order.
     */
    public function delete(User $user, Order $order): bool
    {
        // Only management can delete orders
        return $user->role === 'manajemen';
    }

    /**
     * Determine if the user can confirm the order.
     */
    public function confirm(User $user, Order $order): bool
    {
        return in_array($user->role, [
            'manajemen',
            'customer_care'
        ]);
    }

    /**
     * Determine if the user can cancel the order.
     */
    public function cancel(User $user, Order $order): bool
    {
        // Internal staff can cancel
        if (in_array($user->role, ['manajemen', 'customer_care'])) {
            return true;
        }

        // Klien can cancel own orders (before confirmed)
        if ($user->role === 'klien' && $user->klien && $order->status === 'pending') {
            return $order->klien_id === $user->klien->id;
        }

        return false;
    }

    /**
     * Determine if the user can assign mitra to order.
     */
    public function assignMitra(User $user, Order $order): bool
    {
        return in_array($user->role, [
            'manajemen',
            'customer_care'
        ]);
    }

    /**
     * Determine if the user can complete the order.
     */
    public function complete(User $user, Order $order): bool
    {
        return in_array($user->role, [
            'manajemen',
            'customer_care'
        ]);
    }

    /**
     * Determine if the user can submit feedback for order.
     */
    public function submitFeedback(User $user, Order $order): bool
    {
        // Only klien who owns the order can submit feedback
        if ($user->role === 'klien' && $user->klien) {
            return $order->klien_id === $user->klien->id && $order->status === 'completed';
        }

        return false;
    }
}
