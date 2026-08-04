document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("form-add-cart");
    const variationButtons = document.querySelectorAll(".variation-btn");
    const variacaoInput = document.getElementById("variacao_id");
    const priceDisplay = document.getElementById("preco-display");
    const estoqueDisplay = document.getElementById("estoque-display");

    if (!form) {
        return;
    }

    const basePriceText = priceDisplay?.textContent ?? "0";
    const basePrice = parseFloat(basePriceText.replace(/[R$\s\.]/g, "").replace(",", ".")) || 0;

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

            if (estoqueDisplay) {
                estoqueDisplay.textContent = String(stock);
            }
        });
    });
});
