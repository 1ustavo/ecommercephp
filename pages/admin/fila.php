<?php

require_once __DIR__ . "/../../config/bootstrap.php";

$pageTitle = "Fila de Pedidos";
$currentAdminPage = "fila";
require __DIR__ . "/../../includes/admin-header.php";

// Paginação — máximo de 10 pedidos por página
$limitePorPagina = 10;
$paginaAtual = isset($_GET["pagina"]) ? max(1, (int) $_GET["pagina"]) : 1;
$totalPedidos = Pedido::contarTodos();
$totalPaginas = max(1, (int) ceil($totalPedidos / $limitePorPagina));
$paginaAtual = min($paginaAtual, $totalPaginas);

// Busca pedidos agrupados por status (com paginação)
$pedidos = Pedido::todosAdmin($paginaAtual, $limitePorPagina);

$porStatus = [
    "AGUARDANDO_APROVACAO" => [],
    "APROVADO" => [],
    "PAGO" => [],
    "ENVIADO" => [],
    "ENTREGUE" => [],
    "RECUSADO" => [],
    "CANCELADO" => [],
];

foreach ($pedidos as $p) {
    $status = $p["status"] ?? "AGUARDANDO_APROVACAO";
    if (!isset($porStatus[$status])) {
        $porStatus[$status] = [];
    }
    $porStatus[$status][] = $p;
}

// Contador de pedidos recentes (últimas 2 horas) = "pessoas comprando agora"
$limite = date("Y-m-d H:i:s", strtotime("-2 hours"));
$pedidosRecentes = $pdo->query("
    SELECT COUNT(*) FROM pedidos 
    WHERE created_at >= '{$limite}' 
    AND status NOT IN ('CANCELADO', 'RECUSADO')
")->fetchColumn();
?>

<h1 class="page-title">Fila de Pedidos</h1>
<p class="page-subtitle">Acompanhe os pedidos em tempo real por status.</p>

<div class="stats-grid" style="margin-bottom:24px">
    <div class="stat-card">
        <div class="stat-value"><?= (int) $pedidosRecentes ?></div>
        <div class="stat-label">Pedidos nas últimas 2 horas</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= count($porStatus["AGUARDANDO_APROVACAO"]) ?></div>
        <div class="stat-label">Aguardando aprovação</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= count($porStatus["APROVADO"]) + count($porStatus["PAGO"]) ?></div>
        <div class="stat-label">Aprovados/Pagos</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= count($porStatus["ENVIADO"]) ?></div>
        <div class="stat-label">Enviados</div>
    </div>
</div>

<div class="kanban-board">
    <?php
    $colunas = [
        "AGUARDANDO_APROVACAO" => "Aguardando Aprovação",
        "APROVADO" => "Aprovados",
        "PAGO" => "Pagos",
        "ENVIADO" => "Enviados",
        "ENTREGUE" => "Entregues",
        "RECUSADO" => "Recusados",
        "CANCELADO" => "Cancelados",
    ];
    foreach ($colunas as $status => $label):
        $itens = $porStatus[$status] ?? [];
        $badgeClass = statusPedidoClass($status);
    ?>
        <div class="kanban-column">
            <div class="kanban-header">
                <span class="kanban-title"><?= e($label) ?></span>
                <span class="badge <?= e($badgeClass) ?>"><?= count($itens) ?></span>
            </div>
            <div class="kanban-body">
                <?php if (empty($itens)): ?>
                    <div class="empty-state" style="padding:16px;text-align:center;color:var(--cinza-500);font-size:0.85rem">
                        Nenhum pedido
                    </div>
                <?php else: ?>
                    <?php foreach ($itens as $p): ?>
                        <?php
                        $data = new DateTime($p["created_at"]);
                        $totalItens = 0;
                        $itensPedido = Pedido::itens((int) $p["id"]);
                        foreach ($itensPedido as $i) {
                            $totalItens += (int) $i["quantidade"];
                        }
                        ?>
                        <div class="kanban-card">
                            <div class="flex justify-between items-center" style="margin-bottom:8px">
                                <strong>#<?= (int) $p["id"] ?></strong>
                                <span style="font-size:0.75rem;color:var(--cinza-500)">
                                    <?= $data->format("d/m H:i") ?>
                                </span>
                            </div>
                            <p style="margin:0 0 4px;font-size:0.85rem;color:var(--cinza-600)">
                                <?= e($p["cliente_nome"] ?? "—") ?>
                            </p>
                            <p style="margin:0 0 8px;font-size:0.85rem">
                                <?= $totalItens ?> item(ns) • <?= formatarMoeda((float) $p["total"]) ?>
                            </p>
                            <a href="<?= pageUrl("admin/pedidos.php") ?>?id=<?= (int) $p["id"] ?>"
                               class="btn btn-sm btn-outline" style="width:100%">
                                Ver detalhes
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

    <!-- Paginação -->
    <?php if ($totalPaginas > 1): ?>
        <?php
        // Gera a lista de páginas com janela deslizante
        $paginas = paginacaoJanela($paginaAtual, $totalPaginas, 2);
        ?>
        <div class="pagination-container">
            <?php if ($paginaAtual > 1): ?>
                <a href="<?= pageUrl("admin/fila.php") ?>?pagina=<?= $paginaAtual - 1 ?>" class="btn btn-gray btn-sm">← Anterior</a>
            <?php endif; ?>

            <?php foreach ($paginas as $pagina): ?>
                <?php if ($pagina === ".."): ?>
                    <span class="btn btn-sm" style="pointer-events:none;color:var(--cinza-500)">..</span>
                <?php elseif ($pagina === $paginaAtual): ?>
                    <span class="btn btn-primary btn-sm" style="pointer-events:none"><?= $pagina ?></span>
                <?php else: ?>
                    <a href="<?= pageUrl("admin/fila.php") ?>?pagina=<?= $pagina ?>" class="btn btn-gray btn-sm"><?= $pagina ?></a>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php if ($paginaAtual < $totalPaginas): ?>
                <a href="<?= pageUrl("admin/fila.php") ?>?pagina=<?= $paginaAtual + 1 ?>" class="btn btn-gray btn-sm">Próxima →</a>
            <?php endif; ?>
        </div>
        <p class="pagination-info">
            Página <?= $paginaAtual ?> de <?= $totalPaginas ?> • <?= $totalPedidos ?> pedidos no total
        </p>
    <?php endif; ?>

<?php require __DIR__ . "/../../includes/admin-footer.php"; ?>
