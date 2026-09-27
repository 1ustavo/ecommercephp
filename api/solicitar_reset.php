<?php
require_once __DIR__ . "/../config/bootstrap.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . pageUrl('esqueci_senha.php'));
    exit;
}

// CSRF + rate-limit
verify_csrf_from_request(true);
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!rate_limit_check("reset:{$ip}", 5, 3600)) {
    flash('reset_msg', 'Muitas solicitações. Tente novamente mais tarde.');
    header('Location: ' . pageUrl('esqueci_senha.php'));
    exit;
}

$email = trim(strtolower($_POST['email'] ?? ''));
if ($email === '') {
    flash('reset_msg', 'Email inválido.');
    header('Location: ' . pageUrl('esqueci_senha.php'));
    exit;
}

// Verifica se existe usuário com esse email
$stmt = $pdo->prepare('SELECT id, nome FROM usuarios WHERE email = ? AND ativo = TRUE');
$stmt->execute([$email]);
$user = $stmt->fetch();

// Sempre responder com sucesso para evitar enumeracao de emails
// Token cru (256 bits) vai SOMENTE por email; no banco salvamos apenas o hash.
$token = bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $token);
$expires = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');

try {
    // Uso único: invalida (deleta) TODOS os tokens anteriores do mesmo
    // destinatário antes de inserir o novo — assim só o link mais recente
    // vale, mesmo se o antigo ainda não tiver expirado.
    $pdo->prepare('DELETE FROM password_resets WHERE email = ?')->execute([$email]);

    $stmt = $pdo->prepare('INSERT INTO password_resets (usuario_id, email, token, expires_at) VALUES (?, ?, ?, ?)');
    $stmt->execute([$user['id'] ?? null, $email, $tokenHash, $expires]);
} catch (Exception $e) {
    error_log('Erro ao criar token de reset: ' . $e->getMessage());
}

// Envia email com link de reset (se o usuario existir enviamos para o email)
$link = ($_ENV['APP_URL'] ?? getenv('APP_URL') ?: '') . '/pages/reset_senha.php?token=' . $token;
$assunto = 'Redefinição de senha — EcoShop';
$corpo = '<p>Foi solicitada uma redefinição de senha para sua conta. Clique no link abaixo para criar uma nova senha (válido por 1 hora):</p>';
$corpo .= '<p><a href="' . $link . '">' . $link . '</a></p>';
$corpo .= '<p>Se você não solicitou, ignore este email.</p>';

try {
    Notificacao::enviarEmail($email, $assunto, $corpo);
} catch (Throwable $e) {
    error_log('Erro ao enviar email de reset: ' . $e->getMessage());
}

flash('reset_msg', 'Se o email for cadastrado, um link para redefinição foi enviado. Verifique sua caixa de entrada.');
header('Location: ' . pageUrl('esqueci_senha.php'));
exit;