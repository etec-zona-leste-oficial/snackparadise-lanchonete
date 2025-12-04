<?php
session_start();
include_once __DIR__ . '/../config/DatabaseManager.php';

if (!isset($_SESSION['motoboy'])) {
    header('Location: ../../Tela de login/index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../motoboy_vision.php?error=Método não permitido');
    exit();
}

$motoboy_id = $_SESSION['motoboy']['id'];
$pedido_id = $_POST['pedido_id'] ?? null;

if (!$pedido_id) {
    header('Location: ../motoboy_vision.php?error=Pedido não especificado');
    exit();
}

$db = new DatabaseManager();

try {
    // Verificar se pedido pertence ao motoboy
    $sql_check = "SELECT id FROM pedidos WHERE id = :pedido_id AND motoboy_id = :motoboy_id AND status = 'em_entrega'";
    $stmt_check = $db->prepare($sql_check);
    $stmt_check->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
    $stmt_check->bindParam(':motoboy_id', $motoboy_id, PDO::PARAM_INT);
    $stmt_check->execute();
    
    if (!$stmt_check->fetch()) {
        header('Location: ../motoboy_vision.php?error=Pedido não encontrado ou não está em entrega');
        exit();
    }

    // Finalizar pedido
    $sql = "UPDATE pedidos SET status = 'entregue' WHERE id = :pedido_id";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        // Registrar no histórico
        $sql_hist = "INSERT INTO pedido_historico (pedido_id, status_anterior, status_novo, descricao) 
                     VALUES (:pedido_id, 'em_entrega', 'entregue', 'Pedido entregue pelo motoboy')";
        $stmt_hist = $db->prepare($sql_hist);
        $stmt_hist->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
        $stmt_hist->execute();
        
        // Registrar na tabela de registro
        $sql_reg = "INSERT INTO registro (pedido_id, motoboy_id, confirmar, criado_em) 
                    VALUES (:pedido_id, :motoboy_id, 1, NOW())";
        $stmt_reg = $db->prepare($sql_reg);
        $stmt_reg->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
        $stmt_reg->bindParam(':motoboy_id', $motoboy_id, PDO::PARAM_INT);
        $stmt_reg->execute();
        
        header('Location: ../motoboy_vision.php?success=Pedido finalizado com sucesso!');
    } else {
        header('Location: ../motoboy_vision.php?error=Erro ao finalizar pedido');
    }
} catch (Exception $e) {
    error_log("Erro ao finalizar pedido: " . $e->getMessage());
    header('Location: ../motoboy_vision.php?error=Erro interno do sistema');
}
exit();
?>