## Консольные команды

Этот документ описывает консольный интерфейс репозитория `/tui` на базе Symfony Console.

### Точка входа

- Скрипт запуска: `bin/console`
- Реальная точка входа PHP: `bin/console.php`
- Autoload: `vendor/autoload.php` (Composer)

Быстрые команды:

```bash
./bin/console list
./bin/console help interactive
./bin/console interactive
```

### Приложение Symfony Console

В `bin/console.php` создаётся `Symfony\Component\Console\Application` и регистрируется TUI-команда:

- `interactive` — запускает TUI

Встроенные команды Symfony (могут отличаться по версии):

- `list`
- `help`
- `completion`

### Команда `interactive`

Класс: `src/command/InteractiveCommand.php` (`quanzo\tui\command\InteractiveCommand`)

Назначение: запустить интерактивный TUI (область вывода + многострочный ввод + статус-бар).

Запуск:

```bash
./bin/console interactive
```

#### Команды внутри TUI

Как устроен экран, режимы и клавиши — в `./bin/console help interactive` и, уже внутри TUI, в `/help`. Экран, режимы и клавиши: `src/classes/tui/help/TuiUserGuide.php`. Строки команд: `getHelp()` handlers (см. также `docs/tui.md`).

Список команд:

- `/help`
- `/ws` и `/ws ls`
- `/ws help`
- `/long` и `/long 10`
- `/exec ls -la`
- `/jobs`
- `/break` и `/break job_1`
- `/clear`
- `/exit`

### Отладка и диагностика

#### Проверка списка команд

```bash
./bin/console list
```

#### Проверка, что TUI-команда зарегистрирована

```bash
./bin/console help interactive
```

#### Важно про запуск в non-interactive режиме

TUI использует `stty` и raw-input режим. Если запускать команду через пайп, например:

```bash
printf "/exit\n" | ./bin/console interactive
```

то `stty` может вывести `Inappropriate ioctl for device`, потому что stdin не является TTY.
Это ожидаемо и не является целевым сценарием использования.

