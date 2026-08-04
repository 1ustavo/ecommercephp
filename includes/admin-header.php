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
        <nav aria-label="Navegação administrativa">
            <ul class="nav-links">
                <li><a href="<?= pageUrl("loja.php") ?>">Ver Loja</a></li>
                <li><a href="<?= apiUrl("logout.php") ?>" class="nav-logout">Sair</a></li>
            </ul>
        </nav>
    </div>
</header>
<div class="admin-layout">
    <aside class="admin-sidebar">
        <a href="<?= pageUrl("admin/dashboard.php") ?>" class="<?= $currentAdminPage === "dashboard" ? "active" : "" ?>">Dashboard</a>
        <a href="<?= pageUrl("admin/produtos.php") ?>" class="<?= $currentAdminPage === "produtos" ? "active" : "" ?>">Produtos</a>
        <a href="<?= pageUrl("admin/pedidos.php") ?>" class="<?= $currentAdminPage === "pedidos" ? "active" : "" ?>">Pedidos</a>
        <a href="<?= pageUrl("admin/relatorios.php") ?>" class="<?= $currentAdminPage === "relatorios" ? "active" : "" ?>">Relatórios</a>
        <a href="<?= pageUrl("admin/auditoria.php") ?>" class="<?= $currentAdminPage === "auditoria" ? "active" : "" ?>">Auditoria</a>
    </aside>
    <div class="admin-content">
