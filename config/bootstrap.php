<?php

// Load Composer autoloader if available (for Stripe, PHPMailer, etc.)
$composerAutoload = __DIR__ . "/../vendor/autoload.php";
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

require_once __DIR__ . "/env.php";
loadEnv(__DIR__ . "/../.env");

// ============================================================
// CONFIGURAÇÃO DE ERRORS — controlada por APP_ENV, NÃO pelo php.ini
// ============================================================
// APP_ENV=production (ou não definido) → não exibe erros na tela
// APP_ENV=development                → exibe erros para facilitar debug
$appEnv = strtolower(trim($_ENV["APP_ENV"] ?? "production"));

if ($appEnv === "development") {
    // Dev: exibe todos os erros na tela
    ini_set("display_errors", "1");
    error_reporting(E_ALL);
} else {
    // Produção (padrão): loga todos os erros, mas NUNCA exibe na tela
    ini_set("display_errors", "0");
    ini_set("log_errors", "1");
    error_reporting(E_ALL);
}

// Garantir que o conteúdo HTML seja servido como UTF-8 para evitar problemas de
// encoding (caracteres como ç aparecendo como Ã§) quando o servidor não definir
// explicitamente o charset.
ini_set('default_charset', 'UTF-8');
header('Content-Type: text/html; charset=utf-8');

/**
 * Detecta HTTPS mesmo atrás de proxy/load balancer com TLS termination
 * (nginx, ALB, Cloudflare etc.), onde o $_SERVER["HTTPS"] não é populado
 * pelo servidor web local.
 */
function isHttpsRequest(): bool
{
    if (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") {
        return true;
    }

    // Proxy/LB encaminha o scheme original da requisição do cliente
    $proto = strtolower($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "");
    if ($proto !== "" && str_contains($proto, "https")) {
        return true;
    }

    if (($_SERVER["HTTP_X_FORWARDED_SSL"] ?? "") === "on") {
        return true;
    }

    return false;
}

// ============================================================
// SESSÃO SEGURA — configurações antes de iniciar a sessão
// ============================================================
// Cookie HttpOnly (impede acesso via JS)
ini_set("session.cookie_httponly", "1");

// SameSite=Strict (impede envio do cookie em requests cross-site)
ini_set("session.cookie_samesite", "Strict");

// Secure somente se HTTPS (produção, inclusive atrás de proxy/LB)
if (isHttpsRequest()) {
    ini_set("session.cookie_secure", "1");
}

// Tempo de expiração da sessão (30 min)
ini_set("session.gc_maxlifetime", "1800");
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        "lifetime" => 0, // até fechar o navegador
        "path" => "/",
        "httponly" => true,
        "samesite" => "Strict",
        "secure" => isHttpsRequest(),
    ]);
}

// ============================================================
// HEADERS DE SEGURANÇA
// ============================================================
// Previne MIME sniffing
header("X-Content-Type-Options: nosniff");

// Previne clickjacking
header("X-Frame-Options: SAMEORIGIN");

// Proteção contra XSS em navegadores antigos
header("X-XSS-Protection: 1; mode=block");

// Referrer Policy — não envia referrer completo entre origens
header("Referrer-Policy: strict-origin-when-cross-origin");

// Permissions Policy — desativa recursos desnecessários
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

// CSP básico (ajuste conforme necessário)
$appUrl = $_ENV["APP_URL"] ?? "";
$csp = "default-src 'self'; "
    . "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://js.stripe.com; "
    . "style-src 'self' 'unsafe-inline'; "
    . "img-src 'self' data: blob:; "
    . "font-src 'self' data:; "
    . "connect-src 'self' https://api.stripe.com; "
    . "frame-src https://js.stripe.com https://hooks.stripe.com; "
    . "object-src 'none'; "
    . "base-uri 'self'; "
    . "form-action 'self'";
header("Content-Security-Policy: " . $csp);

require_once __DIR__ . "/database.php";
require_once __DIR__ . "/../src/helpers.php";
require_once __DIR__ . "/../src/Usuario.php";
require_once __DIR__ . "/../src/Auth.php";
require_once __DIR__ . "/../src/Categoria.php";
require_once __DIR__ . "/../src/Cupom.php";
require_once __DIR__ . "/../src/Produto.php";
require_once __DIR__ . "/../src/Carrinho.php";
require_once __DIR__ . "/../src/Pedido.php";
require_once __DIR__ . "/../src/Reembolso.php";
require_once __DIR__ . "/../src/Endereco.php";
require_once __DIR__ . "/../src/Notificacao.php";
require_once __DIR__ . "/../src/Cache.php";
require_once __DIR__ . "/../src/Queue.php";
require_once __DIR__ . "/../src/GatewayPagamento.php";
require_once __DIR__ . "/../src/CorreiosToken.php";
require_once __DIR__ . "/../src/CorreiosFrete.php";
require_once __DIR__ . "/../src/Ticket.php";

Cache::init();
Queue::init();
GatewayPagamento::init();
CorreiosToken::init();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}