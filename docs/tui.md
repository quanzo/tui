## TUI (InteractiveCommand)

Этот документ описывает внутреннюю архитектуру интерактивного TUI-интерфейса команды `interactive` в репозитории `/tui`.

### Назначение

Команда `interactive` (`src/command/InteractiveCommand.php`, namespace `quanzo\tui\command`) предоставляет текстовый UI:

- область вывода (история сообщений) с прокруткой;
- многострочное поле ввода (viewport 3 строки) с редактированием;
- строка состояния (mode/cursor/history count);
- переключение фокуса `Tab` (ввод/просмотр).

### Запуск

TUI запускается как Symfony Console команда:

```bash
./bin/console interactive
```

### Архитектура (компоненты)

- **Terminal режимы**: `src/classes/tui/terminal/TerminalModeManager.php`
  - включает `stty -icanon -echo -icrnl -ixon`, alt-buffer, показывает курсор;
  - включает **Bracketed Paste Mode** (`ESC[?2004h`) для корректной многострочной вставки;
  - обязательно использовать через `try/finally`, чтобы терминал гарантированно восстановился.

- **Ввод**: `src/classes/tui/input/`
  - `Utf8CharReader` — читает один UTF‑8 символ из потока;
  - `KeySequenceParser` — превращает поток в события `KeyEventDto`:
    - стрелки, PageUp/PageDown, Tab, Enter, Backspace, Delete, Home/End, Ctrl+C, текст;
    - **bracketed paste**: `ESC[200~...ESC[201~` → `TYPE_PASTE`.

- **Состояние**: `src/classes/dto/tui/`
  - `TuiStateDto` — модель состояния (history/input/cursor/focus/scroll + поля для partial redraw);
  - `LayoutDto` — вычисленная геометрия (координаты областей);
  - `TerminalSizeDto` — размеры терминала (width/height);
  - `KeyEventDto` — нормализованное событие клавиатуры.

- **Обработка событий (reducer)**: `src/classes/tui/state/TuiReducer.php`
  - единственное место, где описаны правила изменения `TuiStateDto` по `KeyEventDto`;
  - важно: логика остановки по `Ctrl+C` обрабатывается до вставки текста.

- **История (Variant C)**: `src/classes/dto/tui/history/`
  - `TuiHistoryDto` — список записей `TuiHistoryEntryDto`;
  - `TuiHistoryEntryDto` — атом истории (user_input/output/event) с `blocks` (виджетами) и `meta`.

- **Rich-вывод (виджеты)**:
  - `src/interfaces/tui/view/TuiBlockInterface.php` — контракт блока;
  - `src/classes/dto/tui/view/blocks/*` — блоки: `Text/Heading/Panel/Table/List/Code/Notice/Divider/KeyHints`;
  - `src/classes/tui/render/TuiHistoryFormatter.php` — форматтер entries/blocks → плоские строки;
  - `src/classes/dto/tui/view/TuiThemeDto.php` — ANSI-тема.

- **Hooks вывода (pre/post)**:
  - `src/interfaces/tui/TuiPreOutputHookInterface.php` — решает, какие entries добавить в историю и как управлять циклом (`clear/exit`);
  - `src/classes/dto/tui/TuiPreHookDecisionDto.php` — DTO решения pre-hook;
  - `src/interfaces/tui/TuiPostOutputHookInterface.php` — вызывается после рендера кадра, может дописать дополнительный многострочный вывод в history;
  - дефолтные реализации:
    - `src/classes/tui/hooks/WorkspaceTuiPreOutputHook.php` — собственная система команд (parser → dispatcher → handlers);
    - `src/classes/tui/hooks/DefaultTuiPostOutputHook.php` — вывод после кадра (например, дата/время).

