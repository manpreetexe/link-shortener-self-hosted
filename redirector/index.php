<?php
error_reporting(E_ERROR | E_PARSE);
require_once '__ops_config.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

function redirectToOriginalLink(PDO $pdo, string $code): void {
    $stmt = $pdo->prepare("
        SELECT original_url, expires_at 
        FROM links 
        WHERE custom_alias = :custom OR short_code = :short 
        LIMIT 1
    ");
    $stmt->execute([
        'custom' => $code,
        'short'  => $code,
    ]);

    $link = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($link && strtotime($link['expires_at']) > time()) {
        header("Location: " . $link['original_url'], true, 301);
        exit;
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Short code or alias not found or expired']);
    }
}

// Get short code or custom alias from query param (via NGINX rewrite)
$code = trim($_GET['short_code'] ?? '', '/');

if (empty($code)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing short code or alias']);
    exit;
}

redirectToOriginalLink($pdo, $code);
