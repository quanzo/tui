## TUI (InteractiveCommand)

Этот документ описывает внутреннюю архитектуру интерактивного TUI-интерфейса команды `interactive` в репозитории `/tui`.

### Назначение

Команда `interactive` (`src/classes/command/InteractiveCommand.php`) предоставляет текстовый UI:

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
  - включает `stty -icanon -echo`, alt-buffer, скрывает курсор;
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
$interactive = (new \quanzo\tui\classes\command\InteractiveCommand())
    ->setCommandName('interactive')
    ->setDescriptionText('Интерактивный TUI (workspace)')
    ->addHandler(new \quanzo\tui\classes\tui\command\handlers\HelpCommandHandler())
    ->addHandler(new \quanzo\tui\classes\tui\command\handlers\WorkspaceCommandHandler())
    ->addHandler(new \quanzo\tui\classes\tui\command\handlers\ClearCommandHandler())
    ->addHandler(new \quanzo\tui\classes\tui\command\handlers\ExitCommandHandler())
    ->setPostHook(new \quanzo\tui\classes\tui\hooks\DefaultTuiPostOutputHook());
```

### Команды внутри TUI

Текущий набор TUI-команд (handlers) в `/tui`:

- **`/help`**: справка
- **`/ws ls`**: показать содержимое текущей директории (ограниченный список)
- **`/clear`**: очистить историю
- **`/exit`**: выйти из TUI

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

