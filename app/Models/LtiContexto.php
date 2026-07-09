<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LtiContexto extends Model
{
    protected $table = 'lti_contextos';

    protected $guarded = [];

    public function plataforma()
    {
        return $this->belongsTo(LtiPlatform::class, 'lti_platform_id');
    }
}