- **Отрисовка**: `src/classes/tui/render/TuiRenderer.php`
  - full render (очистка и полная отрисовка рамок/контента/курсора);
  - partial render (обновление только изменившихся строк ввода и статус-бара);
  - для переносов/выравнивания использует:
    - `src/helpers/TuiTextHelper.php`
    - `src/helpers/TuiInputBufferHelper.php`

### Wiring handlers (рекомендованный способ)

В `/tui` handlers регистрируются через `InteractiveCommand`, чтобы точка входа `bin/console.php` оставалась минимальной:

```php
$jobs = new \quanzo\tui\classes\tui\async\TuiJobManager();

$interactive = (new \quanzo\tui\command\InteractiveCommand())
    ->setCommandName('interactive')
    ->setDescriptionText('Интерактивный TUI (workspace)')
    ->setJobManager($jobs)
    ->addHandler(new \quanzo\tui\handlers\HelpCommandHandler())
    ->addHandler(new \quanzo\tui\handlers\WorkspaceCommandHandler())
    ->addHandler(new \quanzo\tui\handlers\ClearCommandHandler())
    ->addHandler(new \quanzo\tui\handlers\BreakCommandHandler($jobs))
    ->addHandler(new \quanzo\tui\handlers\LongCommandHandler())
    ->addHandler(new \quanzo\tui\handlers\ExitCommandHandler())
    ->setPostHook(new \quanzo\tui\classes\tui\hooks\DefaultTuiPostOutputHook());
```

### Команды внутри TUI

Текущий набор TUI-команд (handlers) в `/tui`:

- **`/help`**: справка
- **`/ws ls`**: показать содержимое текущей директории (ограниченный список)
- **`/long <seconds>`**: пример длительной async-команды (параллельно можно выполнять другие команды)
- **`/exec <command...>`**: запустить shell-команду (вывод появится после завершения)
- **`/break [jobId]`**: отменить последнюю активную задачу или конкретную по `jobId`
- **`/jobs`**: список async-задач (активные + последние завершённые)
- **`/clear`**: очистить историю
- **`/exit`**: выйти из TUI

### Async-команды (Amp v3) и отмена /break

В `/tui` поддерживаются асинхронные команды на базе **Amp v3** и **Revolt EventLoop**:

- **UI не блокируется**: async-handler возвращает `Future`, а результат попадает в историю, когда `Future` завершится.\n
- **Параллельные задачи**: можно запускать несколько команд подряд — они выполняются одновременно.\n
- **Отмена**: `/break` запрашивает cancellation для задачи.\n
  - это **кооперативная отмена** (Amp cancellation): команда должна реагировать на cancellation;\n
  - best-effort “жёсткая” отмена возможна только если конкретная задача умеет прерывать внешний ресурс.\n

Ключевые компоненты:
- `src/interfaces/tui/command/AsyncTuiCommandHandlerInterface.php` — контракт async handler.\n
- `src/classes/tui/async/TuiJobManager.php` — менеджер задач: start/break/drainCompleted.\n
- `src/classes/tui/hooks/WorkspaceTuiPreOutputHook.php` — стартует async job и возвращает entry “Запущено …”.\n
- `src/command/InteractiveCommand.php` — периодически вызывает `drainCompleted()` и добавляет entries в историю.\n

Дополнительно:
- **`/jobs`** печатает таблицу задач: активные (с `runningFor`) и последние завершённые (с `duration`).\n
- После завершения (успех/отмена/ошибка) TUI добавляет служебное сообщение с **временем выполнения** задачи.\n

### Команда /exec (shell)

`/exec` запускает shell-команду асинхронно через `bash -lc` и добавляет итоговый результат в history после завершения.\n

Примеры:

```bash
/exec ls -la
/exec php -v
/exec sleep 3 && echo done
```

Отмена:
- `/break` отменяет задачу `/exec` (cancellation + best-effort SIGTERM/SIGKILL).\n
- `jobId` можно посмотреть через `/jobs`.\n

### UX: ввод, Ctrl+Enter и история команд

