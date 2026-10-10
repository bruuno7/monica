<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('theme')->default(User::THEME_SYSTEM)->after('contact_sort_order');
            $table->string('accent_color')->default(User::ACCENT_COLOR_DEFAULT)->after('theme');
            $table->boolean('oled_dark')->default(false)->after('accent_color');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['theme', 'accent_color', 'oled_dark']);
        });
    }
};
