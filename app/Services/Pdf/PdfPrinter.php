<?php

namespace App\Services\Pdf;

use App\Http\Controllers\Admin\MapTileController;
use App\Models\Analog;
use App\Models\Attachment;
use App\Reports\RealEstateReport;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Prints a document page (the report or the contract, laid out by Paged.js)
 * into a PDF file with headless Chromium on the server.
 *
 * The page is made self-contained first: stylesheets and fonts go inside it,
 * pictures are read from the storage. Chromium opens it from a temporary
 * file, so it needs no session and never calls this site (a request of the
 * site to itself would wait forever on a one-process server).
 */
class PdfPrinter
{
    /** Where Chromium is usually installed (PDF_CHROME in .env names another place). */
    private const PATHS = ['/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome', '/usr/bin/google-chrome-stable', '/snap/bin/chromium'];

    /** Seconds the pages may take to be laid out. */
    private const LAYOUT_TIMEOUT = 90;

    /** An empty picture in place of one that cannot be read. */
    private const BLANK = 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';

    public function binary(): ?string
    {
        $configured = trim((string) config('fairvalue.pdf.chrome'));

        foreach ($configured !== '' ? [$configured] : self::PATHS as $path) {
            if (is_file($path) && is_executable($path)) {
                return $path;
            }
        }

        return null;
    }

    public function available(): bool
    {
        return $this->binary() !== null;
    }

    /**
     * @param  string  $html  the page as the browser gets it
     * @return string the PDF file
     *
     * @throws RuntimeException when Chromium is missing or the pages are not laid out in time
     */
    public function print(string $html): string
    {
        $binary = $this->binary() ?? throw new RuntimeException('Chromium is not installed on the server.');
        $dir = sys_get_temp_dir().'/fv-pdf-'.Str::random(16);
        File::ensureDirectoryExists($dir);
        File::put($dir.'/document.html', $this->selfContained($html));

        try {
            $browser = (new BrowserFactory($binary))->createBrowser([
                'headless' => true,
                'noSandbox' => true,
                'userDataDir' => $dir.'/profile',
                'startupTimeout' => 30,
                'sendSyncDefaultTimeout' => 30000,
                'customFlags' => ['--disable-gpu', '--disable-dev-shm-usage', '--no-first-run', '--font-render-hinting=none'],
            ]);
            try {
                $page = $browser->createPage();
                $page->navigate(self::fileUrl($dir.'/document.html'))->waitForNavigation(Page::LOAD, 30000);

                $deadline = microtime(true) + self::LAYOUT_TIMEOUT;
                while (! $page->evaluate("document.body !== null && document.body.getAttribute('data-paged') === 'done'")->getReturnValue()) {
                    if (microtime(true) > $deadline) {
                        throw new RuntimeException('The pages were not laid out in '.self::LAYOUT_TIMEOUT.' seconds.');
                    }
                    usleep(150_000);
                }

                return (string) base64_decode($page->pdf(['printBackground' => true, 'preferCSSPageSize' => true])->getBase64(60_000));
            } finally {
                $browser->close();
            }
        } finally {
            File::deleteDirectory($dir);
        }
    }

    /** The page with the files of this site it refers to put inside it or pointed to on the disk. */
    public function selfContained(string $html): string
    {
        $site = preg_quote(rtrim(url('/'), '/'), '#');

        // Paged.js reads the stylesheets itself, which a page opened from a file cannot do: they go inside.
        $html = preg_replace_callback('#<link rel="stylesheet" href="'.$site.'/([^"?]+)(?:\?[^"]*)?">#', fn ($m) => '<style>'.$this->stylesheet($m[1]).'</style>', $html);
        $html = preg_replace_callback('#<script src="'.$site.'/([^"?]+)(?:\?[^"]*)?"></script>#', function ($m) {
            $path = $this->publicFile($m[1]);

            return $path === null ? '' : '<script src="'.e(self::fileUrl($path)).'"></script>';
        }, $html);

        return preg_replace_callback('#\bsrc="('.$site.'/[^"]+)"#', fn ($m) => 'src="'.e($this->picture(html_entity_decode($m[1]))).'"', $html);
    }

    /** A stylesheet with its fonts and pictures as data: URLs (a font from another file is not loaded). */
    private function stylesheet(string $relative): string
    {
        $path = $this->publicFile($relative);
        if ($path === null) {
            return '';
        }

        return preg_replace_callback('/url\(\s*([\'"]?)([^\'")]+)\1\s*\)/', function ($m) use ($path) {
            if (preg_match('#^(data:|[a-z]+:|//|\#)#i', $m[2])) {
                return $m[0];
            }
            $file = self::inPublic(dirname($path).'/'.$m[2]);

            return $file === null ? $m[0] : 'url("data:'.self::mime($file).';base64,'.base64_encode((string) file_get_contents($file)).'")';
        }, (string) file_get_contents($path));
    }

    /** A picture of this site: a file of the storage, or a data: URL of a map tile. */
    private function picture(string $url): string
    {
        try {
            $route = Route::getRoutes()->match(Request::create($url));
            $p = $route->parameters();
            $disk = Storage::disk('local');

            $path = match ($route->getName()) {
                'admin.attachments.show' => Attachment::find($p['attachment'] ?? 0)?->path,
                'admin.analogs.photo' => Analog::find($p['analog'] ?? 0)?->photo_path,
                'admin.report-documents.show' => RealEstateReport::companyDocuments()[(int) ($p['index'] ?? -1)]['path'] ?? null,
                default => null,
            };
            if ($path !== null) {
                return $disk->exists($path) ? self::fileUrl($disk->path($path)) : self::BLANK;
            }

            if ($route->getName() === 'admin.map.tile') {
                $tile = app(MapTileController::class)->show((int) $p['z'], (int) $p['x'], (int) $p['y']);

                return 'data:'.($tile->headers->get('Content-Type') ?: 'image/png').';base64,'.base64_encode((string) $tile->getContent());
            }

            $public = $this->publicFile(ltrim((string) parse_url($url, PHP_URL_PATH), '/'));

            return $public === null ? self::BLANK : self::fileUrl($public);
        } catch (Throwable) {
            return self::BLANK;
        }
    }

    private function publicFile(string $relative): ?string
    {
        return self::inPublic(public_path(rawurldecode($relative)));
    }

    /** The real path of a file under public/, never of one outside it. */
    private static function inPublic(string $path): ?string
    {
        $root = realpath(public_path());
        $path = realpath($path);

        return $root !== false && $path !== false && is_file($path) && str_starts_with($path, $root.DIRECTORY_SEPARATOR) ? $path : null;
    }

    private static function fileUrl(string $path): string
    {
        return 'file://'.implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    private static function mime(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'woff2' => 'font/woff2',
            'woff' => 'font/woff',
            'ttf' => 'font/ttf',
            'svg' => 'image/svg+xml',
            default => mime_content_type($path) ?: 'application/octet-stream',
        };
    }
}
