<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: no-store, max-age=0');

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_name('rsi_form');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function text_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function clean_text(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    return preg_replace('/[ \t]+/', ' ', $value) ?? '';
}

function valid_origin(): bool
{
    $allowed = ['https://www.rsirepuestos.com', 'https://rsirepuestos.com'];
    $origin = rtrim((string)($_SERVER['HTTP_ORIGIN'] ?? ''), '/');
    if ($origin !== '') return in_array($origin, $allowed, true);

    $referer = (string)($_SERVER['HTTP_REFERER'] ?? '');
    foreach ($allowed as $base) {
        if (strpos($referer, $base . '/') === 0) return true;
    }
    return false;
}

function client_ip(): string
{
    $candidate = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return filter_var($candidate, FILTER_VALIDATE_IP) ? $candidate : 'unknown';
}

function rate_limit(string $ip): bool
{
    $window = 900;
    $maximum = 3;
    $file = sys_get_temp_dir() . '/rsi-form-' . hash('sha256', $ip) . '.json';
    $handle = @fopen($file, 'c+');
    if (!$handle) return false;

    try {
        if (!flock($handle, LOCK_EX)) return false;
        $raw = stream_get_contents($handle);
        $entries = $raw ? json_decode($raw, true) : [];
        if (!is_array($entries)) $entries = [];
        $cutoff = time() - $window;
        $entries = array_values(array_filter($entries, static function ($stamp) use ($cutoff) {
            return is_int($stamp) && $stamp > $cutoff;
        }));
        if (count($entries) >= $maximum) return false;
        $entries[] = time();
        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, json_encode($entries));
        fflush($handle);
        return true;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'token') {
    if (($_SERVER['HTTP_X_RSI_FORM'] ?? '') !== '1') respond(403, ['ok' => false]);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    respond(200, ['ok' => true, 'csrf_token' => $_SESSION['csrf_token']]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, ['ok' => false, 'message' => 'Método no permitido.']);
}

if (($_SERVER['HTTP_X_RSI_FORM'] ?? '') !== '1' || !valid_origin()) {
    respond(403, ['ok' => false, 'message' => 'Solicitud no autorizada.']);
}

$token = (string)($_POST['csrf_token'] ?? '');
$sessionToken = (string)($_SESSION['csrf_token'] ?? '');
unset($_SESSION['csrf_token']);
if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
    respond(403, ['ok' => false, 'message' => 'La sesión del formulario expiró. Recargue la página.']);
}

if (trim((string)($_POST['website'] ?? '')) !== '') {
    respond(200, ['ok' => true, 'message' => 'Solicitud recibida.']);
}

$name = clean_text((string)($_POST['nombre'] ?? ''));
$company = clean_text((string)($_POST['empresa'] ?? ''));
$email = trim((string)($_POST['correo'] ?? ''));
$phone = clean_text((string)($_POST['telefono'] ?? ''));
$type = clean_text((string)($_POST['tipo'] ?? ''));
$detail = clean_text((string)($_POST['detalle'] ?? ''));

$allowedTypes = [
    'Cables de alta temperatura o termocuplas',
    'Repuesto industrial',
    'Suministro o equipamiento',
    'Importación bajo demanda',
    'Documentación técnica',
    'Otro requerimiento',
];

if (text_length($name) < 2 || text_length($name) > 80
    || text_length($company) < 2 || text_length($company) > 120
    || !filter_var($email, FILTER_VALIDATE_EMAIL) || text_length($email) > 190
    || text_length($phone) > 30
    || !in_array($type, $allowedTypes, true)
    || text_length($detail) < 10 || text_length($detail) > 3000) {
    respond(422, ['ok' => false, 'message' => 'Revise los datos ingresados e intente nuevamente.']);
}

if (preg_match('/[\r\n]/', $email) || ($phone !== '' && !preg_match('/^[0-9+() .-]{7,30}$/', $phone))) {
    respond(422, ['ok' => false, 'message' => 'El correo o teléfono no tiene un formato válido.']);
}

if (!rate_limit(client_ip())) {
    respond(429, ['ok' => false, 'message' => 'Se alcanzó el límite temporal de solicitudes. Intente nuevamente en 15 minutos.']);
}

$subjectText = 'Nueva solicitud de cotización RSI - ' . $company;
$subject = '=?UTF-8?B?' . base64_encode($subjectText) . '?=';
$message = "Nueva solicitud recibida desde rsirepuestos.com\n\n"
    . "Nombre: {$name}\nEmpresa: {$company}\nCorreo: {$email}\n"
    . "Teléfono: " . ($phone !== '' ? $phone : 'No indicado') . "\nTipo: {$type}\n\n"
    . "Detalle:\n{$detail}\n\nIP: " . client_ip() . "\nFecha UTC: " . gmdate('Y-m-d H:i:s') . "\n";

$headers = [
    'From: Sitio web RSI <ventas@rsirepuestos.com>',
    'Reply-To: ' . $email,
    'Content-Type: text/plain; charset=UTF-8',
    'MIME-Version: 1.0',
    'X-Mailer: RSI-Web',
];

$sent = mail('ventas@rsirepuestos.com', $subject, $message, implode("\r\n", $headers));
if (!$sent) {
    error_log('RSI contact form: mail() failed');
    respond(500, ['ok' => false, 'message' => 'No fue posible enviar la solicitud. Escríbanos a ventas@rsirepuestos.com.']);
}

respond(200, ['ok' => true, 'message' => 'Solicitud enviada. Nuestro equipo comercial se comunicará con usted.']);
