<?php

/**
 * Serviço de cálculo de frete na API dos Correios (preço e prazo nacional).
 *
 * Consulta os serviços SEDEX (03220) e PAC (03298) em um único lote
 * (idLote + parametrosProduto[]) nos endpoints:
 *   - {base}/preco/v1/nacional  → preço (campo pcFinal)
 *   - {base}/prazo/v1/nacional  → prazo (campo prazoEntrega, em dias)
 *
 * REGRA DE DIMENSÕES (documentada):
 *   - Peso total = soma de (peso_gramas × quantidade) de todos os itens.
 *   - Dimensões  = usa a "maior caixa" entre os itens, ou seja, as dimensões
 *     do item com maior volume (comprimento × largura × altura). Isso garante
 *     que a embalagem comporte o maior item individual do pedido.
 *
 * Se a API falhar, der timeout, retornar erro HTTP ou indisponibilidade
 * (campo txErro preenchido), registra com error_log() e retorna os valores
 * simulados fixos que hoje existem no checkout.php (fallback) — o checkout
 * nunca quebra se os Correios estiverem fora do ar.
 */
class CorreiosFrete
{
    // Serviços consultados no mesmo lote (código => nome)
    private const SERVICOS = [
        "03220" => "SEDEX",
        "03298" => "PAC",
    ];

    // Fallback: valores simulados fixos do checkout.php (PAC R$22,50 / SEDEX R$35,90)
    private const FALLBACK_PRECOS = [
        "PAC" => 22.50,
        "SEDEX" => 35.90,
    ];

    // Fallback: prazos estimados fixos (dias) caso a API de prazo falhe
    private const FALLBACK_PRAZOS = [
        "PAC" => 8,
        "SEDEX" => 2,
    ];

    // Defaults de peso/dimensões caso o produto não tenha os campos
    private const PESO_DEFAULT = 300;
    private const COMPRIMENTO_DEFAULT = 16;
    private const LARGURA_DEFAULT = 11;
    private const ALTURA_DEFAULT = 2;

    // TTL do cache de frete (segundos) — evita recalcular a cada re-render
    private const CACHE_TTL = 300; // 5 minutos

    /**
     * Calcula preço + prazo dos serviços de frete para um CEP de destino.
     *
     * Junta o resultado de calcularPrecos() e calcularPrazos() em uma única
     * lista, mesclando por serviço (SEDEX e PAC).
     *
     * @param string $cepDestino CEP de entrega (com ou sem máscara)
     * @param array  $itens      Itens do carrinho (Carrinho::itens() ou similar)
     * @return array Lista de ["servico" => string, "prazo" => ?int, "valor" => float]
     */
    public static function calcularFrete(string $cepDestino, array $itens): array
    {
        // Cache por CEP + carrinho (produto_id + quantidade) para não
        // recalcular a cada re-render da página.
        $cepLimpo = preg_replace("/\D/", "", $cepDestino);
        $hashItens = self::hashItens($itens);
        $cacheKey = "correios_frete_{$cepLimpo}_{$hashItens}";

        $cacheado = Cache::get($cacheKey);
        if ($cacheado) {
            $dados = json_decode($cacheado, true);
            if (is_array($dados)) {
                return $dados;
            }
        }

        $precos = self::calcularPrecos($cepDestino, $itens);
        $prazos = self::calcularPrazos($cepDestino);

        // Indexa prazos por serviço para mesclar
        $prazosPorServico = [];
        foreach ($prazos as $p) {
            $prazosPorServico[$p["servico"]] = $p["prazo"];
        }

        $resultado = [];
        foreach ($precos as $preco) {
            $servico = $preco["servico"];
            $resultado[] = [
                "servico" => $servico,
                "prazo" => $prazosPorServico[$servico] ?? null,
                "valor" => $preco["valor"],
            ];
        }

        Cache::set($cacheKey, json_encode($resultado), self::CACHE_TTL);

        return $resultado;
    }

    /**
     * Gera um hash estável dos itens do carrinho (produto_id + quantidade)
     * para compor a chave de cache do frete.
     */
    private static function hashItens(array $itens): string
    {
        $chave = [];
        foreach ($itens as $item) {
            $chave[] = (int) ($item["produto_id"] ?? 0) . ":" . (int) ($item["quantidade"] ?? 1);
        }
        sort($chave);
        return md5(implode("|", $chave));
    }

