<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_level', function (Blueprint $col) {
            $col->id();
            $col->foreignId('menu_id')->constrained()->onDelete('cascade');
            $col->foreignId('level_id')->constrained()->onDelete('cascade');
            $col->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_level');
    }
};