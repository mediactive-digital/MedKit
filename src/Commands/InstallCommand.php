<?php

namespace MediactiveDigital\MedKit\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

use Symfony\Component\Process\Process;

use Composer\Json\JsonFile;

use MediactiveDigital\MedKit\Helpers\ConfigHelper;
use MediactiveDigital\MedKit\Helpers\FormatHelper;

use MediactiveDigital\MedKit\Json\JsonManipulator;

class InstallCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'medkit:install {--theme=mediactive-digital/medkit-theme-malabar:dev-L11@dev}';
    protected $description = 'Installation du starterkit';

    private $pathToPackageRoot = __DIR__ . '/../../';
    private $promptConfirmation = false;
    private $filesystem = null;

    private $refActionUser = [
        0 => 'Exit',
        1 => 'Run All',
        2 => 'Add Require Packages',
        3 => 'Add Providers',
        4 => 'Copy Source',
        5 => 'Publish packages sources',
        6 => 'Add Route',
        7 => 'Add AdminGuard',
        8 => 'Add session config',
        9 => 'Install Theme',
        10 => 'Add helpers',
        11 => 'Generate Laravel default translations for Poedit',
        12 => 'Generate JS translations',
        13 => 'Generate JS routes'
    ];

    /**
     * Execute the console command.
     * @param \Illuminate\Filesystem\Filesystem $filesystem
     * @return mixed
     */
    public function handle(Filesystem $filesystem) {
        $this->filesystem = $filesystem;

        do {
            $this->getOutput()->section("Medkit installation");

            $rep = 1;
            if (!$this->promptConfirmation){
                $rep = array_flip($this->refActionUser)[
                $this->getOutput()->choice("Quel etapes voulez vous executer ?",$this->refActionUser,$this->refActionUser[0])
                ]; // On récupere l'indice
            }

            switch ($rep) {

                case 0 :

                    return true;

                break;

                case 1 :

                    $this->addHelpers();
                    $this->addRequirePackages();
                    $this->addProviders();
                    $this->composerDump();
                    $this->copySource();
                    $this->publishPackagesSource();
                    $this->addRoutes();
                    $this->addAdminGuard();
                    $this->addSessionConfig();
                    $this->installTheme();
                    $this->generateTranslations();
                    $this->generateJsTranslations();
                    $this->generateJsRoutes();
                    $this->composerDump();

                    return true;

                break;

                case 2 :

                    $this->addRequirePackages();
                    $this->composerDump();

                break;

                case 3 :

                    $this->addProviders();
                    $this->composerDump();

                break;

                case 4 :

                    $this->copySource();

                break;

                case 5 :

                    $this->publishPackagesSource();

                break;

                case 6 :

                    $this->addRoutes();

                break;

                case 7 :

                    $this->addAdminGuard();

                break;

                case 8 :

                    $this->addSessionConfig();

                break;

                case 9 :

                    $this->installTheme();
                    $this->composerDump();

                break;

                case 10 :

                    $this->addHelpers();
                    $this->composerDump();
                    
                break;

                case 11 :

                    $this->generateTranslations();
                    
                break;

                case 12 :

                    $this->generateJsTranslations();
                    
                break;

                case 13 :

                    $this->generateJsRoutes();
                    
                break;
            }

        } while($rep !== 0);

        return true;
    }


    /**
     * Add composers required package
     */
    private function addRequirePackages()
    {
        $devPackages = [
            "orangehill/iseed:*",
            "barryvdh/laravel-debugbar:*",
            "barryvdh/laravel-ide-helper:*",
            "laravel/dusk:*",
            "mediactive-digital/laravel:dev-L11@dev",
            "mediactive-digital/migrations-generator:dev-L11@dev"
        ];
        $this->doCommand("composer require " . implode(' ', $devPackages) . " --dev");

        if ($this->hasOption('theme')) {
            $this->doCommand('composer require ' . $this->option('theme').'');
        }
    }

    /**
     * Add providers to configuration
     */
    public function addProviders() {

        $this->info('Add Providers to bootstrap/providers.php');

        $path = base_path('bootstrap') . '/providers.php';
        $configContents = file_get_contents($path);
        $add = false;

        $configContents = preg_replace_callback('/(return \[)([\s\S]*?)(])/', function ($matches) use (&$add) {

            if (strpos($matches[2], 'Rairlie\LockingSession\LockingSessionServiceProvider') === false) {

                $matches[2] = str_replace('Illuminate\Session\SessionServiceProvider', '// Illuminate\Session\SessionServiceProvider', $matches[2]);

                $matches[2] = rtrim($matches[2]) . FormatHelper::NEW_LINE . FormatHelper::TAB .
                    'Rairlie\LockingSession\LockingSessionServiceProvider::class,' . 
                    FormatHelper::NEW_LINE;

                $add = true;
            }

            if (strpos($matches[2], 'App\Providers\AliasServiceProvider::class') === false) {

                $matches[2] = rtrim($matches[2]) . FormatHelper::NEW_LINE . FormatHelper::TAB .
                    'App\Providers\AliasServiceProvider::class,' . 
                    FormatHelper::NEW_LINE;

                $add = true;
            }

            $return = $matches[1] . $matches[2] . $matches[3];
    
            return $return;

        }, $configContents);

        if ($add) {

            file_put_contents($path, $configContents);
        }
        else {

            $this->error(' #ERR1 [SKIP] ' . $path . ' already has providers');
        }
    }

    /**
     * Add default routes
     *
     * @return void
     */
    private function addRoutes()
    {
        $this->info("Adding back/web.php to routes/web.php");

        $fileToEdit = base_path('routes') . '/web.php';
        $stubFile = $this->pathToPackageRoot . 'stubs/routes/require-back-routes.stub';
        $checkString = 'back/web';


        $stub = $this->filesystem->get($stubFile);
        $fileContent = $this->filesystem->get($fileToEdit);

        if (strpos($fileContent, $checkString) === false) {
            $this->filesystem->append($fileToEdit, $stub);
        } else {
            $this->error(' #ERR1 [SKIP] ' . $fileToEdit . ' already has this stub');
        }
    }


    /**
     * Finish the install
     *
     * @return void
     */
    private function composerDump()
    {
        $this->info('composer dump-autoload');
        $this->doCommand('composer dump-autoload');
        $this->info('Installation done.');
    }

    private function publishPackagesSource(){
        $this->line('---------------------');
        $this->line('| Publish vendor files');
        $this->line('---------------------');
        $this->doCommand("php artisan vendor:publish --all --no-interaction");
    }

    private function installTheme() {
        if ($this->hasOption('theme')) {
            $this->line('---------------------');
            $this->line('| Theme: ' . $this->option('theme'));
            $this->line('---------------------');
            $this->doCommand("php artisan medkit-theme:install --force");
        }
    }
        
    
    /**
     * Execute a command
     *
     * @param [type] $command
     * @return void
     */
    private function doCommand($command)
    {
        $process = Process::fromShellCommandline($command);
        // process = new Process( [  base_path() , $command ]);
        // process = new Process( [ 'cd '.base_path() . ' && ' .$command ]);
        $process->setTimeout(null); // Setting timeout to null to prevent installation from stopping at a certain point in time
        // $process->setEnv([]);    
        $process
            ->setWorkingDirectory( base_path() )
            ->run(function ($type, $buffer) {
            $this->line($buffer); 
        });
    }


    /**
     * Copy required files for starterkit
     *
     * @return void
     */
    public function copySource()
    {
        $src = $this->pathToPackageRoot . 'publishable';
        $this->info('Copying publishable files...');
        if (!file_exists($src)) {
            $this->error(' #ERR2 [SKIP] ' . $src . ' does not exists');
        }
        $res = $this->filesystem->copyDirectory($src, base_path());


        $this->info('Update app/Kernel.php');
    }

    /**
     * Add session configuration
     */
    public function addSessionConfig() {

        $this->info('Add Session configuration to config/session.php');

        $path = base_path('config') . '/session.php';
        $configContents = file_get_contents($path);
        $add = false;

        $configContents = preg_replace_callback('/(Session File Location[\s\S]*?\*\/)([\s\S]*?)(\/\*)/', function($matches) use (&$add) {

            $matches2 = $matches[2];

            if (strpos($matches2, 'lockfile_dir') === false) {

                $matches2 = rtrim($matches2) . FormatHelper::NEW_LINE . FormatHelper::NEW_LINE . FormatHelper::TAB .
                    '\'lockfile_dir\' => storage_path(\'framework/lock_sessions\'),' . 
                    FormatHelper::NEW_LINE . FormatHelper::NEW_LINE . FormatHelper::TAB;

                $add = true;
            }

            $return = $matches[1] . $matches2 . $matches[3];
    
            return $return;

        }, $configContents);

        if ($add) {

            file_put_contents($path, $configContents);
        }
        else {

            $this->error(' #ERR1 [SKIP] ' . $path . ' already has configuration');
        }
    }

    /**
     * Edit config/auth.php to add admin guard
     *
     * @return void
     */
    public function addAdminGuard()
    {
        $this->info("Adding guard to config/auth.php");

        $fileToEdit = base_path('config') . '/auth.php';
        $authConfig = include($fileToEdit);

        /**
         * Add Provider
         */
        $authConfig = include($fileToEdit); //reload conf
        $authConfigProvider = ['providers' => array_merge($authConfig['providers'], [
            'users' => [
                'driver' => 'eloquent',
                'model' => \App\Models\User::class,
            ],
        ])];
        $sectionTitle = "User Providers";
        $nextSectionTitle = "Resetting Passwords";

        ConfigHelper::replaceArrayInConfig($fileToEdit, $sectionTitle, $nextSectionTitle, $authConfigProvider);

        return true;

        /**
         * Add guard
         */
        $authConfigGuards = ['guards' => array_merge($authConfig['guards'], [
            'admin' => [
                'driver' => 'session',
                'provider' => 'admins'
            ],
            'admin-api' => [
                'driver' => 'token',
                'provider' => 'admins',
            ],
        ])];
        $sectionTitle = "Authentication Guards";
        $nextSectionTitle = "User Providers";

        ConfigHelper::replaceArrayInConfig($fileToEdit, $sectionTitle, $nextSectionTitle, $authConfigGuards);

        /**
         * Add Provider
         */
        $authConfig = include($fileToEdit); //reload conf
        $authConfigProvider = ['providers' => array_merge($authConfig['providers'], [
            'admins' => [
                'driver' => 'eloquent',
                'model' => \App\Models\Admin::class,
            ],
        ])];
        $sectionTitle = "User Providers";
        $nextSectionTitle = "Resetting Passwords";

        ConfigHelper::replaceArrayInConfig($fileToEdit, $sectionTitle, $nextSectionTitle, $authConfigProvider);


        /**
         * Add password broker
         */
        $authConfig = include($fileToEdit);   //reload conf
        $authConfigPasswordBroker = ['passwords' => array_merge($authConfig['passwords'], [
            'admins' => [
                'provider' => 'admins',
                'table' => 'password_resets',
                'expire' => 1440,
                'broker' => App\Passwords\Back\PasswordBroker::class,
            ],
        ])];
        $sectionTitle = "Resetting Passwords";
        ConfigHelper::replaceArrayInConfig($fileToEdit, $sectionTitle, null, $authConfigPasswordBroker);
    }

    /**
     * Add helpers to composer.json
     *
     * @return void
     */
    private function addHelpers() {

        $this->info('Adding helpers to composer.json');

        $path = base_path('composer.json');
        $contents = file_get_contents($path);
        $manipulator = new JsonManipulator($contents);
        $jsonContents = $manipulator->getContents();
        $decoded = JsonFile::parseJson($jsonContents);
        $datas = isset($decoded['extra']['include_files']) ? $decoded['extra']['include_files'] : [];
        $value = 'vendor/mediactive-digital/medkit/src/helpers.php';

        foreach ($datas as $key => $entry) {

            if ($entry == $value) {

                $value = '';

                break;
            }
        }

        if ($value) {

            $datas[] = $value;
            $manipulator->addProperty('extra.include_files', $datas);
            $contents = $manipulator->getContents();

            file_put_contents($path, $contents);
        }
        else {

            $this->error(' #ERR1 [SKIP] ' . $path . ' already has helpers');
        }
    }

    /**
     * Generate Laravel default translations for Poedit
     *
     * @return void
     */
    private function generateTranslations() {

        $locales = config('laravel-gettext.supported-locales');

        foreach ($locales as $locale) {

            if (!file_exists(lang_path($locale))) {

                $this->doCommand('php artisan lang:add ' . $locale);
            }
        }

        $this->doCommand('php artisan medkit:generate-translations');
    }

    /**
     * Generate JS translations
     *
     * @return void
     */
    private function generateJsTranslations() {

        $this->doCommand('php artisan medkit:generate-js-translations');
    }

    /**
     * Generate JS routes
     *
     * @return void
     */
    private function generateJsRoutes() {

        $this->doCommand('php artisan medkit:generate-js-routes');
    }
}
