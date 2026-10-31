<?php
// Router for `php -S`: a stand-in for a site, used by the MCP client tests with the real stream transport.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
header('Content-Type: application/json');
switch ($path) {
    case '/api/v2/echo':
        header('ETag: "abc"');
        echo json_encode(['success' => true, 'data' => ['method' => $_SERVER['REQUEST_METHOD'], 'authorization' => $_SERVER['HTTP_AUTHORIZATION'] ?? null, 'actor' => $_SERVER['HTTP_X_WBENGINE_ACTOR'] ?? null, 'if_match' => $_SERVER['HTTP_IF_MATCH'] ?? null, 'query' => $_GET, 'body' => file_get_contents('php://input')]]);
        break;
    case '/api/v2/redirect':
        header('Location: /api/v2/echo', true, 302);
        break;
    case '/api/v2/big':
        echo json_encode(['success' => true, 'data' => str_repeat('x', 3000000)]);
        break;
    case '/api/v2/leak':
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => ['code' => 'forbidden', 'message' => 'bad token ' . ($_SERVER['HTTP_AUTHORIZATION'] ?? '')]]);
        break;
    case '/api/v2/html':
        header('Content-Type: text/html');
        echo '<html>login</html>';
        break;
    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => ['code' => 'not_found', 'message' => 'No such thing.']]);
}
