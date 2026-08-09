<?php

class Cupom
{
    public static function buscarPorCodigo(string $codigo): ?array
    {
        global $pdo;

        $stmt = $pdo->prepare("
            SELECT * FROM cupons
            WHERE codigo = ? AND ativo = TRUE
              AND NOW() BETWEEN data_inicio AND data_fim
              AND quantidade_usada < quantidade_maxima
        ");
        $stmt->execute([$codigo]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Busca um cupom pelo código sem filtrar por validade/uso.
     * Usado para dar mensagens de erro mais precisas (ex: expirado).
     */
    public static function buscarPorCodigoBruto(string $codigo): ?array
    {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM cupons WHERE codigo = ?");
        $stmt->execute([$codigo]);

        return $stmt->fetch() ?: null;
    }

    public static function calcularDesconto(array $cupom, float $subtotal, float $frete): float
    {
        $total = $subtotal + $frete;

        if ($total < $cupom["valor_minimo_pedido"]) {
            return 0;
        }

        if ($cupom["tipo"] === "PERCENTUAL") {
            return round($total * ((float) $cupom["valor"] / 100), 2);
        }

        return min((float) $cupom["valor"], $total);
    }

    public static function registrarUso(int $cupomId): void
    {
        global $pdo;

        $pdo->prepare("UPDATE cupons SET quantidade_usada = quantidade_usada + 1 WHERE id = ?")
            ->execute([$cupomId]);
    }

    public static function todosAdmin(): array
    {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM cupons ORDER BY created_at DESC");
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function criar(array $dados): bool
    {
        global $pdo;

        $stmt = $pdo->prepare("
            INSERT INTO cupons (codigo, tipo, valor, valor_minimo_pedido, quantidade_maxima, data_inicio, data_fim, ativo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([
            $dados["codigo"],
            $dados["tipo"],
            $dados["valor"],
            $dados["valor_minimo_pedido"] ?? 0,
            $dados["quantidade_maxima"] ?? 1,
            $dados["data_inicio"],
            $dados["data_fim"],
            $dados["ativo"] ? 1 : 0,
        ]);
    }

    public static function atualizar(int $id, array $dados): bool
    {
        global $pdo;

        $stmt = $pdo->prepare("
            UPDATE cupons
            SET codigo = ?, tipo = ?, valor = ?, valor_minimo_pedido = ?, quantidade_maxima = ?, data_inicio = ?, data_fim = ?, ativo = ?, updated_at = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([
            $dados["codigo"],
            $dados["tipo"],
            $dados["valor"],
            $dados["valor_minimo_pedido"] ?? 0,
            $dados["quantidade_maxima"] ?? 1,
            $dados["data_inicio"],
            $dados["data_fim"],
            $dados["ativo"] ? 1 : 0,
            $id,
        ]);
    }

    public static function excluir(int $id): bool
    {
        global $pdo;

        $stmt = $pdo->prepare("DELETE FROM cupons WHERE id = ?");
        return $stmt->execute([$id]);
    }
}