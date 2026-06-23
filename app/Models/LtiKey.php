<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class LtiKey extends Model {
    protected $table = 'lti_keys';
    protected $guarded = [];
    protected $casts = ['activo' => 'boolean'];
}
