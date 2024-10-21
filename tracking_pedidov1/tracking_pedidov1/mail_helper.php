<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // Cargar el autoloader de Composer para PHPMailer

function enviarCorreoPedido($email_cliente, $id_pedido, $estado, $accion) {
    $mail = new PHPMailer(true);

    try {
        $mail->SMTPDebug = 0; 
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'pruebademo772@gmail.com';
        $mail->Password = 'lejltrcpdxgmolpo';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        $mail->setFrom('marianogalvez783@gmail.com', 'Tienda online');

        $mail->addAddress($email_cliente);

        //$mail->AddEmbeddedImage('images/logo.png', 'logo');

        $mail->isHTML(true);

        $asunto = ($accion === 'crear') ? "Nuevo pedido creado - ID: $id_pedido" : "Estado de pedido actualizado - ID: $id_pedido";
        $mail->Subject = $asunto;

        $mensaje = ($accion === 'crear') ?
            "<h1 style='color: #333;'>Estimado cliente,</h1>
             <p>Su pedido con ID <strong>$id_pedido</strong> ha sido creado exitosamente. El estado actual de su pedido es: <strong>$estado</strong>.</p>
             <img src='cid:encabezadoImg' alt='Encabezado' style='width: 100%; height: auto;'>
             <p>Gracias por su compra.</p>" :
            "<h1 style='color: #333;'>Estimado cliente,</h1>
             <p>El estado de su pedido con ID <strong>$id_pedido</strong> ha sido actualizado. El nuevo estado es: <strong>$estado</strong>.</p>
             <p>Gracias por su confianza.</p>";

        // Establecer el cuerpo del correo en HTML
        $mail->Body = $mensaje;

        // Enviar el correo
        $mail->send();
        //echo json_encode(["mensaje" => "Correo enviado correctamente al cliente"]);

    } catch (Exception $e) {
        echo json_encode(["mensaje" => "Error al enviar el correo: {$mail->ErrorInfo}"]);
    }
}
