<?php

require_once 'mail_helper.php';

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
    case 'logout':
        handleLogout();
        break;
    case 'perfil':
        handleVerPerfil();
        break;
    case 'clientes': 
        handleClientes($method);
        break;
    case 'tracking': 
        obtenerTrackingPedido($method);
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
        //validarSesion();
        updatePedido($id);
    } else if ($method == 'POST'){

        validarSesion();

        // Decodificar el JSON enviado en la solicitud
        $data = json_decode(file_get_contents("php://input"), true);

        $id_cliente = $data['id_cliente'];
        $id_estado = $data['id_estado'];
        $id_metodo_pago = $data['id_metodo_pago'];
        $productos = $data['productos'];

        $conn->begin_transaction();

        try {
            $monto_total = 0;

            // Validar stock de productos antes de insertar
            foreach ($productos as $producto) {
                $id_producto = $producto['id_producto'];
                $cantidad_solicitada = $producto['cantidad'];

                // Verificar el stock del producto
                $stmt = $conn->prepare("SELECT cantidad_stock FROM productos WHERE id_producto = ?");
                $stmt->bind_param("i", $id_producto);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    $producto_data = $result->fetch_assoc();
                    $cantidad_stock = $producto_data['cantidad_stock'];

                    // Validar que haya suficiente stock
                    if ($cantidad_stock < $cantidad_solicitada) {
                        throw new Exception("No hay suficiente stock para el producto ID $id_producto");
                    }
                } else {
                    throw new Exception("Producto con ID $id_producto no encontrado");
                }

                // Calcular el monto total del pedido
                $monto_total += $producto['precio'] * $producto['cantidad'];
            }

            // Insertar el pedido
            $codigo_seguimiento = substr(bin2hex(random_bytes(5)), 0, 10);
            $stmt = $conn->prepare("INSERT INTO pedidos (fecha_pedido, id_estado, id_cliente, monto_total, id_metodo_pago, codigo_seguimiento, activo) VALUES (NOW(), ?, ?, ?, ?, ?, 1)");
            $stmt->bind_param("iidis", $id_estado, $id_cliente, $monto_total, $id_metodo_pago, $codigo_seguimiento);

            if (!$stmt->execute()) {
                throw new Exception("Error al crear el pedido");
            }

            $id_pedido = $conn->insert_id;

            // Insertar los productos en la tabla 'detalles_pedido' y reducir el stock
            foreach ($productos as $producto) {
                $id_producto = $producto['id_producto'];
                $cantidad = $producto['cantidad'];
                $precio_unitario = $producto['precio'];
                $precio_total = $precio_unitario * $cantidad;

                $stmt = $conn->prepare("INSERT INTO detalles_pedido (id_pedido, id_producto, cantidad, precio_unitario, precio_total, activo) VALUES (?, ?, ?, ?, ?, 1)");
                $stmt->bind_param("iiidd", $id_pedido, $id_producto, $cantidad, $precio_unitario, $precio_total);

                if (!$stmt->execute()) {
                    throw new Exception("Error al insertar los detalles del pedido");
                }
                $stmt = $conn->prepare("UPDATE productos SET cantidad_stock = cantidad_stock - ? WHERE id_producto = ?");
                $stmt->bind_param("ii", $cantidad, $id_producto);

                if (!$stmt->execute()) {
                    throw new Exception("Error al actualizar el stock del producto ID $id_producto");
                }
            }

            $conn->commit();

            $stmt = $conn->prepare("SELECT u.correo_electronico FROM clientes c inner join usuarios u on u.id_persona = c.id_persona WHERE id_cliente = ?");
            $stmt->bind_param("i", $id_cliente);
            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $cliente = $result->fetch_assoc();
                $email_cliente = $cliente['correo_electronico'];

                enviarCorreoPedido($email_cliente, $codigo_seguimiento, "En proceso", 'crear');
            } else {
                echo "No se encontró el cliente con ID $id_cliente";
            }

            echo json_encode(["mensaje" => "Pedido creado exitosamente", "id_pedido" => $id_pedido, "codigo" => $codigo_seguimiento]);

        } catch (Exception $e) {
            $conn->rollback();
            http_response_code(500);
            echo json_encode(["mensaje" => $e->getMessage()]);
        }

        $stmt->close();
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
        $id_perfil = 1;

        // Verificar si el nombre de usuario o correo ya están en uso
        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE nombre_usuario = ? OR correo_electronico = ?");
        $stmt->bind_param("ss", $nombre_usuario, $correo_electronico);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            http_response_code(400);
            echo json_encode(["mensaje" => "El nombre de usuario o el correo electrónico ya están en uso"]);
        } else {
            $id_empresa = null;
            // Manejo de la empresa
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

            // Inserción de la persona
            $stmt = $conn->prepare("INSERT INTO persona (primer_nombre, segundo_nombre, primer_apellido, segundo_apellido, telefono, fecha_nacimiento, id_empresa) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssi", $data['primer_nombre'], $data['segundo_nombre'], $data['primer_apellido'], $data['segundo_apellido'], $data['telefono'], $data['fecha_nacimiento'], $id_empresa );
            
            if ($stmt->execute()) {
                $id_persona = $conn->insert_id;

                // Inserción de la dirección
                $stmt = $conn->prepare("INSERT INTO direccion_persona (id_persona, direccion, codigo_postal, id_municipio, id_departamento, id_pais) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("issiii", $id_persona, $data['direccion'], $data['codigo_postal'], $data['id_municipio'], $data['id_departamento'], $data['id_pais']);

                if ($stmt->execute()) {
                    // Inserción del usuario
                    $contrasena = password_hash($data['contrasena'], PASSWORD_BCRYPT);
                    $stmt = $conn->prepare("INSERT INTO usuarios (nombre_usuario, contrasena, correo_electronico, id_persona, id_perfil) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssii", $nombre_usuario, $contrasena, $correo_electronico, $id_persona, $id_perfil); 

                    if ($stmt->execute()) {
                        $stmt = $conn->prepare("INSERT INTO clientes (id_persona, nit, activo) VALUES (?, ?, 1)");
                        $stmt->bind_param("is", $id_persona, $data['nit']); 
                        $stmt->execute();

                        echo json_encode(["mensaje" => "Usuario creado exitosamente"]);
                    } else {
                        http_response_code(500);
                        echo json_encode(["mensaje" => "Error al crear el usuario"]);
                    }
                } else {
                    http_response_code(500);
                    echo json_encode(["mensaje" => "Error al registrar la dirección de la persona"]);
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
        $session_hash = bin2hex(random_bytes(32));

        $stmt = $conn->prepare("UPDATE usuarios SET session_hash = ? WHERE id_usuario = ?");
        $stmt->bind_param("si", $session_hash, $usuario['id_usuario']);
        $stmt->execute();

        session_start();
        $_SESSION['session_hash'] = $session_hash;

        echo json_encode(["mensaje" => "Login exitoso", "session_id" =>  $session_hash]);
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

    validarSesion();

    $data = json_decode(file_get_contents("php://input"), true);

    $estado = $data['estado'];
    $ubicacion = "Bodega";  // Ubicación para el tracking

    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare("SELECT id_estado FROM estado_pedido WHERE nombre = ?");
        $stmt->bind_param("s", $estado);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $estado = $result->fetch_assoc();
            $id_estado = $estado['id_estado'];
        } else {
            throw new Exception("Estado no encontrado: $estado");
        }

        $stmt = $conn->prepare("UPDATE pedidos SET id_estado = ? WHERE id_pedido = ?");
        $stmt->bind_param("ii", $id_estado, $id);

        if (!$stmt->execute()) {
            throw new Exception("Error al actualizar el estado del pedido ID $id_pedido");
        }

        $stmt = $conn->prepare("INSERT INTO tracking (id_pedido, id_estado, ubicacion, activo, fecha_hora) VALUES (?, ?, ?, 1, NOW())");
        $stmt->bind_param("iis", $id, $id_estado, $ubicacion);

        if (!$stmt->execute()) {
            throw new Exception("Error al insertar el registro de tracking");
        }


        $stmt = $conn->prepare("SELECT u.correo_electronico, p.codigo_seguimiento FROM pedidos p  inner join clientes c on c.id_cliente = p.id_cliente inner join usuarios u on u.id_persona = c.id_persona  WHERE id_pedido = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $pedido = $result->fetch_assoc();
            $email_cliente = $pedido['correo_electronico'];
            $codigo_seguimiento = $pedido['codigo_seguimiento'];

            enviarCorreoPedido($email_cliente, $codigo_seguimiento,  $data['estado'] , 'actualizar');
        } else {
            throw new Exception("Pedido no encontrado: $id_pedido");
        }


        $conn->commit();

        echo json_encode(["mensaje" => "Pedido y tracking actualizados exitosamente"]);

    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(["mensaje" => $e->getMessage()]);
    }

    $stmt->close();

}

function handleClientes($method) {
    global $conn;

    if ($method == 'GET') {
        if (isset($_GET['nit'])) {
            $nit_cliente = $_GET['nit'];

            $stmt = $conn->prepare("SELECT * FROM clientes WHERE nit = ?");
            $stmt->bind_param("s", $nit_cliente);
            $stmt->execute();
            $result = $stmt->get_result();
            $cliente = $result->fetch_assoc();

            if ($cliente) {
                echo json_encode($cliente);
            } else {
                http_response_code(404);
                echo json_encode(["mensaje" => "Cliente no encontrado"]);
            }

            $stmt->close();
        } else {
            http_response_code(400);
            echo json_encode(["mensaje" => "El NIT no fue proporcionado"]);
        }
    } else {
        http_response_code(405);
        echo json_encode(["mensaje" => "Método no permitido"]);
    }
}


function obtenerTrackingPedido($method) {
    global $conn;

    if ($method == 'POST'){

        $data = json_decode(file_get_contents("php://input"), true);

        $codigo_seguimiento = $data['codigo'];

        $stmt = $conn->prepare("
            SELECT ep.nombre AS nombre_estado, t.fecha_hora
            FROM tracking t
            INNER JOIN estado_pedido ep ON t.id_estado = ep.id_estado
            INNER JOIN pedidos p ON t.id_pedido = p.id_pedido
            WHERE p.codigo_seguimiento = ?
            ORDER BY t.fecha_hora ASC
        ");
        
        $stmt->bind_param("s", $codigo_seguimiento);
        $stmt->execute();
        
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $tracking_data = [];

            while ($row = $result->fetch_assoc()) {
                $tracking_data[] = [
                    "estado" => $row['nombre_estado'],
                    "fecha_hora" => $row['fecha_hora']
                ];
            }

            echo json_encode([
                "mensaje" => "Historial de tracking encontrado",
                "tracking" => $tracking_data
            ]);

        } else {
            echo json_encode([
                "mensaje" => "No se encontró historial de tracking para el código de seguimiento $codigo_seguimiento"
            ]);
        }

        $stmt->close();
    }
}

function validarSesion() {
    global $conn;

    session_start();

    if (!isset($_SESSION['session_hash'])) {
        http_response_code(403);
        echo json_encode(["mensaje" => "Sesión no válida"]);
        exit;
    }

    $session_hash = $_SESSION['session_hash'];

    // Comprobar si el hash de sesión existe en la base de datos
    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE session_hash = ?");
    $stmt->bind_param("s", $session_hash);
    $stmt->execute();
    $result = $stmt->get_result();
    $usuario = $result->fetch_assoc();

    if (!$usuario) {
        http_response_code(403);
        echo json_encode(["mensaje" => "Sesión no válida"]);
        exit;
    }

    // Si se valida correctamente, la sesión es válida
    //echo json_encode(["mensaje" => "Sesión válida", "usuario" => $usuario['nombre_usuario']]);
    
    $stmt->close();
}

function handleLogout() {
    global $conn;

    session_start();

    if (!isset($_SESSION['session_hash'])) {
        http_response_code(403);
        echo json_encode(["mensaje" => "No hay sesión activa"]);
        exit;
    }

    $session_hash = $_SESSION['session_hash'];

    $stmt = $conn->prepare("UPDATE usuarios SET session_hash = NULL WHERE session_hash = ?");
    $stmt->bind_param("s", $session_hash);

    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(["mensaje" => "Error al cerrar sesión en la base de datos"]);
        exit;
    }

    session_unset(); 
    session_destroy();

    echo json_encode(["mensaje" => "Sesión cerrada correctamente"]);
}

?>
