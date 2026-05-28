<?php
declare(strict_types=1);

use Silver\Orm\Schema\Blueprint;
use Silver\Orm\Schema\Migration;
use Silver\Orm\Schema\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $t): void {
            $t->id();
            $t->string('email')->unique();
            $t->string('name');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
