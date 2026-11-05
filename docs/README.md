## Документация

Эта директория содержит справочную документацию для standalone‑репозитория TUI.

### Быстрый старт

- Запуск списка команд:

```bash
./bin/console list
```

- Запуск TUI:

```bash
./bin/console interactive
```

### Содержание

- `docs/console.md` — консольные команды и точка входа.
- `docs/tui.md` — архитектура и поведение TUI (ввод → reducer → render), обработчики команд `/help` и т.д.
- Пользовательская справка: `/help` внутри TUI и `./bin/console help interactive`. Экран, режимы и клавиши — `src/classes/tui/help/TuiUserGuide.php`. Текст команд — `getHelp()` каждого handler.

