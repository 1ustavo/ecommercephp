<?php

// Load Composer autoloader if available (for Stripe, PHPMailer, etc.)
$composerAutoload = __DIR__ . "/../vendor/autoload.php";
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

require_once __DIR__ . "/env.php";
loadEnv(__DIR__ . "/../.env");

// Garantir que o conteúdo HTML seja servido como UTF-8 para evitar problemas de
// encoding (caracteres como ç aparecendo como Ã§) quando o servidor não definir
// explicitamente o charset.
ini_set('default_charset', 'UTF-8');
header('Content-Type: text/html; charset=utf-8');

// ============================================================
// SESSÃO SEGURA — configurações antes de iniciar a sessão
// ============================================================
// Cookie HttpOnly (impede acesso via JS)
ini_set("session.cookie_httponly", "1");

// SameSite=Strict (impede envio do cookie em requests cross-site)
ini_set("session.cookie_samesite", "Strict");

// Secure somente se HTTPS (produção)
if (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") {
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
        "secure" => !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off",
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
require_once __DIR__ . "/../src/Endereco.php";
require_once __DIR__ . "/../src/Notificacao.php";
require_once __DIR__ . "/../src/Cache.php";
require_once __DIR__ . "/../src/Queue.php";
require_once __DIR__ . "/../src/GatewayPagamento.php";

Cache::init();
Queue::init();
GatewayPagamento::init();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}