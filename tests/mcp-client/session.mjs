// Starts the MCP server the way a client does (a child process with piped stdio, a socket pair on Unix and
// named pipes on Windows), talks to it, stays quiet for longer than PHP's 60 second default_socket_timeout,
// talks again and ends the session. Used by CI on Linux, macOS and Windows. Only Node built-ins.
//
//   node tests/mcp-client/session.mjs path/to/wbengine-mcp.phar [quiet seconds, default 65]
import { spawn } from 'node:child_process';

const phar = process.argv[2];
const quiet = Number(process.argv[3] ?? 65);
if (!phar) { console.error('usage: node session.mjs <phar> [quiet seconds]'); process.exit(2); }

const started = Date.now();
const at = () => ((Date.now() - started) / 1000).toFixed(1).padStart(6) + ' s';
const log = (...a) => console.log(at(), ...a);
const fail = (message) => { console.error(at(), 'FAIL:', message); child.kill(); process.exit(1); };

const child = spawn(process.env.PHP_BINARY || 'php', [phar], {
  stdio: ['pipe', 'pipe', 'pipe'],
  env: { ...process.env, WBENGINE_MCP_URL: 'https://site.invalid', WBENGINE_MCP_KEY: 'wbv2_session_test_key', WBENGINE_MCP_MODE: 'full' },
});

let stderr = '';
child.stderr.on('data', (d) => { stderr += d; });
let exited = null;
child.on('exit', (code, signal) => { exited = { code, signal, at: at() }; });
child.on('error', (e) => fail('cannot start php: ' + e.message));

const waiting = new Map();
let buffer = '';
child.stdout.on('data', (chunk) => {
  buffer += chunk;
  let i;
  while ((i = buffer.indexOf('\n')) >= 0) {
    const line = buffer.slice(0, i).replace(/\r$/, ''); buffer = buffer.slice(i + 1);
    if (line === '') continue;
    let message;
    try { message = JSON.parse(line); } catch { fail('a line on stdout is not JSON (stdout must carry protocol messages only): ' + line.slice(0, 80)); }
    if (message.id !== undefined && waiting.has(message.id)) { waiting.get(message.id)(message); waiting.delete(message.id); }
  }
});

let nextId = 1;
const request = (method, params = {}) => new Promise((resolve) => {
  const id = nextId++;
  const timer = setTimeout(() => fail('no answer to ' + method + (exited ? ' (the server had exited: ' + JSON.stringify(exited) + ')' : '')), 20000);
  waiting.set(id, (m) => { clearTimeout(timer); resolve(m); });
  child.stdin.write(JSON.stringify({ jsonrpc: '2.0', id, method, params }) + '\n');
});
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

const hardStop = setTimeout(() => fail('the session took too long'), (quiet + 90) * 1000);

const init = await request('initialize', { protocolVersion: '2025-11-25', capabilities: {}, clientInfo: { name: 'ci-session', version: '1' } });
if (!init.result?.serverInfo?.version) fail('initialize gave no server version: ' + JSON.stringify(init));
log('initialized, server', init.result.serverInfo.version, 'protocol', init.result.protocolVersion);
child.stdin.write(JSON.stringify({ jsonrpc: '2.0', method: 'notifications/initialized' }) + '\n');

const tools = await request('tools/list');
const names = (tools.result?.tools ?? []).map((t) => t.name);
if (!names.includes('save_article_draft') || !names.includes('wbengine_guide')) fail('tools/list lacks the expected tools: ' + names.join(','));
log('tools/list gives', names.length, 'tools');

log('quiet for', quiet, 'seconds (PHP default_socket_timeout is 60)');
await sleep(quiet * 1000);
if (exited) fail('the server exited during the quiet time: ' + JSON.stringify(exited) + ' stderr: ' + stderr.trim().split('\n').pop());

const guide = await request('tools/call', { name: 'wbengine_guide', arguments: { topic: 'overview' } });
if (!JSON.stringify(guide.result ?? {}).includes('How a WBengine site works')) fail('the guide tool did not answer after the quiet time: ' + JSON.stringify(guide).slice(0, 200));
log('a tool call after the quiet time works');

child.stdin.end();
for (let i = 0; i < 100 && !exited; i++) await sleep(100);
if (!exited) fail('the server did not stop within 10 seconds after its input was closed');
if (exited.code !== 0) fail('the server exited with ' + JSON.stringify(exited));
if (!stderr.includes('input closed by the client')) fail('stderr does not say how the session ended: ' + stderr.trim());
clearTimeout(hardStop);
log('stopped cleanly after the input was closed:', stderr.trim().split('\n').pop());
console.log('OK');
