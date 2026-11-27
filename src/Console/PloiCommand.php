<?php

declare(strict_types=1);

namespace Siberfx\PloiLaravel\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Siberfx\PloiLaravel\Exceptions\PloiException;
use Siberfx\PloiLaravel\Exceptions\ValidationException;
use Siberfx\PloiLaravel\PloiClient;
use Siberfx\PloiLaravel\PloiManager;

/**
 * Shared --connection option and error output for the ploi:* commands.
 */
abstract class PloiCommand extends Command
{
    public function handle(PloiManager $ploi): int
    {
        try {
            return $this->handlePloi($ploi->connection($this->connectionOption()));
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::INVALID;
        } catch (PloiException $e) {
            $this->components->error($e->getMessage());

            if ($e instanceof ValidationException) {
                foreach ($e->errors() as $field => $messages) {
                    $this->components->bulletList(array_map(fn ($m) => "{$field}: {$m}", (array) $messages));
                }
            }

            return self::FAILURE;
        }
    }

    abstract protected function handlePloi(PloiClient $ploi): int;

    protected function connectionOption(): ?string
    {
        $value = $this->option('connection');

        return is_string($value) && $value !== '' ? $value : null;
    }

    protected function intArgument(string $name): int
    {
        $value = $this->argument($name);

        if (! is_numeric($value) || (int) $value < 1) {
            throw new InvalidArgumentException("The {$name} argument must be a positive Ploi ID.");
        }

        return (int) $value;
    }

    /**
     * Render rows as a table, or as JSON with --json.
     *
     * @param  array<int, string>  $columns
     * @param  iterable<int, mixed>  $items
     */
    protected function renderList(array $columns, iterable $items): void
    {
        $rows = [];

        foreach ($items as $item) {
            $rows[] = is_array($item) ? $item : (array) $item;
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return;
        }

        if ($rows === []) {
            $this->components->info('Nothing found.');

            return;
        }

        $this->table(
            $columns,
            array_map(fn (array $row) => array_map(
                fn (string $column) => self::cell(data_get($row, $column)),
                $columns,
            ), $rows),
        );
    }

    private static function cell(mixed $value): string
    {
        return match (true) {
            $value === null => '-',
            is_bool($value) => $value ? 'yes' : 'no',
            is_scalar($value) => (string) $value,
            default => (string) json_encode($value),
        };
    }
}
