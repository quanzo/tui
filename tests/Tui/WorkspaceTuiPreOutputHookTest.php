<?php

declare(strict_types=1);

namespace Tests\Tui;

use Amp\Cancellation;
use Amp\Future;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use quanzo\tui\classes\dto\tui\command\ParsedUserInputDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandContextDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandResultDto;
use quanzo\tui\classes\tui\async\TuiJobManager;
use quanzo\tui\classes\tui\command\TuiCommandDispatcher;
use quanzo\tui\classes\tui\hooks\WorkspaceTuiPreOutputHook;
use quanzo\tui\handlers\ClearCommandHandler;
use quanzo\tui\handlers\ExitCommandHandler;
use quanzo\tui\handlers\HelpCommandHandler;
use quanzo\tui\interfaces\tui\command\AsyncTuiCommandHandlerInterface;
use quanzo\tui\interfaces\tui\command\TuiCommandHandlerInterface;

/**
 * Тесты {@see WorkspaceTuiPreOutputHook}: текст, пустая команда, sync- и async-handlers.
 */
final class WorkspaceTuiPreOutputHookTest extends TestCase
{
    /**
     * Решение pre-hook для обычного текста, пустой команды, неизвестной и штатных команд.
     *
     * @param string $input
     * @param bool $clear
     * @param bool $exit
     * @param string $plain
     */
    #[DataProvider('decideCases')]
    public function testDecide(string $input, bool $clear, bool $exit, string $plain): void
    {
        $decision = $this->hook()->decide($input);
        $entries = $decision->getAppendEntries();

        $this->assertSame($input, $decision->getOriginalInput());
        $this->assertSame($clear, $decision->isClearHistory());
        $this->assertSame($exit, $decision->isExit());
        $this->assertNotEmpty($entries);
        $this->assertSame($plain, $entries[array_key_last($entries)]->getPlainText());
    }

    /**
     * @return list<array{0:string,1:bool,2:bool,3:string}>
     */
    public static function decideCases(): array
    {
        return [
            ['', false, false, 'Введите /help'],
            ['   ', false, false, 'Введите /help'],
            ['hello', false, false, 'Введите /help'],
            ['/', false, false, 'Введите /help'],
            ['/   ', false, false, 'Введите /help'],
            ['/nope', false, false, 'Неизвестная команда'],
            ['/help', false, false, 'help'],
            ['/clear', true, false, 'История очищена'],
            ['/exit', false, true, 'Выход...'],
            ['/noop', false, false, 'Команда выполнена'],
            ["/help\n", false, false, 'help'],
            ["/\xFF", false, false, 'Неизвестная команда'],
        ];
    }

    /**
     * Async-handler ставит уже завершённый Future в менеджер задач и пишет id в историю.
     */
    public function testAsyncHandlerStartsJob(): void
    {
        $jobs = new TuiJobManager();
        $async = new class implements AsyncTuiCommandHandlerInterface {
            public function getName(): string
            {
                return 'ping';
            }

            public function handleAsync(
                TuiCommandContextDto $ctx,
                ParsedUserInputDto $input,
                Cancellation $cancellation,
            ): Future {
                return Future::complete(new TuiCommandResultDto());
            }

            public function getForceCancelCallback(TuiCommandContextDto $ctx, ParsedUserInputDto $input): ?callable
            {
                return null;
            }
        };

        $hook = new WorkspaceTuiPreOutputHook(new TuiCommandDispatcher([$async]), $jobs);
        $decision = $hook->decide('/ping');
        $plain = (string) $decision->getAppendEntries()[array_key_last($decision->getAppendEntries())]->getPlainText();

        $this->assertStringStartsWith('job started job_', $plain);
        $this->assertNotEmpty($jobs->drainCompleted());
    }

    /**
     * Диспетчер с help/clear/exit и пустым sync-handler.
     */
    private function hook(): WorkspaceTuiPreOutputHook
    {
        $noop = new class implements TuiCommandHandlerInterface {
            public function getName(): string
            {
                return 'noop';
            }

            public function handle(TuiCommandContextDto $ctx, ParsedUserInputDto $input): TuiCommandResultDto
            {
                return new TuiCommandResultDto();
            }
        };

        return new WorkspaceTuiPreOutputHook(
            new TuiCommandDispatcher([
                new HelpCommandHandler(),
                new ClearCommandHandler(),
                new ExitCommandHandler(),
                $noop,
            ]),
            new TuiJobManager(),
        );
    }
}
