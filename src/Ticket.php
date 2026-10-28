<?php

class Ticket
{
    /**
     * Abre um novo ticket/chamado
     * REGRA: Cliente só pode abrir ticket para pedido que ele já fez
     */
    public static function abrir(int $pedidoId, int $usuarioId, string $assunto, string $categoria = 'GERAL', string $prioridade = 'MEDIA'): int
    {
        global $pdo;

        // VALIDAÇÃO CRÍTICA: Verifica se pedido existe e pertence ao usuário
        $stmt = $pdo->prepare("
            SELECT id FROM pedidos 
            WHERE id = ? AND usuario_id = ? AND status != 'CANCELADO'
        ");
        $stmt->execute([$pedidoId, $usuarioId]);
        
        if (!$stmt->fetch()) {
            throw new Exception("Pedido não encontrado ou não pertence a este usuário.");
        }

        // Verifica se já existe ticket aberto para este pedido
        $stmt = $pdo->prepare("
            SELECT id FROM tickets 
            WHERE pedido_id = ? AND usuario_id = ? AND status IN ('ABERTO', 'EM_ANDAMENTO')
        ");
        $stmt->execute([$pedidoId, $usuarioId]);
        
        if ($stmt->fetch()) {
            throw new Exception("Já existe um chamado aberto para este pedido.");
        }

        // Cria o ticket
        $stmt = $pdo->prepare("
            INSERT INTO tickets (pedido_id, usuario_id, assunto, categoria, prioridade)
            VALUES (?, ?, ?, ?, ?)
            RETURNING id
        ");
        $stmt->execute([$pedidoId, $usuarioId, $assunto, $categoria, $prioridade]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Busca ticket por ID
     */
    public static function buscarPorId(int $id): ?array
    {
        global $pdo;

        $stmt = $pdo->prepare("
            SELECT t.*, 
                   u.nome AS cliente_nome,
                   u.email AS cliente_email,
                   p.id AS numero_pedido,
                   p.total AS valor_pedido
            FROM tickets t
            INNER JOIN usuarios u ON u.id = t.usuario_id
            INNER JOIN pedidos p ON p.id = t.pedido_id
            WHERE t.id = ?
        ");
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Lista tickets de um usuário (área do cliente)
     */
    public static function listarPorUsuario(int $usuarioId, string $status = null): array
    {
        global $pdo;

        $sql = "
            SELECT t.*, 
                   p.id AS numero_pedido,
                   p.total AS valor_pedido,
                   COUNT(tm.id) AS total_mensagens,
                   MAX(tm.created_at) AS ultima_mensagem
            FROM tickets t
            INNER JOIN pedidos p ON p.id = t.pedido_id
            LEFT JOIN ticket_mensagens tm ON tm.ticket_id = t.id
            WHERE t.usuario_id = ?
        ";
        $params = [$usuarioId];

        if ($status) {
            $sql .= " AND t.status = ?";
            $params[] = $status;
        }

        $sql .= " GROUP BY t.id, p.id, p.total
                   ORDER BY t.created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Lista todos os tickets (painel admin)
     */
    public static function listarTodos(string $status = null, string $categoria = null, int $page = 1, int $limit = 20): array
    {
        global $pdo;

        $sql = "
            SELECT 
                t.id,
                t.pedido_id,
                t.usuario_id,
                t.assunto,
                t.status,
                t.prioridade,
                t.categoria,
                t.avaliacao,
                t.comentario_avaliacao,
                t.data_avaliacao,
                t.created_at,
                t.updated_at,
                t.closed_at,
                u.nome AS cliente_nome,
                u.email AS cliente_email,
                p.id AS numero_pedido,
                p.total AS valor_pedido,
                COUNT(tm.id) AS total_mensagens,
                MAX(tm.created_at) AS ultima_mensagem
            FROM tickets t
            INNER JOIN usuarios u ON u.id = t.usuario_id
            INNER JOIN pedidos p ON p.id = t.pedido_id
            LEFT JOIN ticket_mensagens tm ON tm.ticket_id = t.id
            WHERE 1=1
        ";
        $params = [];

        if ($status) {
            $sql .= " AND t.status = ?";
            $params[] = $status;
        }

        if ($categoria) {
            $sql .= " AND t.categoria = ?";
            $params[] = $categoria;
        }

        $sql .= " GROUP BY t.id, u.nome, u.email, p.id, p.total, t.assunto, t.status, t.prioridade, t.categoria, t.avaliacao, t.comentario_avaliacao, t.data_avaliacao, t.created_at, t.updated_at, t.closed_at, t.pedido_id, t.usuario_id
                   ORDER BY 
                       CASE t.prioridade 
                           WHEN 'URGENTE' THEN 1 
                           WHEN 'ALTA' THEN 2 
                           WHEN 'MEDIA' THEN 3 
                           WHEN 'BAIXA' THEN 4 
                       END,
                       t.created_at DESC";

        $page = max(1, $page);
        $limit = max(1, min(100, $limit));
        $offset = ($page - 1) * $limit;

        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Conta total de tickets (para paginação)
     */
    public static function contarTodos(string $status = null, string $categoria = null): int
    {
        global $pdo;

        $sql = "SELECT COUNT(*) FROM tickets t WHERE 1=1";
        $params = [];

        if ($status) {
            $sql .= " AND t.status = ?";
            $params[] = $status;
        }

        if ($categoria) {
            $sql .= " AND t.categoria = ?";
            $params[] = $categoria;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Atualiza status do ticket
     */
    public static function atualizarStatus(int $ticketId, string $status): void
    {
        global $pdo;

        $statusPermitidos = ['ABERTO', 'EM_ANDAMENTO', 'RESOLVIDO', 'FECHADO'];
        if (!in_array($status, $statusPermitidos)) {
            throw new Exception("Status inválido.");
        }

        $closedAt = in_array($status, ['RESOLVIDO', 'FECHADO']) ? 'NOW()' : 'NULL';

        $stmt = $pdo->prepare("
            UPDATE tickets 
            SET status = ?, 
                closed_at = $closedAt,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$status, $ticketId]);
    }

    /**
     * Atualiza prioridade do ticket
     */
    public static function atualizarPrioridade(int $ticketId, string $prioridade): void
    {
        global $pdo;

        $prioridadesPermitidas = ['BAIXA', 'MEDIA', 'ALTA', 'URGENTE'];
        if (!in_array($prioridade, $prioridadesPermitidas)) {
            throw new Exception("Prioridade inválida.");
        }

        $stmt = $pdo->prepare("
            UPDATE tickets 
            SET prioridade = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$prioridade, $ticketId]);
    }

    /**
     * Atualiza categoria do ticket
     */
    public static function atualizarCategoria(int $ticketId, string $categoria): void
    {
        global $pdo;

        $categoriasPermitidas = ['GERAL', 'PRODUTO', 'ENTREGA', 'PAGAMENTO', 'TROCA', 'DEVOLUCAO', 'OUTRO'];
        if (!in_array($categoria, $categoriasPermitidas)) {
            throw new Exception("Categoria inválida.");
        }

        $stmt = $pdo->prepare("
            UPDATE tickets 
            SET categoria = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$categoria, $ticketId]);
    }

    /**
     * Adiciona avaliação ao ticket
     */
    public static function avaliar(int $ticketId, int $nota, ?string $comentario = null): void
    {
        global $pdo;

        if ($nota < 1 || $nota > 5) {
            throw new Exception("Avaliação deve ser entre 1 e 5.");
        }

        $stmt = $pdo->prepare("
            UPDATE tickets 
            SET avaliacao = ?, 
                comentario_avaliacao = ?, 
                data_avaliacao = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$nota, $comentario, $ticketId]);
    }

    /**
     * Busca mensagens de um ticket
     */
    public static function mensagens(int $ticketId): array
    {
        global $pdo;

        $stmt = $pdo->prepare("
            SELECT tm.*, u.nome AS autor_nome
            FROM ticket_mensagens tm
            INNER JOIN usuarios u ON u.id = tm.usuario_id
            WHERE tm.ticket_id = ?
            ORDER BY tm.created_at ASC
        ");
        $stmt->execute([$ticketId]);

        return $stmt->fetchAll();
    }

    /**
     * Adiciona mensagem ao ticket
     */
    public static function adicionarMensagem(int $ticketId, int $usuarioId, string $mensagem, string $origem): void
    {
        global $pdo;

        // Valida origem
        if (!in_array($origem, ['CLIENTE', 'ADMIN'])) {
            throw new Exception("Origem inválida.");
        }

        // Verifica se ticket existe
        $stmt = $pdo->prepare("SELECT id FROM tickets WHERE id = ?");
        $stmt->execute([$ticketId]);
        
        if (!$stmt->fetch()) {
            throw new Exception("Ticket não encontrado.");
        }

        // Insere mensagem
        $stmt = $pdo->prepare("
            INSERT INTO ticket_mensagens (ticket_id, usuario_id, origem, mensagem)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$ticketId, $usuarioId, $origem, $mensagem]);

        // Atualiza status para EM_ANDAMENTO se estava ABERTO
        $pdo->prepare("
            UPDATE tickets 
            SET status = CASE WHEN status = 'ABERTO' THEN 'EM_ANDAMENTO' ELSE status END,
                updated_at = NOW()
            WHERE id = ?
        ")->execute([$ticketId]);
    }

    /**
     * Verifica se usuário pode acessar o ticket
     */
    public static function usuarioPodeAcessar(int $ticketId, int $usuarioId, bool $isAdmin = false): bool
    {
        global $pdo;

        if ($isAdmin) {
            // Admin pode acessar qualquer ticket
            return true;
        }

        // Cliente só pode acessar seus próprios tickets
        $stmt = $pdo->prepare("
            SELECT 1 FROM tickets 
            WHERE id = ? AND usuario_id = ?
        ");
        $stmt->execute([$ticketId, $usuarioId]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Estatísticas para dashboard admin
     */
    public static function estatisticas(): array
    {
        global $pdo;

        $stats = [];

        // Total por status
        $stmt = $pdo->query("
            SELECT status, COUNT(*) as total
            FROM tickets
            GROUP BY status
        ");
        foreach ($stmt->fetchAll() as $row) {
            $stats[$row['status']] = (int) $row['total'];
        }

        // Total por categoria
        $stmt = $pdo->query("
            SELECT categoria, COUNT(*) as total
            FROM tickets
            GROUP BY categoria
        ");
        $stats['categorias'] = $stmt->fetchAll();

        // Tickets de hoje
        $stmt = $pdo->query("
            SELECT COUNT(*) FROM tickets 
            WHERE created_at >= CURRENT_DATE
        ");
        $stats['hoje'] = (int) $stmt->fetchColumn();

        // Tickets pendentes (em andamento)
        $stmt = $pdo->query("
            SELECT COUNT(*) FROM tickets 
            WHERE status = 'EM_ANDAMENTO'
        ");
        $stats['pendentes'] = (int) $stmt->fetchColumn();

        return $stats;
    }
}