/* ============================================================
   ADMIN-CUPONS.JS
   CRUD de cupons via modal + API.
   ============================================================ */

document.addEventListener("DOMContentLoaded", function () {
    const modal = document.getElementById("modal-cupom");
    const form = document.getElementById("form-cupom");
    const modalTitulo = document.getElementById("modal-titulo");
    const btnNovo = document.getElementById("btn-novo-cupom");
    const btnCancelar = document.getElementById("btn-cancelar");

    function caminhoApi(arquivo) {
        return "../../api/admin/" + arquivo;
    }

    function abrirModal() {
        modal.style.display = "flex";
    }

    function fecharModal() {
        modal.style.display = "none";
        form.reset();
        document.getElementById("cupom-id").value = "";
    }

    btnNovo.addEventListener("click", function () {
        modalTitulo.textContent = "Novo Cupom";
        abrirModal();
    });

    btnCancelar.addEventListener("click", fecharModal);

    modal.addEventListener("click", function (e) {
        if (e.target === modal) fecharModal();
    });

    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape" && modal.style.display !== "none") {
            fecharModal();
        }
    });

    form.addEventListener("submit", function (e) {
        e.preventDefault();

        const id = document.getElementById("cupom-id").value;
        const codigo = document.getElementById("cupom-codigo").value.trim().toUpperCase();
        const tipo = document.getElementById("cupom-tipo").value;
        const valor = document.getElementById("cupom-valor").value.trim();
        const valorMinimo = document.getElementById("cupom-valor-minimo").value.trim();
        const quantidade = document.getElementById("cupom-quantidade").value.trim();
        const inicio = document.getElementById("cupom-inicio").value;
        const fim = document.getElementById("cupom-fim").value;
        const ativo = document.getElementById("cupom-ativo").value === "1";

        if (!codigo || !tipo || !valor) {
            alert("Código, tipo e valor são obrigatórios.");
            return;
        }

        const formData = new FormData();
        formData.append("codigo", codigo);
        formData.append("tipo", tipo);
        formData.append("valor", valor);
        formData.append("valor_minimo_pedido", valorMinimo || "0");
        formData.append("quantidade_maxima", quantidade || "1");
        formData.append("data_inicio", inicio);
        formData.append("data_fim", fim);
        formData.append("ativo", ativo ? "1" : "0");
        if (id) formData.append("id", id);

        fetch(caminhoApi("salvar_cupom.php"), {
            method: "POST",
            body: formData,
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.sucesso) {
                    alert("Cupom salvo com sucesso!");
                    fecharModal();
                    location.reload();
                } else {
                    alert(data.erro || "Erro ao salvar cupom.");
                }
            })
            .catch(function () {
                alert("Erro de conexão.");
            });
    });

    document.querySelectorAll(".btn-editar").forEach(function (btn) {
        btn.addEventListener("click", function () {
            const id = this.getAttribute("data-id");
            fetch(caminhoApi("salvar_cupom.php"), {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "id=" + encodeURIComponent(id),
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.sucesso || !data.cupom) {
                        alert(data.erro || "Cupom não encontrado.");
                        return;
                    }
                    const c = data.cupom;
                    modalTitulo.textContent = "Editar Cupom";
                    document.getElementById("cupom-id").value = c.id;
                    document.getElementById("cupom-codigo").value = c.codigo;
                    document.getElementById("cupom-tipo").value = c.tipo;
                    document.getElementById("cupom-valor").value = c.valor;
                    document.getElementById("cupom-valor-minimo").value = c.valor_minimo_pedido || "0";
                    document.getElementById("cupom-quantidade").value = c.quantidade_maxima || "1";
                    document.getElementById("cupom-inicio").value = c.data_inicio ? c.data_inicio.slice(0, 16) : "";
                    document.getElementById("cupom-fim").value = c.data_fim ? c.data_fim.slice(0, 16) : "";
                    document.getElementById("cupom-ativo").value = c.ativo ? "1" : "0";
                    abrirModal();
                })
                .catch(function () {
                    alert("Erro de conexão.");
                });
        });
    });

    document.querySelectorAll(".btn-excluir").forEach(function (btn) {
        btn.addEventListener("click", function () {
            const id = this.getAttribute("data-id");
            if (!confirm("Excluir cupom #" + id + "?")) return;

            const formData = new FormData();
            formData.append("id", id);

            fetch(caminhoApi("excluir_cupom.php"), {
                method: "POST",
                body: formData,
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.sucesso) {
                        alert("Cupom excluído.");
                        location.reload();
                    } else {
                        alert(data.erro || "Erro ao excluir cupom.");
                    }
                })
                .catch(function () {
                    alert("Erro de conexão.");
                });
        });
    });
});