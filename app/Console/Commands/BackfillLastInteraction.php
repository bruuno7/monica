<?php

namespace App\Console\Commands;

use App\Models\Contact;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Compute the date of the last interaction of every contact from the data that
 * already exists (notes, calls, journal posts, life events). Safe to run again.
 */
class BackfillLastInteraction extends Command
{
    protected $signature = 'monica:backfill-last-interaction
                            {--force : Recompute even when the contact already has a date.}';

    protected $description = 'Compute the last interaction date of contacts from their notes, calls, posts and life events';

    public function handle(): int
    {
        $query = Contact::query();

        if (! $this->option('force')) {
            $query->whereNull('last_interaction_at');
        }

        $count = 0;

        $query->chunkById(200, function ($contacts) use (&$count) {
            foreach ($contacts as $contact) {
                $latest = $this->latestInteraction($contact);

                if ($latest !== null) {
                    $contact->last_interaction_at = $latest;
                    $contact->saveQuietly();
                    $count++;
                }
            }
        });

        $this->info("{$count} contact(s) updated.");

        return self::SUCCESS;
    }

    private function latestInteraction(Contact $contact): ?Carbon
    {
        $dates = collect([
            $contact->notes()->max('created_at'),
            $contact->calls()->max('called_at'),
            $contact->posts()->max('written_at'),
            $contact->lifeEvents()->max('life_events.happened_at'),
        ])
            ->filter()
            ->map(fn ($date) => Carbon::parse($date))
            ->filter(fn (Carbon $date) => ! $date->isFuture());

        return $dates->isEmpty() ? null : $dates->max();
    }
}
