<?php

declare(strict_types=1);

namespace quanzo\tui\classes\dto\tui\command;

/**
 * Параметры подкоманды `/ws`: токены строки после имени подкоманды.
 *
 * Кавычки уже сняты парсером ввода. Пустой список означает, что после имени ничего не передали.
 *
 * Пример использования:
 *
 * ```php
 * $parameters = new WorkspaceSubcommandParametersDto(['src', '--all']);
 * $path = $parameters->get(0, '.');
 * ```
 */
final class WorkspaceSubcommandParametersDto
{
    /** @var list<string> */
    private array $tokens;

    /**
     * @param list<string> $tokens Позиционные токены после имени подкоманды
     */
    public function __construct(array $tokens)
    {
        $copy = [];
        foreach (array_values($tokens) as $token) {
            $copy[] = $token;
        }
        $this->tokens = $copy;
    }

    /**
     * Возвращает копию списка токенов.
     *
     * @return list<string>
     */
    public function getTokens(): array
    {
        $copy = [];
        foreach ($this->tokens as $token) {
            $copy[] = $token;
        }

        return $copy;
    }

    /**
     * Возвращает токен по индексу или значение по умолчанию, если индекса нет.
     *
     * @param int $index Индекс с нуля. Отрицательный индекс считается отсутствующим.
     * @param string $default Значение, если токена нет
     * @return string
     */
    public function get(int $index, string $default = ''): string
    {
        if ($index < 0) {
            return $default;
        }

        return $this->tokens[$index] ?? $default;
    }

    /**
     * Возвращает число токенов.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->tokens);
    }
}
