<?php

class Categoria
{
    public static function todas(): array
    {
        global $pdo;

        $stmt = $pdo->prepare("
            SELECT * FROM categorias
            WHERE ativo = TRUE
            ORDER BY nome ASC
        ");
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function todasAdmin(): array
    {
        global $pdo;

        $stmt = $pdo->prepare("
            SELECT * FROM categorias
            ORDER BY nome ASC
        ");
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function buscarPorId(int $id): ?array
    {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM categorias WHERE id = ?");
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public static function buscarPorSlug(string $slug): ?array
    {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM categorias WHERE slug = ?");
        $stmt->execute([$slug]);

        return $stmt->fetch() ?: null;
    }

    public static function criar(string $nome, string $slug, ?string $descricao = null, ?int $paiId = null): bool
    {
        global $pdo;

        $stmt = $pdo->prepare("
            INSERT INTO categorias (nome, slug, descricao, pai_id, ativo)
            VALUES (?, ?, ?, ?, TRUE)
        ");
        return $stmt->execute([$nome, $slug, $descricao, $paiId]);
    }

    public static function atualizar(int $id, string $nome, string $slug, ?string $descricao = null, ?int $paiId = null, bool $ativo = true): bool
    {
        global $pdo;

        $stmt = $pdo->prepare("
            UPDATE categorias
            SET nome = ?, slug = ?, descricao = ?, pai_id = ?, ativo = ?, updated_at = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([$nome, $slug, $descricao, $paiId, $ativo, $id]);
    }

    public static function excluir(int $id): bool
    {
        global $pdo;

        $stmt = $pdo->prepare("DELETE FROM categorias WHERE id = ?");
        return $stmt->execute([$id]);
    }
}