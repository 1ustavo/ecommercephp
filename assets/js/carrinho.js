document.addEventListener("DOMContentLoaded", () => {
    const apiEndpoint = "../api/carrinho.php";

    function showToast(message, type = "error") {
        const existing = document.querySelector(".toast");
        if (existing) existing.remove();

        const toast = document.createElement("div");
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        document.body.appendChild(toast);

        requestAnimationFrame(() => toast.classList.add("show"));

        setTimeout(() => {
            toast.classList.remove("show");
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    function setButtonLoading(button, loading) {
        if (!button) return;
        if (loading) {
            button.classList.add("loading");
            button.disabled = true;
        } else {
            button.classList.remove("loading");
            button.disabled = false;
        }
    }

    function setItemRemoving(item, removing) {
        if (!item) return;
        if (removing) {
            item.classList.add("item-removing");
        } else {
            item.classList.remove("item-removing");
        }
    }

    // API helpers
    async function request(action, body) {
        const response = await fetch(`${apiEndpoint}?action=${encodeURIComponent(action)}`, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json",
            },
            body: JSON.stringify(body),
        });

        // Retorna o JSON mesmo em respostas de erro (ex: 400) para que
        // o chamador possa processar a mensagem de erro retornada pela API.
        const data = await response.json().catch(() => null);

        if (!response.ok && !data) {
            throw new Error("Erro na comunicação com o servidor.");
        }

        return data;
    }

    async function updateCartItem(itemId, quantity, button) {
        setButtonLoading(button, true);
        try {
            const data = await request("atualizar", { item_id: itemId, quantidade: quantity });
            if (!data.success) {
                showToast(data.message || "Não foi possível atualizar o item.");
                setButtonLoading(button, false);
                return;
            }
            window.location.reload();
        } catch (error) {
            showToast(error.message);
            setButtonLoading(button, false);
        }
    }

    async function removeCartItem(itemId, item, button) {
        setItemRemoving(item, true);
        setButtonLoading(button, true);
        try {
            const data = await request("remover", { item_id: itemId });
            if (!data.success) {
                showToast(data.message || "Não foi possível remover o item.");
                setItemRemoving(item, false);
                setButtonLoading(button, false);
                return;
            }
            window.location.reload();
        } catch (error) {
            showToast(error.message);
            setItemRemoving(item, false);
            setButtonLoading(button, false);
        }
    }

    document.querySelectorAll(".cart-item").forEach((item) => {
        const itemId = item.dataset.itemId;
        const minus = item.querySelector(".qty-minus");
        const plus = item.querySelector(".qty-plus");
        const removeButton = item.querySelector(".btn-remove");

        if (minus) {
            minus.addEventListener("click", () => {
                const currentQty = Number(item.querySelector(".qty-value")?.textContent || 0);
                const nextQty = Math.max(0, currentQty - 1);
                if (nextQty === 0) {
                    removeCartItem(itemId, item, removeButton);
                } else {
                    updateCartItem(itemId, nextQty, minus);
                }
            });
        }

        if (plus) {
            plus.addEventListener("click", () => {
                const currentQty = Number(item.querySelector(".qty-value")?.textContent || 0);
                const estoqueDisponivel = Number(item.dataset.estoque || 0);

                if (currentQty >= estoqueDisponivel) {
                    showToast(`Estoque máximo disponível: ${estoqueDisponivel} un.`);
                    return;
                }

                updateCartItem(itemId, currentQty + 1, plus);
            });
        }

        if (removeButton) {
            removeButton.addEventListener("click", () => removeCartItem(itemId, item, removeButton));
        }
    });

    const cupomInput = document.getElementById("cupom-input-cart");
    const btnAplicar = document.getElementById("btn-aplicar-cupom-cart");
    const cupomFeedback = document.getElementById("cupom-feedback-cart");
    const descontoRow = document.getElementById("desconto-row-cart");
    const descontoValue = document.getElementById("desconto-value-cart");
    const totalValue = document.getElementById("total-value");
    const subtotalHidden = document.getElementById("subtotal-hidden-cart");
    const descontoHidden = document.getElementById("desconto-hidden-cart");

    // Estado inicial vindo do servidor (sessão)
    let descontoAplicado = Number(descontoHidden?.value || 0);

    function formatarMoeda(valor) {
        return new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" }).format(valor);
    }

    function atualizarTotalComDesconto() {
        const subtotal = Number(subtotalHidden?.value || 0);
        const total = Math.max(0, subtotal - descontoAplicado);

        if (totalValue) {
            totalValue.textContent = formatarMoeda(total);
        }

        if (descontoRow) {
            descontoRow.style.display = descontoAplicado > 0 ? "flex" : "none";
        }

        if (descontoValue) {
            descontoValue.textContent = "- " + formatarMoeda(descontoAplicado);
        }

        if (descontoHidden) {
            descontoHidden.value = descontoAplicado.toFixed(2);
        }
    }

    function aplicarCupom() {
        if (!cupomInput || !cupomFeedback) return;

        const codigo = cupomInput.value.trim().toUpperCase();
        if (!codigo) {
            cupomFeedback.innerHTML = '<span style="color:var(--erro-text)">Informe um código de cupom.</span>';
            return;
        }

        btnAplicar.disabled = true;
        btnAplicar.textContent = "Validando...";

        // Usa a API do carrinho para persistir o cupom na sessão
        request("aplicar_cupom", { cupom: codigo })
            .then((data) => {
                if (data.success) {
                    descontoAplicado = Number(data.desconto);
                    cupomFeedback.innerHTML = '<span style="color:var(--sucesso-text);font-weight:600">' +
                        data.mensagem + " Desconto: " + data.desconto_formatado + "</span>";
                    cupomInput.disabled = true;
                    btnAplicar.textContent = "Aplicado ✓";
                    btnAplicar.classList.add("btn-gray");
                    btnAplicar.classList.remove("btn-outline");
                    atualizarTotalComDesconto();
                    showToast("Cupom aplicado! Desconto: " + data.desconto_formatado, "success");
                } else {
                    descontoAplicado = 0;
                    cupomFeedback.innerHTML = '<span style="color:var(--erro-text)">' + data.message + "</span>";
                    btnAplicar.textContent = "Aplicar";
                    atualizarTotalComDesconto();
                    showToast(data.message, "error");
                }
            })
            .catch((error) => {
                descontoAplicado = 0;
                cupomFeedback.innerHTML = '<span style="color:var(--erro-text)">' + error.message + "</span>";
                btnAplicar.textContent = "Aplicar";
                atualizarTotalComDesconto();
                showToast(error.message, "error");
            })
            .finally(() => {
                btnAplicar.disabled = false;
            });
    }

    if (btnAplicar) {
        btnAplicar.addEventListener("click", aplicarCupom);
    }

    if (cupomInput) {
        cupomInput.addEventListener("keydown", (e) => {
            if (e.key === "Enter") {
                e.preventDefault();
                aplicarCupom();
            }
        });

        // Validação automática com debounce (600ms após parar de digitar)
        let debounceTimer = null;
        cupomInput.addEventListener("input", () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                const valor = cupomInput.value.trim();
                if (valor.length >= 3) {
                    aplicarCupom();
                }
            }, 600);
        });
    }
});
