<?php

namespace quanzo\tui\command;

use Revolt\EventLoop;
use quanzo\tui\classes\status\ModeStatus;
use quanzo\tui\classes\tui\async\TuiJobManager;
use quanzo\tui\classes\tui\terminal\TerminalModeManager;
use quanzo\tui\classes\tui\input\KeySequenceParser;
use quanzo\tui\classes\tui\input\Utf8CharReader;
use quanzo\tui\classes\tui\state\TuiReducer;
use quanzo\tui\classes\tui\render\TuiHistoryFormatter;
use quanzo\tui\classes\tui\render\TuiRenderer;
use quanzo\tui\classes\tui\command\TuiCommandDispatcher;
use quanzo\tui\classes\tui\help\TuiCommandHelpCollector;
use quanzo\tui\classes\tui\help\TuiUserGuide;
use quanzo\tui\classes\tui\hooks\WorkspaceTuiPreOutputHook;
use quanzo\tui\classes\dto\tui\history\TuiHistoryDto;
use quanzo\tui\classes\dto\tui\KeyEventDto;
use quanzo\tui\classes\dto\tui\LayoutDto;
use quanzo\tui\classes\dto\tui\PostOutputContextDto;
use quanzo\tui\classes\dto\tui\TerminalSizeDto;
use quanzo\tui\classes\dto\tui\TuiStateDto;
use quanzo\tui\classes\dto\tui\TuiPreHookDecisionDto;
use quanzo\tui\classes\dto\tui\view\TuiThemeDto;
use quanzo\tui\interfaces\tui\TuiPostOutputHookInterface;
use quanzo\tui\interfaces\tui\TuiPreOutputHookInterface;
use quanzo\tui\interfaces\tui\command\TuiCommandHandlerInterface;
use quanzo\tui\interfaces\tui\command\TuiCommandRegistryAwareInterface;
use quanzo\tui\interfaces\tui\command\TuiNamedCommandHandlerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;
use quanzo\tui\classes\status\StatusBar;

/**
 * Интерактивная TUI-команда.
 *
 * Особенности:
 * - Адаптивная геометрия: при изменении размеров терминала все элементы пересчитываются.
 * - Поддержка Unicode (включая русские буквы).
 * - Частичная перерисовка для минимизации мерцания.
 * - Переключение фокуса между областью вывода и полем ввода (Tab).
 * - Прокрутка истории сообщений стрелками и PageUp/PageDown.
 * - Многострочное поле ввода (3 строки) с редактированием.
 * - Строка состояния собирается из объектов-статусов.
 */
class InteractiveCommand extends Command
{
    protected static $defaultName = 'interactive';

    /**
     * Пользовательское имя команды (переопределяет `$defaultName`).
     *
     * Если задано, применяется в `configure()` и/или при вызове `setCommandName()`.
     */
    private ?string $commandName = null;

    /**
     * Пользовательское описание команды.
     *
     * Если задано, применяется в `configure()` и/или при вызове `setDescriptionText()`.
     */
    private ?string $descriptionText = null;

    /** @var Terminal Объект для получения размеров терминала */
    private Terminal $terminal;

    /** @var TuiHistoryDto История отправленных сообщений */
    private TuiHistoryDto $history;

    /**
     * Добавляет сообщение в историю.
     *
     * @param string $message
     */
    protected function addMessage(string $message): void
    {
        $this->history->appendOutput($message);
    }

    /** @var string Буфер многострочного ввода */
    private string $inputBuffer = '';

    /** @var int Позиция курсора в буфере (в символах) */
    private int $cursorOffset = 0;

    /** @var int Верхняя строка viewport ввода (для отображения 3 строк) */
    private int $inputViewportTopLine = 0;

    /**
     * История введённых (отправленных) команд для навигации ArrowUp/ArrowDown.
     *
     * @var list<string>
     */
    private array $inputHistory = [];

    /**
     * Индекс текущего элемента истории, если пользователь находится в режиме history.
     */
    private ?int $inputHistoryIndex = null;

