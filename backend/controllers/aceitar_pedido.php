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
    
    // Verificar se o pedido está disponível
    $pedido = $db->getPedidoById($pedido_id);
    
    if (!$pedido) {
        throw new Exception('Pedido não encontrado');
    }
    
    if ($pedido['motoboy_id'] !== null && $pedido['motoboy_id'] != $motoboy_id) {
        throw new Exception('Pedido já foi atribuído a outro motoboy');
    }
    
    // Iniciar transação
    $conn = Conectar::getInstance();
    $conn->beginTransaction();
    
    // Atualizar pedido para "em_entrega"
    $sql = $conn->prepare("UPDATE pedidos SET motoboy_id = ?, status = 'em_entrega' WHERE id = ?");
    $sql->execute([$motoboy_id, $pedido_id]);
    
    // Registrar no histórico
    $sql = $conn->prepare("INSERT INTO pedido_historico (pedido_id, motoboy_id, acao, descricao) VALUES (?, ?, 'aceito', 'Pedido aceito para entrega')");
    $sql->execute([$pedido_id, $motoboy_id]);
    
    // Registrar na tabela de registro
    $sql = $conn->prepare("INSERT INTO registro (pedido_id, cliente_id, motoboy_id, itens, endereco, pagamento, confirmar) 
                          SELECT p.id, p.usuario_id, ?, p.itens, p.endereco, p.pagamento, 0 
                          FROM pedidos p WHERE p.id = ?");
    $sql->execute([$motoboy_id, $pedido_id]);
    
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Pedido aceito com sucesso. Status atualizado para "em_entrega".']);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollBack();
    }
    error_log("Erro ao aceitar pedido: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>