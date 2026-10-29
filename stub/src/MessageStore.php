<?php

declare(strict_types=1);

namespace MailChannelsStub;

/**
 * Stores send payloads received by the stub. In-memory by default (tests);
 * pass a file path to share messages across `php -S` requests.
 */
final class MessageStore
{
    /** @var list<array<string, mixed>> */
    private array $memory = [];

    public function __construct(private readonly ?string $path = null) {}

    /** @param array<string, mixed> $entry */
    public function add(array $entry): void
    {
        $this->mutate(function (array $messages) use ($entry): array {
            $messages[] = $entry;

            return $messages;
        });
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->mutate(fn (array $messages): array => $messages);
    }

    public function clear(): void
    {
        $this->mutate(fn (): array => []);
    }

    /**
     * Read, transform and write the list under an exclusive lock.
     *
     * @param  callable(list<array<string, mixed>>): list<array<string, mixed>>  $change
     * @return list<array<string, mixed>>
     */
    private function mutate(callable $change): array
    {
        if ($this->path === null) {
            return $this->memory = $change($this->memory);
        }

        $handle = fopen($this->path, 'c+');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open stub message store {$this->path}");
        }
        try {
            flock($handle, LOCK_EX);
            $raw = stream_get_contents($handle);
            $messages = $raw ? (json_decode($raw, true) ?: []) : [];
            $updated = $change($messages);
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($updated, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            fflush($handle);

            return $updated;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
