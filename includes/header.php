<?php
$cartCount = Carrinho::quantidadeItens();
$logado = isset($_SESSION["usuario_id"]);
$nomeUsuario = e($_SESSION["usuario_nome"] ?? "");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? "E-commerce") ?></title>
    <link rel="stylesheet" href="<?= asset("css/theme.css") ?>">
</head>
<body>
<header class="site-header">
    <div class="header-inner">
        <a href="<?= pageUrl("loja.php") ?>" class="logo" aria-label="EcoShop - Página inicial">
            <span class="logo-icon" aria-hidden="true"></span>
            Eco<span>Shop</span>
        </a>
        <nav aria-label="Navegação principal">
            <ul class="nav-links">
                <li><a href="<?= pageUrl("loja.php") ?>">Loja</a></li>
                <li>
                    <a href="<?= pageUrl("carrinho.php") ?>" class="nav-cart">
                        <span class="nav-cart-icon" aria-hidden="true"></span>
                        Carrinho
                        <?php if ($cartCount > 0): ?>
                            <span class="cart-badge" aria-label="<?= $cartCount ?> itens no carrinho"><?= $cartCount ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php if ($logado): ?>
                    <li><a href="<?= pageUrl("pedidos.php") ?>">Pedidos</a></li>
                    <li><a href="<?= pageUrl("perfil.php") ?>" class="nav-user">
                        <span class="nav-user-avatar" aria-hidden="true"><?= mb_strtoupper(mb_substr($nomeUsuario, 0, 1)) ?></span>
                        <?= $nomeUsuario ?>
                    </a></li>
                    <?php if (isAdmin()): ?>
                        <li><a href="<?= pageUrl("admin/dashboard.php") ?>">Admin</a></li>
                    <?php endif; ?>
                    <li><a href="<?= apiUrl("logout.php") ?>" class="nav-logout">Sair</a></li>
                <?php else: ?>
                    <li><a href="<?= pageUrl("login.php") ?>" class="btn btn-outline btn-sm nav-auth-btn">Entrar</a></li>
                    <li><a href="<?= pageUrl("cadastro.php") ?>" class="btn btn-primary btn-sm nav-auth-btn">Cadastrar</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>
<main class="main-content">
