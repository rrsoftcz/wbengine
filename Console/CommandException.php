<?php
namespace Wbengine\Console;

/** A refusal whose message is safe to print: it never contains passwords, hashes or SQL. */
final class CommandException extends \RuntimeException
{
}
