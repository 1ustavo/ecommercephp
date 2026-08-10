<?php
require_once __DIR__ . "/../config/bootstrap.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

$token = trim($_POST['token'] ?? '');
$senha = $_POST['senha'] ?? '';
$senhaConfirm = $_POST['senha_confirm'] ?? '';

if ($token === '' || $senha === '' || $senha !== $senhaConfirm) {
    flash('reset_msg', 'Parâmetros inválidos ou senhas não coincidem.');
    header('Location: ' . pageUrl('login.php'));
    exit;
}

// Busca token válido
$stmt = $pdo->prepare('SELECT id, usuario_id, email, expires_at FROM password_resets WHERE token = ?');
$stmt->execute([$token]);
$entry = $stmt->fetch();

if (!$entry) {
    flash('reset_msg', 'Token inválido ou expirado.');
    header('Location: ' . pageUrl('login.php'));
    exit;
}

$expires = new DateTime($entry['expires_at']);
$now = new DateTime();
if ($now > $expires) {
    // Deletar token expirado
    $pdo->prepare('DELETE FROM password_resets WHERE id = ?')->execute([$entry['id']]);
    flash('reset_msg', 'Token expirado. Solicite um novo link.');
    header('Location: ' . pageUrl('esqueci_senha.php'));
    exit;
}

// Atualiza senha do usuário (se existir)
if ($entry['usuario_id']) {
    $hash = password_hash($senha, PASSWORD_DEFAULT);
    $pdo->prepare('UPDATE usuarios SET password = ?, updated_at = NOW() WHERE id = ?')->execute([$hash, (int)$entry['usuario_id']]);
    // Deleta o token usado
    $pdo->prepare('DELETE FROM password_resets WHERE id = ?')->execute([$entry['id']]);
    flash('reset_msg', 'Senha alterada com sucesso. Faça login com a nova senha.');
    header('Location: ' . pageUrl('login.php'));
    exit;
}

// Caso o usuario nao exista (registro por email apenas), remove token e responde com sucesso
$pdo->prepare('DELETE FROM password_resets WHERE id = ?')->execute([$entry['id']]);
flash('reset_msg', 'Senha alterada com sucesso. Faça login com a nova senha.');
header('Location: ' . pageUrl('login.php'));
exit;