    /**
     * Черновик (текущий буфер), сохранённый при первом переходе в history.
     */
    private string $inputHistoryDraftBuffer = '';

    /**
     * Позиция курсора в черновике, сохранённая при первом переходе в history.
     */
    private int $inputHistoryDraftCursorOffset = 0;

    /** @var bool Включён ли режим mouse reporting (выключен по умолчанию) */
    private bool $mouseModeEnabled = false;

    /** @var int Смещение прокрутки области вывода (в строках) */
    private int $outputScroll = 0;

    /** @var string Фокус ввода: 'input' или 'output' */
    private string $focus = 'input';

    /** @var bool Флаг работы цикла */
    private bool $running = true;

    /** @var StatusBar Строка состояния */
    private StatusBar $statusBar;

    /** @var array Предыдущее состояние строк ввода (для частичной перерисовки) */
    private array $prevInputLines = ['', '', ''];

    /** @var string Предыдущее состояние строки статуса (для частичной перерисовки) */
    private string $prevStatusLine = '';

    /** @var int Предыдущая ширина терминала (для обнаружения изменения размера) */
    private int $prevWidth;

    /** @var int Предыдущая высота терминала (для обнаружения изменения размера) */
    private int $prevHeight;

    /** @var bool Флаг необходимости полной перерисовки */
    private bool $fullRedraw = true;

    /** Минимальная высота терминала, необходимая для работы (9 строк) */
    private const MIN_HEIGHT = 9;

    /**
     * Pre-hook (команды/диспетчеризация).
     *
     * Может быть задан извне (например, в `bin/console.php`) через fluent API.
     */
    private ?TuiPreOutputHookInterface $preHook = null;

    /**
     * Post-hook (добавочный вывод после кадра).
     *
     * Может быть задан извне (например, в `bin/console.php`) через fluent API.
     */
    private ?TuiPostOutputHookInterface $postHook = null;

    /**
     * Диспетчер TUI-команд (handlers).
     *
     * Инкапсулируется внутри `InteractiveCommand`, чтобы wiring в `bin/console.php` был максимально простым:
     * handlers добавляются через `addHandler()`, а дефолтный pre-hook (Workspace) использует этот dispatcher.
     */
    private ?TuiCommandDispatcher $dispatcher = null;

    private ?PostOutputContextDto $pendingPostHookContext = null;

    /**
     * Менеджер асинхронных задач (jobs) для async-команд и `/break`.
     *
     * По умолчанию создаётся внутри, но может быть внедрён извне (wiring в `bin/console.php`),
     * чтобы handlers могли использовать тот же экземпляр.
     */
    private ?TuiJobManager $jobs = null;

    protected function configure(): void
    {
        if ($this->commandName !== null && $this->commandName !== '') {
            $this->setName($this->commandName);
        }

        $this->setDescription($this->descriptionText ?: 'Запускает интерактивный TUI-интерфейс (адаптивный)');
    }

    /**
     * Собирает плоскую справку из текущего реестра команд.
     *
     * Экран, режимы и клавиши берёт {@see TuiUserGuide}, строки команд — `getHelp()` handlers.
     * Текст совпадает с панелью `/help` и не зависит от `NEURON_TUI_HELP_ASCII`.
     *
     * @return string
     */
    public function getHelp(): string
    {
        return TuiUserGuide::toPlainText(TuiUserGuide::build(
            TuiCommandHelpCollector::fromRegistry($this->getDispatcher()),
        ));
    }

    /**
     * Устанавливает имя команды (fluent API).
     *
     * Используется для создания нескольких TUI-команд на базе одного класса (разные наборы handlers/hooks).
     *
     * @param string $name Имя команды в Symfony Console (например, `workspace:tui`)
     * @return self
     */
    public function setCommandName(string $name): self
    {
        $this->commandName = $name;
        if ($name !== '') {
            $this->setName($name);
        }
        return $this;
    }

