<?php
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization"); 

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

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
    case 'usuarios':
        handleUsuarios($method, $id);
        break;
    case 'login':
        handleLogin();
        break;
    case 'perfil':
        handleVerPerfil();
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

function handleUsuarios($method, $id) {
    global $conn;

    if ($method == 'POST') {
        $data = json_decode(file_get_contents("php://input"), true);
        $nombre_usuario = $data['nombre_usuario'];
        $correo_electronico = $data['correo_electronico'];

        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE nombre_usuario = ? OR correo_electronico = ?");
        $stmt->bind_param("ss", $nombre_usuario, $correo_electronico);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            http_response_code(400);
            echo json_encode(["mensaje" => "El nombre de usuario o el correo electrónico ya están en uso"]);
        } else {
            $id_empresa = null;
            if (isset($data['nombre_empresa'])) {
                $stmt = $conn->prepare("SELECT id_empresa FROM empresas WHERE nombre_empresa = ?");
                $stmt->bind_param("s", $data['nombre_empresa']);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    $empresa = $result->fetch_assoc();
                    $id_empresa = $empresa['id_empresa'];
                } else {
                    $stmt = $conn->prepare("INSERT INTO empresas (nombre_empresa, direccion, telefono) VALUES (?, ?, ?)");
                    $stmt->bind_param("sss", $data['nombre_empresa'], $data['direccion_empresa'], $data['telefono_empresa']);

                    if ($stmt->execute()) {
                        $id_empresa = $conn->insert_id;
                    } else {
                        http_response_code(500);
                        echo json_encode(["mensaje" => "Error al crear la empresa"]);
                        return;
                    }
                }
            }
            $stmt = $conn->prepare("INSERT INTO personas (nombres, apellidos, telefono, fecha_nacimiento, id_empresa) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssi", $data['nombres'], $data['apellidos'], $data['telefono'], $data['fecha_nacimiento'], $id_empresa);
            
            if ($stmt->execute()) {
                $id_persona = $conn->insert_id;

                $contrasena = password_hash($data['contrasena'], PASSWORD_BCRYPT);
                $stmt = $conn->prepare("INSERT INTO usuarios (nombre_usuario, contrasena, correo_electronico, id_persona) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("sssi", $nombre_usuario, $contrasena, $correo_electronico, $id_persona);

                if ($stmt->execute()) {
                    echo json_encode(["mensaje" => "Usuario creado exitosamente"]);
                } else {
                    http_response_code(500);
                    echo json_encode(["mensaje" => "Error al crear el usuario"]);
                }
            } else {
                http_response_code(500);
                echo json_encode(["mensaje" => "Error al registrar los datos de la persona"]);
            }
        }

        $stmt->close();
    } else {
        http_response_code(405);
        echo json_encode(["mensaje" => "Método no permitido"]);
    }
}

function handleLogin() {
    global $conn;

    if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
        http_response_code(401);
        header('WWW-Authenticate: Basic realm="Sistema de Tracking"');
        echo json_encode(["mensaje" => "Autenticación requerida"]);
        exit;
    }

    $nombre_usuario = $_SERVER['PHP_AUTH_USER'];
    $contrasena = $_SERVER['PHP_AUTH_PW'];

    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE nombre_usuario = ?");
    $stmt->bind_param("s", $nombre_usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    $usuario = $result->fetch_assoc();

    if ($usuario && password_verify($contrasena, $usuario['contrasena'])) {
        echo json_encode(["mensaje" => "Login exitoso"]);
        registrarBitacora($usuario['id_usuario'], true);
    } else {
        http_response_code(401);
        echo json_encode(["mensaje" => "Credenciales incorrectas"]);
        registrarBitacora($usuario ? $usuario['id_usuario'] : null, false);
    }

    $stmt->close();
}

function handleVerPerfil() {
    global $conn;

    if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
        http_response_code(401);
        header('WWW-Authenticate: Basic realm="Sistema de Tracking"');
        echo json_encode(["mensaje" => "Autenticación requerida"]);
        exit;
    }

    $nombre_usuario = $_SERVER['PHP_AUTH_USER'];
    $contrasena = $_SERVER['PHP_AUTH_PW'];

    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE nombre_usuario = ?");
    $stmt->bind_param("s", $nombre_usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    $usuario = $result->fetch_assoc();

    if ($usuario && password_verify($contrasena, $usuario['contrasena'])) {
        $id_persona = $usuario['id_persona'];

        $stmt = $conn->prepare("
            SELECT 
                p.nombres, p.apellidos, p.telefono, p.fecha_nacimiento,
                e.nombre_empresa, e.direccion, e.telefono AS telefono_empresa
            FROM personas p
            LEFT JOIN empresas e ON p.id_empresa = e.id_empresa
            WHERE p.id_persona = ?
        ");
        $stmt->bind_param("i", $id_persona);
        $stmt->execute();
        $perfil = $stmt->get_result()->fetch_assoc();

        if ($perfil) {
            echo json_encode([
                "mensaje" => "Login exitoso",
                "usuario" => [
                    "nombre_usuario" => $nombre_usuario,
                    "correo_electronico" => $usuario['correo_electronico'],
                    "perfil" => $perfil
                ]
            ]);
        } else {
            http_response_code(404);
            echo json_encode(["mensaje" => "Perfil no encontrado"]);
        }

        registrarBitacora($usuario['id_usuario'], true);
    } else {
        http_response_code(401);
        echo json_encode(["mensaje" => "Credenciales incorrectas"]);

        registrarBitacora($usuario ? $usuario['id_usuario'] : null, false);
    }

    $stmt->close();
}


function registrarBitacora($id_usuario, $exito) {
    global $conn;

    $ip_origen = $_SERVER['REMOTE_ADDR'];
    $stmt = $conn->prepare("INSERT INTO logs_inicio_sesion (id_usuario, exito, ip_origen) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $id_usuario, $exito, $ip_origen);

    $stmt->execute();
    $stmt->close();
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
