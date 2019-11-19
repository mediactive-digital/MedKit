<?php

namespace MediactiveDigital\MedKit\Commands\Scaffold;

use InfyOm\Generator\Commands\Scaffold\ControllerGeneratorCommand as InfyOmControllerGeneratorCommand;

use MediactiveDigital\MedKit\Commands\BaseCommand;
use MediactiveDigital\MedKit\Common\CommandData;
use MediactiveDigital\MedKit\Generators\ControllerGenerator;

class ControllerGeneratorCommand extends InfyOmControllerGeneratorCommand {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'medkit.scaffold:controller';

    /**
     * Create a new command instance.
     */
    public function __construct() {

        BaseCommand::__construct();

        $this->commandData = new CommandData($this, CommandData::$COMMAND_TYPE_SCAFFOLD);
    }

     /**
     * Execute the command.
     *
     * @return void
     */
    public function handle() {

        BaseCommand::handle();

        $this->controllerGenerator = new ControllerGenerator($this->commandData);

        $this->saveSchemaFile();
        $this->generateForm();
        $this->generate();
        $this->performPostActions();
    }
}
