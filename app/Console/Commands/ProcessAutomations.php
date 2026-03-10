<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AutomationRule;
use App\Models\User;
use App\Models\Booking;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ProcessAutomations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'automations:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process marketing automation rules and send scheduled messages';

    protected $whatsappService;

    public function __construct(WhatsAppService $whatsappService)
    {
        parent::__construct();
        $this->whatsappService = $whatsappService;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        Log::info('Starting Marketing Automations Processor...');

        $rules = AutomationRule::where('is_active', true)->with('branches')->get();

        foreach ($rules as $rule) {
            $this->processRule($rule);
        }

        Log::info('Finished Marketing Automations Processor.');
    }

    private function processRule(AutomationRule $rule)
    {
        $today = Carbon::today();
        $targetDate = $today->copy()->subDays($rule->days_offset);

        $branchIds = $rule->is_global ? [] : $rule->branches->pluck('id')->toArray();

        $query = User::where('role', 'customer');

        if (!empty($branchIds)) {
            // If branch specific, we might filter customers based on their bookings in these branches
            $query->whereHas('bookings', function ($q) use ($branchIds) {
                $q->whereIn('branch_id', $branchIds);
            });
        }

        $targetUsers = collect();

        switch ($rule->trigger) {
            case 'birthday':
                // Birthday is targetDate, we only compare day and month
                // days_offset = 0 means today is birthday. days_offset = -3 means birthday is 3 days from now. 
                // Alternatively, usually days_offset in birthdays is used as "send X days before" or "after".
                // Let's assume days_offset means X days AFTER birthday. If negative, it means before.
                // It's simpler to just calculate the birtday date we're looking for: TargetDate = Today - DaysOffset.
                // Wait, if I want to send 3 days BEFORE birthday, is DaysOffset negative?
                // Typically days_offset > 0 means past, < 0 means future. We'll use subDays($days_offset).
                $targetMonth = $targetDate->month;
                $targetDay = $targetDate->day;

                $targetUsers = (clone $query)->whereMonth('birth_date', $targetMonth)
                    ->whereDay('birth_date', $targetDay)
                    ->get();
                break;

            case 'winback':
                // Has not visited for $days_offset days.
                // Their last booking must be exactly on $targetDate. And no bookings after that.
                // OR we just find users whose *latest* booking was exactly $targetDate.
                $targetUsers = (clone $query)->whereHas('bookings', function ($q) use ($targetDate) {
                    $q->whereDate('booking_date', $targetDate->format('Y-m-d'));
                })->whereDoesntHave('bookings', function ($q) use ($targetDate) {
                    $q->whereDate('booking_date', '>', $targetDate->format('Y-m-d'));
                })->get();
                break;

            case 'post_visit':
                // Exactly $days_offset days after their visit
                // Simply find bookings on $targetDate that were completed
                $targetUsers = (clone $query)->whereHas('bookings', function ($q) use ($targetDate) {
                    $q->whereDate('booking_date', $targetDate->format('Y-m-d'))
                        ->whereIn('status', ['completed', 'paid']);
                })->get();
                break;

            case 'first_visit':
                // Exactly $days_offset days after their FIRST visit.
                // The user must have exactly 1 booking, and it's on $targetDate.
                $targetUsers = (clone $query)->has('bookings', '=', 1)
                    ->whereHas('bookings', function ($q) use ($targetDate) {
                        $q->whereDate('booking_date', $targetDate->format('Y-m-d'))
                            ->whereIn('status', ['completed', 'paid']);
                    })->get();
                break;

            case 'anniversary':
                // Account created exactly X years + days_offset ago
                // Month and Day match, Year doesn't matter (as long as it's not this year)
                $targetMonth = $targetDate->month;
                $targetDay = $targetDate->day;

                $targetUsers = (clone $query)->whereYear('created_at', '<', $today->year)
                    ->whereMonth('created_at', $targetMonth)
                    ->whereDay('created_at', $targetDay)
                    ->get();
                break;
        }

        foreach ($targetUsers as $user) {
            $this->sendMessage($rule, $user);
        }
    }

    private function sendMessage(AutomationRule $rule, User $user)
    {
        if (empty($user->phone)) {
            return;
        }

        $message = $rule->message;

        // Replace placeholders
        $message = str_replace(['{name}', '[Nama_Pelanggan]'], $user->name, $message);
        $message = str_replace(['{discount_code}', '[Kode_Promo]'], $rule->discount_code ?? '-', $message);

        // Send via Whatsapp Service using generic text method since automation rules have custom body
        if ($rule->channel === 'whatsapp') {
            $sent = $this->whatsappService->sendMessage($user->phone, $message);

            if ($sent) {
                // Increment sent count and update last_triggered
                $rule->incrementSent();
                Log::info("Automation rule '{$rule->name}' sent to {$user->phone}");
            }
        } else {
            // Other channels not fully implemented
            Log::info("Automation rule '{$rule->name}' channel '{$rule->channel}' to {$user->phone} is ignored for now.");
        }
    }
}