    /**
     * Calcula o preço dos serviços de frete para um CEP de destino.
     *
     * @param string $cepDestino CEP de entrega (com ou sem máscara)
     * @param array  $itens      Itens do carrinho (Carrinho::itens() ou similar)
     * @return array Lista de ["servico" => string, "prazo" => ?int, "valor" => float]
     */
    public static function calcularPrecos(string $cepDestino, array $itens): array
    {
        // 1) Autentica na API dos Correios
        $token = CorreiosToken::obterToken();
        if (!$token) {
            error_log("CorreiosFrete: sem token de autenticação, usando fallback de preços.");
            return self::fallbackPrecos();
        }

        // 2) Peso total e dimensões (maior caixa)
        $pesoTotal = 0;
        $maiorVolume = 0;
        $dimensoes = [
            "comprimento" => self::COMPRIMENTO_DEFAULT,
            "largura" => self::LARGURA_DEFAULT,
            "altura" => self::ALTURA_DEFAULT,
        ];

        foreach ($itens as $item) {
            $qtd = max(1, (int) ($item["quantidade"] ?? 1));
            $dados = self::dadosProduto($item);

            $pesoTotal += (int) $dados["peso_gramas"] * $qtd;

            $volume = (int) $dados["comprimento_cm"] * (int) $dados["largura_cm"] * (int) $dados["altura_cm"];
            if ($volume > $maiorVolume) {
                $maiorVolume = $volume;
                $dimensoes = [
                    "comprimento" => (int) $dados["comprimento_cm"],
                    "largura" => (int) $dados["largura_cm"],
                    "altura" => (int) $dados["altura_cm"],
                ];
            }
        }

        // 3) Monta o lote com SEDEX e PAC
        $cepOrigem = preg_replace("/\D/", "", (string) CorreiosToken::cepOrigem());
        $cepDestinoLimpo = preg_replace("/\D/", "", $cepDestino);

        $parametros = [];
        foreach (self::SERVICOS as $coProduto => $nome) {
            $parametros[] = [
                "coProduto" => $coProduto,
                "nuRequisicao" => (string) time(),
                "cepOrigem" => $cepOrigem,
                "cepDestino" => $cepDestinoLimpo,
                "psObjeto" => (string) $pesoTotal,
                "tpObjeto" => "2",
                "comprimento" => (string) $dimensoes["comprimento"],
                "largura" => (string) $dimensoes["largura"],
                "altura" => (string) $dimensoes["altura"],
            ];
        }

        $payload = [
            "idLote" => "LOTE_" . time(),
            "parametrosProduto" => $parametros,
        ];

        // 4) Envia a requisição com o token no header Authorization: Bearer
        $url = CorreiosToken::baseUrl() . "/preco/v1/nacional";
        $response = self::postJson($url, $token, $payload);

        // 5) Falha de rede/timeout/HTTP → fallback
        if ($response === null) {
            error_log("CorreiosFrete: falha ao calcular preços, usando fallback.");
            return self::fallbackPrecos();
        }

        $data = json_decode($response, true);
        if (!is_array($data) || empty($data["parametrosProduto"])) {
            error_log("CorreiosFrete: resposta de preços sem parametrosProduto.");
            return self::fallbackPrecos();
        }

        // 6) Monta o resultado a partir da resposta
        $resultado = [];
        foreach ($data["parametrosProduto"] as $param) {
            $coProduto = (string) ($param["coProduto"] ?? "");
            $nome = self::SERVICOS[$coProduto] ?? $coProduto;

            // Indisponibilidade do serviço (campo txErro preenchido) → fallback
            if (!empty($param["txErro"])) {
                error_log("CorreiosFrete: serviço {$nome} indisponível (preço): " . $param["txErro"]);
                continue;
            }

            $resultado[] = [
                "servico" => $nome,
                "prazo" => null, // prazo preenchido por calcularFrete()
                "valor" => (float) ($param["pcFinal"] ?? 0),
            ];
        }

        // Se nenhum serviço retornou valor, usa fallback
        if (empty($resultado)) {
            error_log("CorreiosFrete: nenhum serviço de preço disponível, usando fallback.");
            return self::fallbackPrecos();
        }

        return $resultado;
    }

