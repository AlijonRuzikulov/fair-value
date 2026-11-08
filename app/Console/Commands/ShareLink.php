<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\ExecutableFinder;
use Throwable;

/**
 * Opens the system running on this computer to the internet for a while,
 * through a Cloudflare quick tunnel: a https://….trycloudflare.com link to
 * send to other people. It works while this command and the system run;
 * closing the window closes the link, and the next time the link is new.
 *
 * Refused while an account still signs in with ADMIN_PASSWORD from .env:
 * that password is printed in the instructions (and shown on the sign-in
 * page of a trial copy), so anyone with the link could sign in.
 */
class ShareLink extends Command
{
    protected $signature = 'share {--port=8000 : Port the system runs on (php artisan serve)}';

    protected $description = 'Open the system on this computer to the internet with a temporary link';

    /** Where cloudflared is downloaded from when it is not on the computer. */
    private const DOWNLOADS = [
        'Windows' => 'https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe',
        'Linux' => 'https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64',
    ];

    public function handle(): int
    {
        $port = (int) $this->option('port');

        if ($this->initialPasswordInUse()) {
            $this->error(__('An account still signs in with the first password from the instructions. Change it before giving the link to anyone: your name at the top right → My profile.'));

            return self::FAILURE;
        }

        if (! $this->running($port)) {
            $this->error(__('The system is not running. Start it first (ISHGA-TUSHIRISH.bat), then open the link.'));

            return self::FAILURE;
        }

        $binary = $this->cloudflared();
        if ($binary === null) {
            return self::FAILURE;
        }

        $this->line(__('Opening the link…'));
        $process = proc_open([$binary, 'tunnel', '--no-autoupdate', '--url', "http://127.0.0.1:{$port}"], [1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes);
        if (! is_resource($process)) {
            $this->error(__('The link could not be opened.'));

            return self::FAILURE;
        }

        $link = null;
        $last = [];
        while (($line = fgets($pipes[1])) !== false) {
            $last = array_slice([...$last, rtrim($line)], -5);
            if ($link === null && preg_match('~https://[a-z0-9-]+\.trycloudflare\.com~', $line, $m)) {
                $link = $m[0];
                $this->announce($link);
            }
        }
        fclose($pipes[1]);
        proc_close($process);

        if ($link === null) {
            $this->error(__('The link could not be opened.'));
            foreach ($last as $text) {
                $this->line($text);
            }

            return self::FAILURE;
        }
        $this->line(__('The link is closed.'));

        return self::SUCCESS;
    }

    private function initialPasswordInUse(): bool
    {
        $initial = (string) config('fairvalue.admin.password');

        return $initial !== '' && User::where('is_active', true)->get(['id', 'password'])
            ->contains(fn (User $user) => Hash::check($initial, $user->password));
    }

    private function running(int $port): bool
    {
        try {
            return Http::timeout(5)->get("http://127.0.0.1:{$port}/up")->successful();
        } catch (Throwable) {
            return false;
        }
    }

    /** The cloudflared program: set in .env, beside the system, installed, or downloaded once into the system's folder. */
    private function cloudflared(): ?string
    {
        $windows = PHP_OS_FAMILY === 'Windows';
        $local = base_path($windows ? 'cloudflared.exe' : 'cloudflared');
        foreach ([config('fairvalue.share.cloudflared'), $local, (new ExecutableFinder)->find('cloudflared')] as $path) {
            if (is_string($path) && $path !== '' && is_file($path)) {
                return $path;
            }
        }

        $url = self::DOWNLOADS[PHP_OS_FAMILY] ?? null;
        if ($url === null) {
            $this->error(__('Install cloudflared (developers.cloudflare.com) and run this again.'));

            return null;
        }

        $this->line(__('Downloading cloudflared, the program that opens the link (once, about 55 MB)…'));
        try {
            Http::timeout(600)->withOptions(['sink' => $local])->get($url)->throw();
        } catch (Throwable) {
            @unlink($local);
            $this->error(__('cloudflared could not be downloaded. Check the internet connection and try again.'));

            return null;
        }
        if (! $windows) {
            chmod($local, 0755);
        }

        return $local;
    }

    private function announce(string $link): void
    {
        $this->newLine();
        $this->line('  ============================================================');
        $this->line('  '.__('The link to send:'));
        $this->info('  '.$link);
        $this->line('  ============================================================');

        // On Windows the link is put on the clipboard, ready to paste into a message.
        if (PHP_OS_FAMILY === 'Windows' && is_resource($clip = @proc_open('clip', [0 => ['pipe', 'r']], $pipes))) {
            fwrite($pipes[0], $link);
            fclose($pipes[0]);
            proc_close($clip);
            $this->line('  '.__('It is copied: paste it into Telegram or another messenger.'));
        }
        $this->line('  '.__('It works while this window and the system are open. Each time the link is new.'));
        $this->newLine();
    }
}
