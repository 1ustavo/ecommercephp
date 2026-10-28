<?php

require_once __DIR__ . "/../config/bootstrap.php";

if (isset($_SESSION["usuario_id"])) {
    header("Location: " . pageUrl("loja.php"));
    exit;
}

$pageTitle = "Entrar";
require __DIR__ . "/../includes/header.php";

$erro = $_GET["erro"] ?? null;
$sucesso = isset($_GET["sucesso"]);
?>

<h1 class="page-title">Entrar</h1>

<?php if ($sucesso): ?>
    <div class="alert alert-success">Cadastro realizado com sucesso. Faça login para continuar.</div>
<?php endif; ?>

<?php if ($erro === "campos"): ?>
    <div class="alert alert-error">Preencha e-mail e senha.</div>
<?php elseif ($erro === "credenciais"): ?>
    <div class="alert alert-error">E-mail ou senha inválidos.</div>
<?php elseif ($erro === "bloqueado"): ?>
    <div class="alert alert-error">Conta temporariamente bloqueada devido a múltiplas tentativas inválidas. Tente novamente mais tarde.</div>
<?php elseif ($erro === "rate_limit"): ?>
    <div class="alert alert-error">Muitas tentativas de login. Tente novamente em alguns minutos.</div>
<?php endif; ?>

<form class="auth-form" action="<?= apiUrl("login.php") ?>" method="post">
    <?= csrf_input() ?>
    <div class="form-group">
        <label class="form-label">E-mail</label>
        <input type="email" name="email" class="form-control" required>
    </div>
    <div class="form-group">
        <label class="form-label">Senha</label>
        <input type="password" name="password" class="form-control" required>
    </div>
    <button type="submit" class="btn btn-primary">Entrar</button>
</form>

<p style="margin-top:16px">
    Ainda não tem conta? <a href="<?= pageUrl("cadastro.php") ?>">Cadastre-se aqui</a>.
</p>
<p style="margin-top:8px">
    <a href="<?= pageUrl("esqueci_senha.php") ?>">Esqueci minha senha</a>
</p>

<?php require __DIR__ . "/../includes/footer.php"; ?>
