document.addEventListener("DOMContentLoaded", () => {
    const csrfToken = document.getElementById("csrf-token-checkout")?.value || "";
    const panels = Array.from(document.querySelectorAll(".checkout-panel"));
    const steps = Array.from(document.querySelectorAll(".step"));
    const nextButtons = Array.from(document.querySelectorAll(".btn-next"));
    const prevButtons = Array.from(document.querySelectorAll(".btn-prev"));
    const paymentRadios = Array.from(document.querySelectorAll("input[name='metodo_pagamento']"));
    const cardFields = document.getElementById("cartao-fields");
    // ============ CARTÃO DE CRÉDITO — SIMULAÇÃO (sem gateway real) ============
    // Este bloco implementa apenas validação de FORMATO no navegador (Luhn, validade,
    // CVV, bandeira). Nenhum dado é enviado a servidor nem processado por gateway.
    const cardPlane = document.getElementById("card-plane");
    const ccNumber = document.getElementById("cc-number");
    const ccName = document.getElementById("cc-name");
    const ccExp = document.getElementById("cc-exp");
    const ccCvv = document.getElementById("cc-cvv");
    const cardNumberEl = document.getElementById("card-number");
    const cardHolderEl = document.getElementById("card-holder");
    const cardExpEl = document.getElementById("card-exp");
    const cardCvvEl = document.getElementById("card-cvv");
    const cardBrandEl = document.getElementById("card-brand");
    const numFeedback = document.getElementById("number-feedback");
    const nameFeedback = document.getElementById("name-feedback");
    const expFeedback = document.getElementById("exp-feedback");
    const cvvFeedback = document.getElementById("cvv-feedback");

    // Detecção de bandeira por prefixo (BIN)
    const bandeirasCartao = [
        {
            id: "visa",
            nome: "Visa",
            testar: (n) => /^4/.test(n),
            cvv: 3,
            renderBrand: () => '<div class="visa-logo">VISA<span>®</span></div>',
        },
        {
            id: "mastercard",
            nome: "Mastercard",
            testar: (n) => /^(5[1-5]|2(2[2-9]|[3-6][0-9]|7[01]|720))/.test(n),
            cvv: 3,
            renderBrand: () => '<div class="mc-circles"><div class="mc-circle red"></div><div class="mc-circle yellow"></div></div>',
        },
        {
            id: "elo",
            nome: "Elo",
            testar: (n) => /^(4011|4312|4389|4514|4576|5041|5066|5067|509|6277|6362|6363|6504|6551|6556)/.test(n),
            cvv: 3,
            renderBrand: () => '<div class="elo-circles"><div class="elo-c b1"></div><div class="elo-c b2"></div><div class="elo-c b3"></div></div>',
        },
        {
            id: "amex",
            nome: "Amex",
            testar: (n) => /^(34|37)/.test(n),
            cvv: 4,
            renderBrand: () => '<span class="visa-logo" style="color:#016FD0">AMEX</span>',
        },
        {
            id: "hipercard",
            nome: "Hipercard",
            testar: (n) => /^(606282|637095|637568|637599|637600|637609|637612)/.test(n),
            cvv: 3,
            renderBrand: () => '<span class="brand-text">Hiper</span>',
        },
    ];

    function detectarBandeira(numero) {
        const digitos = (numero || "").replace(/\D/g, "");
        if (digitos.length < 2) return null;
        return bandeirasCartao.find((b) => b.testar(digitos)) || null;
    }

    function renderizarBandeira(bandeira) {
        if (!bandeira) {
            cardBrandEl.className = "card-brand default";
            cardBrandEl.innerHTML = '<span class="brand-text">•••</span>';
            return;
        }
        cardBrandEl.className = "card-brand " + bandeira.id;
        cardBrandEl.innerHTML = bandeira.renderBrand();
    }

    function mascaraNumeroCartao(valor) {
        const digitos = valor.replace(/\D/g, "").slice(0, 16);
        return digitos.replace(/(.{4})/g, "$1 ").trim();
    }

    function mascaraValidadeCartao(valor) {
        let digitos = valor.replace(/\D/g, "").slice(0, 4);
        if (digitos.length === 0) return "";
        let mes = digitos.slice(0, 2);
        if (parseInt(mes, 10) > 12 && digitos.length >= 2) mes = "12";
        let resultado = mes;
        if (digitos.length > 2) resultado += "/" + digitos.slice(2);
        return resultado;
    }

    function validarLuhn(numero) {
        const digitos = numero.replace(/\D/g, "");
        if (digitos.length !== 16) return false;
        let soma = 0;
        let dobrar = false;
        for (let i = digitos.length - 1; i >= 0; i--) {
            let d = parseInt(digitos[i], 10);
            if (dobrar) {
                d *= 2;
                if (d > 9) d -= 9;
            }
            soma += d;
            dobrar = !dobrar;
        }
        return soma % 10 === 0;
    }

    function validarValidadeCartao(valor) {
        const match = valor.match(/^(\d{2})\/(\d{2})$/);
        if (!match) return { ok: false, msg: "Formato MM/AA" };
        const mes = parseInt(match[1], 10);
        const ano = 2000 + parseInt(match[2], 10);
        if (mes < 1 || mes > 12) return { ok: false, msg: "Mês inválido" };
        const agora = new Date();
        const atualMes = agora.getFullYear() * 12 + (agora.getMonth() + 1);
        const cartaoEmMeses = ano * 12 + mes;
        if (cartaoEmMeses < atualMes) return { ok: false, msg: "Cartão vencido" };
        return { ok: true, msg: "" };
    }

    function atualizarCartaoVisual() {
        if (!cardNumberEl || !ccNumber) return;
        // Número
        let numero = ccNumber.value.replace(/\s/g, "");
        if (numero) {
            cardNumberEl.textContent = mascaraNumeroCartao(numero);
            cardNumberEl.classList.remove("placeholder");
        } else {
            cardNumberEl.textContent = "•••• •••• •••• ••••";
            cardNumberEl.classList.add("placeholder");
        }
        // Bandeira
        renderizarBandeira(detectarBandeira(numero));
        // Nome
        if (ccName && cardHolderEl) {
            let nome = ccName.value.trim().toUpperCase();
            if (nome) {
                cardHolderEl.textContent = nome;
                cardHolderEl.classList.remove("placeholder");
            } else {
                cardHolderEl.textContent = "SEU NOME";
                cardHolderEl.classList.add("placeholder");
            }
        }
        // Validade
        if (ccExp && cardExpEl) {
            let exp = ccExp.value;
            if (exp) {
                cardExpEl.textContent = exp;
                cardExpEl.classList.remove("placeholder");
            } else {
                cardExpEl.textContent = "MM/AA";
                cardExpEl.classList.add("placeholder");
            }
        }
        // CVV
        if (ccCvv && cardCvvEl) {
            let cvv = ccCvv.value;
            if (cvv) {
                cardCvvEl.textContent = cvv;
                cardCvvEl.classList.remove("card-back-cvv-placeholder");
            } else {
                cardCvvEl.textContent = "•••";
                cardCvvEl.classList.add("card-back-cvv-placeholder");
            }
        }
    }

    function validarNumeroCartao() {
        if (!ccNumber || !numFeedback) return { ok: true };
        const valor = ccNumber.value.replace(/\D/g, "");
        if (!valor) {
            ccNumber.classList.add("invalid");
            ccNumber.classList.remove("valid");
            numFeedback.textContent = "Informe o número do cartão";
            return { ok: false };
        }
        if (valor.length < 16) {
            ccNumber.classList.add("invalid");
            ccNumber.classList.remove("valid");
            numFeedback.textContent = "Faltam " + (16 - valor.length) + " dígito(s)";
            return { ok: false };
        }
        if (!validarLuhn(valor)) {
            ccNumber.classList.add("invalid");
            ccNumber.classList.remove("valid");
            numFeedback.textContent = "Número de cartão inválido (Luhn)";
            return { ok: false };
        }
        ccNumber.classList.remove("invalid");
        ccNumber.classList.add("valid");
        numFeedback.textContent = "";
        return { ok: true };
    }

    function validarNomeCartao() {
        if (!ccName || !nameFeedback) return { ok: true };
        const valor = ccName.value.trim();
        if (!valor) {
            ccName.classList.add("invalid");
            ccName.classList.remove("valid");
            nameFeedback.textContent = "Informe o nome impresso";
            nameFeedback.classList.remove("feedback-hidden");
            return { ok: false };
        }
        if (valor.length < 3) {
            ccName.classList.add("invalid");
            ccName.classList.remove("valid");
            nameFeedback.textContent = "Mínimo 3 caracteres";
            nameFeedback.classList.remove("feedback-hidden");
            return { ok: false };
        }
        ccName.classList.remove("invalid");
        ccName.classList.add("valid");
        nameFeedback.classList.add("feedback-hidden");
        return { ok: true };
    }

    function validarValidadeCartaoInput() {
        if (!ccExp || !expFeedback) return { ok: true };
        const valor = ccExp.value;
        if (!valor) {
            ccExp.classList.add("invalid");
            ccExp.classList.remove("valid");
            expFeedback.textContent = "Informe a validade";
            return { ok: false };
        }
        const result = validarValidadeCartao(valor);
        if (!result.ok) {
            ccExp.classList.add("invalid");
            ccExp.classList.remove("valid");
            expFeedback.textContent = result.msg;
            return { ok: false };
        }
        ccExp.classList.remove("invalid");
        ccExp.classList.add("valid");
        expFeedback.textContent = "";
        return { ok: true };
    }

    function validarCvvCartao() {
        if (!ccCvv || !cvvFeedback) return { ok: true };
        const valor = ccCvv.value;
        if (!valor) {
            ccCvv.classList.add("invalid");
            ccCvv.classList.remove("valid");
            cvvFeedback.textContent = "Informe o CVV";
            return { ok: false };
        }
        const bandeira = detectarBandeira(ccNumber ? ccNumber.value : "");
        const esperado = bandeira ? bandeira.cvv : 3;
        if (valor.length !== esperado) {
            ccCvv.classList.add("invalid");
            ccCvv.classList.remove("valid");
            cvvFeedback.textContent = "CVV deve ter " + esperado + " dígitos (" + (bandeira ? bandeira.nome : "Visa/outros") + ")";
            return { ok: false };
        }
        ccCvv.classList.remove("invalid");
        ccCvv.classList.add("valid");
        cvvFeedback.textContent = "";
        return { ok: true };
    }

    function initCartao() {
        if (!ccNumber) return;
        ccNumber.addEventListener("input", function () {
            this.value = mascaraNumeroCartao(this.value);
            validarNumeroCartao();
            validarCvvCartao();
            atualizarCartaoVisual();
        });
        if (ccName) {
            ccName.addEventListener("input", function () {
                this.value = this.value.replace(/[^a-zA-ZÀ-ÿçÇ\s]/g, "").toUpperCase();
                validarNomeCartao();
                atualizarCartaoVisual();
            });
        }
        if (ccExp) {
            ccExp.addEventListener("input", function () {
                this.value = mascaraValidadeCartao(this.value);
                validarValidadeCartaoInput();
                atualizarCartaoVisual();
            });
        }
        if (ccCvv && cardPlane) {
            ccCvv.addEventListener("input", function () {
                this.value = this.value.replace(/\D/g, "").slice(0, 4);
                validarCvvCartao();
                atualizarCartaoVisual();
                if (this.value === "" && cardPlane.classList.contains("flipped")) {
                    cardPlane.classList.remove("flipped");
                }
            });
            // Flip no foco do CVV
            ccCvv.addEventListener("focus", function () {
                cardPlane.classList.add("flipped");
            });
            ccCvv.addEventListener("blur", function () {
                if (this.value === "") cardPlane.classList.remove("flipped");
            });
        }
        atualizarCartaoVisual();
    }

    initCartao();

    // ============================================================
    // FRETE REAL — Correios API (via api/frete.php)
    // ============================================================
    const freteLoading = document.getElementById("frete-loading");
    const freteOpcoes = document.getElementById("frete-opcoes");
    const freteHint = document.getElementById("frete-hint");
    const freteHidden = document.getElementById("frete-hidden");
    // Mapa dinâmico: valor do radio => { valor, prazo, servico }
    let freteCalculado = { retirada: { valor: 0, prazo: null, servico: "Retirada na loja" } };

    function getCepSelecionado() {
        // Endereço salvo selecionado (radio com data-cep)
        const radio = document.querySelector("input[name='endereco_id']:checked");
        if (radio && radio.dataset.cep) {
            return radio.dataset.cep;
        }
        // Novo endereço (campo novo_cep)
        const novoCep = document.getElementById("novo_cep");
        if (novoCep && novoCep.value) {
            return novoCep.value;
        }
        return "";
    }

    function carregarFrete(cep) {
        const cepLimpo = (cep || "").replace(/\D/g, "");

        if (cepLimpo.length !== 8) {
            // Sem CEP válido → mostra só retirada
            renderizarOpcoesFrete({ frete: [] });
            return;
        }

        // Loading state
        if (freteLoading) freteLoading.style.display = "block";
        if (freteHint) freteHint.style.display = "none";

        fetch("../api/frete.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json",
                "X-CSRF-Token": csrfToken,
            },
            body: JSON.stringify({ cep: cepLimpo, csrf_token: csrfToken }),
        })
            .then((r) => r.json())
            .then((data) => {
                if (freteLoading) freteLoading.style.display = "none";
                if (data.sucesso) {
                    renderizarOpcoesFrete(data);
                } else {
                    mostrarOpcoesFallback();
                }
            })
            .catch(() => {
                if (freteLoading) freteLoading.style.display = "none";
                mostrarOpcoesFallback();
            });
    }

    function renderizarOpcoesFrete(data) {
        const opcoes = Array.isArray(data.frete) ? data.frete : [];
        if (freteOpcoes) {
            freteOpcoes.innerHTML = "";
        }

        // Sempre inclui retirada na loja
        addOpcaoFrete("retirada", "Retirada na loja", "Grátis", 0, true);

        // Adiciona os Correios (SEDEX/PAC)
        opcoes.forEach((op, i) => {
            const servico = op.servico;
            const valor = Number(op.valor) || 0;
            const prazo = op.prazo;
            const rotuloPrazo = prazo ? (prazo + " dia" + (prazo > 1 ? "s" : "") + " úteis") : "prazo indisponível";
            const valorFmt = formatarMoeda(valor);
            // Mantém "Retirada na loja" marcada por padrão; Correios ficam opcionais
            addOpcaoFrete(
                servico.toLowerCase(),
                servico + " – Correios",
                valorFmt + " • " + rotuloPrazo,
                valor,
                i === 0 ? false : false
            );
        });

        if (freteHint) freteHint.style.display = "none";
        atualizarFreteHidden();
        updateSummary();
    }

    function addOpcaoFrete(valor, titulo, subtitulo, valorNum, checked, forcar) {
        const label = document.createElement("label");
        label.className = "payment-option";
        const input = document.createElement("input");
        input.type = "radio";
        input.name = "metodo_frete";
        input.value = valor;
        if (checked) input.checked = true;
        const div = document.createElement("div");
        const strong = document.createElement("strong");
        strong.textContent = titulo;
        const sub = document.createElement("div");
        sub.style.color = "var(--cinza-600)";
        sub.style.fontSize = "0.85rem";
        sub.textContent = subtitulo;
        div.appendChild(strong);
        div.appendChild(sub);
        const span = document.createElement("span");
        span.textContent = subtitulo;
        label.appendChild(input);
        label.appendChild(div);
        label.appendChild(span);
        if (freteOpcoes) freteOpcoes.appendChild(label);

        // Registra no mapa dinâmico
        freteCalculado[valor] = { valor: valorNum, prazo: null, servico: titulo };

        input.addEventListener("change", () => {
            updateSummary();
            atualizarFreteHidden();
        });
    }

    function mostrarOpcoesFallback() {
        // Fallback (API fora do ar) → valores simulados fixos do checkout.php
        renderizarOpcoesFrete({
            frete: [
                { servico: "PAC", prazo: 8, valor: 22.50 },
                { servico: "SEDEX", prazo: 2, valor: 35.90 },
            ],
        });
    }

    function atualizarFreteHidden() {
        if (!freteHidden) return;
        const metodo = getSelectedFrete();
        const op = freteCalculado[metodo] || { valor: 0 };
        freteHidden.value = String(op.valor || 0);
    }

    // Observa mudanças no endereço (radio salvo)
    document.querySelectorAll("input[name='endereco_id']").forEach((radio) => {
        radio.addEventListener("change", () => {
            const cep = radio.dataset.cep || "";
            if (cep) carregarFrete(cep);
        });
    });

    // Observa mudanças no novo CEP
    const novoCepInput = document.getElementById("novo_cep");
    if (novoCepInput) {
        novoCepInput.addEventListener("input", () => {
            const cep = novoCepInput.value;
            if ((cep.replace(/\D/g, "")).length === 8) {
                carregarFrete(cep);
            }
        });
    }

    // ============================================================
    // FLUXO DO CHECKOUT (steps, resumo, cupom, etc.)
    // ============================================================
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
        return radio ? radio.value : "retirada";
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
        const op = freteCalculado[metodo] || { valor: 0 };
        return Number(op.valor) || 0;
    }

    function updateSummary() {
        const subtotal = getSubtotal();
        const frete = getFrete();
        const total = subtotal + frete;

        const resumoFrete = document.getElementById("resumo-frete");
        const resumoTotal = document.getElementById("resumo-total");

        if (resumoFrete) {
            resumoFrete.textContent = formatarMoeda(frete);
        }

        if (resumoTotal) {
            resumoTotal.textContent = formatarMoeda(total);
        }
        atualizarFreteHidden();
    }

    function toggleCardFields() {
        if (!cardFields) {
            return;
        }
        const metodo = getSelectedPagamento();
        const cartaoSelecionado = metodo === "cartao";
        cardFields.style.display = cartaoSelecionado ? "block" : "none";
        // Habilita/desabilita todos os inputs do cartão conforme seleção
        cardFields.querySelectorAll("input").forEach((input) => {
            input.disabled = !cartaoSelecionado;
        });
    }

    nextButtons.forEach((button) => {
        button.addEventListener("click", () => {
            const currentIndex = getCurrentStep();
            // Valida o cartão na etapa de pagamento (simulação, sem gateway real)
            if (currentIndex === 2 && getSelectedPagamento() === "cartao") {
                const r1 = validarNumeroCartao();
                const r2 = validarNomeCartao();
                const r3 = validarValidadeCartaoInput();
                const r4 = validarCvvCartao();
                if (!(r1.ok && r2.ok && r3.ok && r4.ok)) return;
            }
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
