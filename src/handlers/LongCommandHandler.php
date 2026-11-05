<?php

declare(strict_types=1);

namespace quanzo\tui\handlers;

use Amp\Cancellation;
use Amp\Future;
use quanzo\tui\classes\dto\tui\command\ParsedUserInputDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandContextDto;
use quanzo\tui\classes\dto\tui\command\TuiCommandResultDto;
use quanzo\tui\classes\dto\tui\help\TuiCommandHelpDto;
use quanzo\tui\classes\dto\tui\help\TuiHelpRowDto;
use quanzo\tui\classes\dto\tui\history\TuiHistoryEntryDto;
use quanzo\tui\classes\dto\tui\view\blocks\NoticeBlockDto;
use quanzo\tui\enums\tui\TuiNoticeKindEnum;
use quanzo\tui\interfaces\tui\command\AsyncTuiCommandHandlerInterface;

use function Amp\async;
use function Amp\delay;

/**
 * Handler команды `/long` — пример длительной асинхронной команды.
 *
 * Команда специально сделана максимально простой и предсказуемой, чтобы:
 * - продемонстрировать, что TUI остаётся отзывчивой, пока задача выполняется;
 * - продемонстрировать отмену через `/break` (Amp cancellation).
 *
 * Синтаксис:
 * - `/long` — выполнить 5 секунд
 * - `/long <seconds>` — выполнить указанное количество секунд (clamp: 1..600)
 *
 * Семантика отмены:
 * - отмена кооперативная: `delay()` поддерживает cancellation, а мы также вызываем `throwIfRequested()` на каждом шаге;
 * - при отмене handler *не возвращает* “успешный” результат, а завершает future исключением `CancelledException`,
 *   чтобы JobManager мог единообразно оформить cancelled-entry.
 */
final class LongCommandHandler implements AsyncTuiCommandHandlerInterface
{
    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'long';
    }

    /**
     * Возвращает справку команды `/long`.
     *
     * @return TuiCommandHelpDto
     */
    public function getHelp(): TuiCommandHelpDto
    {
        return new TuiCommandHelpDto([
            new TuiHelpRowDto('/long', 'Фоновая пауза 5 секунд. Интерфейс не блокируется'),
            new TuiHelpRowDto('/long 10', 'Пауза на указанное число секунд, диапазон 1…600. Нечисло (abc, 0, -1) тоже выполняется 5 секунд'),
        ]);
    }

    /**
     * Выполняет “долгую” операцию заданное число секунд.
     *
     * @param TuiCommandContextDto $ctx
     * @param ParsedUserInputDto $input
     * @param Cancellation $cancellation
     * @return Future<TuiCommandResultDto>
     */
    public function handleAsync(TuiCommandContextDto $ctx, ParsedUserInputDto $input, Cancellation $cancellation): Future
    {
        $seconds = $this->parseSeconds($input);
        $label = '/long ' . $seconds;

        return async(function () use ($seconds, $label, $cancellation): TuiCommandResultDto {
            // Быстрый “start” entry: полезно, если результат придёт сильно позже.
            $started = TuiHistoryEntryDto::output('long started')
                ->setBlocks([new NoticeBlockDto(TuiNoticeKindEnum::Info, 'Running ' . $label . ' ...')])
                ->setPlainText('running ' . $label);

            // Основной цикл: задержка на 1 секунду, затем проверка cancellation.
            for ($i = 1; $i <= $seconds; $i++) {
                delay(1.0, true, $cancellation);
                $cancellation->throwIfRequested();
            }

            $done = TuiHistoryEntryDto::output('long done')
                ->setBlocks([new NoticeBlockDto(TuiNoticeKindEnum::Info, 'Done: ' . $label)])
                ->setPlainText('done ' . $label);

            return (new TuiCommandResultDto())->setAppendEntries([$started, $done]);
        });
    }

    /**
     * {@inheritDoc}
     */
    public function getForceCancelCallback(TuiCommandContextDto $ctx, ParsedUserInputDto $input): ?callable
    {
        return null;
    }

    /**
     * Парсит `<seconds>` из аргументов команды.
     *
     * @param ParsedUserInputDto $input
     * @return int
     */
    private function parseSeconds(ParsedUserInputDto $input): int
    {
        $args = $input->getArgs();
        $raw = isset($args[0]) ? trim((string) $args[0]) : '';
        if ($raw === '') {
            return 5;
        }

        $n = (int) $raw;
        if ($n <= 0) {
            return 5;
        }

        return max(1, min(600, $n));
    }
}
