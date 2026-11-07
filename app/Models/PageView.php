<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One page view on the public site (see App\Support\Stats\VisitorTracker; no IP address is stored).
 */
class PageView extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['visitor', 'path', 'source', 'source_name', 'referrer_host', 'country_code', 'device'];
}
