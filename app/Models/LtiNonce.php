<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class LtiNonce extends Model {
    protected $table = 'lti_nonces';
    protected $guarded = [];
    protected $casts = ['expira' => 'datetime'];
}
