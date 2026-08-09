<?php
//erro
class GatewayPagamento
{
    private static ?string $stripeSecret = null;
    private static ?string $mpAccessToken = null;

    public static function init(): void
    {
        self::$stripeSecret = $_ENV["STRIPE_SECRET_KEY"] ?? getenv("STRIPE_SECRET_KEY") ?: null;
        self::$mpAccessToken = $_ENV["MERCADO_PAGO_ACCESS_TOKEN"] ?? getenv("MERCADO_PAGO_ACCESS_TOKEN") ?: null;
    }

    /**
     * Cria uma sessão de checkout no Stripe.
     * Retorna a URL de checkout ou null em caso de erro.
     */
    public static function stripeCheckout(array $pedido, array $itens): ?string
    {
        if (!self::$stripeSecret) {
            return null;
        }

        $stripe = new \Stripe\StripeClient(self::$stripeSecret);

        $lineItems = [];
        foreach ($itens as $item) {
            $lineItems[] = [
                "price_data" => [
                    "currency" => "brl",
                    "unit_amount" => (int) round((float) $item["preco_unitario"] * 100),
                    "product_data" => [
                        "name" => $item["nome_produto"],
                    ],
                ],
                "quantity" => (int) $item["quantidade"],
            ];
        }

        if ((float) $pedido["frete"] > 0) {
            $lineItems[] = [
                "price_data" => [
                    "currency" => "brl",
                    "unit_amount" => (int) round((float) $pedido["frete"] * 100),
                    "product_data" => ["name" => "Frete"],
                ],
                "quantity" => 1,
            ];
        }

        if ((float) $pedido["imposto"] > 0) {
            $lineItems[] = [
                "price_data" => [
                    "currency" => "brl",
                    "unit_amount" => (int) round((float) $pedido["imposto"] * 100),
                    "product_data" => ["name" => "Imposto"],
                ],
                "quantity" => 1,
            ];
        }

        try {
            $session = $stripe->checkout->sessions->create([
                "payment_method_types" => ["card"],
                "line_items" => $lineItems,
                "mode" => "payment",
                "success_url" => "https://seusite.com/checkout/sucesso?session_id={CHECKOUT_SESSION_ID}",
                "cancel_url" => "https://seusite.com/checkout/cancelado",
                "metadata" => [
                    "pedido_id" => $pedido["id"],
                    "usuario_id" => $pedido["usuario_id"],
                ],
            ]);

            return $session->url;
        } catch (Exception $e) {
            error_log("Stripe error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Cria uma preferência de pagamento no Mercado Pago.
     * Retorna a URL de checkout ou null em caso de erro.
     */
    public static function mercadoPagoCheckout(array $pedido, array $itens): ?string
    {
        if (!self::$mpAccessToken) {
            return null;
        }

        $itensMp = [];
        foreach ($itens as $item) {
            $itensMp[] = [
                "title" => $item["nome_produto"],
                "quantity" => (int) $item["quantidade"],
                "unit_price" => (float) $item["preco_unitario"],
            ];
        }

        if ((float) $pedido["frete"] > 0) {
            $itensMp[] = [
                "title" => "Frete",
                "quantity" => 1,
                "unit_price" => (float) $pedido["frete"],
            ];
        }

        if ((float) $pedido["imposto"] > 0) {
            $itensMp[] = [
                "title" => "Imposto",
                "quantity" => 1,
                "unit_price" => (float) $pedido["imposto"],
            ];
        }

        $payload = [
            "items" => $itensMp,
            "back_urls" => [
                "success" => "https://seusite.com/checkout/sucesso",
                "failure" => "https://seusite.com/checkout/cancelado",
            ],
            "metadata" => [
                "pedido_id" => $pedido["id"],
                "usuario_id" => $pedido["usuario_id"],
            ],
            "auto_return" => "approved",
        ];

        $ch = curl_init("https://api.mercadopago.com/checkout/preferences");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer " . self::$mpAccessToken,
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 201) {
            error_log("Mercado Pago error: " . $response);
            return null;
        }

        $data = json_decode($response, true);
        return $data["init_point"] ?? null;
    }

    /**
     * Cria uma sessão de pagamento e retorna a URL.
     * Usa o gateway configurado (stripe ou mercadopago).
     */
    public static function criarCheckout(int $pedidoId, string $gateway = "stripe"): ?string
    {
        self::init();

        $pedido = Pedido::buscar($pedidoId);
        if (!$pedido) {
            return null;
        }

        $itens = Pedido::itens($pedidoId);

        if ($gateway === "stripe") {
            return self::stripeCheckout($pedido, $itens);
        }

        if ($gateway === "mercadopago") {
            return self::mercadoPagoCheckout($pedido, $itens);
        }

        return null;
    }

    /**
     * Processa webhook de confirmação de pagamento.
     */
    public static function processarWebhook(string $gateway, array $payload): bool
    {
        self::init();

        if ($gateway === "stripe") {
            $sigHeader = $_SERVER["HTTP_STRIPE_SIGNATURE"] ?? "";
            try {
                $event = \Stripe\Webhook::constructEvent(
                    json_encode($payload),
                    $sigHeader,
                    $_ENV["STRIPE_WEBHOOK_SECRET"] ?? ""
                );

                if ($event->type === "checkout.session.completed") {
                    $session = $event->data->object;
                    $pedidoId = (int) ($session->metadata->pedido_id ?? 0);
                    if ($pedidoId) {
                        Pedido::confirmarPagamento($pedidoId);
                        return true;
                    }
                }
            } catch (Exception $e) {
                error_log("Stripe webhook error: " . $e->getMessage());
            }
        }

        if ($gateway === "mercadopago") {
            $pedidoId = (int) ($payload["metadata"]["pedido_id"] ?? 0);
            if ($pedidoId && ($payload["status"] ?? "") === "approved") {
                Pedido::confirmarPagamento($pedidoId);
                return true;
            }
        }

        return false;
    }
}
//</arg_value></tool_call>