<?php
namespace Wbengine\Console;

/**
 * Reads a password for the CLI without ever taking it from an argument or the environment.
 * On a terminal it is read without echo and confirmed; otherwise a single line is read
 * from the input stream, which allows `printf '%s\n' "$PW" | wbengine.php user:create ...`.
 */
final class PasswordPrompt
{
    /** @var resource */
    private $input;
    /** @var resource */
    private $output;
    private ?bool $tty;
    /** @var callable(string): string */
    private $stty;

    /**
     * @param resource|null $input  defaults to STDIN
     * @param resource|null $output prompts go here, defaults to STDERR so stdout stays JSON
     * @param bool|null $tty        null detects a terminal on the input
     * @param callable|null $stty   runs `stty <argument>` and returns its output, for tests
     */
    public function __construct($input = null, $output = null, ?bool $tty = null, ?callable $stty = null)
    {
        $this->input = $input ?? STDIN;
        $this->output = $output ?? STDERR;
        $this->tty = $tty;
        $this->stty = $stty ?? static fn (string $argument): string => (string) shell_exec('stty ' . $argument . ' 2>/dev/null');
    }

    public function read(): string
    {
        $tty = $this->tty ?? (function_exists('stream_isatty') && stream_isatty($this->input));
        if (!$tty) {
            return $this->line();
        }
        $first = $this->silent('Password: ');
        $second = $this->silent('Repeat password: ');
        if (!hash_equals($first, $second)) {
            throw new CommandException('The two passwords do not match.');
        }
        return $first;
    }

    /** Exactly one line, without its line ending. */
    private function line(): string
    {
        $line = fgets($this->input, 4096 + 2);
        if ($line === false) {
            throw new CommandException('No password supplied on standard input.');
        }
        return preg_replace('/\r?\n\z/', '', $line);
    }

    private function silent(string $prompt): string
    {
        $saved = trim(($this->stty)('-g'));
        if ($saved === '') {
            throw new CommandException('Cannot disable terminal echo. Pipe the password on standard input instead.');
        }
        $restore = function () use ($saved): void { ($this->stty)(escapeshellarg($saved)); };
        $async = null;
        if (function_exists('pcntl_signal') && function_exists('pcntl_async_signals')) {
            $async = pcntl_async_signals(true);
            $handler = function () use ($restore): void { $restore(); fwrite($this->output, "\n"); exit(130); };
            pcntl_signal(SIGINT, $handler);
            pcntl_signal(SIGTERM, $handler);
        }
        try {
            fwrite($this->output, $prompt);
            ($this->stty)('-echo');
            try {
                return $this->line();
            } finally {
                $restore();
                fwrite($this->output, "\n");
            }
        } finally {
            if ($async !== null) {
                pcntl_signal(SIGINT, SIG_DFL);
                pcntl_signal(SIGTERM, SIG_DFL);
                pcntl_async_signals($async);
            }
        }
    }
}
