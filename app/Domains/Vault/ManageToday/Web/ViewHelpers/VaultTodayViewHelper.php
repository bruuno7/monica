<?php

namespace App\Domains\Vault\ManageToday\Web\ViewHelpers;

use App\Domains\Vault\ManageVault\Web\ViewHelpers\VaultShowViewHelper;
use App\Helpers\DateHelper;
use App\Helpers\MarkdownHelper;
use App\Helpers\StayInTouchHelper;
use App\Models\Contact;
use App\Models\ContactImportantDate;
use App\Models\LifeEvent;
use App\Models\Note;
use App\Models\Post;
use App\Models\User;
use App\Models\Vault;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Everything the "Today" page needs: a daily ritual of two minutes to remember
 * the people who matter.
 */
class VaultTodayViewHelper
{
    public static function data(Vault $vault, User $user): array
    {
        $now = Carbon::now($user->timezone ?? 'UTC');

        return [
            'greeting' => self::greeting($now, $user),
            'date' => $now->isoFormat('dddd, LL'),
            'prompt' => self::prompt($now, $vault, $user),
            'important_dates' => self::importantDates($vault, $now),
            'reminders' => VaultShowViewHelper::upcomingReminders($vault, $user),
            'due_tasks' => VaultShowViewHelper::dueTasks($vault, $user),
            'reach_out' => self::reachOut($vault, $user),
            'memories' => self::memories($vault, $user, $now),
            'activity' => self::activity($vault, $user, $now),
            'mood' => VaultShowViewHelper::moodTrackingEvents($vault, $user),
            'favorites' => VaultShowViewHelper::favorites($vault, $user),
            'url' => [
                'dashboard' => route('vault.show', ['vault' => $vault->id]),
                'contacts' => route('contact.index', ['vault' => $vault->id]),
            ],
        ];
    }

    public static function greeting(Carbon $now, User $user): string
    {
        $name = $user->first_name;
        $hour = $now->hour;

        return match (true) {
            $hour < 5 => trans('Still up, :name?', ['name' => $name]),
            $hour < 12 => trans('Good morning, :name', ['name' => $name]),
            $hour < 18 => trans('Good afternoon, :name', ['name' => $name]),
            default => trans('Good evening, :name', ['name' => $name]),
        };
    }

    /**
     * A writing prompt that changes every day, plus a personal one when someone
     * has not been written about for a while.
     */
    public static function prompt(Carbon $now, Vault $vault, User $user): array
    {
        $prompts = [
            trans('Who made you smile this week? Write down why.'),
            trans('What did you learn about someone recently?'),
            trans('Who should you thank? Note what for, then tell them.'),
            trans('Which conversation keeps coming back to your mind?'),
            trans('Is anyone going through a hard time right now? How could you help?'),
            trans('What is a small detail about a friend you never want to forget?'),
            trans('Who have you not seen in a long time? What would you ask them?'),
            trans('What did someone say that changed your perspective?'),
            trans('Which friendship deserves more of your time this month?'),
            trans('What is something you admire in a person close to you?'),
            trans('Who did you meet recently? What was your first impression?'),
            trans('Which shared memory makes you laugh every time?'),
            trans('What is a goal a friend is working towards? How can you cheer them on?'),
            trans('Who would you call if you had great news right now?'),
        ];

        $cold = StayInTouchHelper::dueContacts((string) $vault->id, 1)->first();

        if ($cold !== null) {
            $days = StayInTouchHelper::daysSinceLastInteraction($cold) ?? 0;

            return [
                'type' => 'contact',
                'text' => trans('It has been :count days since anything happened with :name. What is new in their life?', [
                    'count' => $days,
                    'name' => $cold->name,
                ]),
                'contact' => self::dtoContact($cold),
            ];
        }

        return [
            'type' => 'daily',
            'text' => $prompts[$now->dayOfYear % count($prompts)],
            'contact' => null,
        ];
    }

