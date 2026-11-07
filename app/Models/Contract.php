<?php

namespace App\Models;

use App\Contracts\HasContract;
use App\Enums\ContractStatus;
use App\Enums\ContractType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * A contract for a rental or cup loan. Until it is signed the text follows the template and the record;
 * signing freezes the text ($body) and stores the signed PDF.
 */
class Contract extends Model
{
    protected $fillable = ['type', 'status', 'body', 'sent_at', 'signed_at', 'signer_name', 'signer_ip', 'signature_path', 'pdf_path'];

    protected function casts(): array
    {
        return [
            'type' => ContractType::class,
            'status' => ContractStatus::class,
            'sent_at' => 'datetime',
            'signed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Contract $contract): void {
            $contract->token ??= Str::random(48);
            $contract->status ??= ContractStatus::Draft;
        });
    }

    /**
     * @return MorphTo<Model&HasContract, $this>
     */
    public function contractable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isSigned(): bool
    {
        return $this->status === ContractStatus::Signed;
    }

    public function signUrl(): string
    {
        return route('contracts.show', $this->token);
    }

    /**
     * The contract text: frozen once signed, otherwise rendered from the current template and record.
     */
    public function renderedBody(): string
    {
        if ($this->isSigned() && filled($this->body)) {
            return $this->body;
        }

        $template = ContractTemplate::for($this->type);
        $values = $this->contractable->contractPlaceholders();

        return preg_replace_callback('/\{([a-z_]+)\}/', fn (array $match): string => $values[$match[1]] ?? $match[0], $template->body);
    }

    public function title(): string
    {
        return ContractTemplate::query()->where('type', $this->type)->value('title') ?? $this->type->getLabel();
    }
}
