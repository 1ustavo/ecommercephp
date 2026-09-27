document.addEventListener("DOMContentLoaded", function () {
    const modal = document.getElementById("modal-categoria");
    const form = document.getElementById("form-categoria");
    const modalTitulo = document.getElementById("modal-titulo");
    const btnNova = document.getElementById("btn-nova-categoria");
    const btnCancelar = document.getElementById("btn-cancelar");
    const csrfToken = document.getElementById("csrf-token")?.value || "";

    function caminhoApi(arquivo) {
        return "../../api/admin/" + arquivo;
    }

    function abrirModal() {
        modal.style.display = "flex";
    }

    function fecharModal() {
        modal.style.display = "none";
        form.reset();
        document.getElementById("cat-id").value = "";
    }

    btnNova?.addEventListener("click", function () {
        modalTitulo.textContent = "Nova Categoria";
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

        const id = document.getElementById("cat-id").value;
        const nome = document.getElementById("cat-nome").value.trim();
        const slug = document.getElementById("cat-slug").value.trim();
        const descricao = document.getElementById("cat-descricao").value.trim();
        const paiId = document.getElementById("cat-pai").value;
        const ativo = document.getElementById("cat-ativo").value === "1";

        if (!nome || !slug) {
            alert("Nome e slug são obrigatórios.");
            return;
        }

        const formData = new FormData();
        formData.append("nome", nome);
        formData.append("slug", slug);
        formData.append("descricao", descricao);
        formData.append("pai_id", paiId || "");
        formData.append("ativo", ativo ? "1" : "0");
        if (id) formData.append("id", id);
        if (csrfToken) formData.append("csrf_token", csrfToken);

        fetch(caminhoApi("salvar_categoria.php"), {
            method: "POST",
            body: formData,
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.sucesso) {
                    alert("Categoria salva com sucesso!");
                    fecharModal();
                    location.reload();
                } else {
                    alert(data.erro || "Erro ao salvar categoria.");
                }
            })
            .catch(function () {
                alert("Erro de conexão.");
            });
    });

    document.querySelectorAll(".btn-editar").forEach(function (btn) {
        btn.addEventListener("click", function () {
            const id = this.getAttribute("data-id");
            fetch(caminhoApi("salvar_categoria.php"), {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                    "X-CSRF-Token": csrfToken,
                },
                body: "id=" + encodeURIComponent(id) + "&csrf_token=" + encodeURIComponent(csrfToken),
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.sucesso || !data.categoria) {
                        alert(data.erro || "Categoria não encontrada.");
                        return;
                    }
                    const c = data.categoria;
                    modalTitulo.textContent = "Editar Categoria";
                    document.getElementById("cat-id").value = c.id;
                    document.getElementById("cat-nome").value = c.nome;
                    document.getElementById("cat-slug").value = c.slug;
                    document.getElementById("cat-descricao").value = c.descricao || "";
                    document.getElementById("cat-pai").value = c.pai_id || "";
                    document.getElementById("cat-ativo").value = c.ativo ? "1" : "0";
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
            if (!confirm("Excluir categoria #" + id + "?")) return;

            const formData = new FormData();
            formData.append("id", id);
            if (csrfToken) formData.append("csrf_token", csrfToken);

            fetch(caminhoApi("excluir_categoria.php"), {
                method: "POST",
                body: formData,
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.sucesso) {
                        alert("Categoria excluída.");
                        location.reload();
                    } else {
                        alert(data.erro || "Erro ao excluir categoria.");
                    }
                })
                .catch(function () {
                    alert("Erro de conexão.");
                });
        });
    });
});