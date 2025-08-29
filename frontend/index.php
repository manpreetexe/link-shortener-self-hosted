<?php
error_reporting(E_ERROR | E_PARSE);
session_start();
function write_log($message)
{
  $log_dir = __DIR__ . '/temp';
  if (!is_dir($log_dir)) {
    mkdir($log_dir, 0777, true);
  }
  $log_file = $log_dir . '/logs.txt';
  $timestamp = date('Y-m-d H:i:s');
  $entry = "[$timestamp] $message\n";
  file_put_contents($log_file, $entry, FILE_APPEND);
}

function generateCaptchaText($length = 6)
{
  return substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, $length);
}

function createCaptchaImage($text)
{
  $width = 150; // Fixed max width to 150px
  $height = 50;
  $image = imagecreatetruecolor($width, $height);

  // Soft background color (light grayish)
  $bg = imagecolorallocate($image, 245, 245, 245);
  imagefilledrectangle($image, 0, 0, $width, $height, $bg);

  // Add some gentle lines (5)
  for ($i = 0; $i < 5; $i++) {
    $lineColor = imagecolorallocate($image, rand(150, 180), rand(150, 180), rand(150, 180));
    imageline($image, rand(0, $width), rand(0, $height), rand(0, $width), rand(0, $height), $lineColor);
  }

  // Add moderate noise dots (200)
  for ($i = 0; $i < 200; $i++) {
    $dotColor = imagecolorallocate($image, rand(120, 180), rand(120, 180), rand(120, 180));
    imagesetpixel($image, rand(0, $width), rand(0, $height), $dotColor);
  }

  // Write text with small variation in font size and angle
  $font = __DIR__ . '/arial.ttf';
  $x = 10;
  for ($i = 0; $i < strlen($text); $i++) {
    $fontSize = rand(16, 20); // Smaller font size for 150px width
    $angle = rand(-10, 10);
    $y = rand(35, 40);
    $charColor = imagecolorallocate($image, rand(0, 80), rand(0, 80), rand(0, 80));
    imagettftext($image, $fontSize, $angle, $x, $y, $charColor, $font, $text[$i]);
    $x += $fontSize - 1;
  }

  // Output image directly
  header('Content-Type: image/png');
  imagepng($image);
  imagedestroy($image);
  exit;
}

// NEW: API endpoint for serving CAPTCHA image
if (isset($_GET['action']) && $_GET['action'] === 'captcha_image') {
  write_log("CAPTCHA image requested");

  // Only generate new CAPTCHA if one doesn't exist in session
  if (!isset($_SESSION['captcha']) || empty($_SESSION['captcha'])) {
    $_SESSION['captcha'] = generateCaptchaText();
    write_log("New CAPTCHA generated for image: " . $_SESSION['captcha']);
  } else {
    write_log("Using existing CAPTCHA for image: " . $_SESSION['captcha']);
  }

  createCaptchaImage($_SESSION['captcha']);
  exit;
}

// API endpoint for refreshing CAPTCHA
if (isset($_GET['action']) && $_GET['action'] === 'refresh_captcha') {
  write_log("CAPTCHA refresh requested");
  $_SESSION['captcha'] = generateCaptchaText();
  write_log("New CAPTCHA generated: " . $_SESSION['captcha']);

  header('Content-Type: application/json');
  echo json_encode(['success' => true]);
  exit;
}

// API endpoint for CAPTCHA verification
if (isset($_GET['action']) && $_GET['action'] === 'verify_captcha') {
  header('Content-Type: application/json');
  write_log("CAPTCHA verification requested");

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $user_input = $input['captcha'] ?? '';
    $expected = $_SESSION['captcha'] ?? '';

    write_log("User input: $user_input | Expected: $expected");

    if (strcasecmp($user_input, $expected) === 0) {
      write_log("CAPTCHA verification passed");
      echo json_encode(['success' => true, 'message' => 'CAPTCHA verified']);
    } else {
      write_log("CAPTCHA verification failed");
      echo json_encode(['success' => false, 'message' => 'CAPTCHA verification failed']);
    }
  }
  exit;
}

