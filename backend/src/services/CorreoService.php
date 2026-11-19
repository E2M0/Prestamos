<?php
declare(strict_types=1);

/**
 * Envío del recibo por correo con PHPMailer (descarga manual en backend/lib/PHPMailer/src; no se usa Composer).
 * Requiere el bloque 'correo' en config.php. Si falta algo, se responde con un mensaje claro (503).
 */
final class CorreoService
{
    public function enviarRecibo(array $recibo, string $destino): void
    {
        $cfg = Configuracion::obtener('correo');
        if (!is_array($cfg) || empty($cfg['activo']) || empty($cfg['servidor']) || empty($cfg['usuario']) || empty($cfg['clave'])) {
            throw new ErrorApi('El envío de correos no está configurado en el servidor (bloque "correo" de config.php).', 503);
        }
        $carpeta = RAIZ . '/lib/PHPMailer/src/';
        foreach (['Exception', 'PHPMailer', 'SMTP'] as $archivo) {
            if (!is_file($carpeta . $archivo . '.php')) {
                throw new ErrorApi('Falta PHPMailer en backend/lib/PHPMailer/src.', 503);
            }
            require_once $carpeta . $archivo . '.php';
        }

        $correo = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $correo->isSMTP();
            $correo->Host = (string) $cfg['servidor'];
            $correo->Port = (int) ($cfg['puerto'] ?? 587);
            $correo->SMTPAuth = true;
            $correo->Username = (string) $cfg['usuario'];
            $correo->Password = (string) $cfg['clave'];
            $correo->SMTPSecure = ($cfg['seguridad'] ?? 'tls') === 'ssl'
                ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $correo->CharSet = 'UTF-8';
            $correo->Timeout = 15;
            $correo->setFrom((string) ($cfg['remitente'] ?: $cfg['usuario']), (string) ($cfg['nombre_remitente'] ?? $recibo['empresa']));
            $correo->addAddress($destino);
            $correo->isHTML(true);
            $correo->Subject = 'Recibo de ingreso ' . $recibo['numero'];
            $correo->Body = $this->html($recibo);
            $correo->AltBody = 'Recibo ' . $recibo['numero'] . ' por RD$ ' . number_format($recibo['monto'], 2) . '.';
            $correo->send();
        } catch (Throwable $e) {
            error_log('Correo: ' . $e->getMessage());
            throw new ErrorApi('No se pudo enviar el correo. Verifique la configuración SMTP.', 502);
        }
    }

    private function html(array $r): string
    {
        $h = fn(string $t) => htmlspecialchars($t, ENT_QUOTES, 'UTF-8');
        $m = fn(float $v) => 'RD$ ' . number_format($v, 2);
        $fila = fn(string $a, string $b) => '<tr><td style="padding:4px 12px 4px 0;color:#555">' . $h($a) . '</td><td style="padding:4px 0;text-align:right">' . $h($b) . '</td></tr>';
        $tipos = ['normal' => 'Pago normal', 'abono_capital' => 'Abono a capital', 'solo_mora' => 'Pago de mora'];

        $t = $fila('Recibo', $r['numero']) . $fila('Fecha', str_replace('T', ' ', $r['fecha']))
           . $fila('Préstamo', $r['prestamoNumero']) . $fila('Cliente', $r['cliente'])
           . $fila('Tipo de pago', $tipos[$r['tipoPago']] ?? $r['tipoPago']);
        foreach ([['Mora', 'totalMora'], ['Interés', 'totalInteres'], ['Seguro', 'totalSeguro'], ['Gasto de cierre', 'totalGasto'], ['Capital', 'totalCapital']] as [$etq, $k]) {
            if ($r[$k] > 0) {
                $t .= $fila($etq, $m($r[$k]));
            }
        }
        $t .= $fila('Total recibido', $m($r['monto'])) . $fila('Balance posterior', $m($r['balancePosterior']));

        return '<div style="font-family:Arial,sans-serif;max-width:480px"><h2 style="color:#047857">' . $h($r['empresa']) . '</h2>'
             . '<table style="width:100%;border-collapse:collapse;font-size:14px">' . $t . '</table>'
             . '<p style="color:#777;font-size:12px">Atendido por ' . $h($r['cajero']) . '. Gracias por su pago.</p></div>';
    }
}
