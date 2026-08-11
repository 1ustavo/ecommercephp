<?php
require_once __DIR__ . "/../config/bootstrap.php";

$auth = new Auth();
$auth->verificarAdmin();
$pageTitle = $pageTitle ?? "Admin";
$currentAdminPage = $currentAdminPage ?? "";
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> – Admin</title>
    <link rel="stylesheet" href="<?= asset("css/theme.css") ?>">
</head>
<body>
<header class="site-header">
    <div class="header-inner">
        <a href="<?= pageUrl("admin/dashboard.php") ?>" class="logo" aria-label="EcoShop Admin - Dashboard">
            <span class="logo-icon" aria-hidden="true"></span>
            Eco<span>Shop</span> <span class="admin-badge">Admin</span>
        </a>
        <button type="button" class="admin-menu-toggle" id="btn-admin-menu" aria-label="Abrir menu" aria-expanded="false">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <nav aria-label="Navegação administrativa">
            <ul class="nav-links">
                <li><a href="<?= pageUrl("loja.php") ?>">Ver Loja</a></li>
                <li>
                    <form action="<?= apiUrl("logout.php") ?>" method="post" style="display:inline">
                        <?= csrf_input() ?>
                        <button type="submit" class="nav-logout btn-link">Sair</button>
                    </form>
                </li>
            </ul>
        </nav>
    </div>
</header>
<div class="admin-layout">
    <div class="admin-sidebar-overlay" id="admin-sidebar-overlay"></div>
    <aside class="admin-sidebar" id="admin-sidebar">
        <a href="<?= pageUrl("admin/dashboard.php") ?>" class="<?= $currentAdminPage === "dashboard" ? "active" : "" ?>">Dashboard</a>
        <a href="<?= pageUrl("admin/produtos.php") ?>" class="<?= $currentAdminPage === "produtos" ? "active" : "" ?>">Produtos</a>
        <a href="<?= pageUrl("admin/categorias.php") ?>" class="<?= $currentAdminPage === "categorias" ? "active" : "" ?>">Categorias</a>
        <a href="<?= pageUrl("admin/cupons.php") ?>" class="<?= $currentAdminPage === "cupons" ? "active" : "" ?>">Cupons</a>
        <a href="<?= pageUrl("admin/pedidos_pendentes.php") ?>" class="<?= $currentAdminPage === "pedidos_pendentes" ? "active" : "" ?>">Pedidos Pendentes</a>
        <a href="<?= pageUrl("admin/fila.php") ?>" class="<?= $currentAdminPage === "fila" ? "active" : "" ?>">Fila de Pedidos</a>
        <a href="<?= pageUrl("admin/pedidos.php") ?>" class="<?= $currentAdminPage === "pedidos" ? "active" : "" ?>">Pedidos</a>
        <a href="<?= pageUrl("admin/relatorios.php") ?>" class="<?= $currentAdminPage === "relatorios" ? "active" : "" ?>">Relatórios</a>
        <a href="<?= pageUrl("admin/auditoria.php") ?>" class="<?= $currentAdminPage === "auditoria" ? "active" : "" ?>">Auditoria</a>
    </aside>
    <div class="admin-content">
