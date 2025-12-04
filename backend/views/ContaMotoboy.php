<?php
session_start();

if (!isset($_SESSION['motoboy'])) {
    header("Location: ../../frontend/Tela de login/cadastrar_motoboy.php");
    exit();
}

include_once __DIR__ . '/../config/DatabaseManager.php';
$db = new DatabaseManager();

$motoboy = $_SESSION['motoboy'];
$motoboyId = $motoboy['id'];

// Buscar pedidos com status "preparando" para este motoboy
$pedidosPreparando = [];
try {
    $conn = \Conectar::getInstance();
    $sql = $conn->prepare("SELECT p.*, u.username as cliente_nome FROM pedidos p LEFT JOIN users u ON p.usuario_id = u.id WHERE p.status = 'preparando' ORDER BY p.criado_em DESC");
    $sql->execute();
    $pedidosPreparando = $sql->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Erro ao buscar pedidos preparando: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Conta do Motoboy</title>
    <link rel="stylesheet" href="../public/Conta.css">
    <style>
        .pedidos-container {
            margin-top: 30px;
        }
        .pedido-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            background-color: #f9f9f9;
        }
        .pedido-card h3 {
            margin-top: 0;
            color: #333;
        }
        .pedido-info {
            margin: 10px 0;
            font-size: 14px;
        }
        .pedido-info strong {
            display: inline-block;
            min-width: 120px;
        }
        .pedido-itens {
            margin: 10px 0;
            padding: 10px;
            background-color: #fff;
            border-left: 3px solid #007bff;
        }
        .pedido-itens ul {
            margin: 5px 0;
            padding-left: 20px;
        }
        .pedido-itens li {
            font-size: 13px;
            margin: 3px 0;
        }
        .pedido-actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }
        .btn-cancelar-pedido {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
        }
        .btn-cancelar-pedido:hover {
            background-color: #c82333;
        }
        .no-pedidos {
            text-align: center;
            padding: 20px;
            color: #666;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="container">
        <img src="<?php echo isset($motoboy['profile_picture']) && file_exists($motoboy['profile_picture']) ? $motoboy['profile_picture'] : 'default-profile.png'; ?>" alt="Foto de Perfil" class="profile-picture">
        <h1>Bem-vindo, <?php echo htmlspecialchars($motoboy['name']); ?>!</h1>
        <p><strong>E-mail:</strong> <?php echo htmlspecialchars($motoboy['email']); ?></p>
        <p><strong>Tipo de Veículo:</strong> <?php echo htmlspecialchars($motoboy['vehicle_type']); ?></p>
        <p><strong>Placa:</strong> <?php echo htmlspecialchars($motoboy['license_plate']); ?></p>
        <a href="../controllers/logout.php" class="btn-voltar">Sair</a>
        <div class="upload-section">
            <form method="POST" action="upload.php" enctype="multipart/form-data">
                <input type="file" id="profilePicture" name="profilePicture">
                <label for="profilePicture">Alterar Foto</label>
                <button type="submit" class="btn-voltar">Salvar</button>
            </form>
        </div>

        <!-- Seção de Entregas Aceitas -->
        <div class="pedidos-container">
            <h2>Minhas Entregas em Preparação</h2>
            <?php if (empty($pedidosPreparando)): ?>
                <div class="no-pedidos">
                    Você ainda não aceitou nenhum pedido. Vá para <a href="../../frontend/motoboyvision/index.php">Motoboy Vision</a> para aceitar pedidos.
                </div>
            <?php else: ?>
                <?php foreach ($pedidosPreparando as $pedido): ?>
                    <div class="pedido-card">
                        <h3>Pedido #<?php echo htmlspecialchars($pedido['id']); ?></h3>
                        <div class="pedido-info">
                            <strong>Cliente:</strong> <?php echo htmlspecialchars($pedido['cliente_nome'] ?? 'Desconhecido'); ?>
                        </div>
                        <div class="pedido-info">
                            <strong>Endereço:</strong> <?php echo htmlspecialchars($pedido['endereco']); ?>
                        </div>
                        <div class="pedido-info">
                            <strong>Pagamento:</strong> <?php echo htmlspecialchars($pedido['pagamento']); ?>
                        </div>
                        <div class="pedido-info">
                            <strong>Data:</strong> <?php echo htmlspecialchars($pedido['criado_em']); ?>
                        </div>
                        
                        <!-- Itens do Pedido -->
                        <div class="pedido-itens">
                            <strong>Itens:</strong>
                            <ul>
                                <?php 
                                    $itens = json_decode($pedido['itens'], true);
                                    if (is_array($itens)) {
                                        foreach ($itens as $item) {
                                            echo '<li>' . htmlspecialchars($item['quantidade'] . 'x ' . $item['nome']) . '</li>';
                                        }
                                    } else {
                                        echo '<li>' . htmlspecialchars($pedido['itens']) . '</li>';
                                    }
                                ?>
                            </ul>
                        </div>

                        <!-- Botão para Cancelar Pedido -->
                        <div class="pedido-actions">
                            <form method="POST" action="../../backend/controllers/cancelar_pedido_motoboy.php" style="display:inline;">
                                <input type="hidden" name="pedido_id" value="<?php echo htmlspecialchars($pedido['id']); ?>">
                                <button type="submit" class="btn-cancelar-pedido">Cancelar Pedido</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
