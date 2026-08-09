<?php

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
