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
            $logo = $this->rutaLogo();
            if ($logo !== null) {
                $correo->addEmbeddedImage($logo, 'logo', 'logo.png');
            }
            $correo->Body = $this->html($recibo, $logo !== null);
            $correo->AltBody = 'Recibo ' . $recibo['numero'] . ' por RD$ ' . number_format($recibo['monto'], 2) . '.';
            $correo->send();
        } catch (Throwable $e) {
            error_log('Correo: ' . $e->getMessage());
            throw new ErrorApi('No se pudo enviar el correo. Verifique la configuración SMTP.', 502);
        }
    }

    /** Busca logo.png (en el hosting junto a index.php; en desarrollo en frontend/public). Devuelve la ruta o null. */
    private function rutaLogo(): ?string
    {
        foreach ([dirname(RAIZ) . '/logo.png', dirname(RAIZ) . '/frontend/public/logo.png'] as $ruta) {
            if (is_file($ruta) && filesize($ruta) < 500000) {
                return $ruta;
            }
        }
        return null;
    }

    /** Correo con el mismo diseño del recibo A4: tablas y estilos en línea (los clientes de correo no admiten CSS externo). */
    private function html(array $r, bool $conLogo): string
    {
        $h = fn(string $t) => htmlspecialchars($t, ENT_QUOTES, 'UTF-8');
        $m = fn(float $v) => 'RD$ ' . number_format($v, 2);
        $verde = '#059669'; $oscuro = '#047857'; $dorado = '#c59b27'; $borde = '#e5e7eb'; $gris = '#6b7280';
        $tipos = ['normal' => 'Pago normal', 'abono_capital' => 'Abono a capital', 'solo_mora' => 'Pago de mora'];
        $fecha = ($ts = strtotime((string) $r['fecha'])) ? date('d/m/Y h:i a', $ts) : (string) $r['fecha'];

        $dato = fn(string $a, string $b) => '<tr><td style="padding:3px 0;color:' . $gris . ';font-size:13px">' . $h($a)
            . '</td><td style="padding:3px 0;text-align:right;font-size:13px;font-weight:bold">' . $h($b) . '</td></tr>';
        $titulo = fn(string $s) => '<div style="font-size:12px;text-transform:uppercase;letter-spacing:1px;color:' . $oscuro
            . ';border-bottom:2px solid #d1fae5;padding-bottom:4px;margin-bottom:8px;font-weight:bold">' . $h($s) . '</div>';

        $cliente = $dato('Nombre', $r['cliente']) . $dato('Documento', $r['numeroDocumento']);
        $prestamo = $dato('Préstamo', $r['prestamoNumero'] . ' · ' . $r['tipoPrestamo'])
            . $dato('Tipo de pago', $tipos[$r['tipoPago']] ?? $r['tipoPago'])
            . $dato('Balance restante', $m($r['balancePosterior']));

        $filas = '';
        foreach ($r['detalle'] as $i => $d) {
            $total = $d['mora'] + $d['interes'] + $d['seguro'] + $d['gasto'] + $d['capital'];
            $nombre = $d['cuotaNumero'] === null ? 'Abono a capital' : 'Cuota ' . $d['cuotaNumero'];
            $filas .= '<tr style="background:' . ($i % 2 ? '#f3f4f6' : '#ffffff') . '"><td style="padding:9px 12px;font-size:13px;border-bottom:1px solid '
                . $borde . '">' . $h($nombre) . '</td><td style="padding:9px 12px;font-size:13px;text-align:right;border-bottom:1px solid '
                . $borde . '">' . $h($m($total)) . '</td></tr>';
        }

        $desglose = '';
        foreach ([['Mora', 'totalMora'], ['Interés', 'totalInteres'], ['Seguro', 'totalSeguro'], ['Gasto de cierre', 'totalGasto'], ['Capital', 'totalCapital']] as [$etq, $k]) {
            if ($r[$k] > 0) {
                $desglose .= '<tr><td style="padding:6px 0;font-size:13px;color:' . $gris . ';border-bottom:1px solid ' . $borde . '">' . $h($etq)
                    . '</td><td style="padding:6px 0;font-size:13px;text-align:right;border-bottom:1px solid ' . $borde . '">' . $h($m((float) $r[$k])) . '</td></tr>';
            }
        }

        $logo = $conLogo ? '<td style="width:70px;padding-right:12px"><img src="cid:logo" width="64" height="64" alt="" style="display:block;border:0"></td>' : '';
        $lema = $r['empresaLema'] !== '' ? '<div style="font-size:12px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;color:' . $dorado . '">' . $h($r['empresaLema']) . '</div>' : '';
        $obs = trim((string) ($r['observacion'] ?? '')) !== '' ? $h((string) $r['observacion']) : '—';

        return '<div style="background:#f6f7f9;padding:20px 8px;font-family:Arial,Helvetica,sans-serif;color:#1f2937">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid ' . $borde
            . ';border-top:6px solid ' . $verde . ';border-bottom:4px solid ' . $dorado . '"><tr><td style="padding:28px">'
            // Encabezado
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-bottom:1px solid ' . $borde . ';padding-bottom:16px"><tr>'
            . $logo . '<td><div style="font-size:24px;font-weight:bold;letter-spacing:1px;color:' . $oscuro . '">' . $h($r['empresa']) . '</div>' . $lema . '</td>'
            . '<td style="text-align:right;vertical-align:top"><div style="font-size:18px;font-weight:bold;text-transform:uppercase;color:' . $verde . '">Recibo de pago</div>'
            . '<div style="margin-top:6px;display:inline-block;padding:3px 10px;background:#ecfdf5;border-left:3px solid ' . $verde . ';color:' . $oscuro . ';font-weight:bold;font-size:13px">N.º ' . $h($r['numero']) . '</div>'
            . '<div style="margin-top:6px;font-size:12px;color:' . $gris . '"><b>Fecha:</b> ' . $h($fecha) . '</div></td></tr></table>'
            // Cliente y préstamo
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:20px 0"><tr>'
            . '<td width="48%" valign="top">' . $titulo('Información del cliente') . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $cliente . '</table></td>'
            . '<td width="4%"></td>'
            . '<td width="48%" valign="top">' . $titulo('Detalle del préstamo') . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $prestamo . '</table></td>'
            . '</tr></table>'
            // Detalle
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse"><tr>'
            . '<th align="left" style="background:' . $verde . ';color:#ffffff;font-size:12px;text-transform:uppercase;letter-spacing:1px;padding:10px 12px">Descripción</th>'
            . '<th align="right" style="background:' . $verde . ';color:#ffffff;font-size:12px;text-transform:uppercase;letter-spacing:1px;padding:10px 12px">Total</th></tr>' . $filas . '</table>'
            // Observaciones y totales
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:20px"><tr>'
            . '<td width="52%" valign="top"><div style="background:#ecfdf5;border:1px solid #d1fae5;border-left:3px solid ' . $verde . ';padding:10px 12px;font-size:12px"><b style="color:' . $oscuro . '">Observaciones:</b><br>' . $obs . '</div></td>'
            . '<td width="4%"></td>'
            . '<td width="44%" valign="top"><table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $desglose
            . '<tr><td style="padding-top:10px;border-top:2px solid ' . $verde . ';font-size:15px;font-weight:bold;color:' . $oscuro . '">Total pagado</td>'
            . '<td style="padding-top:10px;border-top:2px solid ' . $verde . ';font-size:15px;font-weight:bold;text-align:right;color:' . $oscuro . '">' . $h($m((float) $r['monto'])) . '</td></tr></table></td>'
            . '</tr></table>'
            // Pie
            . '<div style="margin-top:28px;padding-top:14px;border-top:1px dashed #d1d5db;text-align:center;font-size:12px;color:' . $gris . '">'
            . '<div style="color:' . $verde . ';font-weight:bold;font-size:13px">¡Gracias por su puntualidad y confianza!</div>'
            . '<div>Atendido por ' . $h($r['cajero']) . ' — ' . $h($r['empresa']) . '</div></div>'
            . '</td></tr></table></div>';
    }
}
