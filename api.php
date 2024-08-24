<?php
header("Content-Type: application/json");

// Importar la configuración de la base de datos
require 'conexion.php';

// Obtener el método de la solicitud
$method = $_SERVER['REQUEST_METHOD'];

// Obtener el recurso solicitado (pedidos o productos)
$request = explode('/', trim($_SERVER['PATH_INFO'], '/'));

// Ruta base
$resource = array_shift($request);

$id = array_shift($request);

switch ($resource) {
    case 'pedidos':
        handlePedidos($method, $id);
        break;
    case 'productos':
        handleProductos($method, $id);
        break;
    default:
        http_response_code(404);
        echo json_encode(["mensaje" => "Recurso no encontrado"]);
        break;
}

function handlePedidos($method, $id) {
    global $conn;

    if ($method == 'GET') {
        if ($id) {
            $stmt = $conn->prepare("SELECT * FROM pedidos WHERE id_pedido = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $pedido = $result->fetch_assoc();

            if ($pedido) {
                echo json_encode($pedido);
            } else {
                http_response_code(404);
                echo json_encode(["mensaje" => "Pedido no encontrado"]);
            }

            $stmt->close();
        } else {
            $result = $conn->query("SELECT * FROM pedidos");
            $pedidos = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode($pedidos);
        }
    } elseif ($method == 'PUT') {
        updatePedido($id);
    } else {
        http_response_code(405);
        echo json_encode(["mensaje" => "Método no permitido"]);
    }
}

function handleProductos($method, $id) {
    global $conn;

    if ($method == 'GET') {
        if ($id) {
            $stmt = $conn->prepare("SELECT * FROM productos WHERE id_producto = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $producto = $result->fetch_assoc();

            if ($producto) {
                echo json_encode($producto);
            } else {
                http_response_code(404);
                echo json_encode(["mensaje" => "Producto no encontrado"]);
            }

            $stmt->close();
        } else {
            $result = $conn->query("SELECT * FROM productos");
            $productos = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode($productos);
        }
    } else {
        http_response_code(405);
        echo json_encode(["mensaje" => "Método no permitido"]);
    }
}

function updatePedido($id) {
    global $conn;

    $data = json_decode(file_get_contents("php://input"), true);

    if (isset($data['estado'])) {
        $estado = $data['estado'];

        $stmt = $conn->prepare("UPDATE pedidos SET estado = ? WHERE id_pedido = ?");
        $stmt->bind_param("si", $estado, $id);
        
        if ($stmt->execute()) {
            echo json_encode(["mensaje" => "Pedido actualizado exitosamente"]);
        } else {
            http_response_code(500);
            echo json_encode(["mensaje" => "Error al actualizar el pedido"]);
        }

        $stmt->close();
    } else {
        http_response_code(400);
        echo json_encode(["mensaje" => "Estado no proporcionado"]);
    }
}

?>