    /**
     * Устанавливает описание команды (fluent API).
     *
     * @param string $description
     * @return self
     */
    public function setDescriptionText(string $description): self
    {
        $this->descriptionText = $description;
        if ($description !== '') {
            $this->setDescription($description);
        }
        return $this;
    }

    /**
     * Подключает pre-hook (fluent API).
     *
     * Pre-hook отвечает за интерпретацию введённого текста (парсинг/диспетчеризацию/команды).
     *
     * @param TuiPreOutputHookInterface $hook
     * @return self
     */
    public function setPreHook(TuiPreOutputHookInterface $hook): self
    {
        $this->preHook = $hook;
        return $this;
    }

    /**
     * Подключает post-hook (fluent API).
     *
     * Post-hook вызывается после рендера кадра и может вернуть дополнительный текст,
     * который будет добавлен в историю.
     *
     * @param TuiPostOutputHookInterface $hook
     * @return self
     */
    public function setPostHook(TuiPostOutputHookInterface $hook): self
    {
        $this->postHook = $hook;
        return $this;
    }

    /**
     * Внедряет `TuiJobManager` (fluent API).
     *
     * Это позволяет внешнему коду создать один экземпляр job-manager и передать его:
     * - в `InteractiveCommand` (для доставки результатов в историю);
     * - в handlers (например, `/break`).
     *
     * @param TuiJobManager $jobs
     * @return self
     */
    public function setJobManager(TuiJobManager $jobs): self
    {
        $this->jobs = $jobs;
        return $this;
    }

    /**
     * Регистрирует handler TUI-команды (fluent API).
     *
     * Поведение при совпадении имён: overwrite (последний добавленный handler побеждает).
     *
     * Важно: если вы используете кастомный pre-hook через `setPreHook()`, то добавленные сюда handlers
     * не будут автоматически применяться, пока ваш pre-hook сам не использует dispatcher.
     *
     * @param TuiNamedCommandHandlerInterface $handler
     * @return self
     */
    public function addHandler(TuiNamedCommandHandlerInterface $handler): self
    {
        $this->getDispatcher()->addHandler($handler);
        if ($handler instanceof TuiCommandRegistryAwareInterface) {
            $handler->setCommandRegistry($this->getDispatcher());
        }
        return $this;
    }

    /**
     * Удаляет handler по имени команды.
     *
     * @param string $name Имя команды (без `/`)
     * @return bool true если handler был найден и удалён, иначе false
     */
    public function removeHandlerByName(string $name): bool
    {
        return $this->getDispatcher()->removeHandlerByName($name);
    }

    /**
     * Удаляет handler по экземпляру.
     *
     * Удаление выполняется по имени handler'а (`$handler->getName()`).
     *
     * @param TuiNamedCommandHandlerInterface $handler
     * @return bool true если handler был найден и удалён, иначе false
     */
    public function removeHandler(TuiNamedCommandHandlerInterface $handler): bool
    {
        return $this->getDispatcher()->removeHandler($handler);
    }

    /**
     * Точка входа в команду.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Устанавливаем локаль для корректной работы mbstring с UTF-8
        setlocale(LC_ALL, 'en_US.UTF-8');

        $this->terminal   = new Terminal();
        $this->history    = new TuiHistoryDto();
        $this->statusBar  = new StatusBar();
        $this->prevWidth  = $this->terminal->getWidth();
        $this->prevHeight = $this->terminal->getHeight();

        $this->initHooks();

          // Проверяем, достаточно ли высоты для отображения всех элементов
        if (!$this->checkMinSize()) {
            $output->writeln("<error>Терминал слишком маленький. Нужна высота не менее " . self::MIN_HEIGHT . " строк.</error>");
            return Command::FAILURE;
        }

        $terminalMode = new TerminalModeManager();
        $terminalMode->enter();
        try {
            $this->runLoop($terminalMode);
        } finally {
            $terminalMode->leave();
        }

        return Command::SUCCESS;
    }

    /**
     * Проверяет, соответствует ли текущая высота терминала минимальной.
     */
    private function checkMinSize(): bool
    {
        return $this->terminal->getHeight() >= self::MIN_HEIGHT;
    }

