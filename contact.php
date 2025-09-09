<?php
// contact.php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

ini_set('display_errors', '0'); // evita que warnings rompan el JSON

require __DIR__ . '/phpmailer/src/Exception.php';
require __DIR__ . '/phpmailer/src/PHPMailer.php';
require __DIR__ . '/phpmailer/src/SMTP.php';

/* ================== CONFIG SMTP ================== */
$smtpHost   = 'smtp.hosting.net';
$smtpUser   = 'apikey'; 
$smtpPass   = 'pass'; 
$smtpPort   = 587; // Puerto recomendado para STARTTLS
$smtpSecure = PHPMailer::ENCRYPTION_STARTTLS; // Método de encriptación recomendado


/* ================== REMITENTE / DESTINATARIOS ================== */
$fromEmail = 'brayner@heliosagency.digital';
$fromName  = 'Brayner Solar (Landing Page)';

// Destinatarios finales (según lo que acordamos)
$toEmail = 'jefferson.freitas@braynersolar.com.br';
$cc1     = 'brayner@heliosagency.digital';

/* ================== HELPERS ================== */
function field(string $key): string { return isset($_POST[$key]) ? trim((string)$_POST[$key]) : ''; }
function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function json_out(bool $ok, string $message, int $status = 200): void {
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_UNICODE);
  exit;
}

/* ================== VALIDAR REQUEST ================== */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_out(false, 'Requisição inválida.', 403);
}

// Honeypot anti-spam
if (field('website') !== '') {
  json_out(true, 'OK'); // fingimos éxito para bots
}

/* ================== CAMPOS DEL FORMULARIO ================== */
$name             = strip_tags(field('name'));
$email            = filter_var(field('email'), FILTER_SANITIZE_EMAIL);
$instType         = strip_tags(field('instalation_type'));
$phone            = strip_tags(field('phone'));
$city             = strip_tags(field('city'));
$consumo          = strip_tags(field('consumo'));
$roofType         = strip_tags(field('roof_type'));
$urgency          = strip_tags(field('urgency'));
$message          = trim(field('message'));
$origem           = strip_tags(field('origem'));

/* ================== VALIDACIONES ================== */
$errors = [];
if ($name === '')                                  $errors[] = 'Nome é obrigatório.';
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'E-mail inválido.';
if ($instType === '')                               $errors[] = 'Tipo de Instalação é obrigatório.';
if ($phone === '')                                  $errors[] = 'Telefone é obrigatório.';
if ($city === '')                                   $errors[] = 'Cidade é obrigatória.';
if ($roofType === '')                               $errors[] = 'Tipo de telhado é obrigatório.';
if ($urgency === '')                                $errors[] = 'Urgência é obrigatória.';
if ($message === '')                                $errors[] = 'Mensagem é obrigatória.';

if (!empty($errors)) {
  json_out(false, implode(' ', $errors), 400);
}

/* ================== CUERPO DEL EMAIL ================== */
$ip = $_SERVER['REMOTE_ADDR']     ?? '';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

$subject = 'Novo contato do site — Solicitação de Orçamento';

$bodyHtml  = "<h2>Novo contato (site)</h2>";
$bodyHtml .= "<p><b>Nome:</b> " . h($name) . "</p>";
$bodyHtml .= "<p><b>Email:</b> " . h($email) . "</p>";
$bodyHtml .= "<p><b>Telefone:</b> " . h($phone) . "</p>";
$bodyHtml .= "<p><b>Cidade/Bairro:</b> " . h($city) . "</p>";
$bodyHtml .= "<p><b>Tipo de Instalação:</b> " . h($instType) . "</p>";
$bodyHtml .= "<p><b>Consumo médio (kWh):</b> " . h($consumo) . "</p>";
$bodyHtml .= "<p><b>Tipo de telhado:</b> " . h($roofType) . "</p>";
$bodyHtml .= "<p><b>Urgência:</b> " . h($urgency) . "</p>";
if ($origem !== '') {
  $bodyHtml .= "<p><b>Origem:</b> " . h($origem) . "</p>";
}
$bodyHtml .= "<p><b>Mensagem:</b><br>" . nl2br(h($message)) . "</p>";
$bodyHtml .= "<hr><small>IP: " . h($ip) . " | UA: " . h($ua) . "</small>";

$altBody  = "Novo contato (site)\n";
$altBody .= "Nome: $name\n";
$altBody .= "Email: $email\n";
$altBody .= "Telefone: $phone\n";
$altBody .= "Cidade/Bairro: $city\n";
$altBody .= "Tipo de Instalação: $instType\n";
$altBody .= "Consumo (kWh): $consumo\n";
$altBody .= "Telhado: $roofType\n";
$altBody .= "Urgência: $urgency\n";
if ($origem !== '') {
  $altBody .= "Origem: $origem\n";
}
$altBody .= "Mensagem:\n$message\n----\nIP: $ip | UA: $ua";

/* ================== ENVIAR CON PHPMailer ================== */
$mail = new PHPMailer(true);

try {
  // SMTP
  $mail->isSMTP();
  $mail->Host       = $smtpHost;
  $mail->SMTPAuth   = true;
  $mail->Username   = $smtpUser;
  $mail->Password   = $smtpPass;
  $mail->SMTPSecure = $smtpSecure;
  $mail->Port       = $smtpPort;

  // Metadatos
  $mail->CharSet = 'UTF-8';
  $mail->setFrom($fromEmail, $fromName);

  // Destinatarios
  $mail->addAddress($toEmail, 'Jefferson Freitas');
  $mail->addCC($cc1);

  // Responder-a: el usuario que llenó el form
  $mail->addReplyTo($email, $name);

  // Adjuntos (opcional)
  if (!empty($_FILES['bill']) && is_array($_FILES['bill'])) {
    $f = $_FILES['bill'];
    if (isset($f['error']) && $f['error'] === UPLOAD_ERR_OK && is_uploaded_file($f['tmp_name'])) {
      $maxSize = 5 * 1024 * 1024; // 5MB
      $allowed = ['jpg','jpeg','png','pdf'];
      $origName = $f['name'] ?? 'arquivo';
      $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
      if (in_array($ext, $allowed, true) && (int)$f['size'] <= $maxSize) {
        $mail->addAttachment($f['tmp_name'], $origName);
      }
    }
  }

  // Contenido
  $mail->isHTML(true);
  $mail->Subject = $subject;
  $mail->Body    = $bodyHtml;
  $mail->AltBody = $altBody;

  // Enviar
  $mail->send();
  json_out(true, 'Mensagem enviada com sucesso!', 200);

} catch (Exception $e) {
  error_log('Mailer Error: ' . $mail->ErrorInfo);
  json_out(false, 'Erro: não foi possível enviar a mensagem.', 500);
}
