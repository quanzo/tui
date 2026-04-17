<?php

require __DIR__ . '/../vendor/autoload.php';

use quanzo\tui\classes\command\InteractiveCommand;
use quanzo\tui\classes\tui\command\handlers\ClearCommandHandler;
use quanzo\tui\classes\tui\command\handlers\ExitCommandHandler;
use quanzo\tui\classes\tui\command\handlers\HelpCommandHandler;
use quanzo\tui\classes\tui\command\handlers\WorkspaceCommandHandler;
use quanzo\tui\classes\tui\hooks\DefaultTuiPostOutputHook;
use Symfony\Component\Console\Application;

$app = new Application('tui', '0.0.1');

$interactive = (new InteractiveCommand())
    ->setCommandName('interactive')
    ->setDescriptionText('Интерактивный TUI (workspace)')
    ->addHandler(new HelpCommandHandler())
    ->addHandler(new WorkspaceCommandHandler())
    ->addHandler(new ClearCommandHandler())
    ->addHandler(new ExitCommandHandler())
    ->setPostHook(new DefaultTuiPostOutputHook());

$app->add($interactive);

$app->run();
