<?php

/**
 * Serviço de autenticação na API dos Correios (token de acesso).
 *
 * Obtém e cacheia o token usado nas chamadas de frete (preço/prazo).
 * - Ambiente homologacao  → https://apihom.correios.com.br
 * - Ambiente producao     → https://api.correios.com.br
 *
 * O token é cacheado (Cache.php) com TTL baseado no campo "expiraEm"
 * retornado pela API, com margem de segurança de 5 minutos. Um lock de
 * arquivo (flock) garante que apenas UMA requisição gera o token por vez,
 * evitando estourar o limite de 3 req/s da API.
 */
class CorreiosToken
{
    private static ?string $ambiente = null;
    private static ?string $usuario = null;
    private static ?string $senha = null;
    private static ?string $cartaoPostagem = null;
    private static ?string $cepOrigem = null;

    private const CACHE_KEY = "correios_token";
    private const MARGEM_SEGUNDOS = 300; // 5 minutos antes de expirar
    private const TTL_PADRAO = 300;      // fallback se não houver expiraEm

    public static function init(): void
    {
        self::$ambiente = strtolower(trim($_ENV["CORREIOS_AMBIENTE"] ?? getenv("CORREIOS_AMBIENTE") ?: "homologacao"));
        self::$usuario = $_ENV["CORREIOS_USUARIO"] ?? getenv("CORREIOS_USUARIO") ?: null;
        self::$senha = $_ENV["CORREIOS_SENHA"] ?? getenv("CORREIOS_SENHA") ?: null;
        self::$cartaoPostagem = $_ENV["CORREIOS_CARTAO_POSTAGEM"] ?? getenv("CORREIOS_CARTAO_POSTAGEM") ?: null;
        self::$cepOrigem = $_ENV["CORREIOS_CEP_ORIGEM"] ?? getenv("CORREIOS_CEP_ORIGEM") ?: null;
    }

    /**
     * Base URL da API conforme o ambiente configurado.
     */
    public static function baseUrl(): string
    {
        return self::$ambiente === "producao"
            ? "https://api.correios.com.br"
            : "https://apihom.correios.com.br";
    }

    /**
     * CEP de origem (de onde a loja despacha). Somente números.
     */
    public static function cepOrigem(): ?string
    {
        return self::$cepOrigem;
    }

    /**
     * Retorna um token válido, gerando um novo se necessário.
     *
     * Fluxo:
     * 1. Verifica o cache — se houver token válido (com margem de 5 min), retorna.
     * 2. Adquire um lock de arquivo (flock) para serializar a geração.
     * 3. Re-checa o cache após o lock (outro processo pode ter gerado).
     * 4. Gera um novo token via API e o cacheia.
     */
    public static function obterToken(): ?string
    {
        self::init();

        // 1) Token válido em cache?
        $token = self::tokenDoCache();
        if ($token) {
            return $token;
        }

        // 2) Serializa a geração com lock de arquivo (evita 2 req simultâneas)
        $lockDir = __DIR__ . "/../storage/cache";
        if (!is_dir($lockDir)) {
            @mkdir($lockDir, 0775, true);
        }
        $lockFile = $lockDir . "/correios_token.lock";
        $lock = @fopen($lockFile, "c");

        if ($lock && flock($lock, LOCK_EX)) {
            try {
                // 3) Re-checa o cache após adquirir o lock
                $token = self::tokenDoCache();
                if ($token) {
                    return $token;
                }

                // 4) Gera novo token
                return self::gerarToken();
            } finally {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }

        // Sem lock (falha ao abrir), tenta gerar mesmo assim
        return self::gerarToken();
    }

    /**
     * Lê o token do cache, validando a margem de segurança.
     */
    private static function tokenDoCache(): ?string
    {
        $cacheado = Cache::get(self::CACHE_KEY);
        if (!$cacheado) {
            return null;
        }

        $dados = json_decode($cacheado, true);
        if (!is_array($dados) || empty($dados["token"])) {
            return null;
        }

        // Valida a margem de 5 min antes de expirar (defensivo, pois o TTL
        // do Cache já considera a margem; aqui cobre expiraEm em ms/segundos)
        $expiraEm = (int) ($dados["expiraEm"] ?? 0);
        if ($expiraEm > 0) {
            $expiraSegundos = $expiraEm > 1000000000000 ? (int) floor($expiraEm / 1000) : $expiraEm;
            if ($expiraSegundos - time() <= self::MARGEM_SEGUNDOS) {
                Cache::delete(self::CACHE_KEY);
                return null;
            }
        }

        return $dados["token"];
    }

    /**
     * Chama a API dos Correios para gerar um novo token e o cacheia.
     */
    private static function gerarToken(): ?string
    {
        if (!self::$usuario || !self::$senha || !self::$cartaoPostagem) {
            error_log("CorreiosToken: credenciais não configuradas (CORREIOS_USUARIO/SENHA/CARTAO_POSTAGEM).");
            return null;
        }

        $url = self::baseUrl() . "/token/v1/autentica/cartaopostagem";
        $basic = base64_encode(self::$usuario . ":" . self::$senha);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Basic " . $basic,
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["numero" => self::$cartaoPostagem]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log("CorreiosToken: erro curl ao obter token: " . $curlError);
            return null;
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            error_log("CorreiosToken: HTTP {$httpCode} ao obter token. Resposta: " . mb_substr((string) $response, 0, 500));
            return null;
        }

        $data = json_decode($response, true);
        if (!is_array($data) || empty($data["token"])) {
            error_log("CorreiosToken: resposta sem token. HTTP {$httpCode}.");
            return null;
        }

        // Calcula o TTL do cache a partir do expiraEm (com margem de 5 min)
        $expiraEm = (int) ($data["expiraEm"] ?? 0);
        $ttl = self::TTL_PADRAO;
        if ($expiraEm > 0) {
            // Correios retorna epoch em milissegundos; converte para segundos
            $expiraSegundos = $expiraEm > 1000000000000 ? (int) floor($expiraEm / 1000) : $expiraEm;
            $ttl = $expiraSegundos - time() - self::MARGEM_SEGUNDOS;
            if ($ttl < 60) {
                $ttl = 60;
            }
        }

        Cache::set(self::CACHE_KEY, json_encode([
            "token" => $data["token"],
            "expiraEm" => $expiraEm,
        ]), $ttl);

        return $data["token"];
    }
}