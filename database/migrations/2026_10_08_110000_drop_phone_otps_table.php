<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/** No texts or WhatsApp messages are sent during the MVP, so there are no mobile codes to store. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('phone_otps');
    }
};
