#!/usr/bin/env php
<?php
// MCP server of a WBengine site over stdio. Configuration: WBENGINE_MCP_URL, WBENGINE_MCP_KEY and
// optionally WBENGINE_MCP_MODE (full|draft), WBENGINE_MCP_ACTOR, WBENGINE_MCP_ALLOW_INSECURE.
// stdout carries protocol messages only: PHP errors and warnings are sent to stderr.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', 'stderr');
ini_set('html_errors', '0');
require dirname(__DIR__) . '/Mcp/bootstrap.php';
exit(\Wbengine\Mcp\Cli::run(getenv(), STDIN, STDOUT, STDERR));
