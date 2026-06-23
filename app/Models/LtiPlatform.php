<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class LtiPlatform extends Model {
    protected $table = 'lti_platforms';
    protected $guarded = [];
    protected $casts = ['activo' => 'boolean'];
}
