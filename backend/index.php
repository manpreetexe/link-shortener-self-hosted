<?php
error_reporting(E_ERROR | E_PARSE);
require_once '__ops_config.php';
require_once 'vendor/autoload.php';

use chillerlan\QRCode\{QRCode, QROptions};

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ("$method:$action") {
  case 'POST:create':
    // Use $_POST['originalUrl'] and optional $_POST['customAlias']
    $originalUrl = $_POST['originalUrl'] ?? null;
    $customAlias = $_POST['customAlias'] ?? null;
    createShortLink($pdo, $originalUrl, $customAlias);
    break;

  case 'GET:qr':
    // Use $_POST['originalUrl'] and optional $_POST['customAlias']
    $alias = urldecode($_GET['url']) ?? null;
    generateQRCodeBase64($alias);
    break;

  default:
    http_response_code(404);
    echo json_encode(['error' => 'Invalid API endpoint']);
    break;
}

// ---  MAIN SHORT FUNCTION  ---
function createShortLink(PDO $pdo, ?string $input, ?string $customAlias = null): void
{
  global $config;

  if (!isset($input) || !filter_var($input, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid or missing URL']);
    return;
  }

  $original_url = trim($input);
  $customAlias = $customAlias ? trim($customAlias) : null;

  // If customAlias is provided, check if it's already in use
  if ($customAlias) {
    // Validate custom alias format if needed (optional)
    // e.g., only alphanumeric and dashes, length limits, etc.
    if (!preg_match('/^[a-zA-Z0-9_-]{3,}$/', $customAlias)) {
      http_response_code(400);
      echo json_encode(['error' => 'Custom alias format invalid. Use at least 3 characters: letters, numbers, dash or underscore']);
      return;
    }

    $stmt = $pdo->prepare("SELECT 1 FROM links WHERE custom_alias = :custom_alias");
    $stmt->execute([':custom_alias' => $customAlias]);
    if ($stmt->fetch()) {
      http_response_code(409); // Conflict
      echo json_encode(['error' => 'Alias already in use']);
      return;
    }
  }

  $short_code = generateUniqueShortCode($pdo);

  try {
    $stmt = $pdo->prepare("
      INSERT INTO links (short_code, original_url, custom_alias, expires_at)
      VALUES (:short_code, :original_url, :custom_alias, DATE_ADD(NOW(), INTERVAL 1 MONTH))
    ");
    $stmt->execute([
      ':short_code'   => $short_code,
      ':original_url' => $original_url,
      ':custom_alias' => $customAlias,
    ]);

    echo json_encode([
      'success'    => true,
      'short_url'  => sprintf(
        '%s/%s',
        rtrim($config['app']['base_url'], '/'),
        $customAlias ?: $short_code
      ),
      'expires_at' => date('Y-m-d H:i:s', strtotime('+1 month')),
    ]);
  } catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save the URL']);
  }
}

function generateQRCodeBase64($alias)
{
  header('Content-Type: application/json');
  if (!$alias) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing alias']);
    exit;
  }
  $shortUrl = $alias;
  $options = new QROptions([
    'outputType' => QRCode::OUTPUT_IMAGE_PNG,
    'eccLevel'   => QRCode::ECC_L,
    'scale'      => 5,
  ]);
  $qr = new QRCode($options);
  $dataUri = $qr->render($shortUrl);
  echo json_encode([
    'alias' => $alias,
    'short_url' => $shortUrl,
    'qr_base64' => $dataUri //
  ]);
}

// ---  UTILS  ---
function generateUniqueShortCode(PDO $pdo, int $length = 9): string
{
  $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
  $maxIndex = strlen($characters) - 1;
  do {
    $short_code = '';
    for ($i = 0; $i < $length; $i++) {
      $short_code .= $characters[random_int(0, $maxIndex)];
    }
    $stmt = $pdo->prepare("SELECT 1 FROM links WHERE short_code = ?");
    $stmt->execute([$short_code]);
  } while ($stmt->fetch());
  return $short_code;
}