    /**
     * Important dates happening today and in the coming week.
     */
    public static function importantDates(Vault $vault, Carbon $now): Collection
    {
        $today = $now->copy()->startOfDay();

        return ContactImportantDate::whereHas('contact', fn ($query) => $query->where('vault_id', $vault->id)->where('listed', true))
            ->with(['contact', 'contactImportantDateType'])
            ->whereNotNull('day')
            ->whereNotNull('month')
            ->get()
            ->map(function (ContactImportantDate $date) use ($today) {
                $next = Carbon::createFromDate($today->year, $date->month, $date->day, $today->timezone)->startOfDay();
                if ($next->lessThan($today)) {
                    $next->addYear();
                }
                $inDays = (int) $today->diffInDays($next);

                return [
                    'id' => $date->id,
                    'label' => $date->label,
                    'type' => $date->contactImportantDateType?->label,
                    'in_days' => $inDays,
                    'age' => $date->year ? $next->year - $date->year : null,
                    'date' => DateHelper::formatMonthAndDay($next),
                    'contact' => self::dtoContact($date->contact),
                ];
            })
            ->filter(fn (array $date) => $date['in_days'] <= 7)
            ->sortBy('in_days')
            ->values();
    }

    /**
     * People the user wanted to stay in touch with, who went quiet.
     */
    public static function reachOut(Vault $vault, User $user): Collection
    {
        return StayInTouchHelper::dueContacts((string) $vault->id, 5)
            ->map(fn (Contact $contact) => self::dtoContact($contact) + [
                'days_since_last_interaction' => StayInTouchHelper::daysSinceLastInteraction($contact),
                'status' => StayInTouchHelper::status($contact),
                'status_label' => StayInTouchHelper::statusLabel(StayInTouchHelper::status($contact)),
                'url' => [
                    'show' => route('contact.show', ['vault' => $vault->id, 'contact' => $contact->id]),
                    'touch' => route('contact.stay_in_touch.touch', ['vault' => $vault->id, 'contact' => $contact->id]),
                ],
            ]);
    }

    /**
     * "On this day": posts, life events and notes from previous years on the same date.
     */
    public static function memories(Vault $vault, User $user, Carbon $now): Collection
    {
        $month = $now->month;
        $day = $now->day;
        $memories = collect();

        Post::whereHas('journal', fn ($query) => $query->where('vault_id', $vault->id))
            ->with('journal')
            ->whereNotNull('written_at')
            ->get()
            ->filter(fn (Post $post) => $post->written_at->month === $month && $post->written_at->day === $day && $post->written_at->year < $now->year)
            ->each(fn (Post $post) => $memories->push([
                'type' => 'post',
                'years_ago' => $now->year - $post->written_at->year,
                'year' => $post->written_at->year,
                'title' => $post->title,
                'excerpt' => $post->journal->name,
                'contact' => null,
                'url' => route('post.show', ['vault' => $vault->id, 'journal' => $post->journal_id, 'post' => $post->id]),
            ]));

        LifeEvent::whereHas('timelineEvent', fn ($query) => $query->where('vault_id', $vault->id))
            ->with(['lifeEventType', 'participants'])
            ->get()
            ->filter(fn (LifeEvent $event) => $event->happened_at->month === $month && $event->happened_at->day === $day && $event->happened_at->year < $now->year)
            ->each(fn (LifeEvent $event) => $memories->push([
                'type' => 'life_event',
                'years_ago' => $now->year - $event->happened_at->year,
                'year' => $event->happened_at->year,
                'title' => $event->summary ?? $event->lifeEventType?->label,
                'excerpt' => $event->participants->pluck('name')->join(', '),
                'contact' => $event->participants->first() ? self::dtoContact($event->participants->first()) : null,
                'url' => $event->participants->first()
                    ? route('contact.show', ['vault' => $vault->id, 'contact' => $event->participants->first()->id])
                    : null,
            ]));

        Note::where('vault_id', $vault->id)
            ->with('contact')
            ->get()
            ->filter(fn (Note $note) => $note->contact !== null && $note->created_at->month === $month && $note->created_at->day === $day && $note->created_at->year < $now->year)
            ->each(fn (Note $note) => $memories->push([
                'type' => 'note',
                'years_ago' => $now->year - $note->created_at->year,
                'year' => $note->created_at->year,
                'title' => $note->title ?? trans('A note about :name', ['name' => $note->contact->name]),
                'excerpt' => MarkdownHelper::excerpt($note->body, 160) ?? MarkdownHelper::toPlainText($note->body),
                'contact' => self::dtoContact($note->contact),
                'url' => route('contact.show', ['vault' => $vault->id, 'contact' => $note->contact_id]),
            ]));

        return $memories->sortBy('years_ago')->values();
    }

