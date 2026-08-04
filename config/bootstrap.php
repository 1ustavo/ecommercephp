<?php

require_once __DIR__ . "/database.php";
require_once __DIR__ . "/../src/helpers.php";
require_once __DIR__ . "/../src/Usuario.php";
require_once __DIR__ . "/../src/Auth.php";
require_once __DIR__ . "/../src/Produto.php";
require_once __DIR__ . "/../src/Carrinho.php";
require_once __DIR__ . "/../src/Pedido.php";
require_once __DIR__ . "/../src/Endereco.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
