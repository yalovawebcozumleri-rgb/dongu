<?php

use App\Models\AdvertisementImpression;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('user:make-admin {email}', function (string $email) {
    $user = User::where('email', mb_strtolower(trim($email)))->first();

    if (! $user) {
        $this->error('Bu e-posta adresiyle kayıtlı kullanıcı bulunamadı.');

        return self::FAILURE;
    }

    $user->update(['role' => User::ROLE_ADMIN]);
    $this->info("{$user->email} artık yönetici.");

    return self::SUCCESS;
})->purpose('Kayıtlı bir kullanıcıya yönetici rolü verir');

Schedule::command('announcements:dispatch-due')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('listings:close-expired-conversations')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

Schedule::command('listings:prune')
    ->dailyAt('03:30')
    ->withoutOverlapping();

Schedule::command('messaging:prune')
    ->dailyAt('04:10')
    ->withoutOverlapping();

Schedule::command('notifications:prune')
    ->dailyAt('04:15')
    ->withoutOverlapping();

Schedule::command('queue:prune-batches --hours=168 --unfinished=168 --cancelled=168')
    ->dailyAt('04:20')
    ->withoutOverlapping();

Schedule::command('queue:prune-failed --hours=720')
    ->dailyAt('04:30')
    ->withoutOverlapping();

Schedule::call(function () {
    AdvertisementImpression::query()
        ->where('viewed_at', '<', now()->subDays(config('advertising.impression_retention_days')))
        ->delete();
})->name('advertisement-impressions-prune')
    ->dailyAt('04:40')
    ->withoutOverlapping();
