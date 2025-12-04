<?php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: ../../frontend/Tela de login/index.php");
    exit();
}

include_once '../../backend/config/DatabaseManager.php';

$db = new DatabaseManager();
$user = $_SESSION['user'];

// Recarrega dados mais recentes do usuário
$userData = $db->getUserById($user['id']);
if ($userData) {
    $user = array_merge($user, $userData);
    $_SESSION['user'] = $user;
}

// --------------------------------------------------
// PAGINAÇÃO (Opção A: Paginação real)
// --------------------------------------------------
$itemsPerPage = 6;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $itemsPerPage;

// Tenta usar métodos do DatabaseManager (recomendado).
// Se eles não existirem, usaremos fallback (paginação em PHP).
$useDbPagination = method_exists($db, 'getUserOrdersPaginated') && method_exists($db, 'countUserOrders');

if ($useDbPagination) {
    $orders = $db->getUserOrdersPaginated($user['id'], $itemsPerPage, $offset);
    $totalOrders = (int)$db->countUserOrders($user['id']);
} else {
    // Fallback: carrega todos e pagina em PHP (menos eficiente)
    $allOrders = $db->getUserOrders($user['id']) ?? [];
    $totalOrders = count($allOrders);
    $orders = array_slice($allOrders, $offset, $itemsPerPage);
}

$totalPages = max(1, (int)ceil($totalOrders / $itemsPerPage));

// Buscar histórico de entregas
$deliveries = $db->getDeliveryHistory($user['id']);

// Buscar entrega ativa (se houver) — função usada no seu código original
$activeDelivery = method_exists($db, 'getUserActiveDelivery') ? $db->getUserActiveDelivery($user['id']) : null;

// Buscar avaliações
$reviews = $db->getUserReviews($user['id']);

// Mensagens
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// (opcional) Buscar todos os pedidos para admins/visualização (não usado diretamente)
$allPedidos = method_exists($db, 'getAllPedidos') ? $db->getAllPedidos() : [];

// --------------------------------------------------
// Helper: parse itens (coluna 'itens' no DB é JSON)
// --------------------------------------------------
function parse_order_items($itens_column) {
    // pode ser já array ou string JSON
    if (is_array($itens_column)) return $itens_column;
    $items = json_decode($itens_column, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($items)) return $items;

    // fallback: tentar unserialize
    $s = @unserialize($itens_column);
    if ($s !== false && is_array($s)) return $s;

    // fallback final: retornar string bruta
    return [$itens_column];
}

// --------------------------------------------------
// Instruções para adicionar as funções ao DatabaseManager (recomendado)
// --------------------------------------------------
/*
-- Adicione ao DatabaseManager (exemplo com PDO):

public function getUserOrdersPaginated($userId, $limit, $offset) {
    $stmt = $this->conn->prepare("
        SELECT p.*, u.username as cliente_nome
        FROM pedidos p
        LEFT JOIN users u ON u.id = p.usuario_id
        WHERE p.usuario_id = ?
        ORDER BY p.id DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue(3, (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function countUserOrders($userId) {
    $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM pedidos WHERE usuario_id = ?");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return (int)($row['total'] ?? 0);
}
*/
// --------------------------------------------------

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Perfil do Usuário - Snack Paradise</title>
    <link rel="stylesheet" href="style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="shortcut icon" href="../imgs/Logo.png" type="image/x-icon">
    <style>
        /* Pequeno ajuste visual local para a paginação (se você não tiver essas classes no CSS já) */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-top: 18px;
            align-items: center;
        }
        .page-btn {
            padding: 8px 12px;
            background: var(--cor-primaria);
            color: #fff;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
        }
        .page-btn[disabled] {
            opacity: 0.6;
            pointer-events: none;
        }
    </style>
