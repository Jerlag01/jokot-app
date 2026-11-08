<?php

namespace App\Filament\Resources\MailTemplates\Pages;

use App\Filament\Resources\MailTemplates\MailTemplateResource;
use Filament\Resources\Pages\EditRecord;

class EditMailTemplate extends EditRecord
{
    protected static string $resource = MailTemplateResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->definition()['label'];
    }

    protected function getHeaderActions(): array
    {
        return [
            MailTemplateResource::previewAction(fn (): array => [
                'subject' => (string) ($this->data['subject'] ?? ''),
                'body' => (string) ($this->data['body'] ?? ''),
            ]),
            MailTemplateResource::resetAction(),
        ];
    }
}
