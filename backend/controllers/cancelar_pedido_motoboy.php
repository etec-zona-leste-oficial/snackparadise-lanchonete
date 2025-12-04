<?php
session_start();
if (!isset($_SESSION['motoboy'])) {
    header('Location: ../../frontend/Tela de login/index.php');
    exit();
}

include_once __DIR__ . '/../config/Conexao.php';
include_once __DIR__ . '/../config/DatabaseManager.php';

$pedido_id = $_POST['pedido_id'] ?? null;
$motoboy_id = $_SESSION['motoboy']['id'];

if (!$pedido_id) {
    header('Location: ../../frontend/PerfilMotoboy/index.php?error=ID inválido');
    exit();
}

try {
    $conn = Conectar::getInstance();
    $conn->beginTransaction();

    // Verificar se o pedido existe e está com status "preparando"
    $sql = $conn->prepare("SELECT status FROM pedidos WHERE id = ?");
    $sql->execute([$pedido_id]);
    $pedido = $sql->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        throw new Exception('Pedido não encontrado');
    }

    if ($pedido['status'] !== 'preparando') {
        throw new Exception('Apenas pedidos em preparação podem ser cancelados');
    }

    // Atualizar status do pedido de volta para "pendente"
    $sql = $conn->prepare("UPDATE pedidos SET status = 'pendente' WHERE id = ?");
    $sql->execute([$pedido_id]);

    $conn->commit();
    header('Location: ../../frontend/PerfilMotoboy/index.php?success=Pedido cancelado com sucesso');
    exit();
} catch (Exception $e) {
    if (isset($conn)) $conn->rollBack();
    header('Location: ../../frontend/PerfilMotoboy/index.php?error=' . urlencode($e->getMessage()));
    exit();
}

?>