</head>
<body>
    <header>
        <div class="header-left">
            <button class="btn-menu-lateral" id="btnMenuLateral">☰</button>
            <div class="logo-container">
                <a href="../Cardápio/index.php" class="logo">
                    <img src="../imgs/Logo.png" class="logo" alt="Snack Paradise Logo">
                </a>
            </div>
        </div>

        <div class="header-center">
            <a href="../Cardápio/index.php" class="menu-item">Menu</a>
            <div class="menu-item cardapio-btn" id="cardapioBtn">
                Cardápio
                <div class="submenu" id="submenu">
                    <a href="../Cardápio/menu.php#subheader2" class="submenu-item">Hambúrgueres</a>
                    <a href="../Cardápio/menu.php#acompanhamentos" class="submenu-item">Acompanhamentos</a>
                    <a href="../Cardápio/menu.php#bebidas" class="submenu-item">Bebidas</a>
                </div>
            </div>
            <a href="../Acumular Pontos/pontos.html" class="menu-item">Promoções</a>
            <a href="../Quem somos/index.php" class="menu-item">Sobre Nós</a>
        </div>

        <a href="../Tela de Login/index.php" class="btn-conta">Sair</a>
    </header>

    <!-- Menu Lateral -->
    <nav class="menu-lateral" id="menuLateral">
        <a href="../Cardápio/index.php" class="menu-lateral-item">Início</a>
        <a href="../PerfilUser/index.php" class="menu-lateral-item">Perfil</a>
        <a href="../Acumular Pontos/pontos.html" class="menu-lateral-item active">Pontos</a>
        <a href="../SejaParceiro/index.php" class="menu-lateral-item">Seja Parceiro</a>
        <a href="../Feedback/index.php" class="menu-lateral-item">Avaliações</a>
        <a href="../Quem somos/index.php" class="menu-lateral-item">Sobre nós</a>
        <a href="../Duvidas/index.php" class="menu-lateral-item">Dúvidas</a>
        <a href="../Auxílio Preferencial/auxilio.php" class="menu-lateral-item">Auxílio Preferencial</a>
    </nav>

    <main>
        <div class="main-container">
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="profile-box">
                <div class="profile-header">
                    <div class="logo2">
                        <img src="../imgs/Logo.png" alt="SnackParadiseLogo">
                        <h4>SnackParadise</h4>
                    </div>
                    <h1 class="profile-title">Meu Perfil</h1>
                </div>

                <div class="profile-content">
                    <!-- Coluna esquerda: avatar -->
                    <div class="profile-avatar">
                        <div class="avatar-circle">
                            <?php if (!empty($user['profile_picture'])): ?>
                                <img src="../../backend/uploads/profiles/<?php echo htmlspecialchars($user['profile_picture']); ?>?t=<?php echo time(); ?>" 
                                    alt="Foto de perfil" 
                                    style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                            <?php else: ?>
                                <i class='bx bxs-user'></i>
                            <?php endif; ?>
                        </div>
                        <button class="btn-change-photo" onclick="document.getElementById('profilePicture').click();">
                            <i class='bx bx-camera'></i>
                            Alterar Foto
                        </button>
                        <input type="file" id="profilePicture" name="profilePicture" accept="image/png, image/jpeg" style="display: none;" onchange="uploadProfilePicture(event)">
                    </div>

                    <!-- Coluna direita: forms / pedidos / entrega -->
                    <div class="profile-forms">
                        <!-- Informações Pessoais -->
                        <div class="profile-section">
                            <h3 class="section-title">
                                <i class='bx bx-user-circle'></i>
                                Informações Pessoais
                            </h3>
                            
                            <form class="profile-form" id="personal-form" method="POST" action="../../backend/controllers/ProfileController.php">
                                <input type="hidden" name="action" value="update_personal_info">
                                <div class="form-row">
                                    <div class="input-wrap">
                                        <input type="text" class="input-field" id="username" name="username" 
                                            value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" readonly />
                                        <label>Username</label>
                                    </div>
                                    <div class="input-wrap">
                                        <input type="email" class="input-field" id="email" name="email" 
                                            value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" readonly />
                                        <label>E-mail</label>
                                    </div>
                                </div>

                                <div class="form-actions">
                                    <button type="button" class="btn-edit" id="edit-personal" onclick="toggleEdit('personal')">
                                        <i class='bx bx-edit'></i>
                                        Editar
                                    </button>
                                    <button type="submit" class="btn-save hidden" id="save-personal">
                                        <i class='bx bx-check'></i>
                                        Salvar
                                    </button>
                                    <button type="button" class="btn-cancel hidden" id="cancel-personal" onclick="cancelEdit('personal')">
                                        <i class='bx bx-x'></i>
                                        Cancelar
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Endereço -->
                        <div class="profile-section">
                            <h3 class="section-title">
                                <i class='bx bx-map'></i>
                                Endereço de Entrega
                            </h3>
                            
                            <form class="profile-form" id="address-form" method="POST" action="../../backend/controllers/ProfileController.php">
                                <input type="hidden" name="action" value="update_address">
                                <div class="form-row">
                                    <div class="input-wrap">
                                        <input type="text" class="input-field" id="cep" name="cep" 
                                            value="<?php echo htmlspecialchars($user['cep'] ?? ''); ?>" readonly />
                                        <label>CEP</label>
                                    </div>
                                    <div class="input-wrap">
                                        <input type="text" class="input-field" id="endereco" name="endereco" 
                                            value="<?php echo htmlspecialchars($user['endereco'] ?? $user['rua'] ?? ''); ?>" readonly />
                                        <label>Endereço</label>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="input-wrap">
                                        <input type="text" class="input-field" id="bairro" name="bairro" value="<?php echo htmlspecialchars($user['bairro'] ?? ''); ?>" readonly />
                                        <label>Bairro</label>
                                    </div>
                                    <div class="input-wrap">
                                        <input type="text" class="input-field" id="cidade" name="cidade" value="<?php echo htmlspecialchars($user['cidade'] ?? ''); ?>" readonly />
                                        <label>Cidade</label>
                                    </div>
                                </div>

                                <div class="form-actions">
                                    <button type="button" class="btn-edit" id="edit-address" onclick="toggleEdit('address')">
                                        <i class='bx bx-edit'></i>
                                        Editar
                                    </button>
                                    <button type="submit" class="btn-save hidden" id="save-address">
                                        <i class='bx bx-check'></i>
                                        Salvar
                                    </button>
                                    <button type="button" class="btn-cancel hidden" id="cancel-address" onclick="cancelEdit('address')">
                                        <i class='bx bx-x'></i>
                                        Cancelar
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Alterar Senha -->
                        <div class="profile-section">
                            <h3 class="section-title">
                                <i class='bx bx-lock'></i>
                                Segurança
                            </h3>
                            
                            <form class="profile-form" id="password-form" method="POST" action="../../backend/controllers/ProfileController.php">
                                <input type="hidden" name="action" value="change_password">
                                <div class="form-row">
                                    <div class="input-wrap">
                                        <input type="password" class="input-field" id="senha-atual" name="senha-atual" />
                                        <label>Senha Atual</label>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="input-wrap">
                                        <input type="password" class="input-field" id="nova-senha" name="nova-senha" />
                                        <label>Nova Senha</label>
                                    </div>
                                    <div class="input-wrap">
                                        <input type="password" class="input-field" id="confirmar-senha" name="confirmar-senha" />
                                        <label>Confirmar Nova Senha</label>
                                    </div>
                                </div>

                                <div class="form-actions">
                                    <button type="submit" class="btn-change-password">
                                        <i class='bx bx-key'></i>
                                        Alterar Senha
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Histórico de Pedidos -->
                        <div class="profile-section">
                            <h3 class="section-title">
                                <i class='bx bx-history'></i>
                                Meus Pedidos
                            </h3>

                            <div class="orders-container" id="orders-container">
                                <?php if (empty($orders)): ?>
                                    <p>Nenhum pedido registrado.</p>
                                <?php else: ?>
                                    <?php foreach ($orders as $order): ?>
                                        <div class="order-item order-card">
                                            <div class="order-header">
                                                <div>
                                                    <div class="order-number">Pedido #<?php echo htmlspecialchars($order['id']); ?></div>
                                                    <div class="order-date"><?php echo htmlspecialchars($order['criado_em']); ?></div>
                                                </div>
                                                <div class="order-status <?php echo 'status-' . htmlspecialchars($order['status']); ?>">
                                                    <?php echo htmlspecialchars(ucfirst(str_replace('_',' ', $order['status']))); ?>
                                                </div>
                                            </div>

                                            <p><strong>Cliente:</strong> <?php echo htmlspecialchars($order['cliente_nome'] ?? ($user['username'] ?? '—')); ?></p>

                                            <div class="order-items"><strong>Itens:</strong>
                                                <ul style="margin: 6px 0 0 0; padding-left: 18px;">
                                                    <?php
                                                        $items = parse_order_items($order['itens'] ?? $order['itens_array'] ?? '[]');
                                                        foreach ($items as $it) {
                                                            if (is_array($it)) {
                                                                $q = $it['quantidade'] ?? ($it['qty'] ?? 1);
                                                                $n = $it['nome'] ?? $it['produto'] ?? $it['name'] ?? 'Item';
                                                                $p = isset($it['preco']) ? ' - R$ ' . number_format($it['preco'], 2, ',', '.') : '';
                                                                echo '<li>' . htmlspecialchars($q . 'x ' . $n . $p) . '</li>';
                                                            } else {
                                                                echo '<li>' . htmlspecialchars((string)$it) . '</li>';
                                                            }
                                                        }
                                                    ?>
                                                </ul>
                                            </div>

                                            <?php if (isset($order['total'])): ?>
                                                <p class="order-total"><strong>Total:</strong> R$ <?php echo number_format($order['total'], 2, ',', '.'); ?></p>
                                            <?php endif; ?>

                                            <p class="order-address"><strong>Endereço:</strong> <?php echo htmlspecialchars($order['endereco'] ?? '—'); ?> <br>
                                                <small>Método pagamento: <?php echo htmlspecialchars($order['pagamento'] ?? '—'); ?></small>
                                            </p>

                                            <div style="display:flex; gap:8px; align-items:center; margin-top:8px;">
                                                <?php if (!in_array($order['status'], ['cancelado', 'entregue', 'em_entrega'])): ?>
                                                    <a href="editar_pedido.php?id=<?php echo htmlspecialchars($order['id']); ?>" class="btn-edit">Editar Pedido</a>
                                                    <form method="POST" action="../../backend/controllers/cancelar_pedido_form.php" onsubmit="return confirm('Deseja realmente cancelar o pedido #'+<?php echo json_encode($order['id']); ?>+'?');" style="margin:0;">
                                                        <input type="hidden" name="pedido_id" value="<?php echo htmlspecialchars($order['id']); ?>">
                                                        <button type="submit" class="cancelpedido">Cancelar Pedido</button>
                                                    </form>
                                                <?php else: ?>
                                                    <button class="cancelpedido" disabled>Cancelar</button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <!-- Paginação -->
                            <?php if ($totalPages > 1): ?>
                                <div class="pagination" aria-label="Paginação de pedidos">
                                    <?php if ($page > 1): ?>
                                        <a class="page-btn" href="?page=<?php echo $page - 1; ?>">← Anterior</a>
                                    <?php else: ?>
                                        <span class="page-btn" aria-disabled="true" style="opacity:.6; pointer-events:none;">← Anterior</span>
                                    <?php endif; ?>

                                    <span> Página <?php echo $page; ?> de <?php echo $totalPages; ?> </span>

                                    <?php if ($page < $totalPages): ?>
                                        <a class="page-btn" href="?page=<?php echo $page + 1; ?>">Próxima →</a>
                                    <?php else: ?>
                                        <span class="page-btn" aria-disabled="true" style="opacity:.6; pointer-events:none;">Próxima →</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                        </div> <!-- fim profile-section pedidos -->

                        <!-- Acompanhar Entrega (separado dos pedidos) -->
                        <div class="profile-section delivery-section">
                            <h3 class="section-title">
                                <i class='bx bx-map-pin'></i>
                                Acompanhar Entrega
                            </h3>

                            <div id="tracking-container">
                                <?php if ($activeDelivery && ($activeDelivery['status'] ?? '') === 'em_entrega'): ?>
                                    <div class="delivery-card">
                                        <h4>Seu pedido está a caminho! 🛵</h4>
                                        <p><strong>Motoboy:</strong> <?php echo htmlspecialchars($activeDelivery['motoboy_name'] ?? ($activeDelivery['name'] ?? '—')); ?></p>
                                        <p><strong>Status:</strong> Em entrega</p>
                                        <p><strong>Previsão:</strong> 15-25 minutos</p>

                                        <div class="delivery-progress">
                                            <div class="progress-bar" aria-hidden="true">
                                                <div class="progress-fill" style="width: 70%;"></div>
                                            </div>
                                            <div class="progress-labels">
                                                <span>Preparando</span>
                                                <span>Saiu para entrega</span>
                                                <span>Entregue</span>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <p>Nenhuma entrega em andamento no momento.</p>
                                <?php endif; ?>
                            </div>
                        </div> <!-- fim delivery-section -->

                    </div> <!-- fim profile-forms -->
                </div> <!-- fim profile-content -->
            </div> <!-- fim profile-box -->
        </div> <!-- fim main-container -->
    </main>

    <footer>
        <div class="footer-container">
            <div class="footer-links">
                <a href="../Menu/index.php">Início</a>
                <a href="../Quem somos/index.php">Sobre</a>
                <a href="../Auxílio Preferencial/auxilio.php">Serviços</a>
                <a href="https://www.instagram.com/_snackparadise_/profilecard/?igsh=OHh2eWpsOXBuOWRp">Contato</a>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 SnackParadise. Todos os direitos reservados.</p>
            </div>
        </div>
    </footer>

    <!-- VLibras -->
    <div vw class="enabled">
        <div vw-access-button class="active"></div>
        <div vw-plugin-wrapper>
            <div class="vw-plugin-top-wrapper"></div>
        </div>
    </div>
    <script src="https://vlibras.gov.br/app/vlibras-plugin.js"></script>
    <script>
        new window.VLibras.Widget('https://vlibras.gov.br/app');

        // Funções JS utilitárias (toggle edição e upload foto)
        function toggleEdit(section) {
            const form = document.getElementById(section + '-form');
            const inputs = form.querySelectorAll('.input-field');
            inputs.forEach(i => i.removeAttribute('readonly'));
            document.getElementById('edit-' + section).classList.add('hidden');
            document.getElementById('save-' + section).classList.remove('hidden');
            document.getElementById('cancel-' + section).classList.remove('hidden');
        }
        function cancelEdit(section) {
            // recarrega a página (simples e seguro) para reverter valores
            location.reload();
        }

        function uploadProfilePicture(event) {
            // envio simples via fetch -> você deve implementar o endpoint ../../backend/controllers/upload_profile.php
            const file = event.target.files[0];
            if (!file) return;
            const form = new FormData();
            form.append('profilePicture', file);
            fetch('../../backend/controllers/upload_profile.php', {
                method: 'POST',
                body: form
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Falha ao enviar imagem');
                }
            }).catch(e => alert('Erro ao enviar imagem'));
        }
    </script>

    <script src="script.js"></script>
</body>
</html>