    /**
     * Days with activity over the last year (notes, calls, posts, life events,
     * mood check-ins), plus the writing streak.
     */
    public static function activity(Vault $vault, User $user, Carbon $now): array
    {
        $timezone = $user->timezone ?? 'UTC';
        $today = $now->copy()->startOfDay();
        $start = $today->copy()->subDays(364);

        $dates = collect();
        $toDay = fn ($value) => Carbon::parse($value)->setTimezone($timezone)->format('Y-m-d');

        Note::where('vault_id', $vault->id)->where('created_at', '>=', $start)->pluck('created_at')->each(fn ($d) => $dates->push($toDay($d)));
        DB::table('calls')
            ->join('contacts', 'contacts.id', '=', 'calls.contact_id')
            ->where('contacts.vault_id', $vault->id)
            ->where('calls.called_at', '>=', $start)
            ->pluck('calls.called_at')->each(fn ($d) => $dates->push($toDay($d)));
        Post::whereHas('journal', fn ($query) => $query->where('vault_id', $vault->id))
            ->whereNotNull('written_at')->where('written_at', '>=', $start)
            ->pluck('written_at')->each(fn ($d) => $dates->push($toDay($d)));
        DB::table('life_events')
            ->join('timeline_events', 'timeline_events.id', '=', 'life_events.timeline_event_id')
            ->where('timeline_events.vault_id', $vault->id)
            ->where('life_events.created_at', '>=', $start)
            ->pluck('life_events.created_at')->each(fn ($d) => $dates->push($toDay($d)));
        DB::table('mood_tracking_events')
            ->join('contacts', 'contacts.id', '=', 'mood_tracking_events.contact_id')
            ->where('contacts.vault_id', $vault->id)
            ->where('mood_tracking_events.rated_at', '>=', $start)
            ->pluck('mood_tracking_events.rated_at')->each(fn ($d) => $dates->push($toDay($d)));

        $counts = $dates->countBy();

        $days = collect();
        for ($cursor = $start->copy(); $cursor->lessThanOrEqualTo($today); $cursor->addDay()) {
            $key = $cursor->format('Y-m-d');
            $days->push(['date' => $key, 'count' => $counts[$key] ?? 0]);
        }

        // current streak: consecutive days with activity ending today or yesterday
        $current = 0;
        $cursor = $today->copy();
        if (($counts[$cursor->format('Y-m-d')] ?? 0) === 0) {
            $cursor->subDay();
        }
        while (($counts[$cursor->format('Y-m-d')] ?? 0) > 0) {
            $current++;
            $cursor->subDay();
        }

        // best streak within the window
        $best = 0;
        $run = 0;
        foreach ($days as $day) {
            $run = $day['count'] > 0 ? $run + 1 : 0;
            $best = max($best, $run);
        }

        return [
            'days' => $days,
            'active_days' => $counts->count(),
            'total' => $dates->count(),
            'streak' => [
                'current' => $current,
                'best' => $best,
            ],
            'start_weekday' => (int) $start->dayOfWeekIso,
        ];
    }

    private static function dtoContact(Contact $contact): array
    {
        return [
            'id' => $contact->id,
            'name' => $contact->name,
            'avatar' => $contact->avatar,
            'url' => route('contact.show', ['vault' => $contact->vault_id, 'contact' => $contact->id]),
        ];
    }
}
