document.addEventListener("DOMContentLoaded", () => {
    const panels = Array.from(document.querySelectorAll(".checkout-panel"));
    const steps = Array.from(document.querySelectorAll(".step"));
    const nextButtons = Array.from(document.querySelectorAll(".btn-next"));
    const prevButtons = Array.from(document.querySelectorAll(".btn-prev"));
    const paymentRadios = Array.from(document.querySelectorAll("input[name='metodo_pagamento']"));
    const cardFields = document.getElementById("cartao-fields");
    const freteLabels = {
        pac: 22.5,
        sedex: 35.9,
        retirada: 0,
    };

    function getCurrentStep() {
        return panels.findIndex((panel) => panel.classList.contains("active"));
    }

    function setStep(index) {
        panels.forEach((panel, idx) => {
            panel.classList.toggle("active", idx === index);
        });
        steps.forEach((step, idx) => {
            step.classList.toggle("active", idx === index);
        });
        updateSummary();
    }

    function getSelectedFrete() {
        const radio = document.querySelector("input[name='metodo_frete']:checked");
        return radio ? radio.value : "pac";
    }

    function getSelectedPagamento() {
        const radio = document.querySelector("input[name='metodo_pagamento']:checked");
        return radio ? radio.value : "pix";
    }

    function getSubtotal() {
        const subtotalField = document.querySelector("input[name='subtotal']");
        return Number(subtotalField?.value || 0);
    }

    function getFrete() {
        const metodo = getSelectedFrete();
        return freteLabels[metodo] ?? 15.0;
    }

    function updateSummary() {
        const subtotal = getSubtotal();
        const frete = getFrete();
        const total = subtotal + frete;

        const resumoFrete = document.getElementById("resumo-frete");
        const resumoTotal = document.getElementById("resumo-total");

        if (resumoFrete) {
            resumoFrete.textContent = new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" }).format(frete);
        }

        if (resumoTotal) {
            resumoTotal.textContent = new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" }).format(total);
        }
    }

    function toggleCardFields() {
        if (!cardFields) {
            return;
        }
        const metodo = getSelectedPagamento();
        cardFields.style.display = metodo === "cartao" ? "block" : "none";
        const input = cardFields.querySelector("input");
        if (input) {
            input.disabled = metodo !== "cartao";
        }
    }

    nextButtons.forEach((button) => {
        button.addEventListener("click", () => {
            const currentIndex = getCurrentStep();
            setStep(Math.min(panels.length - 1, currentIndex + 1));
        });
    });

    prevButtons.forEach((button) => {
        button.addEventListener("click", () => {
            const currentIndex = getCurrentStep();
            setStep(Math.max(0, currentIndex - 1));
        });
    });

    paymentRadios.forEach((radio) => {
        radio.addEventListener("change", toggleCardFields);
    });

    document.querySelectorAll("input[name='metodo_frete']").forEach((radio) => {
        radio.addEventListener("change", updateSummary);
    });

    const novoEnderecoFields = document.querySelectorAll("#novo_cep, #novo_titulo, #novo_rua, #novo_numero, #novo_complemento, #novo_bairro, #novo_cidade, #novo_estado");
    novoEnderecoFields.forEach((input) => {
        input.addEventListener("input", () => {
            document.querySelectorAll("input[name='endereco_id']").forEach((radio) => {
                radio.checked = false;
            });
        });
    });

    // Loading state no botão de confirmação
    const confirmButton = document.getElementById("btn-confirmar");
    const checkoutForm = document.getElementById("checkout-form");

    if (confirmButton && checkoutForm) {
        checkoutForm.addEventListener("submit", () => {
            confirmButton.classList.add("loading");
            confirmButton.disabled = true;
        });
    }

    updateSummary();
    toggleCardFields();
});