    /**
     * Главный цикл обработки ввода и отрисовки.
     *
     * Важное отличие от “классического” `while (...) { stream_select(...) }`:
     * здесь цикл построен вокруг **Revolt EventLoop**.
     *
     * Почему так:
     * - Amp v3 (Futures, async, delay, http-client) выполняется поверх Revolt EventLoop.
     * - Если event loop не крутится, `Future` не будет прогрессировать.
     * - Поэтому TUI работает как набор watchers:
     *   - `repeat(...)` — периодический tick для отрисовки и доставки результатов jobs;
     *   - `onReadable(STDIN, ...)` — обработка ввода при появлении байтов.
     *
     * Общая схема:
     * 1) `onReadable()` превращает байты stdin в `KeyEventDto` и применяет их через reducer.
     * 2) reducer обновляет состояние (`TuiStateDto`) и может вернуть submit (Enter).
     * 3) submit попадает в pre-hook (workspace-команды): sync-результат добавляется сразу,
     *    async-команда стартует как job и завершится позже.
     * 4) `repeat()` доставляет завершённые jobs (`drainCompleted()`) в историю и рендерит кадр.
     *
     * Управление временем жизни:
     * - пока `$this->running === true`, watchers активны, а `suspension->suspend()` удерживает control-flow внутри loop.
     * - когда `$this->running` становится false (например `/exit` или Ctrl+C), callbacks вызывают `resume()`,
     *   `runLoop()` продолжает выполнение и корректно освобождает ресурсы.
     *
     * @param TerminalModeManager $terminalMode Управление режимами терминала (mouse/bracketed paste/stty).
     * @return void
     */
    private function runLoop(TerminalModeManager $terminalMode): void
    {
        /** @var resource $stdin */
        $stdin = fopen('php://stdin', 'r');

        // STDIN переводим в non-blocking режим.
        // Это критично для event loop: callback `onReadable` должен быстро возвращаться
        // и не должен зависать на чтении.
        stream_set_blocking($stdin, false);

        // Компоненты TUI:
        // - parser: bytes -> KeyEventDto
        // - reducer: KeyEventDto -> state mutation (+ optional submittedInput)
        // - renderer: state -> ANSI output
        //
        // Эти объекты создаются один раз и используются в callbacks, чтобы не создавать их на каждом tick.
        $parser = new KeySequenceParser(new Utf8CharReader());
        $reducer = new TuiReducer();
        $renderer = new TuiRenderer();

        // Suspension — точка, на которой `runLoop()` “отдаёт управление” event loop.
        // Пока suspension не resume'нут, текущий PHP stack не продолжится дальше `suspend()`.
        //
        // Это и есть “запуск” event loop в нашей парадигме: мы не вызываем `EventLoop::run()`,
        // потому что Revolt управляет итерациями самостоятельно, пока есть активные watchers и мы находимся в suspend().
        $suspension = EventLoop::getSuspension();

        // Render tick.
        // Выбрана частота 50ms (~20 FPS) как компромисс:
        // - достаточно плавно для курсора/скролла/интеракций;
        // - достаточно редко, чтобы не грузить CPU постоянной полной перерисовкой.
        //
        // Если UI “грязный” (`fullRedraw=true`) — делаем полный кадр.
        // Иначе — partial render, обновляя только изменившиеся строки ввода/статуса.
        $renderTickId = EventLoop::repeat(0.05, function () use ($terminalMode, $renderer, $suspension): void {
            if (!$this->running) {
                // Прерывание цикла: возвращаем контроль `runLoop()`.
                // Далее будет cleanup watchers и закрытие STDIN.
                $suspension->resume(null);
                return;
            }

            // Доставляем результаты завершённых async-задач в историю.
            if ($this->jobs !== null) {
                // `drainCompleted()` возвращает entries, которые нужно добавить в историю.
                // Это включает:
                // - вывод async handler'а (например /exec stdout/stderr);
                // - служебные сообщения о статусе и длительности (done/cancelled/failed).
                //
                // Здесь мы сознательно делаем append внутри render tick:
                // UI остаётся thread-safe/управляемым: все изменения истории происходят в одном месте.
                $completed = $this->jobs->drainCompleted();
                if ($completed !== []) {
                    foreach ($completed as $entry) {
                        $this->history->append($entry);
                    }
                    // История изменилась — нужен полный кадр (рамки/скролл/кол-во строк может измениться).
                    $this->fullRedraw = true;
                }
            }

            // Проверяем, не изменился ли размер терминала.
            $this->checkResize();

            // Синхронизируем mouse reporting с текущим состоянием (по умолчанию OFF).
            if ($this->mouseModeEnabled) {
                $terminalMode->enableMouseX10();
            } else {
                $terminalMode->disableMouseX10();
            }

            // Выполняем перерисовку, если необходимо.
            if ($this->fullRedraw) {
                $this->renderFull($renderer);
                $this->fullRedraw = false;
            } else {
                $this->renderPartial($renderer);
            }

            // Post-hook должен вызываться после фактического рендера кадра.
            $this->runPendingPostHookIfAny();
        });

        // Watcher ввода.
        // Callback вызывается event loop'ом, когда STDIN readable.
        // Мы читаем из потока и преобразуем в KeyEventDto.
        //
        // Замечание про “256 событий”:
        // - STDIN non-blocking, поэтому parser может вернуть null (данных не хватает) — прекращаем.
        // - при удержании клавиши или при вставке (особенно bracketed paste) за один readable callback
        //   может накопиться много байтов. Чтение батчами уменьшает input-latency.
        $readableId = EventLoop::onReadable($stdin, function (string $id, $stream) use ($parser, $reducer, $suspension): void {
            if (!$this->running) {
                // Если цикл уже остановлен — снимаем watcher и выходим из suspend.
                EventLoop::cancel($id);
                $suspension->resume(null);
                return;
            }

            // Читаем максимально возможное число событий из non-blocking потока за один callback,
            // чтобы уменьшить задержки при удержании клавиш и при вставке.
            for ($i = 0; $i < 256; $i++) {
                $event = $parser->readEvent($stream);
                if ($event === null) {
                    break;
                }

                // Применение KeyEventDto к state через reducer.
                // Внутри `applyKeyEvent()` может произойти:
                // - обновление input buffer/cursor;
                // - прокрутка output;
                // - submit по Enter -> запуск pre-hook (sync/async jobs);
                // - остановка цикла (`running=false`) по Ctrl+C или /exit.
                $this->applyKeyEvent($event, $reducer);
                if (!$this->running) {
                    // Немедленный выход: cancel watcher и resume suspend.
                    // Это обеспечивает быстрый shutdown без ожидания следующего render tick.
                    EventLoop::cancel($id);
                    $suspension->resume(null);
                    return;
                }
            }
        });

        // Первичный кадр рисуем максимально быстро.
        EventLoop::defer(function (): void {
            // Помечаем UI “грязным” — следующий render tick выполнит full render.
            $this->fullRedraw = true;
        });

        // Входим в event loop и остаёмся в нём, пока не будет вызван `resume()`.
        // `resume()` происходит при `running=false` (см. callbacks выше).
        $suspension->suspend();

        // Cleanup watchers.
        // Очень важно отменить repeat/onReadable, иначе они будут удерживать loop и ресурсы.
        // В `finally` выше (в execute()) гарантированно восстановятся режимы терминала.
        EventLoop::cancel($readableId);
        EventLoop::cancel($renderTickId);

        // Закрываем STDIN (TUI завершён).
        fclose($stdin);
    }

