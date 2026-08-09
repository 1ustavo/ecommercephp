/* ============================================================
   ADMIN-RELATORIOS.JS
   Seletor de mês + fetch AJAX para atualizar relatórios sem
   recarregar a página.
   ============================================================ */

document.addEventListener("DOMContentLoaded", function () {
    const btnAnterior = document.getElementById("btn-mes-anterior");
    const btnProximo = document.getElementById("btn-mes-proximo");
    const btnTotal = document.getElementById("btn-mes-total");
    const mesLabel = document.getElementById("mes-label");
    const relatorioBody = document.getElementById("relatorio-body");
    const loadingEl = document.getElementById("relatorio-loading");

    // Mês atual (YYYY-MM) — vem do data attribute da página
    let mesAtual = document.getElementById("relatorio-container").dataset.mes || new Date().toISOString().slice(0, 7);
    let modoTotal = false;

    // Nomes dos meses em português
    const MESES_PT = [
        "Janeiro", "Fevereiro", "Março", "Abril", "Maio", "Junho",
        "Julho", "Agosto", "Setembro", "Outubro", "Novembro", "Dezembro"
    ];

    function formatarMoeda(valor) {
        return "R$ " + Number(valor).toLocaleString("pt-BR", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function formatarMesLabel(mes) {
        const [ano, mesNum] = mes.split("-").map(Number);
        return MESES_PT[mesNum - 1] + " " + ano;
    }

    function statusLabel(status) {
        const labels = {
            "AGUARDANDO_APROVACAO": "Aguardando Aprovação",
            "APROVADO": "Aprovado",
            "PENDENTE": "Pendente",
            "PAGO": "Pago",
            "ENVIADO": "Enviado",
            "ENTREGUE": "Entregue",
            "CANCELADO": "Cancelado",
            "RECUSADO": "Recusado"
        };
        return labels[status] || status;
    }

    function statusClass(status) {
        const classes = {
            "AGUARDANDO_APROVACAO": "badge-pendente",
            "APROVADO": "badge-pago",
            "PENDENTE": "badge-pendente",
            "PAGO": "badge-pago",
            "ENVIADO": "badge-enviado",
            "ENTREGUE": "badge-entregue",
            "CANCELADO": "badge-cancelado",
            "RECUSADO": "badge-cancelado"
        };
        return classes[status] || "badge-default";
    }

    function mostrarLoading() {
        if (loadingEl) loadingEl.style.display = "block";
        if (relatorioBody) relatorioBody.style.display = "none";
    }

    function esconderLoading() {
        if (loadingEl) loadingEl.style.display = "none";
        if (relatorioBody) relatorioBody.style.display = "block";
    }

    function renderizarRelatorio(dados) {
        if (!relatorioBody) return;

        const statusKeys = Object.keys(dados.status || {});

        // ============================================================
        // CARDS PRINCIPAIS — Números grandes em destaque
        // ============================================================
        const cards = [
            { label: "Total de Pedidos", valor: String(dados.total_pedidos), destaque: true },
            { label: "Faturamento Bruto", valor: formatarMoeda(dados.faturamento_bruto), destaque: false },
            { label: "Faturamento Líquido", valor: formatarMoeda(dados.faturamento_liquido), destaque: true },
            { label: "Imposto Total", valor: formatarMoeda(dados.imposto_total), destaque: false },
            { label: "Descontos", valor: formatarMoeda(dados.desconto_total), destaque: false },
            { label: "Frete Total", valor: formatarMoeda(dados.frete_total), destaque: false }
        ];

        let html = '<div class="relatorio-cards">';
        cards.forEach(function (card) {
            html += '<div class="relatorio-card' + (card.destaque ? " relatorio-card-destaque" : "") + '">' +
                '<div class="relatorio-card-value">' + card.valor + '</div>' +
                '<div class="relatorio-card-label">' + card.label + '</div>' +
                '</div>';
        });
        html += '</div>';

        // ============================================================
        // TABELA DE STATUS — Distribuição por status
        // ============================================================
        html += '<div class="relatorio-status-section">';
        html += '<div class="relatorio-section-header">' +
            '<h2>Distribuição por Status</h2>' +
            '<span class="relatorio-section-count">' + statusKeys.length + ' status</span>' +
            '</div>';

        if (statusKeys.length === 0) {
            html += '<div class="empty-state">' +
                '<h3>Nenhum pedido neste período</h3>' +
                '<p>Os pedidos aparecerão aqui quando houver vendas.</p>' +
                '</div>';
        } else {
            html += '<div class="table-wrap">';
            html += '<table>';
            html += '<thead><tr><th>Status</th><th>Quantidade</th><th>Valor Total</th><th>% do Total</th></tr></thead>';
            html += '<tbody>';

            statusKeys.forEach(function (status) {
                const item = dados.status[status];
                const pct = dados.total_pedidos > 0
                    ? ((item.quantidade / dados.total_pedidos) * 100).toFixed(1).replace(".", ",") + "%"
                    : "—";

                html += '<tr>' +
                    '<td><span class="badge ' + statusClass(status) + '">' + statusLabel(status) + '</span></td>' +
                    '<td><strong>' + item.quantidade + '</strong></td>' +
                    '<td>' + formatarMoeda(item.valor_total) + '</td>' +
                    '<td>' + pct + '</td>' +
                    '</tr>';
            });

            html += '</tbody></table></div>';
        }
        html += '</div>';

        // ============================================================
        // RESUMO FINANCEIRO — Detalhamento de valores
        // ============================================================
        html += '<div class="relatorio-resumo">';
        html += '<div class="relatorio-section-header">' +
            '<h2>Resumo Financeiro</h2>' +
            '</div>';
        html += '<div class="relatorio-resumo-grid">';

        const resumoItens = [
            { label: "Subtotal + Frete (Bruto)", valor: formatarMoeda(dados.faturamento_bruto) },
            { label: "Impostos", valor: formatarMoeda(dados.imposto_total) },
            { label: "Descontos", valor: formatarMoeda(dados.desconto_total) },
            { label: "Frete", valor: formatarMoeda(dados.frete_total) },
            { label: "Faturamento Líquido", valor: formatarMoeda(dados.faturamento_liquido), destaque: true }
        ];

        resumoItens.forEach(function (item) {
            html += '<div class="relatorio-resumo-item' + (item.destaque ? " relatorio-resumo-total" : "") + '">' +
                '<span class="relatorio-resumo-label">' + item.label + '</span>' +
                '<span class="relatorio-resumo-valor">' + item.valor + '</span>' +
                '</div>';
        });

        html += '</div></div>';

        relatorioBody.innerHTML = html;
    }

    function carregarRelatorio() {
        mostrarLoading();

        const url = modoTotal
            ? "../../api/admin/relatorios.php?mes=total"
            : "../../api/admin/relatorios.php?mes=" + mesAtual;

        fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                esconderLoading();
                if (data.sucesso) {
                    renderizarRelatorio(data.dados);
                    // Atualiza label
                    if (mesLabel) {
                        mesLabel.textContent = modoTotal ? "Total Geral" : formatarMesLabel(mesAtual);
                    }
                    // Atualiza estado dos botões
                    if (btnAnterior) btnAnterior.disabled = modoTotal;
                    if (btnProximo) btnProximo.disabled = modoTotal;
                    if (btnTotal) btnTotal.classList.toggle("active", modoTotal);
                } else {
                    if (relatorioBody) {
                        relatorioBody.innerHTML = '<div class="empty-state"><h3>Erro ao carregar relatório</h3><p>' + (data.erro || "Tente novamente.") + '</p></div>';
                    }
                }
            })
            .catch(function () {
                esconderLoading();
                if (relatorioBody) {
                    relatorioBody.innerHTML = '<div class="empty-state"><h3>Erro de conexão</h3><p>Não foi possível carregar os dados do relatório.</p></div>';
                }
            });
    }

    // Navegação de mês
    if (btnAnterior) {
        btnAnterior.addEventListener("click", function () {
            if (modoTotal) return;
            const [ano, mes] = mesAtual.split("-").map(Number);
            const novaData = new Date(ano, mes - 2, 1); // mês anterior
            mesAtual = novaData.getFullYear() + "-" + String(novaData.getMonth() + 1).padStart(2, "0");
            carregarRelatorio();
        });
    }

    if (btnProximo) {
        btnProximo.addEventListener("click", function () {
            if (modoTotal) return;
            const [ano, mes] = mesAtual.split("-").map(Number);
            const novaData = new Date(ano, mes, 1); // próximo mês
            mesAtual = novaData.getFullYear() + "-" + String(novaData.getMonth() + 1).padStart(2, "0");
            carregarRelatorio();
        });
    }

    if (btnTotal) {
        btnTotal.addEventListener("click", function () {
            modoTotal = !modoTotal;
            carregarRelatorio();
        });
    }

    // Carrega relatório inicial
    carregarRelatorio();
});