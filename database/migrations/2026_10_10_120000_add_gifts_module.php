<?php

use App\Models\GiftOccasion;
use App\Models\GiftState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('gifts', function (Blueprint $table) {
            $table->foreignIdFor(GiftOccasion::class)->nullable()->after('type')->constrained()->nullOnDelete();
            $table->foreignIdFor(GiftState::class)->nullable()->after('gift_occasion_id')->constrained()->nullOnDelete();
            $table->string('url')->nullable()->after('description');
        });

        // The gifts module did not exist: give it to every account, on the page
        // where loans live (the information page), after the existing modules.
        $now = now();
        $accounts = DB::table('accounts')->pluck('id');

        foreach ($accounts as $accountId) {
            $exists = DB::table('modules')->where('account_id', $accountId)->where('type', 'gifts')->exists();
            if ($exists) {
                continue;
            }

            $moduleId = DB::table('modules')->insertGetId([
                'account_id' => $accountId,
                'name_translation_key' => 'Gifts',
                'type' => 'gifts',
                'reserved_to_contact_information' => false,
                'can_be_deleted' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $pages = DB::table('module_template_page')
                ->join('modules', 'modules.id', '=', 'module_template_page.module_id')
                ->where('modules.account_id', $accountId)
                ->where('modules.type', 'loans')
                ->pluck('module_template_page.template_page_id');

            foreach ($pages as $pageId) {
                $position = (int) DB::table('module_template_page')->where('template_page_id', $pageId)->max('position');
                DB::table('module_template_page')->insert([
                    'template_page_id' => $pageId,
                    'module_id' => $moduleId,
                    'position' => $position + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $moduleIds = DB::table('modules')->where('type', 'gifts')->pluck('id');
        DB::table('module_template_page')->whereIn('module_id', $moduleIds)->delete();
        DB::table('modules')->whereIn('id', $moduleIds)->delete();

        Schema::table('gifts', function (Blueprint $table) {
            $table->dropConstrainedForeignIdFor(GiftOccasion::class);
            $table->dropConstrainedForeignIdFor(GiftState::class);
            $table->dropColumn('url');
        });
    }
};
