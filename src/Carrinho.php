<?php

class Carrinho
{
    private static function obterCarrinhoId(): int
    {
        global $pdo;

        $usuarioId = $_SESSION["usuario_id"] ?? null;
        $sessionId = getSessionId();

        if ($usuarioId) {
            $stmt = $pdo->prepare("SELECT id FROM carrinhos WHERE usuario_id = ?");
            $stmt->execute([$usuarioId]);
            $id = $stmt->fetchColumn();

            if ($id) {
                return (int) $id;
            }

            $stmt = $pdo->prepare("INSERT INTO carrinhos (usuario_id, session_id) VALUES (?, ?) RETURNING id");
            $stmt->execute([$usuarioId, $sessionId]);

            return (int) $stmt->fetchColumn();
        }

        $stmt = $pdo->prepare("SELECT id FROM carrinhos WHERE session_id = ? AND usuario_id IS NULL");
        $stmt->execute([$sessionId]);
        $id = $stmt->fetchColumn();

        if ($id) {
            return (int) $id;
        }

        $stmt = $pdo->prepare("INSERT INTO carrinhos (session_id) VALUES (?) RETURNING id");
        $stmt->execute([$sessionId]);

        return (int) $stmt->fetchColumn();
    }

    public static function mesclarSessaoComUsuario(int $usuarioId): void
    {
        global $pdo;

        $sessionId = getSessionId();

        $stmt = $pdo->prepare("SELECT id FROM carrinhos WHERE session_id = ? AND usuario_id IS NULL");
        $stmt->execute([$sessionId]);
        $carrinhoGuest = $stmt->fetchColumn();

        if (!$carrinhoGuest) {
            return;
        }

        $stmt = $pdo->prepare("SELECT id FROM carrinhos WHERE usuario_id = ?");
        $stmt->execute([$usuarioId]);
        $carrinhoUser = $stmt->fetchColumn();

        if ($carrinhoUser) {
            $pdo->prepare("
                INSERT INTO carrinho_itens (carrinho_id, produto_id, variacao_id, quantidade)
                SELECT ?, produto_id, variacao_id, quantidade
                FROM carrinho_itens WHERE carrinho_id = ?
                ON CONFLICT (carrinho_id, produto_id, variacao_id)
                DO UPDATE SET quantidade = carrinho_itens.quantidade + EXCLUDED.quantidade
            ")->execute([$carrinhoUser, $carrinhoGuest]);

            $pdo->prepare("DELETE FROM carrinhos WHERE id = ?")->execute([$carrinhoGuest]);
        } else {
            $pdo->prepare("UPDATE carrinhos SET usuario_id = ? WHERE id = ?")->execute([$usuarioId, $carrinhoGuest]);
        }
    }

    public static function adicionar(int $produtoId, ?int $variacaoId, int $quantidade = 1): bool
    {
        global $pdo;

        if (!Produto::estoqueDisponivel($produtoId, $variacaoId, $quantidade)) {
            return false;
        }

        $carrinhoId = self::obterCarrinhoId();

        $stmt = $pdo->prepare("
            INSERT INTO carrinho_itens (carrinho_id, produto_id, variacao_id, quantidade)
            VALUES (?, ?, ?, ?)
            ON CONFLICT (carrinho_id, produto_id, variacao_id)
            DO UPDATE SET quantidade = carrinho_itens.quantidade + EXCLUDED.quantidade
        ");
        $stmt->execute([$carrinhoId, $produtoId, $variacaoId, $quantidade]);

        return true;
    }

    public static function atualizar(int $itemId, int $quantidade): bool
    {
        global $pdo;

        $carrinhoId = self::obterCarrinhoId();

        if ($quantidade <= 0) {
            return self::remover($itemId);
        }

        $stmt = $pdo->prepare("
            SELECT ci.* FROM carrinho_itens ci
            WHERE ci.id = ? AND ci.carrinho_id = ?
        ");
        $stmt->execute([$itemId, $carrinhoId]);
        $item = $stmt->fetch();

        if (!$item || !Produto::estoqueDisponivel((int) $item["produto_id"], $item["variacao_id"] ? (int) $item["variacao_id"] : null, $quantidade)) {
            return false;
        }

        $pdo->prepare("UPDATE carrinho_itens SET quantidade = ? WHERE id = ?")->execute([$quantidade, $itemId]);

        return true;
    }

    public static function remover(int $itemId): bool
    {
        global $pdo;

        $carrinhoId = self::obterCarrinhoId();
        $stmt = $pdo->prepare("DELETE FROM carrinho_itens WHERE id = ? AND carrinho_id = ?");

        return $stmt->execute([$itemId, $carrinhoId]);
    }

    public static function itens(): array
    {
        global $pdo;

        $carrinhoId = self::obterCarrinhoId();

        $stmt = $pdo->prepare("
            SELECT ci.*, p.nome, p.slug, p.preco,
                   pv.tamanho, pv.cor, pv.preco_adicional,
                   (SELECT url FROM produto_imagens WHERE produto_id = p.id AND principal = TRUE LIMIT 1) AS imagem
            FROM carrinho_itens ci
            JOIN produtos p ON p.id = ci.produto_id
            LEFT JOIN produto_variacoes pv ON pv.id = ci.variacao_id
            WHERE ci.carrinho_id = ?
        ");
        $stmt->execute([$carrinhoId]);

        $itens = $stmt->fetchAll();

        foreach ($itens as &$item) {
            $item["preco_final"] = (float) $item["preco"] + (float) ($item["preco_adicional"] ?? 0);
            $item["subtotal"] = $item["preco_final"] * (int) $item["quantidade"];
        }

        return $itens;
    }

    public static function subtotal(): float
    {
        return array_sum(array_column(self::itens(), "subtotal"));
    }

    public static function quantidadeItens(): int
    {
        return array_sum(array_column(self::itens(), "quantidade"));
    }

    public static function limpar(): void
    {
        global $pdo;

        $carrinhoId = self::obterCarrinhoId();
        $pdo->prepare("DELETE FROM carrinho_itens WHERE carrinho_id = ?")->execute([$carrinhoId]);
    }
}
