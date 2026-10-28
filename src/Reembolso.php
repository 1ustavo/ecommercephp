<?php

class Reembolso
{
    /** Status de pedido que permitem solicitar reembolso */
    public const STATUS_ELEGIVEIS = ["PAGO", "ENVIADO", "ENTREGUE"];

    /**
     * Cliente solicita reembolso de um pedido PRÓPRIO.
     * Valida: motivo, posse do pedido, status elegível, e que não há
     * reembolso APROVADO nem SOLICITADO em aberto.
     *
     * @throws Exception com mensagem pronta para exibir ao cliente
     * @return int id do reembolso criado
     */
    public static function solicitar(int $pedidoId, int $usuarioId, string $motivo): int
    {
        global $pdo;

        $motivo = trim($motivo);
        if (mb_strlen($motivo) < 10) {
            throw new Exception("Descreva o motivo do reembolso em pelo menos 10 caracteres.");
        }

        // Posse + status elegível (mesma query, sem vazar informação de pedidos alheios)
        $stmt = $pdo->prepare("
            SELECT id FROM pedidos
            WHERE id = ? AND usuario_id = ? AND status IN ('PAGO', 'ENVIADO', 'ENTREGUE')
        ");
        $stmt->execute([$pedidoId, $usuarioId]);
        if (!$stmt->fetch()) {
            throw new Exception("Pedido não encontrado, não pertence a você ou não permite reembolso.");
        }

        // Não reembolsar duas vezes
        $stmt = $pdo->prepare("SELECT id FROM reembolsos WHERE pedido_id = ? AND status = 'APROVADO'");
        $stmt->execute([$pedidoId]);
        if ($stmt->fetch()) {
            throw new Exception("Este pedido já foi reembolsado.");
        }

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO reembolsos (pedido_id, usuario_id, motivo) VALUES (?, ?, ?) RETURNING id"
            );
            $stmt->execute([$pedidoId, $usuarioId, $motivo]);
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            // 23505 = violação do índice único parcial (já existe SOLICITADO em aberto)
            if ($e->getCode() === "23505") {
                throw new Exception("Já existe uma solicitação de reembolso em análise para este pedido.");
            }
            throw $e;
        }
    }

    /** Busca reembolso com dados do pedido e do cliente (uso admin + notificações) */
    public static function buscarPorId(int $id): ?array
    {
        global $pdo;
        $stmt = $pdo->prepare("
            SELECT r.*, p.total AS pedido_total, p.status AS pedido_status,
                   u.nome AS cliente_nome, u.email AS cliente_email
            FROM reembolsos r
            JOIN pedidos p ON p.id = r.pedido_id
            JOIN usuarios u ON u.id = r.usuario_id
            WHERE r.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** Reembolso mais recente de um pedido PRÓPRIO (uso na página do cliente) */
    public static function buscarPorPedido(int $pedidoId, int $usuarioId): ?array
    {
        global $pdo;
        $stmt = $pdo->prepare("
            SELECT * FROM reembolsos
            WHERE pedido_id = ? AND usuario_id = ?
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$pedidoId, $usuarioId]);
        return $stmt->fetch() ?: null;
    }

    /** Lista para o painel admin (com join p/ exibir cliente e total) */
    public static function listarAdmin(?string $status, int $page = 1, int $limit = 10): array
    {
        global $pdo;
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));
        $offset = ($page - 1) * $limit;

        $sql = "
            SELECT r.*, p.total AS pedido_total, p.status AS pedido_status, u.nome AS cliente_nome, u.email AS cliente_email FROM reembolsos r JOIN pedidos p ON p.id = r.pedido_id
            JOIN usuarios u ON u.id = r.usuario_id
        ";
        $params = [];
        if ($status) {
            $sql .= " WHERE r.status = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY r.created_at DESC LIMIT {$limit} OFFSET {$offset}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function contarAdmin(?string $status = null): int
    {
        global $pdo;
        if ($status) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM reembolsos WHERE status = ?");
            $stmt->execute([$status]);
        } else {
            $stmt = $pdo->query("SELECT COUNT(*) FROM reembolsos");
        }
        return (int) $stmt->fetchColumn();
    }

    /**
     * ADMIN/GERENTE aprova o reembolso.
     * Transição atômica: UPDATE ... WHERE status='SOLICITADO' (rowCount).
     * Estorno: real via Stripe quando houver payment_intent capturado
     * e chave configurada; caso contrário, estorno simulado (interno).
     * O estorno é registrado na tabela payments (status='REFUND').
     */
    public static function aprovar(int $reembolsoId, int $adminId): bool
    {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM reembolsos WHERE id = ?");
        $stmt->execute([$reembolsoId]);
        $reembolso = $stmt->fetch();
        if (!$reembolso || $reembolso["status"] !== "SOLICITADO") {
            return false;
        }

        // Estorno externo ANTES da transação: se falhar, a aprovação não prossegue
        $estornoStripe = self::tentarEstornoStripe((int) $reembolso["pedido_id"]);
        if ($estornoStripe === false) {
            return false;
        }

        try {
            $pdo->beginTransaction();

            $upd = $pdo->prepare("
                UPDATE reembolsos SET status = 'APROVADO', updated_at = NOW()
                WHERE id = ? AND status = 'SOLICITADO'
            ");
            $upd->execute([$reembolsoId]);
            if ($upd->rowCount() !== 1) {
                $pdo->rollBack();
                return false; // outra decisão venceu (concorrência)
            }

            $totalCents = self::totalPedidoEmCents((int) $reembolso["pedido_id"]);
            if ($estornoStripe !== null) {
                $pdo->prepare("
                    INSERT INTO payments (pedido_id, gateway, stripe_payment_intent_id, amount_cents, currency, status, metadata, created_at)
                    VALUES (?, 'stripe', ?, ?, 'brl', 'REFUND', ?::jsonb, NOW())
                ")->execute([
                    $reembolso["pedido_id"],
                    $estornoStripe["payment_intent"],
                    $totalCents,
                    json_encode(["refund_id" => $estornoStripe["refund_id"], "reembolso_id" => (int) $reembolsoId, "aprovado_por" => $adminId]),
                ]);
            } else {
                $pdo->prepare("
                    INSERT INTO payments (pedido_id, gateway, amount_cents, currency, status, metadata, created_at)
                    VALUES (?, 'interno', ?, 'brl', 'REFUND', ?::jsonb, NOW())
                ")->execute([
                    $reembolso["pedido_id"],
                    $totalCents,
                    json_encode(["reembolso_id" => (int) $reembolsoId, "aprovado_por" => $adminId, "tipo" => "estorno_simulado"]),
                ]);
            }

            $pdo->commit();
            try { Notificacao::reembolsoAprovado($reembolsoId); } catch (Throwable $e) { error_log('Erro ao notificar reembolso aprovado: ' . $e->getMessage()); }
            return true;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Erro ao aprovar reembolso #{$reembolsoId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ADMIN/GERENTE recusa o reembolso. Motivo obrigatório.
     * Transição atômica: só recusa se ainda estiver SOLICITADO.
     */
    public static function recusar(int $reembolsoId, int $adminId, string $motivo): bool
    {
        global $pdo;

        $motivo = trim($motivo);
        if ($motivo === "") {
            return false;
        }

        // Garante estado SOLICITADO e pega o pedido (para notificar) em uma query
        $stmt = $pdo->prepare("SELECT pedido_id FROM reembolsos WHERE id = ? AND status = 'SOLICITADO'");
        $stmt->execute([$reembolsoId]);
        $pedidoId = $stmt->fetchColumn();
        if ($pedidoId === false) {
            return false;
        }

        $stmt = $pdo->prepare("
            UPDATE reembolsos
            SET status = 'RECUSADO', motivo_recusa = ?, updated_at = NOW()
            WHERE id = ? AND status = 'SOLICITADO'
        ");
        $stmt->execute([$motivo, $reembolsoId]);
        if ($stmt->rowCount() !== 1) {
            return false;
        }

        try { Notificacao::reembolsoRecusado($reembolsoId); } catch (Throwable $e) { error_log('Erro ao notificar reembolso recusado: ' . $e->getMessage()); }
        return true;
    }

    /**
     * Tenta estorno real via Stripe. Retorna:
     *   - array  -> estorno criado (refund_id, payment_intent)
     *   - null   -> sem Stripe configurado/payment_intent: usar estorno simulado
     *   - false  -> Stripe configurado mas o estorno FALHOU: aprovação deve abortar
     */
    private static function tentarEstornoStripe(int $pedidoId): array|false|null
    {
        $secret = $_ENV["STRIPE_SECRET_KEY"] ?? getenv("STRIPE_SECRET_KEY") ?: "";
        if ($secret === "") {
            return null;
        }

        global $pdo;
        $stmt = $pdo->prepare("
            SELECT stripe_payment_intent_id FROM payments
            WHERE pedido_id = ? AND gateway = 'stripe'
              AND stripe_payment_intent_id IS NOT NULL
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$pedidoId]);
        $paymentIntent = $stmt->fetchColumn();
        if (!$paymentIntent) {
            return null;
        }

        try {
            $stripe = new \Stripe\StripeClient($secret);
            $refund = $stripe->refunds->create(["payment_intent" => $paymentIntent]);
            return ["refund_id" => $refund->id, "payment_intent" => $paymentIntent];
        } catch (\Throwable $e) {
            error_log("Falha no estorno Stripe do pedido #{$pedidoId}: " . $e->getMessage());
            return false;
        }
    }

    private static function totalPedidoEmCents(int $pedidoId): int
    {
        global $pdo;
        $stmt = $pdo->prepare("SELECT total FROM pedidos WHERE id = ?");
        $stmt->execute([$pedidoId]);
        return (int) round(((float) $stmt->fetchColumn()) * 100);
    }
}
