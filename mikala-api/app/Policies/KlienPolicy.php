<?php

namespace App\Policies;

use App\Models\Klien;
use App\Models\User;

class KlienPolicy
{
    /**
     * Determine if the user can view any klien.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [
            'manajemen',
            'customer_care',
            'finance',
            'marketing'
        ]);
    }

    /**
     * Determine if the user can view the klien.
     */
    public function view(User $user, Klien $klien): bool
    {
        // Klien can view own profile
        if ($user->role === 'klien') {
            return $user->id === $klien->user_id;
        }

        // Internal staff can view any klien
        return in_array($user->role, [
            'manajemen',
            'customer_care',
            'finance',
            'marketing'
        ]);
    }

    /**
     * Determine if the user can create klien.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, [
            'manajemen',
            'customer_care',
            'marketing'
        ]);
    }

    /**
     * Determine if the user can update the klien.
     */
    public function update(User $user, Klien $klien): bool
    {
        // Klien can only update own profile (limited fields)
        if ($user->role === 'klien') {
            return $user->id === $klien->user_id;
        }

        // Internal staff can update klien data
        return in_array($user->role, [
            'manajemen',
            'customer_care'
        ]);
    }

    /**
     * Determine if the user can delete the klien.
     */
    public function delete(User $user, Klien $klien): bool
    {
        return $user->role === 'manajemen';
    }

    /**
     * Determine if the user can manage billing for klien.
     */
    public function manageBilling(User $user): bool
    {
        return in_array($user->role, [
            'manajemen',
            'finance'
        ]);
    }

    /**
     * Determine if the user can verify klien.
     */
    public function verify(User $user, Klien $klien): bool
    {
        return in_array($user->role, [
            'manajemen',
            'customer_care'
        ]);
    }

    /**
     * Determine if the user can suspend klien.
     */
    public function suspend(User $user, Klien $klien): bool
    {
        return in_array($user->role, [
            'manajemen',
            'customer_care'
        ]);
    }
}
