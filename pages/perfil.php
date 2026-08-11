<?php

require_once __DIR__ . "/../config/bootstrap.php";

$auth = new Auth();
$auth->verificarLogin();
$usuarioId = (int) $_SESSION["usuario_id"];
$enderecos = Endereco::porUsuario($usuarioId);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // CSRF protection
    verify_csrf_from_request(true);

    $dados = [
        "titulo" => trim($_POST["titulo"] ?? "Casa"),
        "cep" => trim($_POST["cep"] ?? ""),
        "rua" => trim($_POST["rua"] ?? ""),
        "numero" => trim($_POST["numero"] ?? ""),
        "complemento" => trim($_POST["complemento"] ?? ""),
        "bairro" => trim($_POST["bairro"] ?? ""),
        "cidade" => trim($_POST["cidade"] ?? ""),
        "estado" => trim($_POST["estado"] ?? ""),
        "principal" => isset($_POST["principal"]),
    ];

    if ($dados["cep"] === "" || $dados["rua"] === "" || $dados["numero"] === "" || $dados["bairro"] === "" || $dados["cidade"] === "" || $dados["estado"] === "") {
        flash("perfil_error", "Preencha todos os campos obrigatórios do endereço.");
        header("Location: " . pageUrl("perfil.php"));
        exit;
    }

    Endereco::salvar($usuarioId, $dados);
    flash("perfil_success", "Endereço salvo com sucesso.");
    header("Location: " . pageUrl("perfil.php"));
    exit;
}

$pageTitle = "Perfil";
require __DIR__ . "/../includes/header.php";

$erro = flash("perfil_error");
$sucesso = flash("perfil_success");
?>

<h1 class="page-title">Perfil</h1>

<?php if ($sucesso): ?>
    <div class="alert alert-success"><?= e($sucesso) ?></div>
<?php elseif ($erro): ?>
    <div class="alert alert-error"><?= e($erro) ?></div>
<?php endif; ?>

<div class="card-grid" style="grid-template-columns: 1fr; gap:24px;">
    <div class="card">
        <h2>Dados do usuário</h2>
        <p><strong>Nome:</strong> <?= e($_SESSION["usuario_nome"] ?? "") ?></p>
        <p><strong>E-mail:</strong> <?= e($_SESSION["email"] ?? "") ?></p>
    </div>

    <div class="card">
        <h2>Endereços de entrega</h2>
        <?php if (empty($enderecos)): ?>
            <p>Nenhum endereço cadastrado ainda.</p>
        <?php else: ?>
            <ul style="list-style:none;padding:0;margin:0;">
                <?php foreach ($enderecos as $end): ?>
                    <li style="margin-bottom:16px;border-bottom:1px solid #e5e7eb;padding-bottom:12px;">
                        <strong><?= e($end["titulo"]) ?></strong> <?= $end["principal"] ? "<span style=\"color:#16a34a;\">(Principal)</span>" : "" ?>
                        <p style="margin:4px 0 0;color:#4b5563;">
                            <?= e($end["rua"]) ?>, <?= e($end["numero"]) ?> <?= e($end["complemento"]) ?><br>
                            <?= e($end["bairro"]) ?> - <?= e($end["cidade"]) ?>/<?= e($end["estado"]) ?> - CEP <?= e($end["cep"]) ?>
                        </p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Adicionar novo endereço</h2>
        <form method="post">
            <?= csrf_input() ?>
            <div class="form-group">
                <label class="form-label">Título</label>
                <input type="text" name="titulo" class="form-control" value="Casa" required>
            </div>
            <div class="form-group">
                <label class="form-label">CEP</label>
                <input type="text" name="cep" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Rua</label>
                <input type="text" name="rua" class="form-control" required>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Número</label><input type="text" name="numero" class="form-control" required></div>
                <div class="form-group"><label class="form-label">Complemento</label><input type="text" name="complemento" class="form-control"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Bairro</label><input type="text" name="bairro" class="form-control" required></div>
                <div class="form-group"><label class="form-label">Cidade</label><input type="text" name="cidade" class="form-control" required></div>
            </div>
            <div class="form-group" style="max-width:100px">
                <label class="form-label">UF</label>
                <input type="text" name="estado" class="form-control" maxlength="2" required>
            </div>
            <label class="payment-option" style="margin-top:12px;">
                <input type="checkbox" name="principal" value="1">
                <div>Definir como endereço principal</div>
            </label>
            <button type="submit" class="btn btn-primary" style="margin-top:16px">Salvar endereço</button>
        </form>
    </div>
</div>

<?php require __DIR__ . "/../includes/footer.php"; ?>
