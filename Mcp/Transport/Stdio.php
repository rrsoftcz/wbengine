<?php

namespace Wbengine\Mcp\Transport;

use Wbengine\Mcp\Protocol;
use Wbengine\Mcp\Server;

/**
 * Newline-delimited JSON-RPC over streams. stdout carries protocol messages only, every diagnostic
 * goes to stderr. Returns when the input ends, which is how a client shuts the server down.
 */
final class Stdio
{
    /** @param resource $in @param resource $out @param resource $err */
    public function __construct(private $in, private $out, private $err, private readonly int $maxMessageBytes = Server::MAX_MESSAGE_BYTES)
    {
        // A client that starts the server over a socket pair (Node does) is subject to PHP's default_socket_timeout
        // (60 s): without this a quiet minute would look like the end of the input and the server would stop.
        stream_set_timeout($this->in, 86400 * 365);
    }

    public function run(Server $server): void
    {
        $started = microtime(true);
        $messages = 0;
        while (null !== ($line = $this->readLine())) {
            ++$messages;
            if (false === $line) {
                $this->write('{"jsonrpc":"2.0","id":null,"error":{"code":' . Protocol::INVALID_REQUEST . ',"message":"The message is larger than ' . $this->maxMessageBytes . ' bytes and was discarded."}}');
                continue;
            }
            if ('' === trim($line)) { continue; }
            $response = $server->handle($line);
            if (null !== $response) { $this->write($response); }
        }
        // The only way the loop ends: the client closed its end. Said on stderr so a disconnect can be explained.
        $this->log('input closed by the client after ' . (int) round(microtime(true) - $started) . ' s and ' . $messages . ' messages, stopping');
    }

    public function log(string $line): void
    {
        fwrite($this->err, '[wbengine-mcp] ' . str_replace(["\r", "\n"], ' ', $line) . "\n");
    }

    /** The next line, null at end of input, false for a line over the size limit (the rest of it is skipped). */
    private function readLine(): string|false|null
    {
        $buffer = '';
        $tooLong = false;
        while (true) {
            $chunk = fgets($this->in, 8192);
            if (false === $chunk) {
                // A read timeout is not the end of the input: only a closed stream ends the server.
                if (!feof($this->in) && (stream_get_meta_data($this->in)['timed_out'] ?? false)) { continue; }
                return '' === $buffer && !$tooLong ? null : ($tooLong ? false : $buffer);
            }
            $complete = str_ends_with($chunk, "\n");
            if (!$tooLong) {
                $buffer .= $chunk;
                if (strlen($buffer) > $this->maxMessageBytes) { $tooLong = true; $buffer = ''; }
            }
            if ($complete) {
                return $tooLong ? false : rtrim($buffer, "\r\n");
            }
        }
    }

    private function write(string $message): void
    {
        fwrite($this->out, $message . "\n");
        fflush($this->out);
    }
}
