<?php
// One-shot TLS server with a self-signed certificate: the MCP client must refuse to talk to it.
// Usage: php mcp-tls-fixture.php <port-file>; writes the chosen port into the file, serves one connection.
$key = openssl_pkey_new(['private_key_bits' => 2048]);
$csr = openssl_csr_new(['commonName' => 'localhost'], $key);
$cert = openssl_csr_sign($csr, null, $key, 1);
$pem = sys_get_temp_dir() . '/wb-tls-' . bin2hex(random_bytes(4)) . '.pem';
openssl_x509_export($cert, $certOut);
openssl_pkey_export($key, $keyOut);
file_put_contents($pem, $certOut . $keyOut);
$context = stream_context_create(['ssl' => ['local_cert' => $pem, 'verify_peer' => false]]);
$server = stream_socket_server('ssl://127.0.0.1:0', $errno, $error, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
file_put_contents($argv[1], (string) parse_url(stream_socket_get_name($server, false), PHP_URL_PORT));
$connection = @stream_socket_accept($server, 15);
if ($connection) { @fread($connection, 4096); @fwrite($connection, "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nContent-Length: 29\r\n\r\n{\"success\":true,\"data\":\"hi\"}"); @fclose($connection); }
@unlink($pem);
