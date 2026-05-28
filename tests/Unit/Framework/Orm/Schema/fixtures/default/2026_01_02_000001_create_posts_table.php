<?php
declare(strict_types=1);

use Silver\Orm\Schema\Blueprint;
use Silver\Orm\Schema\Migration;
use Silver\Orm\Schema\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInt('user_id');
            $t->string('title');
            $t->text('body');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