    /**
     * Calcula o prazo (em dias) dos serviços de frete para um CEP de destino.
     *
     * @param string $cepDestino CEP de entrega (com ou sem máscara)
     * @return array Lista de ["servico" => string, "prazo" => ?int]
     */
    public static function calcularPrazos(string $cepDestino): array
    {
        // 1) Autentica na API dos Correios
        $token = CorreiosToken::obterToken();
        if (!$token) {
            error_log("CorreiosFrete: sem token de autenticação, usando fallback de prazos.");
            return self::fallbackPrazos();
        }

        // 2) Monta o lote com SEDEX e PAC (prazo não precisa de peso/dimensões)
        $cepOrigem = preg_replace("/\D/", "", (string) CorreiosToken::cepOrigem());
        $cepDestinoLimpo = preg_replace("/\D/", "", $cepDestino);

        $parametros = [];
        foreach (self::SERVICOS as $coProduto => $nome) {
            $parametros[] = [
                "coProduto" => $coProduto,
                "nuRequisicao" => (string) time(),
                "cepOrigem" => $cepOrigem,
                "cepDestino" => $cepDestinoLimpo,
            ];
        }

        $payload = [
            "idLote" => "LOTE_" . time(),
            "parametrosProduto" => $parametros,
        ];

        // 3) Envia a requisição com o token no header Authorization: Bearer
        $url = CorreiosToken::baseUrl() . "/prazo/v1/nacional";
        $response = self::postJson($url, $token, $payload);

        // 4) Falha de rede/timeout/HTTP → fallback
        if ($response === null) {
            error_log("CorreiosFrete: falha ao calcular prazos, usando fallback.");
            return self::fallbackPrazos();
        }

        $data = json_decode($response, true);
        if (!is_array($data) || empty($data["parametrosProduto"])) {
            error_log("CorreiosFrete: resposta de prazos sem parametrosProduto.");
            return self::fallbackPrazos();
        }

        // 5) Monta o resultado a partir da resposta
        $resultado = [];
        foreach ($data["parametrosProduto"] as $param) {
            $coProduto = (string) ($param["coProduto"] ?? "");
            $nome = self::SERVICOS[$coProduto] ?? $coProduto;

            // Indisponibilidade do serviço (campo txErro preenchido) → fallback
            if (!empty($param["txErro"])) {
                error_log("CorreiosFrete: serviço {$nome} indisponível (prazo): " . $param["txErro"]);
                continue;
            }

            $resultado[] = [
                "servico" => $nome,
                "prazo" => isset($param["prazoEntrega"]) ? (int) $param["prazoEntrega"] : null,
            ];
        }

        // Se nenhum serviço retornou prazo, usa fallback
        if (empty($resultado)) {
            error_log("CorreiosFrete: nenhum serviço de prazo disponível, usando fallback.");
            return self::fallbackPrazos();
        }

        return $resultado;
    }

    /**
     * Envia um POST JSON com token Bearer e retorna o corpo da resposta,
     * ou null em caso de erro curl / timeout / HTTP não-2xx.
     */
    private static function postJson(string $url, string $token, array $payload): ?string
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer " . $token,
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log("CorreiosFrete: erro curl em {$url}: " . $curlError);
            return null;
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            error_log("CorreiosFrete: HTTP {$httpCode} em {$url}. Resposta: " . mb_substr((string) $response, 0, 500));
            return null;
        }

        return $response;
    }

    /**
     * Fallback de preços: valores simulados fixos do checkout.php.
     */
    private static function fallbackPrecos(): array
    {
        $resultado = [];
        foreach (self::FALLBACK_PRECOS as $servico => $valor) {
            $resultado[] = [
                "servico" => $servico,
                "prazo" => null,
                "valor" => $valor,
            ];
        }
        return $resultado;
    }

    /**
     * Fallback de prazos: prazos estimados fixos (dias).
     */
    private static function fallbackPrazos(): array
    {
        $resultado = [];
        foreach (self::FALLBACK_PRAZOS as $servico => $prazo) {
            $resultado[] = [
                "servico" => $servico,
                "prazo" => $prazo,
            ];
        }
        return $resultado;
    }

    /**
     * Obtém peso/dimensões de um item.
     *
     * Prioridade:
     * 1. Campos já presentes no item (ex: Carrinho::itens() enriquecido).
     * 2. Busca no banco pelo produto_id.
     * 3. Defaults (300g / 16x11x2cm).
     */
    private static function dadosProduto(array $item): array
    {
        $produtoId = (int) ($item["produto_id"] ?? 0);

        $peso = (int) ($item["peso_gramas"] ?? 0);
        $comp = (int) ($item["comprimento_cm"] ?? 0);
        $larg = (int) ($item["largura_cm"] ?? 0);
        $alt = (int) ($item["altura_cm"] ?? 0);

        if ($peso > 0 && $comp > 0 && $larg > 0 && $alt > 0) {
            return [
                "peso_gramas" => $peso,
                "comprimento_cm" => $comp,
                "largura_cm" => $larg,
                "altura_cm" => $alt,
            ];
        }

        // Busca no banco pelo produto_id
        if ($produtoId > 0) {
            global $pdo;
            $stmt = $pdo->prepare("SELECT peso_gramas, comprimento_cm, largura_cm, altura_cm FROM produtos WHERE id = ?");
            $stmt->execute([$produtoId]);
            $row = $stmt->fetch();
            if ($row) {
                return [
                    "peso_gramas" => (int) ($row["peso_gramas"] ?? self::PESO_DEFAULT),
                    "comprimento_cm" => (int) ($row["comprimento_cm"] ?? self::COMPRIMENTO_DEFAULT),
                    "largura_cm" => (int) ($row["largura_cm"] ?? self::LARGURA_DEFAULT),
                    "altura_cm" => (int) ($row["altura_cm"] ?? self::ALTURA_DEFAULT),
                ];
            }
        }

        // Defaults
        return [
            "peso_gramas" => self::PESO_DEFAULT,
            "comprimento_cm" => self::COMPRIMENTO_DEFAULT,
            "largura_cm" => self::LARGURA_DEFAULT,
            "altura_cm" => self::ALTURA_DEFAULT,
        ];
    }
}