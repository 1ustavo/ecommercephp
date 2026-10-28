document.addEventListener("DOMContentLoaded", function () {
    const modal = document.getElementById("modal-recusar-reembolso");
    const csrfToken = document.getElementById("csrf-token")?.value || "";
    const modalReembolsoId = document.getElementById("modal-reembolso-id");
    const motivoTextarea = document.getElementById("motivo-recusa-reembolso");
    const btnConfirmarRecusa = document.getElementById("btn-confirmar-recusa-reembolso");
    const btnCancelarRecusa = document.getElementById("btn-cancelar-recusa-reembolso");
    let reembolsoAlvo = null;

    function abrirModalRecusa(id) {
        reembolsoAlvo = id;
        if (modalReembolsoId) modalReembolsoId.textContent = String(id);
        if (motivoTextarea) motivoTextarea.value = "";
        if (modal) modal.style.display = "flex";
    }

    function fecharModal() { if (modal) modal.style.display = "none"; }

    async function postAction(action, body = {}) {
        const res = await fetch("../../api/admin/reembolso_acao.php", {
            method: "POST",
            headers: { "Content-Type": "application/json", "X-CSRF-Token": csrfToken },
            body: JSON.stringify(Object.assign({ action, csrf_token: csrfToken }, body)),
        });
        return res.json();
    }

    document.querySelectorAll(".btn-aprovar-reembolso").forEach(function (btn) {
        btn.addEventListener("click", async function () {
            if (!confirm("Confirma aprovar este reembolso?")) return;
            btn.disabled = true;
            const r = await postAction("aprovar", { reembolso_id: btn.dataset.reembolsoId });
            if (r.sucesso) { location.reload(); }
            else { alert(r.erro || "Erro ao aprovar reembolso."); btn.disabled = false; }
        });
    });

    document.querySelectorAll(".btn-recusar-reembolso").forEach(function (btn) {
        btn.addEventListener("click", function () { abrirModalRecusa(btn.dataset.reembolsoId); });
    });

    btnCancelarRecusa?.addEventListener("click", fecharModal);
    modal?.addEventListener("click", function (e) { if (e.target === modal) fecharModal(); });
    document.addEventListener("keydown", function (e) { if (e.key === "Escape") fecharModal(); });

    btnConfirmarRecusa?.addEventListener("click", async function () {
        const motivo = motivoTextarea?.value.trim() || "";
        if (!motivo) { alert("Informe o motivo da recusa."); return; }
        btnConfirmarRecusa.disabled = true;
        const r = await postAction("recusar", { reembolso_id: reembolsoAlvo, motivo: motivo });
        if (r.sucesso) { location.reload(); }
        else { alert(r.erro || "Erro ao recusar reembolso."); btnConfirmarRecusa.disabled = false; }
    });
});
