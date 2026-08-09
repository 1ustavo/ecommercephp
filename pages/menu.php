<?php

require_once __DIR__ . "/../config/bootstrap.php";

$auth = new Auth();
$auth->verificarLogin();

$nome = htmlspecialchars($_SESSION["usuario_nome"] ?? "", ENT_QUOTES, "UTF-8");
$cargo = htmlspecialchars($_SESSION["cargo"] ?? "", ENT_QUOTES, "UTF-8");

$pageTitle = "Menu";
require __DIR__ . "/../includes/header.php";
?>

<div class="card" style="max-width:600px;margin:0 auto;text-align:center;">
    <h2>Bem-vindo, <?= e($nome) ?>! </h2>
    <p style="color:var(--cinza-500);margin-bottom:20px">
        Cargo: <span class="badge badge-pago"><?= e($cargo) ?></span>
    </p>
    <div class="flex gap-12 justify-center flex-wrap">
        <a href="<?= pageUrl("loja.php") ?>" class="btn btn-primary">Ir para a Loja</a>
        <a href="<?= pageUrl("perfil.php") ?>" class="btn btn-outline">Meu Perfil</a>
        <a href="<?= apiUrl("logout.php") ?>" class="btn btn-gray">Sair</a>
    </div>
</div>

<?php require __DIR__ . "/../includes/footer.php"; ?>