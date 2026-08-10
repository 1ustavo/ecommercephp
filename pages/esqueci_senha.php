<?php
require_once __DIR__ . "/../config/bootstrap.php";

$pageTitle = "Esqueci minha senha";
require __DIR__ . "/../includes/header.php";
?>

<h1 class="page-title">Recuperar senha</h1>

<?php $msg = flash('reset_msg'); if ($msg): ?>
    <div class="alert alert-success"><?= e($msg) ?></div>
<?php endif; ?>

<form action="<?= apiUrl('solicitar_reset.php') ?>" method="post">
    <?= csrf_input() ?>
    <div class="form-group">
        <label class="form-label">Email cadastrado</label>
        <input type="email" name="email" class="form-control" required>
    </div>
    <div style="margin-top:12px">
        <button class="btn btn-primary">Enviar link de recuperação</button>
        <a class="btn btn-gray" href="<?= pageUrl('login.php') ?>">Voltar ao login</a>
    </div>
</form>

<?php require __DIR__ . "/../includes/footer.php"; ?>