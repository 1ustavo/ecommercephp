<?php
require_once __DIR__ . "/../config/bootstrap.php";

$token = $_GET['token'] ?? '';
$pageTitle = 'Redefinir senha';
require __DIR__ . "/../includes/header.php";
?>

<h1 class="page-title">Redefinir senha</h1>

<?php if (!$token): ?>
    <div class="alert alert-error">Token inválido.</div>
<?php else: ?>
    <form action="<?= apiUrl('resetar_senha.php') ?>" method="post">
        <?= csrf_input() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="form-group">
            <label class="form-label">Nova senha</label>
            <input type="password" name="senha" class="form-control" required minlength="8">
        </div>
        <div class="form-group">
            <label class="form-label">Confirme a nova senha</label>
            <input type="password" name="senha_confirm" class="form-control" required minlength="8">
        </div>
        <div style="margin-top:12px">
            <button class="btn btn-primary">Redefinir senha</button>
            <a class="btn btn-gray" href="<?= pageUrl('login.php') ?>">Voltar ao login</a>
        </div>
    </form>
<?php endif; ?>

<?php require __DIR__ . "/../includes/footer.php"; ?>