// API endpoint for creating short URLs (after CAPTCHA verification)
if (isset($_GET['action']) && $_GET['action'] === 'create') {
  header('Content-Type: application/json');
  write_log("Short URL creation requested");

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $captcha_input = $_POST['captcha_input'] ?? '';
    $expected = $_SESSION['captcha'] ?? '';

    write_log("Captcha input: $captcha_input | Expected: $expected");

    if (strcasecmp($captcha_input, $expected) !== 0) {
      write_log("Short URL creation blocked: CAPTCHA failed");
      echo json_encode(['error' => 'CAPTCHA verification failed']);
      exit;
    }

    $api_url = 'https://api.your-domain.com?action=create';
    $payload = [
      'originalUrl' => $_POST['originalUrl'] ?? '',
      'customAlias' => $_POST['customAlias'] ?? '',
    ];

    write_log("Sending to internal API: " . json_encode($payload));

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    write_log("API response status: $http_code | Body: $response");

    if ($http_code === 200) {
      $decoded_response = json_decode($response, true);
      if ($decoded_response && isset($decoded_response['success']) && $decoded_response['success']) {
        write_log("Short URL created successfully");
        echo $response;
      } else {
        write_log("API returned unsuccessful response");
        echo json_encode(['error' => 'API returned unsuccessful response']);
      }
    } else {
      write_log("API request failed with HTTP code $http_code");
      echo json_encode(['error' => 'API request failed with status: ' . $http_code]);
    }
  }
  exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'generate_qr') {
  header('Content-Type: application/json');

  write_log("QR code generation requested from client PHP (GET mode).");

  $short_url = trim($_GET['url'] ?? '');

  if (empty($short_url)) {
    write_log("No URL provided for QR generation.");
    echo json_encode(['error' => 'No URL provided for QR code generation.']);
    exit;
  }

  write_log("Sending QR request to API for URL: {$short_url}");

  $api_url = 'https://api.your-domain.com?action=qr&url=' . $short_url;

  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => $api_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_FOLLOWLOCATION => true,
  ]);

  $response = curl_exec($ch);
  $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $curl_error = curl_error($ch);
  curl_close($ch);

  if ($response === false) {
    write_log("cURL error during QR API request: {$curl_error}");
    echo json_encode(['error' => 'QR API request failed: ' . $curl_error]);
    exit;
  }

  write_log("QR API response HTTP {$http_code}: " . substr($response, 0, 300));

  if ($http_code === 200) {
    $decoded_response = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
      if (isset($decoded_response['qr_base64'])) {
        write_log("QR code generated successfully for URL: {$short_url}");
        echo json_encode([
          'success' => true,
          'qr_base64' => $decoded_response['qr_base64'],
          'short_url' => $short_url
        ]);
        exit;
      } elseif (isset($decoded_response['error'])) {
        write_log("QR API returned error: " . $decoded_response['error']);
        echo json_encode(['error' => 'QR API error: ' . $decoded_response['error']]);
        exit;
      } else {
        write_log("Unexpected QR API response structure.");
        echo json_encode(['error' => 'Unexpected response from QR API.']);
        exit;
      }
    } else {
      write_log("Invalid JSON from QR API.");
      echo json_encode(['error' => 'Invalid JSON returned from QR API.']);
      exit;
    }
  } else {
    write_log("QR API request failed with HTTP code {$http_code}");
    echo json_encode(['error' => 'QR code generation failed. HTTP status: ' . $http_code]);
    exit;
  }
}


// On GET: show form with captcha
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  // Only generate CAPTCHA if it doesn't exist
  if (!isset($_SESSION['captcha']) || empty($_SESSION['captcha'])) {
    $_SESSION['captcha'] = generateCaptchaText();
    write_log("New CAPTCHA generated for form: " . $_SESSION['captcha']);
  } else {
    write_log("Using existing CAPTCHA for form: " . $_SESSION['captcha']);
  }

