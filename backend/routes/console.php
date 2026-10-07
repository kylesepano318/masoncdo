<?php

use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

Artisan::command('lodge:homepage', function () {
    $sections = json_decode(file_get_contents(database_path('content/home.json')), true, flags: JSON_THROW_ON_ERROR);
    DB::transaction(function () use ($sections) {
        $page = Page::where('slug', 'home')->lockForUpdate()->firstOrFail();
        $page->sections()->delete();
        foreach ($sections as $i => $section) {
            $page->sections()->create(['section_type' => $section[0], 'title' => $section[1], 'subtitle' => $section[2], 'body' => $section[3], 'image' => $section[4], 'settings' => $section[5], 'display_order' => $i, 'is_visible' => true]);
        }
        $page->update(['published_sections' => $page->sections()->get()->toArray(), 'is_published' => true]);
    });
    $this->info('SIGLO-style lodge homepage published.');
})->purpose('Replace the homepage with the editable SIGLO archive and Golden Friendship lodge template');

Artisan::command('lodge:admin {email?} {--from-env : Initialize using LODGE_ADMIN_EMAIL and LODGE_ADMIN_PASSWORD}', function () {
    if (User::where('is_admin', true)->exists()) {
        $this->info('Administrator accounts already exist. Use Login credentials or AdministratorSeeder.');

        return $this->option('from-env') ? 0 : 1;
    }
    $email = $this->option('from-env') ? getenv('LODGE_ADMIN_EMAIL') : ($this->argument('email') ?: $this->ask('Administrator email'));
    $password = $this->option('from-env') ? getenv('LODGE_ADMIN_PASSWORD') : $this->secret('Password (12+ characters, mixed case, number, symbol)');
    $validator = validator(['email' => $email, 'password' => $password], ['email' => 'required|email|unique:users', 'password' => [Password::min(12)->mixedCase()->numbers()->symbols()]]);
    if ($validator->fails()) {
        $this->error($validator->errors()->first());

        return 1;
    }
    $user = new User(['name' => 'Lodge Administrator', 'email' => $email, 'password' => $password]);
    $user->is_admin = true;
    $user->save();
    $this->info('Administrator created.');
})->purpose('Securely bootstrap an initial lodge administrator');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Shared hosting runs this bounded worker from cron instead of a persistent process.
Artisan::command('lodge:mail-queue', function () {
    if (config('queue.default') !== 'database') {
        $this->error('Set QUEUE_CONNECTION=database before running the mail queue.');

        return 1;
    }
    $lock = Cache::lock('lodge-mail-queue', 300);
    if (! $lock->get()) {
        $this->info('An earlier mail queue run is still active.');

        return 0;
    }
    try {
        return $this->call('queue:work', [
            'connection' => 'database', '--stop-when-empty' => true,
            '--max-time' => 45, '--max-jobs' => 20, '--tries' => 5,
            '--timeout' => 45, '--sleep' => 0, '--memory' => 128,
        ]);
    } finally {
        $lock->release();
    }
})->purpose('Process a bounded batch of queued emails; safe for once-per-minute cron');
