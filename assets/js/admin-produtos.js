/* ============================================================
   ADMIN-PRODUTOS.JS — Interatividade do Painel de Gestão
   de Produtos: abas, validação, previews e feedback visual
   ============================================================ */

document.addEventListener("DOMContentLoaded", function () {
    /* --------------------------------------------------------
       1. SISTEMA DE ABAS (TABS)
       -------------------------------------------------------- */
    const tabButtons = document.querySelectorAll(".tab-btn");
    const tabPanels = document.querySelectorAll(".tab-panel");

    tabButtons.forEach((btn) => {
        btn.addEventListener("click", function () {
            const target = this.dataset.tab;

            // Remove active de todos os botões e painéis
            tabButtons.forEach((b) => b.classList.remove("active"));
            tabPanels.forEach((p) => p.classList.remove("active"));

            // Ativa o botão e painel clicado
            this.classList.add("active");
            const panel = document.getElementById("tab-" + target);
            if (panel) panel.classList.add("active");
        });
    });

    /* --------------------------------------------------------
       2. SEÇÕES COLAPSÁVEIS
       -------------------------------------------------------- */
    const collapsibleHeaders = document.querySelectorAll(".collapsible-header");

    collapsibleHeaders.forEach((header) => {
        header.addEventListener("click", function () {
            const section = this.closest(".collapsible-section");
            section.classList.toggle("collapsed");
        });
    });

    /* --------------------------------------------------------
       3. GERAÇÃO AUTOMÁTICA DE SLUG
       -------------------------------------------------------- */
    const nomeInput = document.getElementById("produto-nome");
    const slugInput = document.getElementById("produto-slug");
    const slugAuto = document.getElementById("slug-auto");
    let slugManual = false;

    // Função para gerar slug a partir do nome
    function gerarSlug(texto) {
        return texto
            .toLowerCase()
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "") // remove acentos
            .replace(/[^a-z0-9\s-]/g, "")     // remove caracteres especiais
            .trim()
            .replace(/\s+/g, "-")             // espaços viram hífens
            .replace(/-+/g, "-");             // remove hífens duplicados
    }

    // Gera slug automaticamente quando o nome muda (se não foi editado manualmente)
    if (nomeInput && slugInput) {
        nomeInput.addEventListener("input", function () {
            if (!slugManual) {
                slugInput.value = gerarSlug(this.value);
                atualizarPreviewSERP();
            }
        });

        // Marca como manual quando o usuário edita o slug
        slugInput.addEventListener("input", function () {
            slugManual = this.value !== gerarSlug(nomeInput.value);
            atualizarPreviewSERP();
        });

        // Botão para regenerar slug automaticamente
        if (slugAuto) {
            slugAuto.addEventListener("click", function () {
                slugManual = false;
                slugInput.value = gerarSlug(nomeInput.value);
                atualizarPreviewSERP();
                mostrarToast("Slug regenerado automaticamente", "success");
            });
        }
    }

    /* --------------------------------------------------------
       4. CONTADOR DE CARACTERES (Meta Título e Descrição)
       -------------------------------------------------------- */
    const metaTitulo = document.getElementById("meta-titulo");
    const metaDescricao = document.getElementById("meta-descricao");

    // Limites recomendados pelo Google
    const LIMITE_TITULO = 60;
    const LIMITE_DESCRICAO = 160;

    function atualizarContador(input, counterId, limite) {
        if (!input || !counterId) return;
        const counter = document.getElementById(counterId);
        if (!counter) return;

        const length = input.value.length;
        counter.textContent = length + " / " + limite;

        // Feedback visual por faixa de caracteres
        counter.classList.remove("warning", "danger");
        if (length > limite) {
            counter.classList.add("danger");
        } else if (length > limite * 0.8) {
            counter.classList.add("warning");
        }
    }

    if (metaTitulo) {
        metaTitulo.addEventListener("input", function () {
            atualizarContador(this, "contador-titulo", LIMITE_TITULO);
            atualizarPreviewSERP();
        });
    }

    if (metaDescricao) {
        metaDescricao.addEventListener("input", function () {
            atualizarContador(this, "contador-descricao", LIMITE_DESCRICAO);
            atualizarPreviewSERP();
        });
    }

    /* --------------------------------------------------------
       5. PREVIEW DE SERP (Simulação Google)
       -------------------------------------------------------- */
    function atualizarPreviewSERP() {
        const previewTitulo = document.getElementById("serp-titulo");
        const previewDesc = document.getElementById("serp-descricao");
        const previewUrl = document.getElementById("serp-url");

        // Título: usa meta título ou nome do produto
        if (previewTitulo) {
            const titulo = metaTitulo && metaTitulo.value
                ? metaTitulo.value
                : (nomeInput ? nomeInput.value : "");
            previewTitulo.textContent = titulo || "Título do produto aparecerá aqui";
        }

        // Descrição: usa meta descrição ou descrição curta
        if (previewDesc) {
            const desc = metaDescricao && metaDescricao.value
                ? metaDescricao.value
                : (document.getElementById("descricao-curta")
                    ? document.getElementById("descricao-curta").value
                    : "");
            previewDesc.textContent = desc || "A descrição do produto aparecerá aqui no Google.";
        }

        // URL: usa o slug
        if (previewUrl && slugInput) {
            const slug = slugInput.value || "nome-do-produto";
            previewUrl.textContent = "www.ecoshop.com.br/produto/" + slug;
        }
    }

    // Atualiza preview quando a descrição curta muda
    const descricaoCurta = document.getElementById("descricao-curta");
    if (descricaoCurta) {
        descricaoCurta.addEventListener("input", function () {
            atualizarPreviewSERP();
            atualizarContador(this, "contador-desc-curta", 200);
        });
    }

    /* --------------------------------------------------------
       6. UPLOAD DE IMAGENS COM PREVIEW
       -------------------------------------------------------- */
    const imageUploaders = document.querySelectorAll(".image-upload-item");

    imageUploaders.forEach((item) => {
        const input = item.querySelector('input[type="file"]');
        const removeBtn = item.querySelector(".remove-image");

        if (input) {
            input.addEventListener("change", function () {
                const file = this.files[0];
                if (!file) return;

                // Valida tipo de arquivo
                if (!file.type.startsWith("image/")) {
                    mostrarToast("Por favor, selecione um arquivo de imagem", "error");
                    this.value = "";
                    return;
                }

                // Valida tamanho (máx 5MB)
                if (file.size > 5 * 1024 * 1024) {
                    mostrarToast("Imagem muito grande. Máximo 5MB.", "error");
                    this.value = "";
                    return;
                }

                // Cria preview com FileReader
                const reader = new FileReader();
                reader.onload = function (e) {
                    // Remove preview anterior se existir
                    const imgExistente = item.querySelector("img");
                    if (imgExistente) imgExistente.remove();

                    const img = document.createElement("img");
                    img.src = e.target.result;
                    img.alt = "Preview da imagem";
                    item.appendChild(img);
                    item.classList.add("has-image");
                };
                reader.readAsDataURL(file);
            });
        }

        // Botão remover imagem
        if (removeBtn) {
            removeBtn.addEventListener("click", function (e) {
                e.preventDefault();
                e.stopPropagation();

                const img = item.querySelector("img");
                if (img) img.remove();
                if (input) input.value = "";
                item.classList.remove("has-image");
            });
        }
    });

    /* --------------------------------------------------------
       7. TAGS DE PALAVRAS-CHAVE
       -------------------------------------------------------- */
    const tagsInputs = document.querySelectorAll(".tags-input");

    tagsInputs.forEach((container) => {
        const input = container.querySelector("input");
        const hiddenInput = container.querySelector('input[type="hidden"]');
        
        // Carrega tags existentes do atributo data-tags (modo edição)
        const tagsIniciais = (container.dataset.tags || "")
            .split(",")
            .map((t) => t.trim())
            .filter((t) => t.length > 0);
        const tags = [...tagsIniciais];

        // Renderiza tags iniciais
        renderTags();

        function renderTags() {
            // Remove tags existentes (exceto o input)
            container.querySelectorAll(".tag").forEach((t) => t.remove());

            // Renderiza cada tag
            tags.forEach((tag, index) => {
                const tagEl = document.createElement("span");
                tagEl.className = "tag";
                tagEl.innerHTML = tag + ' <span class="tag-remove" data-index="' + index + '">×</span>';
                container.insertBefore(tagEl, input);
            });

            // Atualiza input hidden com tags separadas por vírgula
            if (hiddenInput) {
                hiddenInput.value = tags.join(",");
            }
        }

        // Adiciona tag ao pressionar Enter ou vírgula
        input.addEventListener("keydown", function (e) {
            if (e.key === "Enter" || e.key === ",") {
                e.preventDefault();
                const valor = this.value.trim().replace(/,/g, "");
                if (valor && !tags.includes(valor)) {
                    tags.push(valor);
                    this.value = "";
                    renderTags();
                }
            }
        });

        // Remove tag ao clicar no X
        container.addEventListener("click", function (e) {
            if (e.target.classList.contains("tag-remove")) {
                const index = parseInt(e.target.dataset.index);
                tags.splice(index, 1);
                renderTags();
            }
        });

        // Foca o input ao clicar no container
        container.addEventListener("click", function (e) {
            if (e.target === container) {
                input.focus();
            }
        });
    });

    /* --------------------------------------------------------
       8. SUGESTÃO AUTOMÁTICA DE SKU
       -------------------------------------------------------- */
    const categoriaSelect = document.getElementById("categoria");
    const skuInput = document.getElementById("produto-sku");
    const skuSugerir = document.getElementById("sku-sugerir");
    let skuManual = false;

    function gerarSugestaoSKU() {
        if (!skuInput || !nomeInput) return;

        // Pega iniciais da categoria (máx 3 letras)
        const categoria = categoriaSelect ? categoriaSelect.value : "";
        const catIniciais = categoria
            .split(" ")
            .map((p) => p[0])
            .join("")
            .toUpperCase()
            .slice(0, 3);

        // Pega iniciais do nome (máx 3 letras)
        const nome = nomeInput.value || "PROD";
        const nomeIniciais = nome
            .split(" ")
            .map((p) => p[0])
            .join("")
            .toUpperCase()
            .replace(/[^A-Z]/g, "")
            .slice(0, 3);

        // Gera número aleatório de 4 dígitos
        const numero = Math.floor(1000 + Math.random() * 9000);

        return (catIniciais || "GEN") + "-" + (nomeIniciais || "PROD") + "-" + numero;
    }

    // Botão para sugerir SKU
    if (skuSugerir && skuInput) {
        skuSugerir.addEventListener("click", function () {
            skuManual = false;
            skuInput.value = gerarSugestaoSKU();
            validarCampo(skuInput);
            mostrarToast("SKU sugerido automaticamente", "success");
        });
    }

    // Marca como manual quando o usuário edita o SKU
    if (skuInput) {
        skuInput.addEventListener("input", function () {
            skuManual = true;
            validarCampo(this);
        });
    }

    /* --------------------------------------------------------
       9. VALIDAÇÃO DE CAMPOS
       -------------------------------------------------------- */
    // Validação de EAN-13 (código de barras)
    // Aceita EAN-8 ou EAN-13 com apenas dígitos numéricos.
    // NÃO valida dígito verificador para não bloquear EANs válidos
    // que podem ter sido gerados sem o dígito verificador correto.
    function validarEAN(ean) {
        // Remove espaços e hífens
        ean = ean.replace(/[\s-]/g, "");

        // Deve ter 8 ou 13 dígitos numéricos
        return /^\d{8}$|^\d{13}$/.test(ean);
    }

    // Validação de NCM (8 dígitos)
    function validarNCM(ncm) {
        return /^\d{8}$/.test(ncm.replace(/[\s.-]/g, ""));
    }

    // Função genérica para validar um campo
    function validarCampo(campo) {
        const valor = campo.value.trim();
        const id = campo.id;
        let valido = true;
        let mensagem = "";

        // Validações específicas por campo
        switch (id) {
            case "produto-nome":
                valido = valor.length >= 3;
                mensagem = "O nome do produto deve ter pelo menos 3 caracteres.";
                break;
            case "produto-sku":
                valido = valor.length >= 5;
                mensagem = "O SKU deve ter pelo menos 5 caracteres.";
                break;
            case "produto-ean":
                valido = validarEAN(valor);
                mensagem = "EAN inválido. Use 8 ou 13 dígitos válidos.";
                break;
            case "produto-ncm":
                valido = valor === "" || validarNCM(valor);
                mensagem = "NCM deve ter 8 dígitos numéricos.";
                break;
            case "produto-preco":
                valido = parseFloat(valor) > 0;
                mensagem = "O preço deve ser maior que zero.";
                break;
            case "produto-estoque":
                valido = parseInt(valor) >= 0;
                mensagem = "O estoque não pode ser negativo.";
                break;
            case "produto-slug":
                valido = /^[a-z0-9-]+$/.test(valor) && valor.length > 0;
                mensagem = "Slug inválido. Use apenas letras minúsculas, números e hífens.";
                break;
            default:
                // Campos obrigatórios genéricos
                if (campo.dataset.required === "true") {
                    valido = valor.length > 0;
                    mensagem = "Este campo é obrigatório.";
                }
        }

        // Aplica classes de validação
        campo.classList.remove("is-valid", "is-invalid");
        if (valido) {
            campo.classList.add("is-valid");
        } else if (valor.length > 0) {
            campo.classList.add("is-invalid");
        }

        // Atualiza mensagem de erro
        const feedback = campo.closest(".form-group")?.querySelector(".invalid-feedback");
        if (feedback) {
            feedback.textContent = mensagem;
            feedback.classList.toggle("show", !valido && valor.length > 0);
        }

        return valido;
    }

    // Adiciona listeners de validação em tempo real
    const camposValidaveis = document.querySelectorAll(
        "#produto-nome, #produto-sku, #produto-ean, #produto-ncm, #produto-preco, #produto-estoque, #produto-slug"
    );

    camposValidaveis.forEach((campo) => {
        campo.addEventListener("blur", function () {
            validarCampo(this);
            atualizarValidacaoAbas();
        });
        campo.addEventListener("input", function () {
            validarCampo(this);
            atualizarValidacaoAbas();
        });
    });

    /* --------------------------------------------------------
       10. VALIDAÇÃO DAS ABAS (check verde quando válida)
       -------------------------------------------------------- */
    function atualizarValidacaoAbas() {
        // Aba 1: Dados básicos — nome, preço, estoque
        const nomeOk = document.getElementById("produto-nome")?.value.trim().length >= 3;
        const precoOk = parseFloat(document.getElementById("produto-preco")?.value) > 0;
        const estoqueOk = parseInt(document.getElementById("produto-estoque")?.value) >= 0;
        const tab1Ok = nomeOk && precoOk && estoqueOk;

        // Aba 2: SEO — slug válido
        const slugOk = /^[a-z0-9-]+$/.test(document.getElementById("produto-slug")?.value || "");
        const tab2Ok = slugOk;

        // Aba 3: Identificação — SKU e EAN válidos
        const skuOk = document.getElementById("produto-sku")?.value.trim().length >= 5;
        const eanOk = validarEAN(document.getElementById("produto-ean")?.value || "");
        const tab3Ok = skuOk && eanOk;

        // Aplica classes nas abas
        const tab1 = document.querySelector('.tab-btn[data-tab="basicos"]');
        const tab2 = document.querySelector('.tab-btn[data-tab="seo"]');
        const tab3 = document.querySelector('.tab-btn[data-tab="identificacao"]');

        if (tab1) tab1.classList.toggle("is-valid", tab1Ok);
        if (tab2) tab2.classList.toggle("is-valid", tab2Ok);
        if (tab3) tab3.classList.toggle("is-valid", tab3Ok);

        // Atualiza barra de progresso
        atualizarProgresso();
    }

    /* --------------------------------------------------------
       11. BARRA DE PROGRESSO DO FORMULÁRIO
       -------------------------------------------------------- */
    function atualizarProgresso() {
        const barra = document.querySelector(".progress-bar-fill");
        const percentual = document.querySelector(".progress-percent");
        if (!barra || !percentual) return;

        // Campos obrigatórios: nome, SKU, EAN, preço, estoque, slug
        const campos = [
            document.getElementById("produto-nome"),
            document.getElementById("produto-sku"),
            document.getElementById("produto-ean"),
            document.getElementById("produto-preco"),
            document.getElementById("produto-estoque"),
            document.getElementById("produto-slug"),
        ];

        const preenchidos = campos.filter((c) => c && c.value.trim().length > 0).length;
        const total = campos.length;
        const pct = Math.round((preenchidos / total) * 100);

        barra.style.width = pct + "%";
        percentual.textContent = pct + "%";
    }

    /* --------------------------------------------------------
       12. FAQ — Expandir/recolher itens
       -------------------------------------------------------- */
    const faqItems = document.querySelectorAll(".faq-item");

    faqItems.forEach((item) => {
        const header = item.querySelector(".faq-item-header");
        if (header) {
            header.addEventListener("click", function () {
                item.classList.toggle("open");
            });
        }
    });

    /* --------------------------------------------------------
       13. TOAST — Feedback visual flutuante
       -------------------------------------------------------- */
    function mostrarToast(mensagem, tipo = "success") {
        // Remove toasts existentes
        document.querySelectorAll(".toast").forEach((t) => t.remove());

        const toast = document.createElement("div");
        toast.className = "toast toast-" + tipo;
        toast.textContent = mensagem;
        document.body.appendChild(toast);

        // Anima entrada
        requestAnimationFrame(() => {
            toast.classList.add("show");
        });

        // Remove após 3 segundos
        setTimeout(() => {
            toast.classList.remove("show");
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // Expõe a função globalmente para uso em atributos onclick
    window.mostrarToast = mostrarToast;

    /* --------------------------------------------------------
       14. SUBMISSÃO DO FORMULÁRIO (via API)
       -------------------------------------------------------- */
    const form = document.getElementById("form-produto");

    if (form) {
        form.addEventListener("submit", function (e) {
            e.preventDefault();

            // Valida todos os campos obrigatórios
            const camposObrigatorios = [
                "produto-nome",
                "produto-sku",
                "produto-ean",
                "produto-preco",
                "produto-estoque",
                "produto-slug",
            ];

            let todosValidos = true;
            let primeiroInvalido = null;

            camposObrigatorios.forEach((id) => {
                const campo = document.getElementById(id);
                if (campo) {
                    const valido = validarCampo(campo);
                    if (!valido && !primeiroInvalido) {
                        primeiroInvalido = campo;
                    }
                    if (!valido) todosValidos = false;
                }
            });

            if (!todosValidos) {
                mostrarToast("Preencha corretamente os campos obrigatórios", "error");

                // Foca no primeiro campo inválido e abre a aba correta
                if (primeiroInvalido) {
                    const aba = primeiroInvalido.closest(".tab-panel");
                    if (aba) {
                        const tabId = aba.id.replace("tab-", "");
                        const btn = document.querySelector('.tab-btn[data-tab="' + tabId + '"]');
                        if (btn) btn.click();
                    }
                    primeiroInvalido.focus();
                }
                return;
            }

            // Coleta dados do formulário usando FormData (suporta upload de arquivos)
            const formData = new FormData(form);

            // Feedback de loading no botão
            const btnSalvar = document.getElementById("btn-salvar");
            if (btnSalvar) {
                btnSalvar.classList.add("loading");
                btnSalvar.textContent = "Salvando...";
            }

            // Envia para a API via fetch (caminho relativo à página admin)
            fetch("../../api/admin/salvar_produto.php", {
                method: "POST",
                body: formData,
            })
            .then((response) => response.json())
            .then((data) => {
                if (btnSalvar) {
                    btnSalvar.classList.remove("loading");
                    btnSalvar.textContent = "💾 Salvar Produto";
                }

                if (data.sucesso) {
                    mostrarToast(data.mensagem || "Produto salvo com sucesso!", "success");

                    // Redireciona para edição do produto criado
                    setTimeout(() => {
                        const produtoIdExistente = formData.get("id");
                        if (data.id && !produtoIdExistente) {
                            window.location.href = "produtos.php?id=" + data.id;
                        }
                    }, 1500);
                } else {
                    // Exibe erros retornados pela API
                    if (data.erros && Array.isArray(data.erros)) {
                        data.erros.forEach((msg) => mostrarToast(msg, "error"));
                    } else {
                        mostrarToast(data.erro || "Erro ao salvar produto", "error");
                    }
                }
            })
            .catch((error) => {
                if (btnSalvar) {
                    btnSalvar.classList.remove("loading");
                    btnSalvar.textContent = "💾 Salvar Produto";
                }
                mostrarToast("Erro de conexão ao salvar produto", "error");
            });
        });
    }

    /* --------------------------------------------------------
       14b. ENVIO DE MENSAGEM PARA O CLIENTE
       -------------------------------------------------------- */
    const btnEnviarMensagem = document.getElementById("btn-enviar-mensagem");
    const novaMensagem = document.getElementById("nova-mensagem");

    if (btnEnviarMensagem && novaMensagem) {
        btnEnviarMensagem.addEventListener("click", function () {
            const mensagem = novaMensagem.value.trim();

            if (!mensagem) {
                mostrarToast("Digite uma mensagem para o cliente", "error");
                novaMensagem.focus();
                return;
            }

            const produtoId = document.getElementById("produto-id")?.value;

            if (!produtoId) {
                mostrarToast("Salve o produto primeiro antes de enviar mensagens", "error");
                return;
            }

            // Cria um novo ticket ou usa o primeiro existente
            // (em uma implementação real, selecionaria o ticket ativo)
            fetch("../../api/admin/buscar_produto.php?id=" + produtoId, {
                method: "GET",
            })
            .then((r) => r.json())
            .then((data) => {
                if (data.sucesso && data.tickets && data.tickets.length > 0) {
                    // Usa o primeiro ticket e envia mensagem
                    const ticketId = data.tickets[0].id;
                    return enviarMensagemTicket(ticketId, mensagem);
                } else {
                    mostrarToast("Não há tickets abertos para este produto", "error");
                }
            })
            .catch(() => mostrarToast("Erro ao verificar tickets", "error"));
        });
    }

    function enviarMensagemTicket(ticketId, mensagem) {
        // Envia mensagem via API real
        return fetch("../../api/admin/responder_ticket.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify({
                ticket_id: ticketId,
                mensagem: mensagem,
            }),
        })
        .then((response) => response.json())
        .then((data) => {
            if (data.sucesso) {
                mostrarToast(data.mensagem || "Mensagem enviada ao cliente!", "success");
                novaMensagem.value = "";
            } else {
                mostrarToast(data.erro || "Erro ao enviar mensagem", "error");
            }
        })
        .catch(() => {
            mostrarToast("Erro de conexão ao enviar mensagem", "error");
        });
    }

    /* --------------------------------------------------------
       15. INICIALIZAÇÃO
       -------------------------------------------------------- */
    // Atualiza contadores iniciais
    atualizarContador(metaTitulo, "contador-titulo", LIMITE_TITULO);
    atualizarContador(metaDescricao, "contador-descricao", LIMITE_DESCRICAO);
    atualizarContador(descricaoCurta, "contador-desc-curta", 200);

    // Atualiza preview SERP inicial
    atualizarPreviewSERP();

    // Atualiza validação das abas
    atualizarValidacaoAbas();

    // Atualiza barra de progresso
    atualizarProgresso();
});