    /**
     * Полная перерисовка всего экрана.
     *
     * @param TuiRenderer $renderer
     * @return void
     */
    private function renderFull(TuiRenderer $renderer): void
    {
        $this->terminal = new Terminal();
        $size           = new TerminalSizeDto($this->terminal->getWidth(), $this->terminal->getHeight());
        $layout         = $this->buildLayoutDto();
        $state          = $this->buildStateDto();
        $state          = $renderer->renderFull($state, $layout, $size, $this->statusBar);
        $this->applyStateDto($state);
    }

    /**
     * Частичная перерисовка.
     *
     * @param TuiRenderer $renderer
     * @return void
     */
    private function renderPartial(TuiRenderer $renderer): void
    {
        $this->terminal = new Terminal();
        $size           = new TerminalSizeDto($this->terminal->getWidth(), $this->terminal->getHeight());
        $layout         = $this->buildLayoutDto();
        $state          = $this->buildStateDto();
        $state          = $renderer->renderPartial($state, $layout, $size, $this->statusBar);
        $this->applyStateDto($state);
    }

    /**
     * Применяет нормализованное событие клавиатуры к текущему состоянию.
     *
     * @param KeyEventDto $event
     * @param TuiReducer  $reducer
     */
    private function applyKeyEvent(KeyEventDto $event, TuiReducer $reducer): void
    {
        $state  = $this->buildStateDto();
        $layout = $this->buildLayoutDto();
        $result = $reducer->applyKeyEventWithResult($state, $event, $layout);
        $this->applyStateDto($result->getState());

        $submittedInput = $result->getSubmittedInput();
        if ($submittedInput !== null) {
            $decision = $this->getPreHook()->decide($submittedInput);
            $this->applyPreHookDecision($decision);
        }

          // Clamp прокрутку после любых изменений.
        $this->terminal     = new Terminal();
        $maxScroll          = $this->getMaxOutputScroll();
        $this->outputScroll = min($this->outputScroll, $maxScroll);
    }

