<?php

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
        Schema::table('contacts', function (Blueprint $table) {
            // when something last happened with this contact: a note, a call, a journal post, a life event…
            $table->timestamp('last_interaction_at')->nullable()->after('last_updated_at');
            // how often the user wants to be in touch with this contact, in days (null: no goal)
            $table->unsignedSmallInteger('stay_in_touch_frequency')->nullable()->after('last_interaction_at');
            // when the user was last told that this contact was going cold
            $table->timestamp('stay_in_touch_triggered_at')->nullable()->after('stay_in_touch_frequency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['last_interaction_at', 'stay_in_touch_frequency', 'stay_in_touch_triggered_at']);
        });
    }
};
