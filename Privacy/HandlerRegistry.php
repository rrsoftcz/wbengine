<?php

namespace Wbengine\Privacy;

final class HandlerRegistry
{
    /** @var array<string, HandlerInterface> */
    private array $handlers = [];

    /** @param iterable<HandlerInterface> $handlers */
    public function __construct(iterable $handlers = [])
    {
        foreach ($handlers as $handler) {
            if (!$handler instanceof HandlerInterface) {
                throw new \InvalidArgumentException('Privacy handlers must implement HandlerInterface.');
            }
            if (isset($this->handlers[$handler->name()])) {
                throw new \InvalidArgumentException('Privacy handler names must be unique.');
            }
            $this->handlers[$handler->name()] = $handler;
        }
    }

    /** @return array<string, array{actions: array, fingerprint: string, blocked: bool}> */
    public function inspect(int $userId): array
    {
        $findings = [];
        foreach ($this->handlers as $name => $handler) {
            $finding = $handler->inspect($userId);
            if (!isset($finding['actions'], $finding['fingerprint'], $finding['blocked'])
                || !is_array($finding['actions']) || !is_string($finding['fingerprint'])
                || !is_bool($finding['blocked'])) {
                throw new \UnexpectedValueException('Privacy handler returned an invalid finding.');
            }
            $findings[$name] = $finding;
        }
        return $findings;
    }

    public function erase(int $userId, array $fingerprints): void
    {
        foreach ($this->handlers as $name => $handler) {
            if (!isset($fingerprints[$name]) || !is_string($fingerprints[$name])) {
                throw new \RuntimeException('The inspection has changed. Run privacy:inspect again.');
            }
            $handler->erase($userId, $fingerprints[$name]);
        }
    }
}
