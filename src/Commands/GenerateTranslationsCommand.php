<?php

namespace MediactiveDigital\MedKit\Commands;

use Illuminate\Console\Command;
use Illuminate\Translation\FileLoader;
use Illuminate\Filesystem\Filesystem;

use Sepia\PoParser\Parser;
use Sepia\PoParser\Catalog\Entry;
use Sepia\PoParser\SourceHandler\FileSystem as SepiaFileSystem;
use Sepia\PoParser\PoCompiler;

use Xinax\LaravelGettext\FileSystem as XinaxFileSystem;
use Xinax\LaravelGettext\Config\ConfigManager;

use MediactiveDigital\MedKit\Helpers\FormatHelper;

use Arr;
use Str;
use Artisan;

class GenerateTranslationsCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'medkit:generate-translations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Génère les traductions par défaut de Laravel pour Poedit';

    /**
     * @var \Illuminate\Filesystem\Filesystem $filesystem
     */
    protected $filesystem;

    /**
     * @var array $fileLoader
     */
    protected $fileLoader;

    /**
     * @var array $locales
     */
    protected $locales;

    /**
     * @var string $locale
     */
    protected $locale;

    /**
     * @var array $domains
     */
    protected $domains;

    /**
     * @var string $referencePath
     */
    protected $referencePath;

    /**
     * @var string $referenceFile
     */
    protected $referenceFile;

    /**
     * @var string $reference
     */
    protected $reference;

    /**
     * @var string $comment
     */
    protected $comment;

    /**
     * @var \Sepia\PoParser\PoCompiler $compiler
     */
    protected $compiler;

    /**
     * Create a new console command instance.
     *
     * @return void
     */
    public function __construct(Filesystem $filesystem) {

        parent::__construct();

        $this->filesystem = $filesystem;

        $resourcePath = str_replace('\\', '/', lang_path()) . '/';

        $this->fileLoader = [
            'path' => $resourcePath,
            'loader' => new FileLoader($this->filesystem, $resourcePath)
        ];

        $this->locales = config('laravel-gettext.supported-locales');
        $this->locale = config('laravel-gettext.locale');

        $this->domains = array_filter([config('laravel-gettext.domain')] + array_keys(config('laravel-gettext.source-paths')), 'is_string');

        $this->referencePath = str_replace('\\', '/', lang_path('po_laravel/'));
        $this->referenceFile = 'po_laravel.php';
        $this->reference = '../lang/po_laravel/' . $this->referenceFile;

        $this->comment = '// Laravel default translations';

        $this->compiler = new PoCompiler;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {

        $this->info('Generating Laravel default translations for Poedit');

        $translationsDatas = [
            'keys' => [],
            'langs' => []
        ];

        $referencePathTrimed = substr($this->referencePath, 0, -1);

        if (!$this->filesystem->isDirectory($this->referencePath)) {

            $this->filesystem->makeDirectory($this->referencePath, 0755, true);

            $this->comment('Lang directory created : ' . $referencePathTrimed);
        }
        else {

            $this->comment('Lang directory already exists : ' . $referencePathTrimed);
        }

        $configManager = ConfigManager::create();
        $filesystem = new XinaxFileSystem($configManager->get(), app_path(), storage_path());
        $localesGenerated = $filesystem->generateLocales();
        $createdLocales = [];

        foreach ($localesGenerated as $localePath) {

            $locale = substr($localePath, -2);
            $createdLocales[] = $locale;
        }

        $path = str_replace('\\', '/', lang_path('i18n/'));

        foreach ($this->locales as $locale) {

            $hasCatalog = false;

            foreach ($this->domains as $domain) {

                $translationsDatas['langs'][$locale][$domain] = [
                    'translations' => []
                ];

                $localePath = $path . $locale;
                $translationsDatas['langs'][$locale][$domain]['file'] = $localePath . '/LC_MESSAGES/' . $domain . '.po';

                $translationsDatas['langs'][$locale][$domain]['catalog'] = file_exists($translationsDatas['langs'][$locale][$domain]['file']) ? 
                    Parser::parseFile($translationsDatas['langs'][$locale][$domain]['file']) : null;

                $hasCatalog = $hasCatalog ?: (bool)$translationsDatas['langs'][$locale][$domain]['catalog'];
            }

            if (in_array($locale, $createdLocales)) {

                $this->comment('Locale directory created : ' . $localePath);

                foreach ($this->domains as $domain) {

                    $this->comment('Locale file created : ' . $translationsDatas['langs'][$locale][$domain]['file']);
                }
            }
            else {

                $this->comment('Locale directory already exists : ' . $localePath);

                foreach ($this->domains as $domain) {

                    $this->comment('Locale file already exists : ' . $translationsDatas['langs'][$locale][$domain]['file']);
                }
            }

            $fileLoader = $this->getFileLoader($locale);

            if ($fileLoader) {

                $this->comment('Laravel translations directory found : ' . $fileLoader['path']);

                $laravelTranslations = $this->filesystem->files($fileLoader['path']);

                if ($hasCatalog) {

                    foreach ($laravelTranslations as $laravelTranslation) {

                        $this->comment('Laravel translations file found : ' . $fileLoader['path'] . '/' . $laravelTranslation->getFilename());

                        $group = $laravelTranslation->getBasename('.php');
                        $translations = $fileLoader['loader']->load($fileLoader['locale'], $group);

                        if ($translations) {

                            if ($group == 'validation') {

                                unset($translations['custom']['attribute-name']);

                                if (!($translations['custom'] ?? null)) {

                                    unset($translations['custom']);
                                }

                                if (!($translations['attributes'] ?? null)) {

                                    unset($translations['attributes']);
                                }
                            }

                            $translations = [
                                $group => $translations
                            ];

                            $dotedTranslations = Arr::dot($translations);

                            foreach ($this->domains as $domain) {

                                if ($translationsDatas['langs'][$locale][$domain]['catalog']) {

                                    $translationsDatas['langs'][$locale][$domain]['translations'] = array_merge($translationsDatas['langs'][$locale][$domain]['translations'], $dotedTranslations);
                                }
                            }
                            
                            $translationsDatas['keys'] = array_merge($translationsDatas['keys'], array_keys($dotedTranslations));
                        }
                    }
                }
            }
        }

        if ($translationsDatas['keys']) {

            $translationsDatas['keys'] = array_unique($translationsDatas['keys']);
            sort($translationsDatas['keys']);

            foreach ($translationsDatas['langs'] as $locale => $domains) {

                foreach ($domains as $domain => $datas) {

                    if ($datas['catalog']) {

                        foreach ($translationsDatas['keys'] as $key) {

                            $translation = '';

                            if (isset($datas['translations'][$key]) && is_string($datas['translations'][$key])) {

                                $translation = $datas['translations'][$key];
                            }
                            else if (Str::startsWith($key, 'validation')) {

                                $translation = str_replace('_', ' ', Str::afterLast($key, '.'));
                            }

                            $entry = $datas['catalog']->getEntry($key);

                            if ($entry && !$entry->getMsgStr()) {

                                $entry->setMsgStr($translation);
                            }

                            $entry = $entry ?: new Entry($key, $translation);
                            $references = $entry->getReference();
                            $addReference = true;

                            foreach ($references as $reference) {

                                if ($this->reference == $reference || Str::startsWith($reference, $this->reference . ':')) {

                                    $addReference = false;

                                    break;
                                }
                            }

                            if ($addReference) {

                                $references[] = $this->reference;
                            }

                            $entry->setReference($references);
                            $datas['catalog']->addEntry($entry);
                        }

                        foreach ($datas['catalog']->getEntries() as $entry) {

                            $entry->setPreviousEntry(null);
                        }

                        $fileHandler = new SepiaFileSystem($datas['file']);
                        $fileHandler->save($this->compiler->compile($datas['catalog']));

                        $this->comment('Locale file updated : ' . $datas['file']);
                    }
                }
            }

            foreach ($translationsDatas['keys'] as $index => $key) {

                unset($translationsDatas['keys'][$index]);
                Arr::set($translationsDatas['keys'], $key, FormatHelper::UNESCAPE . '_i(' . FormatHelper::writeValueToPhp($key) . ')');
            }

            $fileContents = '<?php' . infy_nl_tab(2, 0) . $this->comment . infy_nl_tab(2, 0) . 'return ' . FormatHelper::writeValueToPhp($translationsDatas['keys']) . ';' . infy_nl_tab(1, 0);
            $filePath = $this->referencePath . $this->referenceFile;
            $exists = $this->filesystem->exists($filePath);
            $this->filesystem->replace($filePath, $fileContents);

            if ($exists) {

                $this->comment('Lang file updated : ' . $filePath);
            }
            else {

                $this->comment('Lang file updated : ' . $filePath);
            }
        }
        else {

            $this->error('No translations found');
        }
    }

    /**
     * Get file loader for locale.
     *
     * @param string $locale
     *
     * @return array $fileLoader
     */
    public function getFileLoader(string $locale): array {

        $fileLoader = [];

        $locales = [
            $locale
        ];

        if ($locale != $this->locale) {

            $locales[] = $this->locale;
        }

        foreach ($locales as $locale) {

            extract($this->fileLoader);

            $fullPath = $path . $locale;
            $isDir = $this->filesystem->isDirectory($fullPath);

            if (!$isDir) {

                Artisan::call('lang:add ' . $locale);

                $isDir = $this->filesystem->isDirectory($fullPath);
            }

            if ($isDir) {

                $fileLoader = [
                    'locale' => $locale,
                    'path' => $fullPath,
                    'loader' => $loader
                ];
            }
        }

        return $fileLoader;
    }
}
