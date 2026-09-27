document.addEventListener("DOMContentLoaded", function () {
    const mainImage = document.getElementById("main-product-image");
    const thumbs = document.querySelectorAll(".product-gallery-thumb");
    const mainImageContainer = document.getElementById("main-image-container");
    const prevBtn = document.getElementById("gallery-prev");
    const nextBtn = document.getElementById("gallery-next");

    // Carrossel de imagens
    if (mainImage && thumbs.length > 0) {
        let currentIndex = 0;

        function mostrarImagem(index) {
            const total = thumbs.length;
            currentIndex = (index + total) % total;

            const thumb = thumbs[currentIndex];
            mainImage.src = thumb.dataset.src;

            thumbs.forEach((t) => t.classList.remove("active"));
            thumb.classList.add("active");
            mainImage.dataset.currentIndex = currentIndex;
        }

        // Clique nas miniaturas
        thumbs.forEach((thumb) => {
            thumb.addEventListener("click", function () {
                mostrarImagem(parseInt(this.dataset.index));
            });
        });

        // Botões prev/next
        if (prevBtn) {
            prevBtn.addEventListener("click", () => mostrarImagem(currentIndex - 1));
        }
        if (nextBtn) {
            nextBtn.addEventListener("click", () => mostrarImagem(currentIndex + 1));
        }

        // Navegação por teclado
        document.addEventListener("keydown", function (e) {
            if (e.key === "ArrowLeft") mostrarImagem(currentIndex - 1);
            if (e.key === "ArrowRight") mostrarImagem(currentIndex + 1);
        });

        // Define índice inicial
        mainImage.dataset.currentIndex = 0;
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
                headers: {
                    "Accept": "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
            })
            .then((response) => response.json())
            .then((data) => {
                if (data.success) {
                    mostrarMensagem("Produto adicionado ao carrinho com sucesso!", "success");
                    btn.textContent = "Adicionado!";
                    setTimeout(() => {
                        btn.textContent = originalText;
                        btn.disabled = false;
                    }, 2000);
                } else {
                    mostrarMensagem(data.message || "Erro ao adicionar produto", "error");
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

            // Atualiza o max do input de quantidade conforme o estoque da variação
            const qtdInput = document.querySelector('input[name="quantidade"]');
            if (qtdInput) {
                qtdInput.max = estoque;
                qtdInput.dataset.estoque = estoque;
                if (parseInt(qtdInput.value) > estoque) {
                    qtdInput.value = estoque > 0 ? estoque : 1;
                }
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