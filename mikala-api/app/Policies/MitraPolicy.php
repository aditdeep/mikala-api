<?php

namespace App\Policies;

use App\Models\Mitra;
use App\Models\User;

class MitraPolicy
{
    /**
     * Determine if the user can view any mitra.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [
            'manajemen',
            'rekrutmen',
            'training_center',
            'customer_care',
            'finance'
        ]);
    }

    /**
     * Determine if the user can view the mitra.
     */
    public function view(User $user, Mitra $mitra): bool
    {
        // Mitra can view own profile
        if ($user->role === 'mitra') {
            return $user->id === $mitra->user_id;
        }

        // Internal staff can view any mitra
        return in_array($user->role, [
            'manajemen',
            'rekrutmen',
            'training_center',
            'customer_care',
            'finance'
        ]);
    }

    /**
     * Determine if the user can create mitra.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, [
            'manajemen',
            'rekrutmen'
        ]);
    }

    /**
     * Determine if the user can update the mitra.
     */
    public function update(User $user, Mitra $mitra): bool
    {
        // Mitra can only update own profile (limited fields)
        if ($user->role === 'mitra') {
            return $user->id === $mitra->user_id;
        }

        // Specific roles can update mitra data
        return in_array($user->role, [
            'manajemen',
            'rekrutmen',
            'training_center'
        ]);
    }

    /**
     * Determine if the user can delete the mitra.
     */
    public function delete(User $user, Mitra $mitra): bool
    {
        return in_array($user->role, [
            'manajemen',
            'rekrutmen'
        ]);
    }

    /**
     * Determine if the user can manage training for mitra.
     */
    public function manageTraining(User $user, Mitra $mitra): bool
    {
        return in_array($user->role, [
            'manajemen',
            'training_center'
        ]);
    }

    /**
     * Determine if the user can assign mitra to jobs.
     */
    public function assignJobs(User $user): bool
    {
        return in_array($user->role, [
            'manajemen',
            'customer_care'
        ]);
    }

    /**
     * Determine if the user can manage payroll for mitra.
     */
    public function managePayroll(User $user): bool
    {
        return in_array($user->role, [
            'manajemen',
            'finance'
        ]);
    }
}
