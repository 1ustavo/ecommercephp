<?php

class Notificacao
{
    /**
     * Envia email usando mail() do PHP.
     * Se MAIL_ENABLED=false no .env (ou ausente), apenas registra
     * o email em storage/emails.log para debug/teste.
     *
     * @return bool true se enviado/logado, false em erro critico
     */
    public static function enviarEmail(string $para, string $assunto, string $corpoHtml): bool
    {
        $enabled = getenv("MAIL_ENABLED");
        if ($enabled === false || $enabled === "0" || strtolower($enabled) === "false") {
            return self::logEmail($para, $assunto, $corpoHtml);
        }

        // Se variáveis SMTP estiverem presentes, usar PHPMailer
        $smtpHost = getenv('MAIL_HOST') ?: '';
        $smtpPort = getenv('MAIL_PORT') ?: '';
        $smtpUser = getenv('MAIL_USERNAME') ?: '';
        $smtpPass = getenv('MAIL_PASSWORD') ?: '';
        $smtpSecure = getenv('MAIL_SECURE') ?: 'tls';
        $from = getenv('MAIL_FROM') ?: 'no-reply@ecoshop.com.br';
        $fromName = getenv('MAIL_FROM_NAME') ?: 'EcoShop';

        if ($smtpHost && class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
            try {
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                // Servidor SMTP
                $mail->isSMTP();
                $mail->Host = $smtpHost;
                $mail->SMTPAuth = ($smtpUser !== '');
                if ($smtpUser !== '') {
                    $mail->Username = $smtpUser;
                    $mail->Password = $smtpPass;
                }
                $mail->SMTPSecure = $smtpSecure;
                if (is_numeric($smtpPort)) $mail->Port = (int) $smtpPort;

                $mail->setFrom($from, $fromName);
                $mail->addAddress($para);
                $mail->isHTML(true);
                $mail->Subject = $assunto;
                $mail->Body = $corpoHtml;

                $mail->send();
                return true;
            } catch (\PHPMailer\PHPMailer\Exception $e) {
                error_log('PHPMailer error: ' . $e->getMessage());
                return self::logEmail($para, $assunto, $corpoHtml);
            }
        }

        // Fallback para mail() nativo
        $de = $from;
        $headers = [
            "From: {$fromName} <" . $de . ">",
            "Reply-To: " . $de,
            "Content-Type: text/html; charset=UTF-8",
            "X-Mailer: PHP/" . phpversion(),
        ];

        try {
            $sent = mail($para, $assunto, $corpoHtml, implode("\r\n", $headers));
            if (!$sent) {
                return self::logEmail($para, $assunto, $corpoHtml);
            }
            return true;
        } catch (Throwable $e) {
            error_log("Erro ao enviar email: " . $e->getMessage());
            return self::logEmail($para, $assunto, $corpoHtml);
        }
    }

