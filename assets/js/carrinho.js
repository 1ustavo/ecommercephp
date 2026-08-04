document.addEventListener("DOMContentLoaded", () => {
    const apiEndpoint = "../api/carrinho.php";

    // Feedback visual de erro — toast em vez de alert nativo
    function showToast(message, type = "error") {
        const existing = document.querySelector(".toast");
        if (existing) existing.remove();

        const toast = document.createElement("div");
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        document.body.appendChild(toast);

        // Anima entrada
        requestAnimationFrame(() => toast.classList.add("show"));

        // Auto-dismiss após 3s
        setTimeout(() => {
            toast.classList.remove("show");
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // Adiciona loading state ao botão
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

    // Adiciona classe de "removendo" ao item do carrinho
    function setItemRemoving(item, removing) {
        if (!item) return;
        if (removing) {
            item.classList.add("item-removing");
        } else {
            item.classList.remove("item-removing");
        }
    }

    async function request(action, body) {
        const response = await fetch(`${apiEndpoint}?action=${encodeURIComponent(action)}`, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json",
            },
            body: JSON.stringify(body),
        });

        if (!response.ok) {
            throw new Error("Erro na comunicação com o servidor.");
        }

        return response.json();
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
                updateCartItem(itemId, currentQty + 1, plus);
            });
        }

        if (removeButton) {
            removeButton.addEventListener("click", () => removeCartItem(itemId, item, removeButton));
        }
    });
});