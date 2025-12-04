<?php
session_start();
include_once __DIR__ . '/../config/DatabaseManager.php';

if (!isset($_SESSION['motoboy'])) {
    header('Location: ../../frontend/Tela de login/index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../frontend/motoboyvision/index.php?error=Método não permitido');
    exit();
}

$motoboy_id = $_SESSION['motoboy']['id'];
$pedido_id = $_POST['pedido_id'] ?? null;

if (!$pedido_id) {
    header('Location: ../../frontend/motoboyvision/index.php?error=Pedido não especificado');
    exit();
}

$db = new DatabaseManager();

try {
    // Verificar se pedido ainda está disponível
    if (!$db->isPedidoDisponivel($pedido_id)) {
        header('Location: ../../frontend/motoboyvision/index.php?error=Pedido já foi aceito por outro motoboy');
        exit();
    }

    // Verificar limite de pedidos
    if (!$db->canMotoboyAcceptOrder($motoboy_id)) {
        header('Location: ../../frontend/motoboyvision/index.php?error=Você já tem muitos pedidos ativos (máximo: 3)');
        exit();
    }

    // Aceitar pedido
    $sql = "UPDATE pedidos SET motoboy_id = :motoboy_id, status = 'em_entrega' WHERE id = :pedido_id";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':motoboy_id', $motoboy_id, PDO::PARAM_INT);
    $stmt->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);

    if ($stmt->execute()) {

        // Registrar histórico
        $sql_hist = "INSERT INTO pedido_historico (pedido_id, status_anterior, status_novo, descricao) 
                     VALUES (:pedido_id, 'pronto', 'em_entrega', 'Pedido aceito pelo motoboy')";
        $stmt_hist = $db->prepare($sql_hist);
        $stmt_hist->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
        $stmt_hist->execute();

        header('Location: ../../frontend/motoboyvision/index.php?success=Pedido aceito com sucesso');
        exit();

    } else {
        header('Location: ../../frontend/motoboyvision/index.php?error=Erro ao aceitar pedido');
        exit();
    }

} catch (Exception $e) {
    error_log("Erro ao aceitar pedido: " . $e->getMessage());
    header('Location: ../../frontend/motoboyvision/index.php?error=Erro interno do sistema');
    exit();
}
?>
