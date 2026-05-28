<?php
declare(strict_types=1);

use Silver\Orm\Schema\Blueprint;
use Silver\Orm\Schema\Migration;
use Silver\Orm\Schema\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('events', function (Blueprint $t): void {
            $t->id();
            $t->string('type');
            $t->text('payload');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
