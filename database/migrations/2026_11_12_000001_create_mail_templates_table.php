<?php

use App\Support\MailTemplates;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_templates', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('subject');
            $table->text('body');
            $table->timestamps();
        });

        MailTemplates::sync();
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_templates');
    }
};
