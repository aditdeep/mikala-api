<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Kerjasama extends Model
{
    use SoftDeletes;

    protected $table = 'kerjasama';

    protected $fillable = [
        'partner_name',
        'partner_type',
        'contact_person',
        'phone',
        'email',
        'notes',
        'status',
    ];
}