    /**
     * Registra o email em arquivo de log para debug/teste.
     * Dados sensíveis (e-mail completo, corpo com CPF/telefone) são mascarados.
     */
    private static function logEmail(string $para, string $assunto, string $corpoHtml): bool
    {
        $logDir = __DIR__ . "/../../storage";
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        $logFile = $logDir . "/emails.log";
        $data = date("Y-m-d H:i:s");
        $separator = str_repeat("=", 80);

        // Mascara o e-mail: jo***@ex***.com
        $partes = explode("@", $para);
        $emailMascarado = (count($partes) === 2) ? "***@" . $partes[1] : "***";

        // Mascara CPF (11 dígitos), telefone (10-11 dígitos) e e-mails no corpo
        $corpoMascarado = preg_replace('/\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/', '***.***.***-**', $corpoHtml);
        $corpoMascarado = preg_replace('/\b(\+?\d{2})?\s?\(?\d{2}\)?\s?\d{4,5}-?\d{4}\b/', '(**) *****-****', $corpoMascarado ?? $corpoHtml);
        $corpoMascarado = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', '***@***.***', $corpoMascarado ?? $corpoHtml);

        // Não grava o corpo HTML completo — apenas um resumo seguro
        $resumo = strip_tags($corpoMascarado ?? "");
        $resumo = mb_substr($resumo, 0, 200);

        $linhas = [
            $separator,
            "[{$data}] PARA: {$emailMascarado}",
            "ASSUNTO: {$assunto}",
            "RESUMO: {$resumo}",
            $separator,
            "",
        ];

        try {
            return file_put_contents($logFile, implode("\n", $linhas), FILE_APPEND | LOCK_EX) !== false;
        } catch (Throwable $e) {
            error_log("Erro ao gravar log de email: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Monta o HTML base de email (template simples).
     */
    private static function templateHtml(string $titulo, string $corpo): string
    {
        return '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>' . htmlspecialchars($titulo, ENT_QUOTES, "UTF-8") . '</title></head><body style="margin:0;padding:0;background-color:#f5f5f5;font-family:Arial,Helvetica,sans-serif"><table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f5f5"><tr><td align="center" style="padding:24px 0"><table width="600" cellpadding="0" cellspacing="0" border="0" style="background-color:#ffffff;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.1)"><tr><td style="padding:32px 24px 24px"><h1 style="margin:0 0 16px;color:#1B4332;font-size:22px">' . htmlspecialchars($titulo, ENT_QUOTES, "UTF-8") . '</h1><div style="color:#333333;font-size:15px;line-height:1.6">' . $corpo . '</div><p style="margin-top:24px;color:#666666;font-size:13px">Atenciosamente,<br><strong>Equipe EcoShop</strong></p></td></tr></table></td></tr></table></body></html>';
    }

    /**
     * Notifica cliente que o pedido foi aprovado.
     */
    public static function pedidoAprovado(int $pedidoId): void
    {
        global $pdo;

        $pedido = Pedido::buscar($pedidoId);
        if (!$pedido) {
            return;
        }

        $usuario = $pdo->prepare("SELECT nome, email FROM usuarios WHERE id = ?");
        $usuario->execute([$pedido["usuario_id"]]);
        $u = $usuario->fetch();
        if (!$u || empty($u["email"])) {
            return;
        }

        $assunto = "Pedido #{$pedidoId} aprovado — EcoShop";
        $corpo = self::templateHtml("Pedido aprovado", '<p>Seu pedido <strong>#' . (int) $pedidoId . '</strong> foi aprovado e já está sendo preparado para envio.</p><p><strong>Total:</strong> ' . formatarMoeda((float) $pedido["total"]) . '<br><strong>Status:</strong> Pendente</p><p>Em breve você receberá o código de rastreamento.</p>');

        self::enviarEmail($u["email"], $assunto, $corpo);
    }

    /**
     * Notifica cliente que o pedido foi recusado.
     */
    public static function pedidoRecusado(int $pedidoId): void
    {
        global $pdo;

        $pedido = Pedido::buscar($pedidoId);
        if (!$pedido) {
            return;
        }

        $usuario = $pdo->prepare("SELECT nome, email FROM usuarios WHERE id = ?");
        $usuario->execute([$pedido["usuario_id"]]);
        $u = $usuario->fetch();
        if (!$u || empty($u["email"])) {
            return;
        }

        $motivo = !empty($pedido["motivo_recusa"]) ? nl2br(e($pedido["motivo_recusa"])) : "Não informado.";
        $assunto = "Pedido #{$pedidoId} recusado — EcoShop";
        $corpo = self::templateHtml("Pedido recusado", '<p>Infelizmente seu pedido <strong>#' . (int) $pedidoId . '</strong> não foi aprovado.</p><p><strong>Motivo:</strong><br>' . $motivo . '</p><p>Se achar que houve um engano, entre em contato com nosso suporte.</p>');

        self::enviarEmail($u["email"], $assunto, $corpo);
    }

    /**
     * Notifica cliente que o pagamento do pedido foi recebido.
     */
    public static function pedidoPago(int $pedidoId): void
    {
        global $pdo;

        $pedido = Pedido::buscar($pedidoId);
        if (!$pedido) {
            return;
        }

        $usuario = $pdo->prepare("SELECT nome, email FROM usuarios WHERE id = ?");
        $usuario->execute([$pedido["usuario_id"]]);
        $u = $usuario->fetch();
        if (!$u || empty($u["email"])) {
            return;
        }

        $assunto = "Pagamento recebido — Pedido #{$pedidoId} — EcoShop";
        $corpo = self::templateHtml("Pagamento recebido", '<p>Recebemos o pagamento do seu pedido <strong>#' . (int) $pedidoId . '</strong>.</p><p><strong>Total:</strong> ' . formatarMoeda((float) $pedido["total"]) . '</p><p>Em breve o pedido será analisado para aprovação e envio.</p>');

        self::enviarEmail($u["email"], $assunto, $corpo);
    }

    /**
     * Notifica cliente que o reembolso foi aprovado.
     */
    public static function reembolsoAprovado(int $reembolsoId): void
    {
        $r = Reembolso::buscarPorId($reembolsoId);
        if (!$r) {
            return;
        }

        $assunto = "Reembolso aprovado — Pedido #{$r['pedido_id']} — EcoShop";
        $corpo = self::templateHtml(
            "Reembolso aprovado",
            '<p>Seu pedido de reembolso referente ao pedido <strong>#' . (int) $r["pedido_id"] . '</strong> foi <strong>aprovado</strong>.</p>'
            . '<p><strong>Valor do pedido:</strong> ' . formatarMoeda((float) $r["pedido_total"]) . '</p>'
            . '<p>O estorno será processado para a forma de pagamento original.</p>'
        );
        self::enviarEmail($r["cliente_email"], $assunto, $corpo);
    }

    /**
     * Notifica cliente que o reembolso foi recusado (com motivo).
     */
    public static function reembolsoRecusado(int $reembolsoId): void
    {
        $r = Reembolso::buscarPorId($reembolsoId);
        if (!$r) {
            return;
        }

        $motivo = !empty($r["motivo_recusa"]) ? nl2br(e($r["motivo_recusa"])) : "Não informado.";
        $assunto = "Reembolso recusado — Pedido #{$r['pedido_id']} — EcoShop";
        $corpo = self::templateHtml(
            "Reembolso recusado",
            '<p>Seu pedido de reembolso referente ao pedido <strong>#' . (int) $r["pedido_id"] . '</strong> não foi aprovado.</p>'
            . '<p><strong>Motivo:</strong><br>' . $motivo . '</p>'
            . '<p>Se achar que houve um engano, fale com nosso suporte (Tickets SAC).</p>'
        );
        self::enviarEmail($r["cliente_email"], $assunto, $corpo);
    }
}