- **Заголовок сверху**: по центру первой строки рамки выводится заголовок (сейчас — имя команды, например `interactive`).
- **Новая строка без отправки**: **Ctrl+Enter** во многих терминалах не отличается от Enter, поэтому используем **Ctrl+J** (LF) как эквивалент Ctrl+Enter.\n
  - Ctrl+J вставляет `\\n` в input (multi-line) и **не отправляет** сообщение.\n
  - Enter (CR) — отправляет введённый буфер в обработку.\n
- **История команд**: в первой строке input стрелки **Up/Down** работают как в консоли:\n
  - Up подставляет предыдущую отправленную команду;\n
  - Down листает вперёд и на «конце истории» возвращает черновик, который был до входа в history.\n

### Поток данных

```mermaid
flowchart TD
InteractiveCommand -->|stdin| KeySequenceParser
KeySequenceParser -->|KeyEventDto| TuiReducer
TuiReducer -->|ReducerResultDto| InteractiveCommand
InteractiveCommand -->|PreHook| HistoryEntries
HistoryEntries -->|Formatter| TuiHistoryFormatter
InteractiveCommand -->|TuiStateDto,LayoutDto,TerminalSizeDto| TuiRenderer
TuiRenderer -->|ANSI output| Terminal
InteractiveCommand -->|PostHook (after render)| HistoryEntries
```

### Порядок вызовов pre/post hooks

- при Enter `TuiReducer` возвращает `submittedInput` (но сам ничего не пишет в историю);
- `InteractiveCommand` вызывает **pre-hook**, который возвращает `TuiPreHookDecisionDto`:
  - entries для добавления;
  - флаги `clearHistory/exit`;
- `InteractiveCommand` применяет решение (очистка/append/exit);
- выполняется фактическая отрисовка кадра (`renderFull`/`renderPartial`);
- **после рендера** `InteractiveCommand` вызывает **post-hook** и, если тот вернул текст, добавляет его в history;
- добавленное post-hook будет видно на **следующем кадре** (т.к. хук вызывается именно после рендера).

### Ограничения и edge cases

- При запуске без TTY (например, `printf \"/exit\\n\" | ./bin/console interactive`) `stty` может сообщить `Inappropriate ioctl for device`. Это нормально для non-interactive пайпов; для реального использования запускайте в настоящем терминале.
- Если после выхода из TUI терминал «сломался» (не отображается ввод), причина обычно в некорректном восстановлении `stty` режима. В `TerminalModeManager` используется snapshot `stty -g` с нормализацией и fallback на `stty icanon echo`.

### Где лежат команда и handlers

- Консольная команда: `src/command/InteractiveCommand.php`, namespace `quanzo\tui\command`.
- Handlers TUI (`/help`, `/ws`, `/exec`, …): `src/handlers/`, namespace `quanzo\tui\handlers`.
- Парсер и диспетчер остаются в `src/classes/tui/command/`.

### Unit-тесты

Запуск: `./vendor/bin/phpunit`. Bash из тестов не вызывается. Покрыты чистые компоненты: парсер ввода, текстовые хелперы, буфер ввода, reducer, разбор клавиш и UTF-8, форматтер истории, pre-hook и sync-handlers.

Сознательно оставленное текущее поведение (тесты его фиксируют, код не менялся):

- `/exec` склеивает аргументы пробелом. `/exec ls "my file"` уходит в shell как `ls my file`. Токен `&&` при этом остаётся оператором.
- `/ws ls` при несуществующем каталоге показывает пустую таблицу, без отдельной ошибки.
- `TuiTextHelper::trimToWidthWithEllipsis()` с многоточием шире лимита возвращает строку шире лимита (`'...'` при ширине 1).
- `/long` с мусором (`abc`, `0`, `-1`) выполняется 5 секунд; значение выше 600 обрезается до 600. В тестах секунды читаются через reflection, без ожидания.


