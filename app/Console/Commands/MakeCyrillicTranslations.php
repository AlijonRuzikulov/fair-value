<?php

namespace App\Console\Commands;

use App\Support\Transliterator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Builds the Uzbek Cyrillic translation ("oz") from the Uzbek Latin one
 * ("uz"), so translators only maintain one Uzbek file:
 *
 *     lang/uz.json        → lang/oz.json
 *     lang/uz/*.php       → lang/oz/*.php
 *
 * Words the rules get wrong are fixed in lang/oz.overrides.json
 * ("Latin key" => "Cyrillic text"); overrides always win.
 */
class MakeCyrillicTranslations extends Command
{
    protected $signature = 'lang:cyrillic';

    protected $description = 'Generate Uzbek Cyrillic translations (lang/oz) from Uzbek Latin (lang/uz)';

    public function handle(): int
    {
        $overridesPath = lang_path('oz.overrides.json');
        $overrides = File::exists($overridesPath) ? json_decode(File::get($overridesPath), true, flags: JSON_THROW_ON_ERROR) : [];

        $latin = json_decode(File::get(lang_path('uz.json')), true, flags: JSON_THROW_ON_ERROR);
        $cyrillic = [];
        foreach ($latin as $key => $text) {
            $cyrillic[$key] = $overrides[$key] ?? Transliterator::toCyrillic($text);
        }
        File::put(lang_path('oz.json'), json_encode($cyrillic, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
        $this->info('lang/oz.json: '.count($cyrillic).' strings');

        File::ensureDirectoryExists(lang_path('oz'));
        foreach (File::files(lang_path('uz')) as $file) {
            $array = $this->convert(require $file->getPathname());
            File::put(lang_path('oz/'.$file->getFilename()), "<?php\n\n// Generated from lang/uz/{$file->getFilename()} by \"php artisan lang:cyrillic\". Do not edit.\n\nreturn ".$this->export($array).";\n");
            $this->info('lang/oz/'.$file->getFilename());
        }

        return self::SUCCESS;
    }

    private function convert(array $array): array
    {
        return array_map(fn ($value) => is_array($value) ? $this->convert($value) : Transliterator::toCyrillic((string) $value), $array);
    }

    private function export(array $array, int $depth = 1): string
    {
        $pad = str_repeat('    ', $depth);
        $lines = [];
        foreach ($array as $key => $value) {
            $lines[] = $pad.var_export($key, true).' => '.(is_array($value) ? $this->export($value, $depth + 1) : var_export($value, true)).',';
        }

        return "[\n".implode("\n", $lines)."\n".str_repeat('    ', $depth - 1).']';
    }
}
