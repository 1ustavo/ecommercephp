document.addEventListener("DOMContentLoaded", () => {
    const csrfToken = document.getElementById("csrf-token-checkout")?.value || "";
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

    const cupomInput = document.getElementById("cupom-input");
    const btnAplicarCupom = document.getElementById("btn-aplicar-cupom");
    const cupomFeedback = document.getElementById("cupom-feedback");
    const resumoDescontoRow = document.getElementById("resumo-desconto-row");
    const resumoDesconto = document.getElementById("resumo-desconto");
    const descontoHidden = document.getElementById("desconto-hidden");
    // Estado inicial vindo do servidor (sessão)
    let descontoAplicado = Number(descontoHidden?.value || 0);

    function formatarMoeda(valor) {
        return new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" }).format(valor);
    }

    function atualizarTotalComDesconto() {
        const subtotal = getSubtotal();
        const frete = getFrete();
        const total = Math.max(0, subtotal + frete - descontoAplicado);

        const resumoTotal = document.getElementById("resumo-total");
        if (resumoTotal) {
            resumoTotal.textContent = formatarMoeda(total);
        }

        if (resumoDescontoRow) {
            resumoDescontoRow.style.display = descontoAplicado > 0 ? "flex" : "none";
        }
        if (resumoDesconto) {
            resumoDesconto.textContent = "- " + formatarMoeda(descontoAplicado);
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

        const subtotal = getSubtotal();
        const frete = getFrete();

        btnAplicarCupom.disabled = true;
        btnAplicarCupom.textContent = "Validando...";

        // Usa a API do carrinho para persistir o cupom na sessão
        fetch("../api/carrinho.php?action=aplicar_cupom", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json",
                "X-CSRF-Token": csrfToken,
            },
            body: JSON.stringify({ cupom: codigo, csrf_token: csrfToken }),
        })
            .then((res) => res.json())
            .then((data) => {
                if (data.success) {
                    descontoAplicado = Number(data.desconto);
                    cupomFeedback.innerHTML = '<span style="color:var(--sucesso-text);font-weight:600">' + data.mensagem + " Desconto: " + data.desconto_formatado + "</span>";
                    cupomInput.disabled = true;
                    btnAplicarCupom.textContent = "Aplicado ✓";
                    btnAplicarCupom.classList.add("btn-gray");
                    btnAplicarCupom.classList.remove("btn-outline");
                    atualizarTotalComDesconto();
                } else {
                    descontoAplicado = 0;
                    cupomFeedback.innerHTML = '<span style="color:var(--erro-text)">' + data.message + "</span>";
                    btnAplicarCupom.textContent = "Aplicar";
                    atualizarTotalComDesconto();
                }
            })
            .catch(() => {
                descontoAplicado = 0;
                cupomFeedback.innerHTML = '<span style="color:var(--erro-text)">Erro ao validar cupom. Tente novamente.</span>';
                btnAplicarCupom.textContent = "Aplicar";
                atualizarTotalComDesconto();
            })
            .finally(() => {
                btnAplicarCupom.disabled = false;
            });
    }

    if (btnAplicarCupom) {
        btnAplicarCupom.addEventListener("click", aplicarCupom);
    }

    if (cupomInput) {
        cupomInput.addEventListener("keydown", (e) => {
            if (e.key === "Enter") {
                e.preventDefault();
                aplicarCupom();
            }
        });
    }

    // Sobrescreve updateSummary para incluir desconto
    const originalUpdateSummary = updateSummary;
    updateSummary = function () {
        originalUpdateSummary();
        atualizarTotalComDesconto();
    };

    updateSummary();
    toggleCardFields();
});
