document.addEventListener("DOMContentLoaded", function () {
    const modal = document.getElementById("modal-recusar");
    const csrfToken = document.getElementById("csrf-token")?.value || "";
    const modalPedidoId = document.getElementById("modal-pedido-id");
    const motivoTextarea = document.getElementById("motivo-recusa");
    const motivoErro = document.getElementById("motivo-erro");
    const btnConfirmarRecusa = document.getElementById("btn-confirmar-recusa");
    const btnCancelarRecusa = document.getElementById("btn-cancelar-recusa");

    let pedidoRecusaAtual = null;

    /* ---------- Toast simples (reaproveita estilo do admin-produtos) ---------- */
    function mostrarToast(mensagem, tipo) {
        tipo = tipo || "success";
        document.querySelectorAll(".toast").forEach(function (t) { t.remove(); });
        const toast = document.createElement("div");
        toast.className = "toast toast-" + tipo;
        toast.textContent = mensagem;
        document.body.appendChild(toast);
        requestAnimationFrame(function () { toast.classList.add("show"); });
        setTimeout(function () {
            toast.classList.remove("show");
            setTimeout(function () { toast.remove(); }, 300);
        }, 3500);
    }

    /* ---------- Helpers ---------- */
    function caminhoApi(arquivo) {
        // A pagina esta em /pages/admin/, entao sobe 2 niveis para /api/admin/
        return "../../api/admin/" + arquivo;
    }

    function desabilitarBotoesDoCard(card, desabilitar) {
        card.querySelectorAll(".btn-aprovar, .btn-recusar").forEach(function (b) {
            b.disabled = desabilitar;
        });
    }

    function removerCardComAnimacao(card) {
        card.style.transition = "opacity 0.3s, transform 0.3s";
        card.style.opacity = "0";
        card.style.transform = "translateY(-10px)";
        setTimeout(function () {
            card.remove();
            // Se nao sobrou nenhum pedido, mostra empty-state
            const lista = document.getElementById("lista-pendentes");
            if (lista && !lista.querySelector(".pedido-card")) {
                lista.innerHTML =
                    '<div class="empty-state">' +
                    "<h3>Nenhum pedido aguardando aprovação</h3>" +
                    "<p>Quando clientes finalizarem compras, os pedidos aparecerão aqui para revisão.</p>" +
                    "</div>";
                // Zera o contador de exibidos
                const statCards = document.querySelectorAll(".stats-grid .stat-value");
                if (statCards.length >= 2) {
                    statCards[0].textContent = "0";
                    statCards[1].textContent = "0";
                }
            } else {
                // Atualiza contador de exibidos
                const restantes = document.querySelectorAll(".pedido-card").length;
                const statCards = document.querySelectorAll(".stats-grid .stat-value");
                if (statCards.length >= 2) {
                    statCards[1].textContent = String(restantes);
                    // contador "aguardando aprovacao" tambem diminui
                    const atual = parseInt(statCards[0].textContent, 10) || 0;
                    if (atual > 0) statCards[0].textContent = String(atual - 1);
                }
            }
        }, 300);
    }

    /* ---------- Aprovar ---------- */
    document.querySelectorAll(".btn-aprovar").forEach(function (btn) {
        btn.addEventListener("click", function () {
            const pedidoId = this.getAttribute("data-pedido-id");
            const card = this.closest(".pedido-card");
            if (!pedidoId || !card) return;

            if (!confirm("Confirmar aprovação do pedido #" + pedidoId + "?")) return;

            desabilitarBotoesDoCard(card, true);
            const textoOriginal = this.textContent;
            this.textContent = "Aprovando...";

            fetch(caminhoApi("aprovar_pedido.php"), {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-Token": csrfToken,
                },
                body: JSON.stringify({ pedido_id: parseInt(pedidoId, 10), csrf_token: csrfToken }),
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.sucesso) {
                        mostrarToast(data.mensagem || "Pedido aprovado.", "success");
                        removerCardComAnimacao(card);
                    } else {
                        mostrarToast(data.erro || "Erro ao aprovar pedido.", "error");
                        desabilitarBotoesDoCard(card, false);
                        btn.textContent = textoOriginal;
                    }
                })
                .catch(function () {
                    mostrarToast("Erro de conexão ao aprovar pedido.", "error");
                    desabilitarBotoesDoCard(card, false);
                    btn.textContent = textoOriginal;
                });
        });
    });

    /* ---------- Recusar (abre modal) ---------- */
    document.querySelectorAll(".btn-recusar").forEach(function (btn) {
        btn.addEventListener("click", function () {
            const pedidoId = this.getAttribute("data-pedido-id");
            if (!pedidoId) return;
            pedidoRecusaAtual = {
                id: pedidoId,
                card: this.closest(".pedido-card"),
                botao: this,
                textoOriginal: this.textContent,
            };
            modalPedidoId.textContent = pedidoId;
            motivoTextarea.value = "";
            motivoErro.style.display = "none";
            modal.style.display = "flex";
            motivoTextarea.focus();
        });
    });

    /* ---------- Modal: cancelar ---------- */
    btnCancelarRecusa.addEventListener("click", fecharModal);

    function fecharModal() {
        modal.style.display = "none";
        pedidoRecusaAtual = null;
    }

    /* ---------- Modal: confirmar recusa ---------- */
    btnConfirmarRecusa.addEventListener("click", function () {
        if (!pedidoRecusaAtual) return;

        const motivo = motivoTextarea.value.trim();
        if (!motivo) {
            motivoErro.style.display = "block";
            motivoTextarea.focus();
            return;
        }
        motivoErro.style.display = "none";

        const ctx = pedidoRecusaAtual;
        desabilitarBotoesDoCard(ctx.card, true);
        ctx.botao.textContent = "Recusando...";
        btnConfirmarRecusa.disabled = true;
        btnConfirmarRecusa.textContent = "Enviando...";

        fetch(caminhoApi("recusar_pedido.php"), {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-Token": csrfToken,
            },
            body: JSON.stringify({
                pedido_id: parseInt(ctx.id, 10),
                motivo: motivo,
                csrf_token: csrfToken,
            }),
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                btnConfirmarRecusa.disabled = false;
                btnConfirmarRecusa.textContent = "Confirmar recusa";
                if (data.sucesso) {
                    mostrarToast(data.mensagem || "Pedido recusado.", "success");
                    fecharModal();
                    removerCardComAnimacao(ctx.card);
                } else {
                    mostrarToast(data.erro || "Erro ao recusar pedido.", "error");
                    desabilitarBotoesDoCard(ctx.card, false);
                    ctx.botao.textContent = ctx.textoOriginal;
                }
            })
            .catch(function () {
                btnConfirmarRecusa.disabled = false;
                btnConfirmarRecusa.textContent = "Confirmar recusa";
                mostrarToast("Erro de conexão ao recusar pedido.", "error");
                desabilitarBotoesDoCard(ctx.card, false);
                ctx.botao.textContent = ctx.textoOriginal;
            });
    });

    /* ---------- Fechar modal ao clicar no overlay ---------- */
    modal.addEventListener("click", function (e) {
        if (e.target === modal) fecharModal();
    });

    /* ---------- ESC fecha modal ---------- */
    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape" && modal.style.display !== "none") {
            fecharModal();
        }
    });
});