?>
  <!DOCTYPE html>
  <html lang="en">

  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>your-domain - URL Shortener</title>
    <style>
      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
      }

      body {
        font-family: 'SF Pro Display', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 25%, #0f3460 50%, #533483 75%, #7209b7 100%);
        background-size: 200% 200%;
        animation: subtleGradient 15s ease infinite;
        min-height: 100vh;
        color: #ffffff;
        position: relative;
        overflow-x: hidden;
      }

      @keyframes subtleGradient {
        0% {
          background-position: 0% 50%;
        }

        50% {
          background-position: 100% 50%;
        }

        100% {
          background-position: 0% 50%;
        }
      }

      .background-elements {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        z-index: 1;
      }

      .grid-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-image:
          linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
          linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
        background-size: 50px 50px;
        animation: gridMove 20s linear infinite;
      }

      @keyframes gridMove {
        0% {
          transform: translate(0, 0);
        }

        100% {
          transform: translate(50px, 50px);
        }
      }

      .floating-element {
        position: absolute;
        border-radius: 50%;
        background: linear-gradient(45deg, rgba(255, 255, 255, 0.05), rgba(255, 255, 255, 0.02));
        backdrop-filter: blur(1px);
        border: 1px solid rgba(255, 255, 255, 0.05);
      }

      .floating-element:nth-child(2) {
        width: 200px;
        height: 200px;
        top: 10%;
        left: -50px;
        animation: floatSlow 25s ease-in-out infinite;
      }

      .floating-element:nth-child(3) {
        width: 150px;
        height: 150px;
        top: 60%;
        right: -30px;
        animation: floatSlow 30s ease-in-out infinite reverse;
      }

      .floating-element:nth-child(4) {
        width: 100px;
        height: 100px;
        bottom: 20%;
        left: 80%;
        animation: floatSlow 20s ease-in-out infinite;
      }

      @keyframes floatSlow {

        0%,
        100% {
          transform: translateY(0px) translateX(0px);
        }

        25% {
          transform: translateY(-30px) translateX(20px);
        }

        50% {
          transform: translateY(-10px) translateX(-15px);
        }

        75% {
          transform: translateY(-20px) translateX(10px);
        }
      }

      .container {
        position: relative;
        z-index: 10;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 40px 20px;
      }

      .main-card {
        background: rgba(255, 255, 255, 0.08);
        backdrop-filter: blur(20px);
        border-radius: 16px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        width: 100%;
        max-width: 600px;
        overflow: hidden;
      }

      .header {
        padding: 40px 40px 0 40px;
        text-align: center;
      }

      .logo {
        font-size: 2.2rem;
        font-weight: 700;
        margin-bottom: 8px;
        background: linear-gradient(135deg, #ffffff 0%, #e0e0e0 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
      }

      .tagline {
        color: rgba(255, 255, 255, 0.7);
        font-size: 1rem;
        font-weight: 400;
        margin-bottom: 40px;
      }

      .form-section {
        padding: 0 40px 40px 40px;
      }

      .input-group {
        margin-bottom: 24px;
      }

      .input-label {
        display: block;
        font-size: 0.9rem;
        font-weight: 500;
        color: rgba(255, 255, 255, 0.8);
        margin-bottom: 8px;
      }

      .input-field {
        width: 100%;
        padding: 16px 20px;
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: 8px;
        color: #ffffff;
        font-size: 1rem;
        font-weight: 400;
        transition: all 0.3s ease;
        backdrop-filter: blur(10px);
      }

      .input-field::placeholder {
        color: rgba(255, 255, 255, 0.5);
      }

      .input-field:focus {
        outline: none;
        border-color: rgba(255, 255, 255, 0.3);
        background: rgba(255, 255, 255, 0.12);
        box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.05);
      }

      .input-field.error {
        border-color: rgba(239, 68, 68, 0.5);
        background: rgba(239, 68, 68, 0.1);
      }

      .input-field.success {
        border-color: rgba(34, 197, 94, 0.5);
        background: rgba(34, 197, 94, 0.1);
      }

      .captcha-container {
        display: flex;
        gap: 16px;
        align-items: center;
        margin-bottom: 16px;
      }

      .captcha-image {
        border-radius: 8px;
        border: 1px solid rgba(255, 255, 255, 0.15);
        max-width: 150px;
        /* Ensure max width is 150px */
        width: 150px;
        height: auto;
      }

      .captcha-input {
        flex: 1;
      }

      .captcha-status {
        font-size: 0.85rem;
        margin-top: 8px;
        font-weight: 500;
      }

      .captcha-status.success {
        color: #10b981;
      }

      .captcha-status.error {
        color: #ef4444;
      }

      .primary-button {
        width: 100%;
        padding: 16px 24px;
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
        border: none;
        border-radius: 8px;
        color: #ffffff;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
      }

      .primary-button:before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        transition: left 0.5s;
      }

      .primary-button:hover:before {
        left: 100%;
      }

      .primary-button:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 30px rgba(99, 102, 241, 0.3);
      }

      .primary-button:active {
        transform: translateY(0);
      }

      .primary-button:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
      }

      .result-section {
        margin-top: 32px;
        padding: 24px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 12px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        display: none;
        animation: slideIn 0.4s ease-out;
      }

      @keyframes slideIn {
        from {
          opacity: 0;
          transform: translateY(-10px);
        }

        to {
          opacity: 1;
          transform: translateY(0);
        }
      }

      .result-section.show {
        display: block;
      }

      .result-label {
        font-size: 0.85rem;
        color: rgba(255, 255, 255, 0.6);
        margin-bottom: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
      }

      .shortened-url {
        background: rgba(255, 255, 255, 0.08);
        padding: 16px 20px;
        border-radius: 8px;
        font-family: 'SF Mono', Monaco, 'Cascadia Code', monospace;
        font-size: 1rem;
        color: #ffffff;
        margin-bottom: 16px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        word-break: break-all;
      }

      .action-buttons {
        display: flex;
        gap: 12px;
      }

      .secondary-button {
        flex: 1;
        padding: 12px 20px;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 6px;
        color: #ffffff;
        font-size: 0.9rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
      }

      .secondary-button:hover {
        background: rgba(255, 255, 255, 0.15);
        border-color: rgba(255, 255, 255, 0.3);
      }

      .secondary-button.success {
        background: rgba(34, 197, 94, 0.2);
        border-color: rgba(34, 197, 94, 0.4);
        color: #10b981;
      }

      /* Fixed icon size */
      .feature-icon {
        width: 16px;
        height: 16px;
        flex-shrink: 0;
      }

      @media (max-width: 768px) {
        .container {
          padding: 20px 15px;
        }

        .header,
        .form-section {
          padding-left: 24px;
          padding-right: 24px;
        }

        .captcha-container {
          flex-direction: column;
          align-items: stretch;
        }

        .action-buttons {
          flex-direction: column;
        }
      }
    </style>
    <link rel="apple-touch-icon" sizes="180x180" href="assets/images/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/images/favicon/favicon-16x16.png">
    <link rel="manifest" href="assets/images/favicon/site.webmanifest">
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-0906D45SWD"></script>
    <script>
      window.dataLayer = window.dataLayer || [];

      function gtag() {
        dataLayer.push(arguments);
      }
      gtag('js', new Date());
      gtag('config', 'G-0906D45SWD');
    </script>
  </head>

  <body>
    <div class="background-elements">
      <div class="grid-overlay"></div>
      <div class="floating-element"></div>
      <div class="floating-element"></div>
      <div class="floating-element"></div>
    </div>
    <div class="container">
      <div class="main-card">
        <div class="header">
          <img src="assets/images/logo-your-domain-light.png" width="220" style="margin-bottom:20px" alt="your-domain logo">
          <p class="tagline">Streamlined URL Shortening for Efficient Sharing</p>
        </div>

        <div class="form-section">
          <form id="urlForm">
            <div class="input-group">
              <label class="input-label" for="originalUrl">Original URL</label>
              <input type="url" id="originalUrl" class="input-field" placeholder="https://example.com/your-long-url-here" required>
            </div>

            <div class="input-group">
              <label class="input-label" for="customSlug">Custom alias (optional)</label>
              <input type="text" id="customSlug" class="input-field" placeholder="custom-link-name" pattern="[a-zA-Z0-9_-]+">
            </div>

            <div class="input-group">
              <label class="input-label">CAPTCHA Verification</label>
              <div class="captcha-container">
                <div class="captcha-image-container">
                  <img src="?action=captcha_image" alt="CAPTCHA" class="captcha-image" id="captchaImage">
                  <button type="button" class="refresh-captcha" id="refreshCaptcha" title="Refresh CAPTCHA">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <polyline points="23 4 23 10 17 10"></polyline>
                      <polyline points="1 20 1 14 7 14"></polyline>
                      <path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"></path>
                    </svg>
                  </button>
                </div>
                <div class="captcha-input">
                  <input type="text" id="captchaInput" class="input-field" placeholder="Enter CAPTCHA" required>
                  <div id="captchaStatus" class="captcha-status"></div>
                </div>
              </div>
            </div>

            <button type="submit" class="primary-button" id="shortenBtn" disabled>
              Verify CAPTCHA First
            </button>
          </form>

          <div class="result-section" id="result">
            <div class="result-label">Shortened URL</div>
            <div class="shortened-url" id="shortUrl"></div>
            <div class="action-buttons">
              <button class="secondary-button" id="copyBtn">
                <svg class="feature-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                  <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                </svg>
                <span id="copyText">Copy Link</span>
              </button>
              <button class="secondary-button" id="qrBtn">
                <svg class="feature-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <rect x="3" y="3" width="7" height="7"></rect>
                  <rect x="14" y="3" width="7" height="7"></rect>
                  <rect x="14" y="14" width="7" height="7"></rect>
                  <path d="M3 14h4v4H3z"></path>
                  <path d="M7 17h3"></path>
                  <path d="M10 14v3"></path>
                </svg>
                Generate QR
              </button>
              <button class="secondary-button" id="shareBtn">
                <svg class="feature-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"></path>
                  <polyline points="16,6 12,2 8,6"></polyline>
                  <line x1="12" y1="2" x2="12" y2="15"></line>
                </svg>
                Share
              </button>
            </div>
            <div id="qrContainer" style="margin-top: 16px; display: none; text-align: center;">
              <img id="qrImage" src="" alt="QR Code" style="max-width: 200px; height: auto; border-radius: 8px; border: 1px solid #e5e7eb;">
              <p style="margin-top: 8px; font-size: 14px; color: #fff;">Scan to open your short link</p>
              <button id="downloadQrBtn" class="secondary-button" style="margin: 10px auto;">
                Download QR
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <script>
      class ProfessionalLinkShortener {
        constructor() {
          this.captchaVerified = false;

          // Set DOM references as properties
          this.form = document.getElementById('urlForm');
          this.result = document.getElementById('result');
          this.button = document.getElementById('shortenBtn');
          this.shortUrlEl = document.getElementById('shortUrl');
          this.copyBtn = document.getElementById('copyBtn');
          this.copyText = document.getElementById('copyText');
          this.shareBtn = document.getElementById('shareBtn');
          this.captchaInput = document.getElementById('captchaInput');
          this.captchaStatus = document.getElementById('captchaStatus');
          this.captchaImage = document.getElementById('captchaImage');
          this.refreshCaptcha = document.getElementById('refreshCaptcha');
          this.qrBtn = document.getElementById('qrBtn');
          this.qrContainer = document.getElementById('qrContainer');
          this.qrImage = document.getElementById('qrImage');
          this.downloadQrBtn = document.getElementById('downloadQrBtn');

          this.initialize();
        }

        validateUrl(url) {
          try {
            new URL(url);
            return true;
          } catch {
            return false;
          }
        }

        async refreshCaptchaImage() {
          try {
            // Request new CAPTCHA generation
            await fetch('?action=refresh_captcha', {
              method: 'POST'
            });

            // Refresh the image with cache-busting
            this.captchaImage.src = '?action=captcha_image&t=' + Date.now();

            // Reset verification state
            this.resetCaptchaState();
            this.captchaInput.value = '';

            this.showNotification('CAPTCHA refreshed');
          } catch (error) {
            console.error('Error refreshing CAPTCHA:', error);
            this.showNotification('Failed to refresh CAPTCHA', 'error');
          }
        }

        async verifyCaptcha(captchaValue) {
          try {
            const response = await fetch('?action=verify_captcha', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
              },
              body: JSON.stringify({
                captcha: captchaValue
              })
            });

            const result = await response.json();
            return result.success;
          } catch (error) {
            console.error('CAPTCHA verification error:', error);
            return false;
          }
        }

        async createShortUrl(originalUrl, customAlias = '', captchaValue = '') {
          if (!this.validateUrl(originalUrl)) {
            throw new Error('Please enter a valid URL');
          }

          if (!this.captchaVerified) {
            throw new Error('Please verify CAPTCHA first');
          }

          const formData = new FormData();
          formData.append('originalUrl', originalUrl);
          formData.append('customAlias', customAlias.trim());
          formData.append('captcha_input', captchaValue);

          try {
            const response = await fetch('?action=create', {
              method: 'POST',
              body: formData
            });

            const resultData = await response.json();

            // Check if response indicates success
            if (resultData.error) {
              throw new Error(resultData.error);
            }

            if (!response.ok) {
              throw new Error('Failed to create short URL');
            }

            if (!resultData.short_url) {
              throw new Error('Invalid response from server');
            }

            const shortUrl = resultData.short_url;
            this.shortUrlEl.textContent = shortUrl;
            this.result.classList.add('show');
            this.form.reset();
            this.resetCaptchaState();
            await this.refreshCaptchaImage();
            this.showNotification('Short link created successfully!');
          } catch (error) {
            this.showNotification(error.message, 'error');
          } finally {
            this.button.disabled = false;
            this.button.textContent = 'Generate Short Link';
          }
        }

        resetCaptchaState() {
          this.captchaVerified = false;
          this.captchaInput.classList.remove('success', 'error');
          this.captchaStatus.textContent = '';
          this.captchaStatus.classList.remove('success', 'error');
          this.button.disabled = true;
          this.button.textContent = 'Verify CAPTCHA First';
        }

        async copyToClipboard(text) {
          try {
            await navigator.clipboard.writeText(text);
            return true;
          } catch {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'absolute';
            textArea.style.left = '-9999px';
            document.body.appendChild(textArea);
            textArea.select();
            const success = document.execCommand('copy');
            document.body.removeChild(textArea);
            return success;
          }
        }

        showNotification(message, type = 'success') {
          const notification = document.createElement('div');
          notification.style.cssText = `
          position: fixed;
          top: 20px;
          right: 20px;
          background: ${type === 'success' ? 'rgba(34, 197, 94, 0.9)' : 'rgba(239, 68, 68, 0.9)'};
          color: white;
          padding: 12px 20px;
          border-radius: 8px;
          font-size: 14px;
          font-weight: 500;
          z-index: 1000;
          backdrop-filter: blur(10px);
          border: 1px solid ${type === 'success' ? 'rgba(34, 197, 94, 0.3)' : 'rgba(239, 68, 68, 0.3)'};
        `;
          notification.textContent = message;
          document.body.appendChild(notification);

          setTimeout(() => {
            notification.style.transform = 'translateX(100%)';
            notification.style.opacity = '0';
            setTimeout(() => notification.remove(), 300);
          }, 3000);
        }

        initialize() {
          // CAPTCHA refresh button
          this.refreshCaptcha.addEventListener('click', () => {
            this.refreshCaptchaImage();
          });

          // CAPTCHA verification on input
          this.captchaInput.addEventListener('input', async (e) => {
            const value = e.target.value.trim();

            if (value.length >= 6) { // Assuming 6-character CAPTCHA
              this.captchaStatus.textContent = 'Verifying...';
              this.captchaStatus.classList.remove('success', 'error');

              const isValid = await this.verifyCaptcha(value);

              if (isValid) {
                this.captchaVerified = true;
                this.captchaInput.classList.remove('error');
                this.captchaInput.classList.add('success');
                this.captchaStatus.textContent = '✓ CAPTCHA verified';
                this.captchaStatus.classList.add('success');
                this.captchaStatus.classList.remove('error');
                this.button.disabled = false;
                this.button.textContent = 'Generate Short Link';
              } else {
                this.captchaVerified = false;
                this.captchaInput.classList.remove('success');
                this.captchaInput.classList.add('error');
                this.captchaStatus.textContent = '✗ Invalid CAPTCHA';
                this.captchaStatus.classList.add('error');
                this.captchaStatus.classList.remove('success');
                this.button.disabled = true;
                this.button.textContent = 'Verify CAPTCHA First';
              }
            } else {
              this.resetCaptchaState();
            }
          });

          // Form submission
          this.form.addEventListener('submit', async (e) => {
            e.preventDefault();

            if (!this.captchaVerified) {
              this.showNotification('Please verify CAPTCHA first', 'error');
              return;
            }

            const originalUrl = document.getElementById('originalUrl').value.trim();
            const customAlias = document.getElementById('customSlug').value.trim();
            const captchaValue = this.captchaInput.value.trim();

            this.button.disabled = true;
            this.button.textContent = 'Generating...';

            try {
              await new Promise(resolve => setTimeout(resolve, 1000)); // UX delay
              await this.createShortUrl(originalUrl, customAlias, captchaValue);
            } catch (error) {
              this.showNotification(error.message, 'error');
              this.button.disabled = false;
              this.button.textContent = 'Generate Short Link';
            }
          });

          // Copy functionality
          this.copyBtn.addEventListener('click', async () => {
            const shortUrl = this.shortUrlEl.textContent;
            const success = await this.copyToClipboard(shortUrl);

            if (success) {
              this.copyBtn.classList.add('success');
              this.copyText.textContent = 'Copied!';
              this.showNotification('Link copied to clipboard!');

              setTimeout(() => {
                this.copyBtn.classList.remove('success');
                this.copyText.textContent = 'Copy Link';
              }, 2000);
            } else {
              this.showNotification('Failed to copy link', 'error');
            }
          });

          // Share functionality
          this.shareBtn.addEventListener('click', () => {
            const shortUrl = this.shortUrlEl.textContent;

            if (navigator.share) {
              navigator.share({
                title: 'Shortened Link',
                url: shortUrl
              });
            } else {
              this.showNotification('Share feature not supported on this device', 'error');
            }
          });

          this.qrBtn.addEventListener('click', async () => {
            const shortUrl = this.shortUrlEl.textContent.trim();

            if (!shortUrl) {
              this.showNotification('Please generate a short link first', 'error');
              return;
            }
            this.qrBtn.disabled = true;
            this.qrBtn.textContent = 'Generating QR...';
            try {
              let encodedShortUrl = encodeURI(shortUrl);
              const response = await fetch(`?action=generate_qr&url=${encodedShortUrl}`, {
                method: 'GET',
                headers: {
                  'Content-Type': 'application/json',
                }
              });
              const result = await response.json();
              if (result.error || !result.qr_base64) {
                throw new Error(result.error || 'Invalid QR generation response');
              }
              this.qrImage.src = `${result.qr_base64}`;
              this.qrContainer.style.display = 'block';
              this.showNotification('QR code generated successfully!');
            } catch (error) {
              console.error(error);
              this.showNotification(error.message || 'Failed to generate QR code', 'error');
            } finally {
              this.qrBtn.disabled = false;
              this.qrBtn.textContent = 'Generate QR';
            }
          });

          this.downloadQrBtn.addEventListener('click', () => {
            const timestamp = Date.now(); // current milliseconds
            const randomNum = Math.floor(Math.random() * (5000 - 1000 + 1)) + 1000; // random between 1000-5000
            const fileName = `qr-${timestamp}-${randomNum}.png`;
            const qrImage = document.getElementById('qrImage');
            const link = document.createElement('a');
            link.href = qrImage.src;
            link.download = fileName;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
          });
        }
      }

      // Initialize when DOM is ready
      document.addEventListener('DOMContentLoaded', () => {
        new ProfessionalLinkShortener();
      });
    </script>

    <style>
      .captcha-image-container {
        position: relative;
        display: inline-block;
      }

      .refresh-captcha {
        position: absolute;
        top: 5px;
        right: 5px;
        background: rgba(255, 255, 255, 0.8);
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 4px;
        cursor: pointer;
        transition: background-color 0.2s;
      }

      .refresh-captcha:hover {
        background: rgba(255, 255, 255, 0.95);
      }

      .captcha-container {
        display: flex;
        gap: 10px;
        align-items: flex-start;
      }

      .captcha-input {
        flex: 1;
      }

      .captcha-status {
        margin-top: 5px;
        font-size: 12px;
      }

      .captcha-status.success {
        color: #22c55e;
      }

      .captcha-status.error {
        color: #ef4444;
      }

      .input-field.success {
        border-color: #22c55e !important;
      }

      .input-field.error {
        border-color: #ef4444 !important;
      }
    </style>

  </body>

  </html>

<?php
  exit;
}
