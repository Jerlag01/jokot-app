<?php

namespace App\Models;

use App\Support\MailTemplates;
use Illuminate\Database\Eloquent\Model;

/**
 * The editable subject and text of one of the website's mails. See App\Support\MailTemplates for the defaults.
 */
class MailTemplate extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'subject', 'body'];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return MailTemplates::definition($this->key);
    }
}
