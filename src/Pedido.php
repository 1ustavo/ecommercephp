<?php

class Pedido
{
    public static function criar(int $usuarioId, int $enderecoId, string $metodoFrete, string $metodoPagamento, float $desconto = 0, float $imposto = 0, ?int $cupomId = null): ?int
    {
        global $pdo;

        $itens = Carrinho::itens();

        if (empty($itens)) {
            return null;
        }

        foreach ($itens as $item) {
            $variacaoId = $item["variacao_id"] ? (int) $item["variacao_id"] : null;

            if (!Produto::estoqueDisponivel((int) $item["produto_id"], $variacaoId, (int) $item["quantidade"])) {
                return null;
            }
        }

        $subtotal = Carrinho::subtotal();
        $frete = calcularFrete($metodoFrete, $subtotal);
        $total = max(0, $subtotal + $frete + $imposto - $desconto);

        try {
            $pdo->beginTransaction();

            // NOVO FLUXO: pedido nasce aguardando aprovacao do admin.
            // Estoque NAO e decrementado aqui; so sera decrementado
            // quando o admin aprovar o pedido (ver Pedido::aprovar()).
            $stmt = $pdo->prepare("
                INSERT INTO pedidos (usuario_id, endereco_id, status, subtotal, frete, desconto, imposto, total, metodo_pagamento, metodo_frete, cupom_id)
                VALUES (?, ?, 'AGUARDANDO_APROVACAO', ?, ?, ?, ?, ?, ?, ?, ?)
                RETURNING id
            ");
            $stmt->execute([$usuarioId, $enderecoId, $subtotal, $frete, $desconto, $imposto, $total, $metodoPagamento, $metodoFrete, $cupomId]);
            $pedidoId = (int) $stmt->fetchColumn();

            foreach ($itens as $item) {
                $variacaoDesc = trim(($item["tamanho"] ?? "") . " " . ($item["cor"] ?? ""));

                $pdo->prepare("
                    INSERT INTO pedido_itens (pedido_id, produto_id, variacao_id, nome_produto, variacao_descricao, preco_unitario, quantidade, subtotal)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $pedidoId,
                    $item["produto_id"],
                    $item["variacao_id"],
                    $item["nome"],
                    $variacaoDesc ?: null,
                    $item["preco_final"],
                    $item["quantidade"],
                    $item["subtotal"],
                ]);
                // Estoque nao decrementado aqui (fluxo de aprovacao).
            }

            Carrinho::limpar();
            $pdo->commit();

            return $pedidoId;
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log($e->getMessage());

            return null;
        }
    }