    /**
     * Инициализирует pre/post hooks.
     *
     * @return void
     */
    private function initHooks(): void
    {
        if ($this->jobs === null) {
            $this->jobs = new TuiJobManager();
        }

        if ($this->preHook === null) {
            $this->preHook = new WorkspaceTuiPreOutputHook($this->getDispatcher(), $this->jobs);
        }
        if ($this->postHook === null) {
            $this->postHook = new \quanzo\tui\classes\tui\hooks\DefaultTuiPostOutputHook();
        }
    }

    /**
     * Возвращает dispatcher, гарантируя его инициализацию.
     *
     * По умолчанию dispatcher пустой — handlers должны быть добавлены через `addHandler()`.
     *
     * @return TuiCommandDispatcher
     */
    private function getDispatcher(): TuiCommandDispatcher
    {
        if ($this->dispatcher === null) {
            $this->dispatcher = new TuiCommandDispatcher([]);
        }

        return $this->dispatcher;
    }

    /**
     * Возвращает актуальный pre-hook, гарантируя его инициализацию.
     *
     * @return TuiPreOutputHookInterface
     */
    private function getPreHook(): TuiPreOutputHookInterface
    {
        if ($this->preHook === null) {
            $this->initHooks();
        }
        /** @var TuiPreOutputHookInterface $hook */
        $hook = $this->preHook;
        return $hook;
    }

    /**
     * Возвращает актуальный post-hook, гарантируя его инициализацию.
     *
     * @return TuiPostOutputHookInterface
     */
    private function getPostHook(): TuiPostOutputHookInterface
    {
        if ($this->postHook === null) {
            $this->initHooks();
        }
        /** @var TuiPostOutputHookInterface $hook */
        $hook = $this->postHook;
        return $hook;
    }

