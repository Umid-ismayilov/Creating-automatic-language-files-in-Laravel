<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

class LocalizeBladeText extends Command
{
    protected $signature = 'localize:blade-text';
    protected $description = 'Extracts text from Blade files for localization.';


    public function handle()
    {
        $path = resource_path('views/front/pages/');
        $fileName = 'about.blade.php';
        // or $fileName = '*.blade.php'; allfile in pages folder
        $finder = new Finder();
        $finder->files()->in($path)->name($fileName);

        if ($finder->hasResults()) {
            foreach ($finder as $file) {
                $html = $file->getContents();

                $_fileName = str_replace('.blade.php', '', $file->getFilename());
                preg_match_all('/>([^<]+)</', $html, $matches);
                $texts = array_unique($matches[1]);
                $formattedStrings = [];
                foreach ($texts as $text) {
                    $cleanString = preg_replace('/{{\s*\$[a-zA-Z_][a-zA-Z0-9_]*\s*}}/', '', $text);
                    $cleanString = trim($cleanString);
                    $text        = trim($text);
                    if (!empty($cleanString) && !isset($formattedStrings[$cleanString]) &&   !preg_match('/^@(extends|lang|section|include|yield|stack|push|once|endsection|endif|endforeach)(\s*\(.+\))?/',
                            $cleanString)) {
                        $html = str_replace('>' . $text, ">@lang('$_fileName.$text')", $html);
                        $html = str_replace('> ' . $text, "> @lang('$_fileName.$text')", $html);
                        $formattedStrings[$cleanString] = $cleanString;
                    }
                }

                file_put_contents($file->getRealPath(), $html);
                $langFilePath = resource_path("lang/az/$_fileName.php");
                if (!file_exists($langFilePath)) {
                    $file = fopen($langFilePath, 'w');
                    if ($file === false) {
                        $this->info("Failed to create language file.");
                    } else {
                        $content = "<?php\n\nreturn [\n\t// Add your localization keys and values here\n];\n";
                        fwrite($file, $content);
                        fclose($file);
                        chmod($langFilePath, 0664);
                        $this->info("Language file created successfully.");
                    }
                } else {
                    $this->info("Language file already exists.");
                }
                $existingEntries = file_exists($langFilePath) ? include($langFilePath) : [];
                $mergedEntries = array_merge($existingEntries, $formattedStrings);
                $phpCode = "<?php\n\nreturn " . var_export($mergedEntries, true) . ";\n";
                file_put_contents($langFilePath, $phpCode);

            }

            $this->info('Localization completed successfully.');
            return Command::SUCCESS;
        } else {
            $this->error('No Blade files found.');
            return Command::FAILURE;
        }
    }

}
