<?php

require __DIR__ . '/../vendor/autoload.php';

use quanzo\tui\classes\command\InteractiveCommand;
use quanzo\tui\classes\tui\async\TuiJobManager;
use quanzo\tui\classes\tui\async\ShellProcessRunner;
use quanzo\tui\classes\tui\command\handlers\BreakCommandHandler;
use quanzo\tui\classes\tui\command\handlers\ClearCommandHandler;
use quanzo\tui\classes\tui\command\handlers\ExecCommandHandler;
use quanzo\tui\classes\tui\command\handlers\ExitCommandHandler;
use quanzo\tui\classes\tui\command\handlers\HelpCommandHandler;
use quanzo\tui\classes\tui\command\handlers\JobsCommandHandler;
use quanzo\tui\classes\tui\command\handlers\LongCommandHandler;
use quanzo\tui\classes\tui\command\handlers\WorkspaceCommandHandler;
use quanzo\tui\classes\tui\hooks\DefaultTuiPostOutputHook;
use Symfony\Component\Console\Application;

$app = new Application('tui', '0.0.1');

$jobs = new TuiJobManager();
$runner = new ShellProcessRunner();

$interactive = (new InteractiveCommand())
    ->setCommandName('interactive')
    ->setDescriptionText('Интерактивный TUI (workspace)')
    ->setJobManager($jobs)
    ->addHandler(new HelpCommandHandler())
    ->addHandler(new WorkspaceCommandHandler())
    ->addHandler(new ClearCommandHandler())
    ->addHandler(new BreakCommandHandler($jobs))
    ->addHandler(new JobsCommandHandler($jobs))
    ->addHandler(new LongCommandHandler())
    ->addHandler(new ExecCommandHandler($runner))
    ->addHandler(new ExitCommandHandler())
    ->setPostHook(new DefaultTuiPostOutputHook());

$app->add($interactive);

$app->run();