    /**
     * Применяет решение pre-hook: добавляет entries/управляет TUI.
     *
     * @param TuiPreHookDecisionDto $decision
     * @return void
     */
    private function applyPreHookDecision(TuiPreHookDecisionDto $decision): void
    {
        if ($decision->isClearHistory()) {
            $this->history->clear();
        }

        $append = $decision->getAppendEntries();
        foreach ($append as $entry) {
            $this->history->append($entry);
        }

        if ($decision->isExit()) {
            $this->running = false;
        }

        $this->terminal     = new Terminal();
        $this->outputScroll = $this->getMaxOutputScroll();
        $this->fullRedraw   = true;

        $renderedOutput = '';
        if (!empty($append)) {
            $last           = end($append);
            $renderedOutput = (string) ($last?->getPlainText() ?? '');
        }

        $this->pendingPostHookContext = new PostOutputContextDto(
            originalInput : $decision->getOriginalInput(),
            renderedOutput: $renderedOutput,
        );
    }

    /**
     * Выполняет post-hook, если он запланирован после предыдущего вывода.
     *
     * @return void
     */
    private function runPendingPostHookIfAny(): void
    {
        if ($this->pendingPostHookContext === null) {
            return;
        }

        $ctx = $this->pendingPostHookContext;
        $this->pendingPostHookContext = null;

        $extra = $this->getPostHook()->afterRender($ctx);
        if ($extra === null || $extra === '') {
            return;
        }

        $this->history->appendOutput($extra);
        $this->terminal     = new Terminal();
        $this->outputScroll = $this->getMaxOutputScroll();
        $this->fullRedraw   = true;
    }

    /**
     * Собирает DTO состояния из полей команды.
     */
    private function buildStateDto(): TuiStateDto
    {
        return (new TuiStateDto())
            ->setHeaderTitle((string) ($this->getName() ?: 'interactive'))
            ->setHistory($this->history)
            ->setInputBuffer($this->inputBuffer)
            ->setCursorOffset($this->cursorOffset)
            ->setInputViewportTopLine($this->inputViewportTopLine)
            ->setInputHistory($this->inputHistory)
            ->setInputHistoryIndex($this->inputHistoryIndex)
            ->setInputHistoryDraftBuffer($this->inputHistoryDraftBuffer)
            ->setInputHistoryDraftCursorOffset($this->inputHistoryDraftCursorOffset)
            ->setMouseModeEnabled($this->mouseModeEnabled)
            ->setOutputScroll($this->outputScroll)
            ->setFocus($this->focus)
            ->setRunning($this->running)
            ->setFullRedraw($this->fullRedraw)
            ->setPrevInputLines($this->prevInputLines)
            ->setPrevStatusLine($this->prevStatusLine)
            ->setPrevWidth($this->prevWidth)
            ->setPrevHeight($this->prevHeight);
    }

    /**
     * Применяет DTO состояния к полям команды.
     */
    private function applyStateDto(TuiStateDto $state): void
    {
        $this->history                       = $state->getHistory();
        $this->inputBuffer                   = $state->getInputBuffer();
        $this->cursorOffset                  = $state->getCursorOffset();
        $this->inputViewportTopLine          = $state->getInputViewportTopLine();
        $this->inputHistory                  = $state->getInputHistory();
        $this->inputHistoryIndex             = $state->getInputHistoryIndex();
        $this->inputHistoryDraftBuffer       = $state->getInputHistoryDraftBuffer();
        $this->inputHistoryDraftCursorOffset = $state->getInputHistoryDraftCursorOffset();
        $this->mouseModeEnabled              = $state->isMouseModeEnabled();
        $this->outputScroll                  = $state->getOutputScroll();
        $this->focus                         = $state->getFocus();
        $this->running                       = $state->isRunning();
        $this->fullRedraw                    = $state->isFullRedraw();
        $this->prevInputLines                = $state->getPrevInputLines();
        $this->prevStatusLine                = $state->getPrevStatusLine();
        $this->prevWidth                     = $state->getPrevWidth();
        $this->prevHeight                    = $state->getPrevHeight();
    }

