document.addEventListener("DOMContentLoaded", function () {

    const tabButtons = document.querySelectorAll(".tab-btn");
    const tabPanels = document.querySelectorAll(".tab-panel");

    tabButtons.forEach((btn) => {
        btn.addEventListener("click", function () {
            const target = this.dataset.tab;

            tabButtons.forEach((b) => b.classList.remove("active"));
            tabPanels.forEach((p) => p.classList.remove("active"));

            this.classList.add("active");
            const panel = document.getElementById("tab-" + target);
            if (panel) panel.classList.add("active");
        });
    });

    const collapsibleHeaders = document.querySelectorAll(".collapsible-header");

    collapsibleHeaders.forEach((header) => {
        header.addEventListener("click", function () {
            const section = this.closest(".collapsible-section");
            section.classList.toggle("collapsed");
        });
    });

    const nomeInput = document.getElementById("produto-nome");
    const slugInput = document.getElementById("produto-slug");
    const slugAuto = document.getElementById("slug-auto");
    let slugManual = false;

    function gerarSlug(texto) {
        return texto
            .toLowerCase()
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .replace(/[^a-z0-9\s-]/g, "")
            .trim()
            .replace(/\s+/g, "-")
            .replace(/-+/g, "-");
    }

    if (nomeInput && slugInput) {
        nomeInput.addEventListener("input", function () {
            if (!slugManual) {
                slugInput.value = gerarSlug(this.value);
                atualizarPreviewSERP();
            }
        });

        slugInput.addEventListener("input", function () {
            slugManual = this.value !== gerarSlug(nomeInput.value);
            atualizarPreviewSERP();
        });

        if (slugAuto) {
            slugAuto.addEventListener("click", function () {
                slugManual = false;
                slugInput.value = gerarSlug(nomeInput.value);
                atualizarPreviewSERP();
                mostrarToast("Slug regenerado automaticamente", "success");
            });
        }
    }

    const metaTitulo = document.getElementById("meta-titulo");
    const metaDescricao = document.getElementById("meta-descricao");

    const LIMITE_TITULO = 60;
    const LIMITE_DESCRICAO = 160;

    function atualizarContador(input, counterId, limite) {
        if (!input || !counterId) return;
        const counter = document.getElementById(counterId);
        if (!counter) return;

        const length = input.value.length;
        counter.textContent = length + " / " + limite;

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

    function atualizarPreviewSERP() {
        const previewTitulo = document.getElementById("serp-titulo");
        const previewDesc = document.getElementById("serp-descricao");
        const previewUrl = document.getElementById("serp-url");

        if (previewTitulo) {
            const titulo = metaTitulo && metaTitulo.value
                ? metaTitulo.value
                : (nomeInput ? nomeInput.value : "");
            previewTitulo.textContent = titulo || "Título do produto aparecerá aqui";
        }

        if (previewDesc) {
            const desc = metaDescricao && metaDescricao.value
                ? metaDescricao.value
                : (document.getElementById("descricao-curta")
                    ? document.getElementById("descricao-curta").value
                    : "");
            previewDesc.textContent = desc || "A descrição do produto aparecerá aqui no Google.";
        }

        if (previewUrl && slugInput) {
            const slug = slugInput.value || "nome-do-produto";
            previewUrl.textContent = "www.ecoshop.com.br/produto/" + slug;
        }
    }

    const descricaoCurta = document.getElementById("descricao-curta");
    if (descricaoCurta) {
        descricaoCurta.addEventListener("input", function () {
            atualizarPreviewSERP();
            atualizarContador(this, "contador-desc-curta", 200);
        });
    }

    const imageUploaders = document.querySelectorAll(".image-upload-item");
    const uploadZone = document.getElementById("upload-zone");
    const imageUploader = document.getElementById("image-uploader");

    // Atualiza o hidden "imagem_principal" conforme o 1º slot com imagem.
    // Para imagens existentes, usa a URL real (uploads/... ou http...).
    // Para imagens novas (data URL base64), envia o marcador __NOVO__:<índice>
    // que o backend mapeia para a URL gerada no servidor.
    function atualizarPrincipalHidden() {
        let principalInput = document.querySelector('input[name="imagem_principal"]');
        if (!principalInput) {
            principalInput = document.createElement("input");
            principalInput.type = "hidden";
            principalInput.name = "imagem_principal";
            const form = document.getElementById("form-produto");
            if (form) form.appendChild(principalInput);
        }

        let primeiraUrl = "";
        // Primeiro slot (index 0) com imagem
        const slot1 = document.getElementById("imagem-slot-1");
        if (slot1) {
            const img0 = slot1.querySelector("img");
            if (img0) {
                if (img0.dataset.url && !img0.dataset.url.startsWith("data:")) {
                    // Imagem existente → URL real
                    primeiraUrl = img0.dataset.url;
                } else {
                    // Imagem nova (data URL) → marcador __NOVO__:<índice DOM>
                    // O índice DOM (posição no array name="imagens[]") é o que
                    // o backend recebe em $_FILES["imagens"].
                    const input = slot1.querySelector('input[type="file"]');
                    if (input && input.files && input.files.length > 0) {
                        const inputsImagens = Array.from(document.querySelectorAll('input[name="imagens[]"]'));
                        const indiceDom = inputsImagens.indexOf(input);
                        if (indiceDom >= 0) {
                            primeiraUrl = "__NOVO__:" + indiceDom;
                        }
                    }
                }
            }
        }
        principalInput.value = primeiraUrl;
    }
    atualizarPrincipalHidden();

    // Drag and Drop
    if (uploadZone && imageUploader) {
        ["dragenter", "dragover", "dragleave", "drop"].forEach((eventName) => {
            document.body.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        document.body.addEventListener("dragenter", () => {
            uploadZone.style.display = "block";
        });

        uploadZone.addEventListener("dragleave", (e) => {
            if (e.target === uploadZone) {
                uploadZone.style.display = "none";
            }
        });

        uploadZone.addEventListener("drop", (e) => {
            uploadZone.style.display = "none";
            const files = e.dataTransfer.files;
            handleFiles(files);
        });

        imageUploader.addEventListener("dragover", (e) => {
            e.preventDefault();
            imageUploader.style.borderColor = "var(--primary)";
        });

        imageUploader.addEventListener("dragleave", () => {
            imageUploader.style.borderColor = "";
        });

        imageUploader.addEventListener("drop", (e) => {
            e.preventDefault();
            imageUploader.style.borderColor = "";
            const files = e.dataTransfer.files;
            handleFiles(files);
        });
    }

    function handleFiles(files) {
        const slotsVazios = Array.from(imageUploaders).filter(
            (slot) => !slot.classList.contains("has-image")
        );

        if (slotsVazios.length === 0) {
            mostrarToast("Máximo de 8 imagens atingido", "error");
            return;
        }

        Array.from(files).forEach((file, index) => {
            if (index >= slotsVazios.length) return;

            if (!file.type.startsWith("image/")) {
                mostrarToast(`Arquivo ${file.name} não é uma imagem`, "error");
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                mostrarToast(`Imagem ${file.name} muito grande. Máximo 5MB.`, "error");
                return;
            }

            const slot = slotsVazios[index];
            const input = slot.querySelector('input[type="file"]');
            const progressDiv = slot.querySelector(".upload-progress");
            const progressBar = slot.querySelector(".upload-progress-bar");

            if (progressDiv && progressBar) {
                progressDiv.style.display = "block";
                progressBar.style.width = "0%";
            }

            const reader = new FileReader();
            reader.onprogress = (e) => {
                if (e.lengthComputable && progressBar) {
                    const percent = (e.loaded / e.total) * 100;
                    progressBar.style.width = percent + "%";
                }
            };

            reader.onload = function (e) {
                const imgExistente = slot.querySelector("img");
                if (imgExistente) imgExistente.remove();

                const img = document.createElement("img");
                img.src = e.target.result;
                img.alt = "Preview da imagem";
                img.draggable = true;
                slot.appendChild(img);
                slot.classList.add("has-image");
                slot.draggable = true;

                if (progressDiv) {
                    progressDiv.style.display = "none";
                }

                // Adiciona evento de drag na imagem
                img.addEventListener("dragstart", (ev) => {
                    ev.dataTransfer.setData("text/plain", slot.dataset.index);
                    slot.style.opacity = "0.5";
                });

                img.addEventListener("dragend", () => {
                    slot.style.opacity = "1";
                });

                // Adiciona evento de drop no slot para reordenar
                slot.addEventListener("dragover", (ev) => {
                    ev.preventDefault();
                    const draggedIndex = ev.dataTransfer.getData("text/plain");
                    if (draggedIndex && draggedIndex !== slot.dataset.index) {
                        slot.style.borderColor = "var(--primary)";
                        slot.style.transform = "scale(1.05)";
                    }
                });

                slot.addEventListener("dragleave", () => {
                    slot.style.borderColor = "";
                    slot.style.transform = "";
                });

                slot.addEventListener("drop", (ev) => {
                    ev.preventDefault();
                    slot.style.borderColor = "";
                    slot.style.transform = "";

                    const draggedIndex = ev.dataTransfer.getData("text/plain");
                    const targetIndex = slot.dataset.index;

                    if (draggedIndex !== targetIndex) {
                        reordenarImagens(draggedIndex, targetIndex);
                    }
                });

                // Atualiza a principal se a imagem nova estiver no slot 1
                atualizarPrincipalHidden();
                mostrarToast("Imagem carregada com sucesso", "success");
            };
            reader.readAsDataURL(file);
        });
    }

    // Estado da ordem visual dos slots. Cada entrada é:
    //   { slotIndex, url }  — url é a URL real (existente) ou "__NOVO__:<índice DOM>"
    //                         para imagens novas (data URL) ainda sem URL no servidor.
    // IMPORTANTE: o índice DOM do input (posição no array name="imagens[]") é o que
    // o backend recebe em $_FILES["imagens"], então o marcador usa esse índice,
    // NÃO o data-index (que é trocado na reordenação).
    let ordemSlots = [];

    function coletarOrdemSlots() {
        ordemSlots = [];
        // Ordena os slots pela ordem visual (data-index), que é a ordem
        // que o admin vê e que deve ser gravada em produto_imagens.ordem.
        const slotsOrdenados = Array.from(imageUploaders)
            .filter((slot) => slot.querySelector("img")) // só slots com imagem
            .sort((a, b) => parseInt(a.dataset.index) - parseInt(b.dataset.index));

        slotsOrdenados.forEach((slot) => {
            const input = slot.querySelector('input[type="file"]');
            const hidden = slot.querySelector('input[name="imagem_existente[]"]');

            if (hidden && hidden.value) {
                // Imagem existente → URL real
                ordemSlots.push({ slotIndex: slot.dataset.index, url: hidden.value });
            } else if (input && input.files && input.files.length > 0) {
                // Imagem nova (data URL) → marcador __NOVO__:<índice DOM do input>
                // O índice DOM (posição no array name="imagens[]") é o que o
                // backend recebe em $_FILES["imagens"].
                const inputsImagens = Array.from(document.querySelectorAll('input[name="imagens[]"]'));
                const indiceDom = inputsImagens.indexOf(input);
                if (indiceDom >= 0) {
                    ordemSlots.push({ slotIndex: slot.dataset.index, url: "__NOVO__:" + indiceDom });
                }
            }
        });
    }

    function reordenarImagens(fromIndex, toIndex) {
        const slots = Array.from(imageUploaders);
        const draggedSlot = slots.find((s) => s.dataset.index === fromIndex);
        const targetSlot = slots.find((s) => s.dataset.index === toIndex);

        if (!draggedSlot || !targetSlot) return;

        // Troca os índices dos slots (dataset) — a ordem visual é definida
        // pelos índices, não pelos valores dos file inputs (que são read-only).
        draggedSlot.dataset.index = toIndex;
        targetSlot.dataset.index = fromIndex;

        // Atualiza os IDs dos slots
        draggedSlot.id = "imagem-slot-" + (parseInt(toIndex) + 1);
        targetSlot.id = "imagem-slot-" + (parseInt(fromIndex) + 1);

        // Atualiza os data-index dos inputs de arquivo
        const draggedInput = draggedSlot.querySelector('input[type="file"]');
        const targetInput = targetSlot.querySelector('input[type="file"]');
        if (draggedInput) draggedInput.dataset.index = toIndex;
        if (targetInput) targetInput.dataset.index = fromIndex;

        // Troca as imagens (visual)
        const draggedImg = draggedSlot.querySelector("img");
        const targetImg = targetSlot.querySelector("img");

        if (draggedImg && targetImg) {
            const draggedSrc = draggedImg.src;
            const targetSrc = targetImg.src;

            draggedImg.src = targetSrc;
            targetImg.src = draggedSrc;

            // Atualiza data-url
            const draggedUrl = draggedImg.dataset.url;
            const targetUrl = targetImg.dataset.url;
            draggedImg.dataset.url = targetUrl;
            targetImg.dataset.url = draggedUrl;
        }

        // Troca os valores dos hidden (URLs existentes)
        const draggedHidden = draggedSlot.querySelector('input[name="imagem_existente[]"]');
        const targetHidden = targetSlot.querySelector('input[name="imagem_existente[]"]');
        if (draggedHidden && targetHidden) {
            const tempVal = draggedHidden.value;
            draggedHidden.value = targetHidden.value;
            targetHidden.value = tempVal;
        }

        coletarOrdemSlots();
        atualizarPrincipalHidden();
        mostrarToast("Imagens reordenadas", "success");
    }

    imageUploaders.forEach((item) => {
        const input = item.querySelector('input[type="file"]');
        const removeBtn = item.querySelector(".remove-image");

        if (input) {
            input.addEventListener("change", function () {
                const file = this.files[0];
                if (!file) return;

                if (!file.type.startsWith("image/")) {
                    mostrarToast("Por favor, selecione um arquivo de imagem", "error");
                    this.value = "";
                    return;
                }

                if (file.size > 5 * 1024 * 1024) {
                    mostrarToast("Imagem muito grande. Máximo 5MB.", "error");
                    this.value = "";
                    return;
                }

                const progressDiv = item.querySelector(".upload-progress");
                const progressBar = item.querySelector(".upload-progress-bar");

                if (progressDiv && progressBar) {
                    progressDiv.style.display = "block";
                    progressBar.style.width = "0%";
                }

                const reader = new FileReader();
                reader.onprogress = (e) => {
                    if (e.lengthComputable && progressBar) {
                        const percent = (e.loaded / e.total) * 100;
                        progressBar.style.width = percent + "%";
                    }
                };

                reader.onload = function (e) {
                    const imgExistente = item.querySelector("img");
                    if (imgExistente) imgExistente.remove();

                    // Se o slot tinha uma imagem existente (hidden), remove o hidden
                    // para que o backend não mantenha a URL antiga (a nova assume o slot).
                    const hiddenExistente = item.querySelector('input[name="imagem_existente[]"]');
                    if (hiddenExistente) hiddenExistente.remove();

                    const img = document.createElement("img");
                    img.src = e.target.result;
                    img.alt = "Preview da imagem";
                    img.draggable = true;
                    item.appendChild(img);
                    item.classList.add("has-image");
                    item.draggable = true;

                    if (progressDiv) {
                        progressDiv.style.display = "none";
                    }

                    // Adiciona eventos de drag para reordenar
                    img.addEventListener("dragstart", (ev) => {
                        ev.dataTransfer.setData("text/plain", item.dataset.index);
                        item.style.opacity = "0.5";
                    });

                    img.addEventListener("dragend", () => {
                        item.style.opacity = "1";
                    });

                    // Adiciona evento de drop no slot para reordenar
                    item.addEventListener("dragover", (ev) => {
                        ev.preventDefault();
                        const draggedIndex = ev.dataTransfer.getData("text/plain");
                        if (draggedIndex && draggedIndex !== item.dataset.index) {
                            item.style.borderColor = "var(--primary)";
                            item.style.transform = "scale(1.05)";
                        }
                    });

                    item.addEventListener("dragleave", () => {
                        item.style.borderColor = "";
                        item.style.transform = "";
                    });

                    item.addEventListener("drop", (ev) => {
                        ev.preventDefault();
                        item.style.borderColor = "";
                        item.style.transform = "";

                        const draggedIndex = ev.dataTransfer.getData("text/plain");
                        const targetIndex = item.dataset.index;

                        if (draggedIndex && draggedIndex !== targetIndex) {
                            reordenarImagens(draggedIndex, targetIndex);
                        }
                    });

                    atualizarPrincipalHidden();
                    mostrarToast("Imagem carregada com sucesso", "success");
                };
                reader.readAsDataURL(file);
            });
        }

        if (removeBtn) {
            removeBtn.addEventListener("click", function (e) {
                e.preventDefault();
                e.stopPropagation();

                const img = item.querySelector("img");
                if (img) img.remove();
                if (input) input.value = "";
                // Remove também o hidden da imagem existente (para o backend saber que foi removida)
                const hidden = item.querySelector('input[name="imagem_existente[]"]');
                if (hidden) hidden.remove();
                item.classList.remove("has-image");
                item.draggable = false;
                atualizarPrincipalHidden();
                mostrarToast("Imagem removida", "success");
            });
        }
    });

    const tagsInputs = document.querySelectorAll(".tags-input");

    tagsInputs.forEach((container) => {
        const input = container.querySelector("input");
        const hiddenInput = container.querySelector('input[type="hidden"]');
        
        const tagsIniciais = (container.dataset.tags || "")
            .split(",")
            .map((t) => t.trim())
            .filter((t) => t.length > 0);
        const tags = [...tagsIniciais];
        renderTags();

        function renderTags() {
            container.querySelectorAll(".tag").forEach((t) => t.remove());

            tags.forEach((tag, index) => {
                const tagEl = document.createElement("span");
                tagEl.className = "tag";
                tagEl.innerHTML = tag + ' <span class="tag-remove" data-index="' + index + '">×</span>';
                container.insertBefore(tagEl, input);
            });

            if (hiddenInput) {
                hiddenInput.value = tags.join(",");
            }
        }

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

        container.addEventListener("click", function (e) {
            if (e.target.classList.contains("tag-remove")) {
                const index = parseInt(e.target.dataset.index);
                tags.splice(index, 1);
                renderTags();
            }
        });

        container.addEventListener("click", function (e) {
            if (e.target === container) {
                input.focus();
            }
        });
    });

    const categoriaSelect = document.getElementById("categoria");
    const skuInput = document.getElementById("produto-sku");
    const skuSugerir = document.getElementById("sku-sugerir");
    let skuManual = false;

    function gerarSugestaoSKU() {
        if (!skuInput || !nomeInput) return;

        const categoria = categoriaSelect ? categoriaSelect.value : "";
        const catIniciais = categoria
            .split(" ")
            .map((p) => p[0])
            .join("")
            .toUpperCase()
            .slice(0, 3);

        const nome = nomeInput.value || "PROD";
        const nomeIniciais = nome
            .split(" ")
            .map((p) => p[0])
            .join("")
            .toUpperCase()
            .replace(/[^A-Z]/g, "")
            .slice(0, 3);

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
            case "produto-peso":
            case "produto-comprimento":
            case "produto-largura":
            case "produto-altura":
                valido = parseInt(valor) > 0;
                mensagem = "Este campo deve ser um número positivo.";
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
        "#produto-nome, #produto-sku, #produto-ean, #produto-ncm, #produto-preco, #produto-estoque, #produto-slug, #produto-peso, #produto-comprimento, #produto-largura, #produto-altura"
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

    function atualizarValidacaoAbas() {
        const nomeOk = document.getElementById("produto-nome")?.value.trim().length >= 3;
        const precoOk = parseFloat(document.getElementById("produto-preco")?.value) > 0;
        const estoqueOk = parseInt(document.getElementById("produto-estoque")?.value) >= 0;
        const pesoOk = parseInt(document.getElementById("produto-peso")?.value) > 0;
        const comprimentoOk = parseInt(document.getElementById("produto-comprimento")?.value) > 0;
        const larguraOk = parseInt(document.getElementById("produto-largura")?.value) > 0;
        const alturaOk = parseInt(document.getElementById("produto-altura")?.value) > 0;
        const tab1Ok = nomeOk && precoOk && estoqueOk && pesoOk && comprimentoOk && larguraOk && alturaOk;

        const slugOk = /^[a-z0-9-]+$/.test(document.getElementById("produto-slug")?.value || "");
        const tab2Ok = slugOk;

        // Aba 3: Identificação — SKU e EAN válidos
        const skuOk = document.getElementById("produto-sku")?.value.trim().length >= 5;
        const eanOk = validarEAN(document.getElementById("produto-ean")?.value || "");
        const tab3Ok = skuOk && eanOk;

        const tab1 = document.querySelector('.tab-btn[data-tab="basicos"]');
        const tab2 = document.querySelector('.tab-btn[data-tab="seo"]');
        const tab3 = document.querySelector('.tab-btn[data-tab="identificacao"]');

        if (tab1) tab1.classList.toggle("is-valid", tab1Ok);
        if (tab2) tab2.classList.toggle("is-valid", tab2Ok);
        if (tab3) tab3.classList.toggle("is-valid", tab3Ok);

        // Atualiza barra de progresso
        atualizarProgresso();
    }

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
            document.getElementById("produto-peso"),
            document.getElementById("produto-comprimento"),
            document.getElementById("produto-largura"),
            document.getElementById("produto-altura"),
        ];

        const preenchidos = campos.filter((c) => c && c.value.trim().length > 0).length;
        const total = campos.length;
        const pct = Math.round((preenchidos / total) * 100);

        barra.style.width = pct + "%";
        percentual.textContent = pct + "%";
    }

    const faqItems = document.querySelectorAll(".faq-item");

    faqItems.forEach((item) => {
        const header = item.querySelector(".faq-item-header");
        if (header) {
            header.addEventListener("click", function () {
                item.classList.toggle("open");
            });
        }
    });

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

    window.mostrarToast = mostrarToast;

    const form = document.getElementById("form-produto");

    if (form) {
        // Adiciona o token CSRF ao FormData antes do envio
        const csrfToken = document.getElementById("csrf-token")?.value || "";

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
                "produto-peso",
                "produto-comprimento",
                "produto-largura",
                "produto-altura",
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
            if (csrfToken) {
                formData.append("csrf_token", csrfToken);
            }

            // Envia a ordem visual dos slots (PASSO 5)
            coletarOrdemSlots();
            ordemSlots.forEach((item) => {
                formData.append("imagem_ordem[]", item.url);
            });

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
                    btnSalvar.textContent = "Salvar Produto";
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
                    btnSalvar.textContent = "Salvar Produto";
                }
                mostrarToast("Erro de conexão ao salvar produto", "error");
            });
        });
    }

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
        const csrfToken = document.getElementById("csrf-token")?.value || "";
        return fetch("../../api/admin/responder_ticket.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-Token": csrfToken,
            },
            body: JSON.stringify({
                ticket_id: ticketId,
                mensagem: mensagem,
                csrf_token: csrfToken,
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