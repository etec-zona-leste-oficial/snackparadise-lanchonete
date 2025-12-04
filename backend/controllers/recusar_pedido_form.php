<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['motoboy'])) {
    echo json_encode(['success' => false, 'message' => 'Motoboy não autenticado']);
    exit();
}

include_once __DIR__ . '/../config/Conexao.php';
include_once __DIR__ . '/../config/DatabaseManager.php';

$input = json_decode(file_get_contents('php://input'), true);
$pedido_id = $input['pedido_id'] ?? null;

if (!$pedido_id) {
    echo json_encode(['success' => false, 'message' => 'ID do pedido não informado']);
    exit();
}

try {
    $db = new DatabaseManager();
    $motoboy_id = $_SESSION['motoboy']['id'];
    
    // Verificar se o pedido pertence ao motoboy
    $pedido = $db->getPedidoById($pedido_id);
    
    if (!$pedido) {
        throw new Exception('Pedido não encontrado');
    }
    
    if ($pedido['motoboy_id'] != $motoboy_id) {
        throw new Exception('Este pedido não está atribuído a você');
    }
    
    // Iniciar transação
    $conn = Conectar::getInstance();
    $conn->beginTransaction();
    
    // Remover motoboy do pedido e voltar status para "pronto"
    $sql = $conn->prepare("UPDATE pedidos SET motoboy_id = NULL, status = 'pronto' WHERE id = ?");
    $sql->execute([$pedido_id]);
    
    // Registrar a recusa no histórico
    $sql = $conn->prepare("INSERT INTO pedido_historico (pedido_id, motoboy_id, acao, descricao) VALUES (?, ?, 'recusado', 'Pedido recusado pelo motoboy')");
    $sql->execute([$pedido_id, $motoboy_id]);
    
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Pedido recusado com sucesso']);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollBack();
    }
    error_log("Erro ao recusar pedido: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>