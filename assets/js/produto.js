document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("form-add-cart");
    const variationButtons = document.querySelectorAll(".variation-btn");
    const variacaoInput = document.getElementById("variacao_id");
    const priceDisplay = document.getElementById("preco-display");
    const estoqueDisplay = document.getElementById("estoque-display");
    const qtyInput = form?.querySelector('input[name="quantidade"]');

    if (!form) {
        return;
    }

    const basePriceText = priceDisplay?.textContent ?? "0";
    const basePrice = parseFloat(basePriceText.replace(/[R$\s\.]/g, "").replace(",", ".")) || 0;

    // Estoque atual (produto base ou variação selecionada)
    let estoqueAtual = parseInt(form.dataset.estoque || "0", 10);

    // Atualiza o campo de quantidade com o max e valida o valor atual
    function atualizarLimiteEstoque(estoque) {
        estoqueAtual = Math.max(0, estoque);

        if (qtyInput) {
            qtyInput.max = String(estoqueAtual);
            qtyInput.dataset.estoque = String(estoqueAtual);

            // Se o valor atual exceder o novo limite, ajusta para o máximo
            const valorAtual = parseInt(qtyInput.value || "1", 10);
            if (valorAtual > estoqueAtual) {
                qtyInput.value = String(Math.max(1, estoqueAtual));
            }
        }

        if (estoqueDisplay) {
            estoqueDisplay.textContent = String(estoqueAtual);
        }
    }

    // Impede digitar valores acima do estoque
    if (qtyInput) {
        qtyInput.addEventListener("input", () => {
            let valor = parseInt(qtyInput.value || "0", 10);
            if (isNaN(valor) || valor < 1) {
                qtyInput.value = "1";
                return;
            }
            if (valor > estoqueAtual) {
                qtyInput.value = String(estoqueAtual);
                showMessage(`Estoque máximo disponível: ${estoqueAtual} un.`, "error");
            }
        });

        // Impede incrementar acima do estoque via setas do navegador
        qtyInput.addEventListener("change", () => {
            let valor = parseInt(qtyInput.value || "1", 10);
            if (isNaN(valor) || valor < 1) {
                qtyInput.value = "1";
            } else if (valor > estoqueAtual) {
                qtyInput.value = String(estoqueAtual);
                showMessage(`Estoque máximo disponível: ${estoqueAtual} un.`, "error");
            }
        });
    }

    // Valida no submit antes de enviar
    form.addEventListener("submit", (e) => {
        const valor = parseInt(qtyInput?.value || "1", 10);
        if (isNaN(valor) || valor < 1) {
            e.preventDefault();
            showMessage("Quantidade inválida.", "error");
            return;
        }
        if (valor > estoqueAtual) {
            e.preventDefault();
            showMessage(`Estoque máximo disponível: ${estoqueAtual} un.`, "error");
            return;
        }
    });

    // Feedback visual de erro
    function showMessage(message, type = "error") {
        const msgEl = document.getElementById("add-msg");
        if (!msgEl) return;
        msgEl.innerHTML = "";
        const div = document.createElement("div");
        div.className = `alert alert-${type === "error" ? "error" : "success"}`;
        div.textContent = message;
        msgEl.appendChild(div);
        setTimeout(() => div.remove(), 3000);
    }

    variationButtons.forEach((button) => {
        button.addEventListener("click", () => {
            variationButtons.forEach((item) => item.classList.remove("selected"));
            button.classList.add("selected");

            const variationId = button.dataset.id || "";
            const extra = parseFloat(button.dataset.precoAd || "0") || 0;
            const stock = parseInt(button.dataset.estoque || "0", 10);

            if (variacaoInput) {
                variacaoInput.value = variationId;
            }

            if (priceDisplay) {
                priceDisplay.textContent = new Intl.NumberFormat("pt-BR", {
                    style: "currency",
                    currency: "BRL",
                }).format(basePrice + extra);
            }

            // Atualiza o limite de estoque para a variação selecionada
            atualizarLimiteEstoque(stock);
        });
    });

    // Inicializa o limite com o estoque do produto base
    atualizarLimiteEstoque(parseInt(form.dataset.estoque || "0", 10));
});