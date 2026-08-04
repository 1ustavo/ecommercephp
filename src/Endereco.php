<?php

class Endereco
{
    public static function porUsuario(int $usuarioId): array
    {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM enderecos WHERE usuario_id = ? ORDER BY principal DESC, id DESC");
        $stmt->execute([$usuarioId]);

        return $stmt->fetchAll();
    }

    public static function buscar(int $id, int $usuarioId): ?array
    {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM enderecos WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$id, $usuarioId]);

        return $stmt->fetch() ?: null;
    }

    public static function salvar(int $usuarioId, array $dados, ?int $id = null): int
    {
        global $pdo;

        if (!empty($dados["principal"])) {
            $pdo->prepare("UPDATE enderecos SET principal = FALSE WHERE usuario_id = ?")->execute([$usuarioId]);
        }

        if ($id) {
            $stmt = $pdo->prepare("
                UPDATE enderecos
                SET titulo = ?, cep = ?, rua = ?, numero = ?, complemento = ?, bairro = ?, cidade = ?, estado = ?, principal = ?
                WHERE id = ? AND usuario_id = ?
            ");
            $stmt->execute([
                $dados["titulo"], $dados["cep"], $dados["rua"], $dados["numero"],
                $dados["complemento"], $dados["bairro"], $dados["cidade"], $dados["estado"],
                !empty($dados["principal"]) ? 1 : 0, $id, $usuarioId,
            ]);

            return $id;
        }

        $stmt = $pdo->prepare("
            INSERT INTO enderecos (usuario_id, titulo, cep, rua, numero, complemento, bairro, cidade, estado, principal)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            RETURNING id
        ");
        $stmt->execute([
            $usuarioId, $dados["titulo"], $dados["cep"], $dados["rua"], $dados["numero"],
            $dados["complemento"], $dados["bairro"], $dados["cidade"], $dados["estado"],
            !empty($dados["principal"]) ? 1 : 0,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public static function excluir(int $id, int $usuarioId): void
    {
        global $pdo;

        $pdo->prepare("DELETE FROM enderecos WHERE id = ? AND usuario_id = ?")->execute([$id, $usuarioId]);
    }
}
