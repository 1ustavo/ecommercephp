document.addEventListener("DOMContentLoaded", function () {
    const mainImage = document.getElementById("main-product-image");
    const thumbs = document.querySelectorAll(".product-gallery-thumb");
    const mainImageContainer = document.getElementById("main-image-container");

    // Galeria de miniaturas
    if (thumbs.length > 0 && mainImage) {
        thumbs.forEach((thumb) => {
            thumb.addEventListener("click", function () {
                const src = this.dataset.src;
                const index = parseInt(this.dataset.index);

                mainImage.src = src;

                thumbs.forEach((t) => t.classList.remove("active"));
                this.classList.add("active");
                mainImage.dataset.currentIndex = index;
            });
        });

        // Define índice inicial
        mainImage.dataset.currentIndex = 0;
    }

    // Lightbox
    if (mainImageContainer && mainImage) {
        mainImageContainer.addEventListener("click", function () {
            const images = JSON.parse(mainImage.dataset.images || "[]");
            if (images.length === 0) return;

            const currentIndex = parseInt(mainImage.dataset.currentIndex || 0);
            abrirLightbox(images, currentIndex);
        });
    }

    function abrirLightbox(images, currentIndex) {
        // Cria lightbox
        const lightbox = document.createElement("div");
        lightbox.className = "lightbox";
        lightbox.id = "lightbox";

        // Imagem atual
        const img = document.createElement("img");
        img.src = images[currentIndex];
        img.alt = "Imagem ampliada";
        lightbox.appendChild(img);

        // Botão fechar
        const closeBtn = document.createElement("button");
        closeBtn.className = "lightbox-close";
        closeBtn.innerHTML = "×";
        closeBtn.setAttribute("aria-label", "Fechar lightbox");
        lightbox.appendChild(closeBtn);

        // Navegação anterior
        if (images.length > 1) {
            const prevBtn = document.createElement("button");
            prevBtn.className = "lightbox-nav lightbox-prev";
            prevBtn.innerHTML = "‹";
            prevBtn.setAttribute("aria-label", "Imagem anterior");
            lightbox.appendChild(prevBtn);

            const nextBtn = document.createElement("button");
            nextBtn.className = "lightbox-nav lightbox-next";
            nextBtn.innerHTML = "›";
            nextBtn.setAttribute("aria-label", "Próxima imagem");
            lightbox.appendChild(nextBtn);

            let index = currentIndex;

            prevBtn.addEventListener("click", (e) => {
                e.stopPropagation();
                index = (index - 1 + images.length) % images.length;
                img.src = images[index];
            });

            nextBtn.addEventListener("click", (e) => {
                e.stopPropagation();
                index = (index + 1) % images.length;
                img.src = images[index];
            });

            // Navegação por teclado
            document.addEventListener("keydown", function keyHandler(e) {
                if (!document.getElementById("lightbox")) return;

                if (e.key === "ArrowLeft") {
                    index = (index - 1 + images.length) % images.length;
                    img.src = images[index];
                } else if (e.key === "ArrowRight") {
                    index = (index + 1) % images.length;
                    img.src = images[index];
                } else if (e.key === "Escape") {
                    fecharLightbox();
                }
            });
        }

        // Fechar lightbox
        closeBtn.addEventListener("click", fecharLightbox);
        lightbox.addEventListener("click", function (e) {
            if (e.target === lightbox) {
                fecharLightbox();
            }
        });

        document.body.appendChild(lightbox);
        document.body.style.overflow = "hidden";

        function fecharLightbox() {
            lightbox.remove();
            document.body.style.overflow = "";
        }
    }

    // Formulário de adicionar ao carrinho
    const formAddCart = document.getElementById("form-add-cart");
    if (formAddCart) {
        formAddCart.addEventListener("submit", function (e) {
            e.preventDefault();

            const btn = this.querySelector('button[type="submit"]');
            const originalText = btn.textContent;
            btn.disabled = true;
            btn.textContent = "Adicionando...";

            const formData = new FormData(this);

            fetch(this.action, {
                method: "POST",
                body: formData,
            })
            .then((response) => response.json())
            .then((data) => {
                if (data.sucesso) {
                    mostrarMensagem("Produto adicionado ao carrinho com sucesso!", "success");
                    btn.textContent = "Adicionado!";
                    setTimeout(() => {
                        btn.textContent = originalText;
                        btn.disabled = false;
                    }, 2000);
                } else {
                    mostrarMensagem(data.erro || "Erro ao adicionar produto", "error");
                    btn.textContent = originalText;
                    btn.disabled = false;
                }
            })
            .catch(() => {
                mostrarMensagem("Erro de conexão. Tente novamente.", "error");
                btn.textContent = originalText;
                btn.disabled = false;
            });
        });
    }

    function mostrarMensagem(mensagem, tipo = "success") {
        // Remove mensagens existentes
        document.querySelectorAll(".alert").forEach((a) => a.remove());

        const alert = document.createElement("div");
        alert.className = `alert alert-${tipo}`;
        alert.textContent = mensagem;
        alert.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 14px 20px;
            background: ${tipo === "success" ? "#d1fae5" : "#fee2e2"};
            color: ${tipo === "success" ? "#065f46" : "#991b1b"};
            border-radius: 8px;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
            font-size: 14px;
            font-weight: 500;
            z-index: 9999;
            animation: slideIn 0.3s ease;
        `;

        document.body.appendChild(alert);

        setTimeout(() => {
            alert.style.animation = "slideOut 0.3s ease";
            setTimeout(() => alert.remove(), 300);
        }, 3000);
    }

    // Variações de produto
    const variacaoBotoes = document.querySelectorAll(".variation-btn");
    const variacaoIdInput = document.getElementById("variacao_id");
    const precoDisplay = document.getElementById("preco-display");
    const estoqueDisplay = document.getElementById("estoque-display");
    const precoBase = parseFloat(document.querySelector('[data-produto-id]')?.dataset.preco || 0);

    variacaoBotoes.forEach((btn) => {
        btn.addEventListener("click", function () {
            // Atualiza classe selected
            variacaoBotoes.forEach((b) => b.classList.remove("selected"));
            this.classList.add("selected");

            // Atualiza input hidden
            const variacaoId = this.dataset.id;
            if (variacaoIdInput) {
                variacaoIdInput.value = variacaoId;
            }

            // Atualiza preço e estoque
            const precoAdicional = parseFloat(this.dataset.precoAd || 0);
            const estoque = parseInt(this.dataset.estoque || 0);

            if (precoDisplay) {
                const novoPreco = precoBase + precoAdicional;
                precoDisplay.textContent = formatarMoeda(novoPreco);
            }

            if (estoqueDisplay) {
                estoqueDisplay.textContent = estoque;
            }
        });
    });

    function formatarMoeda(valor) {
        return "R$ " + valor.toFixed(2).replace(".", ",");
    }
});

// Adiciona animações CSS
const style = document.createElement("style");
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);