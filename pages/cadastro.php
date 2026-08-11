<?php

require_once __DIR__ . "/../config/bootstrap.php";

if (isset($_SESSION["usuario_id"])) {
    header("Location: " . pageUrl("loja.php"));
    exit;
}

$pageTitle = "Cadastrar";
require __DIR__ . "/../includes/header.php";

$erro = $_GET["erro"] ?? null;
?>

<h1 class="page-title">Criar Conta</h1>

<?php if ($erro === "campos"): ?>
    <div class="alert alert-error">Preencha todos os campos obrigatórios.</div>
<?php elseif ($erro === "senha"): ?>
    <div class="alert alert-error">As senhas não coincidem.</div>
<?php elseif ($erro === "email"): ?>
    <div class="alert alert-error">Este e-mail já está em uso.</div>
<?php endif; ?>

<form class="auth-form" action="<?= apiUrl("cadastrar_cliente.php") ?>" method="post">
    <?= csrf_input() ?>
    <div class="form-group">
        <label class="form-label">Nome completo</label>
        <input type="text" name="nome" class="form-control" required>
    </div>
    <div class="form-group">
        <label class="form-label">E-mail</label>
        <input type="email" name="email" class="form-control" required>
    </div>
    <div class="form-group">
        <label class="form-label">Senha</label>
        <input type="password" name="password" class="form-control" required>
    </div>
    <div class="form-group">
        <label class="form-label">Confirme a senha</label>
        <input type="password" name="password_confirm" class="form-control" required>
    </div>
    <button type="submit" class="btn btn-primary">Cadastrar</button>
</form>

<p style="margin-top:16px">
    Já tem conta? <a href="<?= pageUrl("login.php") ?>">Faça login</a>.
</p>

<?php require __DIR__ . "/../includes/footer.php"; ?>
