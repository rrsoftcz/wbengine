import test from 'node:test';
import assert from 'node:assert/strict';
import https from 'node:https';
import {spawnSync} from 'node:child_process';
import {randomBytes} from 'node:crypto';

const installations = {
  first: {base: process.env.PILOT_API_URL, key: process.env.PILOT_API_KEY},
  second: {base: process.env.PILOT_SECOND_API_URL, key: process.env.PILOT_SECOND_API_KEY}
};
if (installations.first.base !== 'https://api.wbengine.test/' || installations.second.base !== 'https://api2.wbengine.test/' || !installations.first.key || !installations.second.key || !process.env.NODE_EXTRA_CA_CERTS) {
  throw Error('Use the isolated dual-web pilot consumer.env and its mounted CA.');
}
assert.ok(installations.first.key !== installations.second.key, 'Installations require independent keys');
function call(path, {installation = 'first', authenticate = false, token, method = 'GET', data, etag} = {}) {
  const target = installations[installation];
  const body = data === undefined ? undefined : JSON.stringify(data);
  return new Promise((resolve, reject) => {
    const request = https.request(new URL(path, target.base), {
      rejectUnauthorized: true, method,
      headers: {
        ...(authenticate ? {Authorization: `Bearer ${token ?? target.key}`, 'X-WBengine-Actor': 'admin:1'} : {}),
        ...(body ? {'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(body)} : {}),
        ...(etag ? {'If-Match': etag} : {})
      }, timeout: 5000
    }, response => {
      const chunks = [], authorized = response.socket.authorized;
      response.on('data', chunk => chunks.push(chunk));
      response.on('error', reject);
      response.on('end', () => {
        const text = Buffer.concat(chunks).toString();
        assert.ok(Object.values(installations).every(site => !text.includes(site.key)), 'An integration secret must never appear in a response');
        resolve({status: response.statusCode, headers: response.headers, body: text, authorized});
      });
    });
    request.on('error', reject);
    request.on('timeout', () => request.destroy(Error('Pilot request timed out')));
    request.end(body);
  });
}
const get = (path, authenticate = false, installation = 'first') => call(path, {authenticate, installation});
test('verified HTTPS reaches independent API, persistent content and public target rendering', async () => {
  for (const [installation, heading] of [['first', 'Published pilot'], ['second', 'Published second pilot']]) {
    const discovery = await get('api/v2/capabilities', false, installation);
    assert.equal(discovery.status, 200);
    assert.equal(discovery.authorized, true);
    assert.equal(discovery.headers['access-control-allow-origin'], undefined);
    const capabilities = JSON.parse(discovery.body).data;
    assert.equal(capabilities.api_version, '2.0');
    assert.equal(capabilities.capabilities.images.write, true);
    assert.ok(capabilities.box_types.some(box => box.type === 'story'));
    const identity = await get('api/v2/identity', true, installation);
    assert.equal(identity.status, 200);
    assert.ok(JSON.parse(identity.body).data.scopes.includes('publish:write'));
    const article = await get('api/v2/articles/1', true, installation);
    assert.equal(article.status, 200);
    assert.match(article.headers.etag, /^"[a-f0-9]{64}"$/);
    assert.equal(JSON.parse(article.body).data.content_format, 'markdown');
    const page = await get('pilot/article/', false, installation);
    assert.equal(page.status, 200);
    assert.ok(page.body.includes(`<h1>${heading}</h1>`));
    const anonymous = await get('api/v2/identity', false, installation);
    assert.equal(anonymous.status, 401);
    assert.equal(JSON.parse(anonymous.body).error.code, 'unauthorized');
  }
});
test('independent installations reject each other keys and keep mutations in their own database', async () => {
  for (const [installation, other] of [['first', 'second'], ['second', 'first']]) {
    const rejected = await call('api/v2/identity', {installation, authenticate: true, token: installations[other].key});
    assert.equal(rejected.status, 401);
    assert.equal(JSON.parse(rejected.body).error.code, 'unauthorized');
  }
  const first = await get('api/v2/pages/2', true, 'first');
  const second = await get('api/v2/pages/2', true, 'second');
  const marker = 'Independent web2 ' + randomBytes(8).toString('hex');
  const changed = await call('api/v2/pages/2', {installation: 'second', authenticate: true, method: 'PATCH', data: {title: marker}, etag: second.headers.etag});
  assert.equal(changed.status, 200);
  try {
    const isolated = await get('api/v2/pages/2', true, 'first');
    assert.equal(isolated.headers.etag, first.headers.etag);
    assert.equal(JSON.parse(isolated.body).data.title, JSON.parse(first.body).data.title);
    assert.equal(JSON.parse((await get('api/v2/pages/2', true, 'second')).body).data.title, marker);
  } finally {
    const restored = await call('api/v2/pages/2', {installation: 'second', authenticate: true, method: 'PATCH', data: {title: JSON.parse(second.body).data.title}, etag: changed.headers.etag});
    assert.equal(restored.status, 200);
  }
});
test('the generated certificate remains untrusted without the explicit pilot CA', () => {
  const environment = {...process.env};
  delete environment.NODE_EXTRA_CA_CERTS;
  const result = spawnSync(process.execPath, ['--input-type=module', '-e', `
    import https from 'node:https';
    const request=https.get('https://api2.wbengine.test/api/v2', {rejectUnauthorized:true}, response=>{response.resume();process.exitCode=1;});
    request.on('error', error=>{if(!['SELF_SIGNED_CERT_IN_CHAIN','UNABLE_TO_VERIFY_LEAF_SIGNATURE','UNABLE_TO_GET_ISSUER_CERT_LOCALLY'].includes(error.code))process.exitCode=2;});
  `], {env: environment, encoding: 'utf8', timeout: 8000});
  assert.equal(result.status, 0, 'TLS must reject the private CA unless explicitly trusted');
});
