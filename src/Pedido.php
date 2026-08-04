<?php

class Pedido
{
    public static function criar(int $usuarioId, int $enderecoId, string $metodoFrete, string $metodoPagamento, float $desconto = 0): ?int
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
        $total = max(0, $subtotal + $frete - $desconto);

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO pedidos (usuario_id, endereco_id, status, subtotal, frete, desconto, total, metodo_pagamento, metodo_frete)
                VALUES (?, ?, 'PENDENTE', ?, ?, ?, ?, ?, ?)
                RETURNING id
            ");
            $stmt->execute([$usuarioId, $enderecoId, $subtotal, $frete, $desconto, $total, $metodoPagamento, $metodoFrete]);
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

                if ($item["variacao_id"]) {
                    $pdo->prepare("UPDATE produto_variacoes SET estoque = estoque - ? WHERE id = ?")
                        ->execute([$item["quantidade"], $item["variacao_id"]]);
                }

                $pdo->prepare("UPDATE produtos SET estoque = estoque - ?, updated_at = NOW() WHERE id = ?")
                    ->execute([$item["quantidade"], $item["produto_id"]]);
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

    public static function confirmarPagamento(int $pedidoId): void
    {
        global $pdo;

        $pdo->prepare("UPDATE pedidos SET status = 'PAGO', updated_at = NOW() WHERE id = ? AND status = 'PENDENTE'")
            ->execute([$pedidoId]);
    }

    public static function porUsuario(int $usuarioId): array
    {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE usuario_id = ? ORDER BY created_at DESC");
        $stmt->execute([$usuarioId]);

        return $stmt->fetchAll();
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

    public static function todosAdmin(): array
    {
        global $pdo;

        return $pdo->query("
            SELECT p.*, u.nome AS cliente_nome
            FROM pedidos p
            JOIN usuarios u ON u.id = p.usuario_id
            ORDER BY p.created_at DESC
        ")->fetchAll();
    }

    public static function atualizarStatus(int $id, string $status): void
    {
        global $pdo;

        $permitidos = ["PENDENTE", "PAGO", "ENVIADO", "ENTREGUE", "CANCELADO"];

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
        $faturamento = $pdo->query("SELECT COALESCE(SUM(total), 0) FROM pedidos WHERE status NOT IN ('CANCELADO')")->fetchColumn();
        $pendentes = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE status = 'PENDENTE'")->fetchColumn();
        $produtos = $pdo->query("SELECT COUNT(*) FROM produtos WHERE ativo = TRUE")->fetchColumn();

        return [
            "total_pedidos" => (int) $total,
            "faturamento" => (float) $faturamento,
            "pendentes" => (int) $pendentes,
            "produtos_ativos" => (int) $produtos,
        ];
    }
}