    /**
     * Собирает DTO геометрии из текущего размера терминала.
     */
    private function buildLayoutDto(): LayoutDto
    {
        return new LayoutDto(
            outputContentStart: $this->getOutputContentStart(),
            outputContentEnd  : $this->getOutputContentEnd(),
            inputContentStart : $this->getInputContentStart(),
            inputContentEnd   : $this->getInputContentEnd(),
            statusLine        : $this->getStatusLine(),
        );
    }

    /**
     * Проверяет изменение размера терминала и при необходимости ставит флаг полной перерисовки.
     */
    private function checkResize(): void
    {
        // Создаём новый объект Terminal, чтобы получить актуальные размеры
        $this->terminal = new Terminal();
        $w = $this->terminal->getWidth();
        $h = $this->terminal->getHeight();
        if ($w !== $this->prevWidth || $h !== $this->prevHeight) {
            $this->prevWidth = $w;
            $this->prevHeight = $h;
            $this->fullRedraw = true;
        }
    }

    // (Обработка клавиш и изменение состояния вынесены в TuiReducer)

    // ==================== МЕТОДЫ ГЕОМЕТРИИ ====================
    // Все эти методы используют актуальную высоту терминала ($this->terminal->getHeight())
    // и позволяют адаптировать интерфейс при изменении размера окна.

    /**
     * Номер строки, с которой начинается содержимое области вывода (первая строка после верхней рамки).
     * @return int
     */
    private function getOutputContentStart(): int
    {
        return 2; // всегда 2, потому что 1-я строка – верхняя граница
    }

    /**
     * Номер строки, на которой заканчивается содержимое области вывода (последняя строка перед нижней рамкой).
     * @return int
     */
    private function getOutputContentEnd(): int
    {
        // Высота терминала минус 7: (статус 1 + поле ввода 3 + верхняя граница поля 1 + нижняя граница поля 1 + нижняя граница вывода 1)
        return $this->terminal->getHeight() - 7;
    }

    /**
     * Количество строк, доступных для отображения содержимого области вывода.
     * @return int
     */
    private function getOutputContentLines(): int
    {
        return max(0, $this->getOutputContentEnd() - $this->getOutputContentStart() + 1);
    }

    /**
     * Номер строки, на которой располагается нижняя граница области вывода.
     * @return int
     */
    private function getOutputBottomBorder(): int
    {
        return $this->terminal->getHeight() - 6;
    }

    /**
     * Номер строки, на которой располагается верхняя граница поля ввода.
     * @return int
     */
    private function getInputTopBorder(): int
    {
        return $this->terminal->getHeight() - 5;
    }

    /**
     * Номер строки, с которой начинается содержимое поля ввода (первая строка после верхней рамки).
     * @return int
     */
    private function getInputContentStart(): int
    {
        return $this->terminal->getHeight() - 4;
    }

    /**
     * Номер строки, на которой заканчивается содержимое поля ввода (последняя строка перед нижней рамкой).
     * @return int
     */
    private function getInputContentEnd(): int
    {
        return $this->terminal->getHeight() - 2;
    }

    /**
     * Номер строки, на которой располагается нижняя граница поля ввода.
     * @return int
     */
    private function getInputBottomBorder(): int
    {
        return $this->terminal->getHeight() - 1;
    }

    /**
     * Номер строки, на которой располагается строка состояния.
     * @return int
     */
    private function getStatusLine(): int
    {
        return $this->terminal->getHeight();
    }

    /**
     * Возвращает максимально возможное смещение прокрутки для текущей истории и размера области вывода.
     *
     * @return int
     */
    private function getMaxOutputScroll(): int
    {
        // Должно совпадать с safe-width логикой TuiRenderer (не рисуем в последней колонке).
        $innerWidth = $this->terminal->getWidth() - 3;
        $formatter = new TuiHistoryFormatter();
        $displayLines = $formatter->toDisplayLines($this->history, $innerWidth, new TuiThemeDto());
        $visibleLines = $this->getOutputContentLines();
        return max(0, count($displayLines) - $visibleLines);
    }
}