    /**
     * Aprova um pedido que estava aguardando aprovacao.
     * Transicao: AGUARDANDO_APROVACAO -> PENDENTE
     * Neste ponto o estoque e decrementado (efetiva a "reserva" do pedido).
     *
     * @return bool true se aprovou, false se o pedido nao estava aguardando aprovacao
     */
    public static function aprovar(int $pedidoId, int $adminId): bool
    {
        global $pdo;

        $pedido = self::buscar($pedidoId);
        if (!$pedido || $pedido["status"] !== "AGUARDANDO_APROVACAO") {
            return false;
        }

        // Revalida estoque antes de aprovar (pode ter mudado desde o checkout)
        $itens = self::itens($pedidoId);
        foreach ($itens as $item) {
            $variacaoId = $item["variacao_id"] ? (int) $item["variacao_id"] : null;
            if (!Produto::estoqueDisponivel((int) $item["produto_id"], $variacaoId, (int) $item["quantidade"])) {
                return false;
            }
        }

        try {
            $pdo->beginTransaction();

            // Decrementa o estoque de forma atômica com condição de estoque suficiente.
            // Se algum item não tiver estoque suficiente, o rowCount será 0 e a transação é revertida.
            foreach ($itens as $item) {
                $variacaoId = $item["variacao_id"] ? (int) $item["variacao_id"] : null;
                $ok = Produto::decrementarEstoque((int) $item["produto_id"], $variacaoId, (int) $item["quantidade"]);
                if (!$ok) {
                    $pdo->rollBack();
                    error_log("Estoque insuficiente ao aprovar pedido #{$pedidoId} para produto #{$item['produto_id']}");
                    return false;
                }
            }

            // Atualiza status e registra quem/quando aprovou
            $pdo->prepare("
                UPDATE pedidos
                SET status = 'APROVADO',
                    decisao_admin = 'APROVADO',
                    aprovado_por = ?,
                    aprovado_em = NOW(),
                    updated_at = NOW()
                WHERE id = ? AND status = 'AGUARDANDO_APROVACAO'
            ")->execute([$adminId, $pedidoId]);

            $pdo->commit();
            return true;
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Erro ao aprovar pedido #{$pedidoId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Recusa um pedido que estava aguardando aprovacao.
     * Transicao: AGUARDANDO_APROVACAO -> RECUSADO
     * Como o estoque nao foi decrementado na criacao, nao ha nada a restaurar.
     *
     * @return bool true se recusou, false se o pedido nao estava aguardando aprovacao
     */
    public static function recusar(int $pedidoId, int $adminId, string $motivo): bool
    {
        global $pdo;

        $motivo = trim($motivo);
        if ($motivo === "") {
            return false;
        }

        $pedido = self::buscar($pedidoId);
        if (!$pedido || $pedido["status"] !== "AGUARDANDO_APROVACAO") {
            return false;
        }

        $pdo->prepare("
            UPDATE pedidos
            SET status = 'RECUSADO',
                decisao_admin = 'RECUSADO',
                motivo_recusa = ?,
                aprovado_por = ?,
                aprovado_em = NOW(),
                updated_at = NOW()
            WHERE id = ? AND status = 'AGUARDANDO_APROVACAO'
        ")->execute([$motivo, $adminId, $pedidoId]);

        return true;
    }

    /**
     * Lista pedidos aguardando aprovacao (para o painel admin).
     */
    public static function pendentesAprovacao(int $page = 1, int $limit = 10): array
    {
        global $pdo;

        $page = max(1, $page);
        $limit = max(1, min(100, $limit));
        $offset = ($page - 1) * $limit;

        $stmt = $pdo->prepare("
            SELECT p.*, u.nome AS cliente_nome, u.email AS cliente_email
            FROM pedidos p
            JOIN usuarios u ON u.id = p.usuario_id
            WHERE p.status = 'AGUARDANDO_APROVACAO'
            ORDER BY p.created_at ASC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);

        return $stmt->fetchAll();
    }

    public static function contarPendentesAprovacao(): int
    {
        global $pdo;

        return (int) $pdo->query("
            SELECT COUNT(*) FROM pedidos WHERE status = 'AGUARDANDO_APROVACAO'
        ")->fetchColumn();
    }

    public static function confirmarPagamento(int $pedidoId): void
    {
        global $pdo;

        $pdo->prepare("UPDATE pedidos SET status = 'PAGO', updated_at = NOW() WHERE id = ? AND status IN ('PENDENTE', 'APROVADO')")
            ->execute([$pedidoId]);
    }

    public static function porUsuario(int $usuarioId, int $page = 1, int $limit = 20): array
    {
        global $pdo;

        $page = max(1, $page);
        $limit = max(1, min(100, $limit));
        $offset = ($page - 1) * $limit;

        $stmt = $pdo->prepare("
            SELECT * FROM pedidos
            WHERE usuario_id = ?
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$usuarioId, $limit, $offset]);

        return $stmt->fetchAll();
    }

    public static function contarPorUsuario(int $usuarioId): int
    {
        global $pdo;

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE usuario_id = ?");
        $stmt->execute([$usuarioId]);

        return (int) $stmt->fetchColumn();
    }

    public static function buscar(int $id): ?array
    {
        global $pdo;

        $stmt = $pdo->prepare("
            SELECT p.*, u.nome AS cliente_nome, u.email AS cliente_email,
                   e.rua, e.numero, e.complemento, e.bairro, e.cidade, e.estado, e.cep
            FROM pedidos p
            JOIN usuarios u ON u.id = p.usuario_id
            LEFT JOIN enderecos e ON e.id = p.endereco_id
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public static function itens(int $pedidoId): array
    {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM pedido_itens WHERE pedido_id = ?");
        $stmt->execute([$pedidoId]);

        return $stmt->fetchAll();
    }

    public static function todosAdmin(int $page = 1, int $limit = 20): array
    {
        global $pdo;

        $page = max(1, $page);
        $limit = max(1, min(100, $limit));
        $offset = ($page - 1) * $limit;

        $stmt = $pdo->prepare("
            SELECT p.*, u.nome AS cliente_nome
            FROM pedidos p
            JOIN usuarios u ON u.id = p.usuario_id
            ORDER BY p.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);

        return $stmt->fetchAll();
    }

    public static function contarTodos(): int
    {
        global $pdo;

        return (int) $pdo->query("SELECT COUNT(*) FROM pedidos")->fetchColumn();
    }

    public static function atualizarStatus(int $id, string $status): void
    {
        global $pdo;

        // RECUSADO nao e setavel por atualizarStatus: so via recusar()
        $permitidos = ["APROVADO", "PENDENTE", "PAGO", "ENVIADO", "ENTREGUE", "CANCELADO"];

        if (!in_array($status, $permitidos, true)) {
            return;
        }

        $pdo->prepare("UPDATE pedidos SET status = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$status, $id]);
    }

    public static function estatisticas(): array
    {
        global $pdo;

        $total = $pdo->query("SELECT COUNT(*) FROM pedidos")->fetchColumn();
        $faturamento = $pdo->query("SELECT COALESCE(SUM(total), 0) FROM pedidos WHERE status NOT IN ('CANCELADO', 'RECUSADO')")->fetchColumn();
        $faturamentoBruto = $pdo->query("SELECT COALESCE(SUM(subtotal + frete), 0) FROM pedidos WHERE status NOT IN ('CANCELADO', 'RECUSADO')")->fetchColumn();
        $impostoTotal = $pdo->query("SELECT COALESCE(SUM(imposto), 0) FROM pedidos WHERE status NOT IN ('CANCELADO', 'RECUSADO')")->fetchColumn();
        $pendentes = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE status IN ('PENDENTE', 'APROVADO')")->fetchColumn();
        $aguardandoAprovacao = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE status = 'AGUARDANDO_APROVACAO'")->fetchColumn();
        $produtos = $pdo->query("SELECT COUNT(*) FROM produtos WHERE ativo = TRUE")->fetchColumn();

        return [
            "total_pedidos" => (int) $total,
            "faturamento" => (float) $faturamento,
            "faturamento_bruto" => (float) $faturamentoBruto,
            "imposto_total" => (float) $impostoTotal,
            "pendentes" => (int) $pendentes,
            "aguardando_aprovacao" => (int) $aguardandoAprovacao,
            "produtos_ativos" => (int) $produtos,
        ];
    }

    /* ============================================================
       RELATÓRIOS — Agregação de pedidos por mês e total geral
       ============================================================ */

    /**
     * Agrega pedidos de um mês específico (formato YYYY-MM).
     * Retorna contagem, somas de valores e distribuição por status.
     */
    public static function relatorioMensal(string $mes): array
    {
        global $pdo;

        // Valida formato YYYY-MM
        if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
            return [
                "mes" => $mes,
                "total_pedidos" => 0,
                "faturamento_bruto" => 0.0,
                "faturamento_liquido" => 0.0,
                "imposto_total" => 0.0,
                "desconto_total" => 0.0,
                "frete_total" => 0.0,
                "status" => [],
                "erro" => "Formato de mês inválido. Use YYYY-MM.",
            ];
        }

        // Agregado geral do mês (exclui cancelados/recusados do faturamento)
        $stmt = $pdo->prepare("
            SELECT
                COUNT(*) AS total_pedidos,
                COALESCE(SUM(CASE WHEN status NOT IN ('CANCELADO', 'RECUSADO') THEN total ELSE 0 END), 0) AS faturamento_liquido,
                COALESCE(SUM(CASE WHEN status NOT IN ('CANCELADO', 'RECUSADO') THEN subtotal + frete ELSE 0 END), 0) AS faturamento_bruto,
                COALESCE(SUM(CASE WHEN status NOT IN ('CANCELADO', 'RECUSADO') THEN imposto ELSE 0 END), 0) AS imposto_total,
                COALESCE(SUM(CASE WHEN status NOT IN ('CANCELADO', 'RECUSADO') THEN desconto ELSE 0 END), 0) AS desconto_total,
                COALESCE(SUM(CASE WHEN status NOT IN ('CANCELADO', 'RECUSADO') THEN frete ELSE 0 END), 0) AS frete_total
            FROM pedidos
            WHERE TO_CHAR(created_at, 'YYYY-MM') = ?
        ");
        $stmt->execute([$mes]);
        $agregado = $stmt->fetch();

        // Distribuição por status no mês
        $stmt = $pdo->prepare("
            SELECT status, COUNT(*) AS quantidade, COALESCE(SUM(total), 0) AS valor_total
            FROM pedidos
            WHERE TO_CHAR(created_at, 'YYYY-MM') = ?
            GROUP BY status
            ORDER BY quantidade DESC
        ");
        $stmt->execute([$mes]);
        $porStatus = $stmt->fetchAll();

        $statusMap = [];
        foreach ($porStatus as $s) {
            $statusMap[$s["status"]] = [
                "quantidade" => (int) $s["quantidade"],
                "valor_total" => (float) $s["valor_total"],
            ];
        }

        return [
            "mes" => $mes,
            "total_pedidos" => (int) $agregado["total_pedidos"],
            "faturamento_bruto" => (float) $agregado["faturamento_bruto"],
            "faturamento_liquido" => (float) $agregado["faturamento_liquido"],
            "imposto_total" => (float) $agregado["imposto_total"],
            "desconto_total" => (float) $agregado["desconto_total"],
            "frete_total" => (float) $agregado["frete_total"],
            "status" => $statusMap,
        ];
    }

    /**
     * Agrega todos os pedidos (sem filtro de mês).
     * Retorna contagem, somas de valores e distribuição por status.
     */
    public static function relatorioTotal(): array
    {
        global $pdo;

        // Agregado geral (exclui cancelados/recusados do faturamento)
        $agregado = $pdo->query("
            SELECT
                COUNT(*) AS total_pedidos,
                COALESCE(SUM(CASE WHEN status NOT IN ('CANCELADO', 'RECUSADO') THEN total ELSE 0 END), 0) AS faturamento_liquido,
                COALESCE(SUM(CASE WHEN status NOT IN ('CANCELADO', 'RECUSADO') THEN subtotal + frete ELSE 0 END), 0) AS faturamento_bruto,
                COALESCE(SUM(CASE WHEN status NOT IN ('CANCELADO', 'RECUSADO') THEN imposto ELSE 0 END), 0) AS imposto_total,
                COALESCE(SUM(CASE WHEN status NOT IN ('CANCELADO', 'RECUSADO') THEN desconto ELSE 0 END), 0) AS desconto_total,
                COALESCE(SUM(CASE WHEN status NOT IN ('CANCELADO', 'RECUSADO') THEN frete ELSE 0 END), 0) AS frete_total
            FROM pedidos
        ")->fetch();

        // Distribuição por status (todos os pedidos)
        $porStatus = $pdo->query("
            SELECT status, COUNT(*) AS quantidade, COALESCE(SUM(total), 0) AS valor_total
            FROM pedidos
            GROUP BY status
            ORDER BY quantidade DESC
        ")->fetchAll();

        $statusMap = [];
        foreach ($porStatus as $s) {
            $statusMap[$s["status"]] = [
                "quantidade" => (int) $s["quantidade"],
                "valor_total" => (float) $s["valor_total"],
            ];
        }

        return [
            "mes" => "total",
            "total_pedidos" => (int) $agregado["total_pedidos"],
            "faturamento_bruto" => (float) $agregado["faturamento_bruto"],
            "faturamento_liquido" => (float) $agregado["faturamento_liquido"],
            "imposto_total" => (float) $agregado["imposto_total"],
            "desconto_total" => (float) $agregado["desconto_total"],
            "frete_total" => (float) $agregado["frete_total"],
            "status" => $statusMap,
        ];
    }
}
