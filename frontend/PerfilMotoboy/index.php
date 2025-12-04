<?php
session_start();
include_once '../../backend/config/DatabaseManager.php';

$db = new DatabaseManager();
$motoboyId = isset($_SESSION['motoboy']['id']) ? $_SESSION['motoboy']['id'] : 1;

// Configuração de paginação
$paginaAtual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$pedidosPorPagina = 12;
$filtroStatus = $_GET['status'] ?? 'ativos';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : ($pedidosPorPagina * 2);

// Buscar pedidos com filtros
if ($filtroStatus === 'todos') {
    $allPedidos = $db->getAllPedidosPaginated($paginaAtual, $pedidosPorPagina);
    $totalPedidos = $db->getTotalPedidosAtivos();
} else {
    $allPedidos = $db->getPedidosFiltrados($filtroStatus, $limit);
    $totalPedidos = count($allPedidos);
}

$totalPaginas = ceil($totalPedidos / $pedidosPorPagina);
$pedidosMotoboy = $motoboyId ? $db->getPedidosByMotoboy($motoboyId) : [];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil do Motoboy - Snack Paradise</title>
    <link rel="stylesheet" href="style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="shortcut icon" href="../imgs/Logo.png" type="image/x-icon">
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

        <a href="../../backend/controllers/logout.php" class="btn-conta">Sair</a>
    </header>

    <!-- Menu Lateral -->
    <nav class="menu-lateral" id="menuLateral">
        <a href="../Cardápio/index.php" class="menu-lateral-item">Início</a>
        <a href="../PerfilMotoboy/index.php" class="menu-lateral-item active">Perfil</a>
        <a href="../Acumular Pontos/pontos.html" class="menu-lateral-item">Pontos</a>
        <a href="../SejaParceiro/index.php" class="menu-lateral-item">Seja Parceiro</a>
    <a href="../Feedback/index.php" class="menu-lateral-item">Avaliações</a>
    <a href="../Quem somos/index.php" class="menu-lateral-item">Sobre nós</a>
    <a href="../Duvidas/index.php" class="menu-lateral-item">Dúvidas</a>
    <a href="../Auxílio Preferencial/auxilio.php" class="menu-lateral-item">Auxílio Preferencial</a>
    </nav>

    <main>
        <div class="main-container">
            <div class="profile-box">
                <div class="profile-header">
                    <div class="logo2">
                        <img src="../imgs/Logo.png" alt="SnackParadiseLogo">
                        <h4>SnackParadise Delivery</h4>
                    </div>
                    <h1 class="profile-title">Perfil do Motoboy</h1>
                </div>

                <div class="profile-content">
                    <div class="profile-avatar">
                        <div class="avatar-circle">
                            <?php if (!empty($motoboyData['profile_picture'])): ?>
                                <img src="../../backend/uploads/profiles/<?php echo htmlspecialchars($motoboyData['profile_picture']); ?>?t=<?php echo time(); ?>" 
                                    alt="Foto do perfil" 
                                    style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                            <?php else: ?>
                                <i class='bx bxs-user'></i>
                            <?php endif; ?>
                        </div>
                        <button class="btn-change-photo" onclick="changePhoto()">
                            <i class='bx bx-camera'></i>
                            Alterar Foto
                        </button>
                        <?php $status = $motoboyData['status'] ?? 'offline'; ?>
                        <div class="rider-status <?php echo htmlspecialchars($status); ?>" id="riderStatus">
                            <i class='bx bx-circle'></i>
                            <span id="riderStatusText"><?php echo htmlspecialchars(ucfirst($status)); ?></span>
                        </div>
                            <a class="btn-view-orders " role="button" href="../motoboyvision/index.php">
                                Ver Pedidos Disponíveis
                            </a>

                        <div class="rating-display">
                            <div class="stars">
                                <i class='bx bxs-star'></i>
                                <i class='bx bxs-star'></i>
                                <i class='bx bxs-star'></i>
                                <i class='bx bxs-star'></i>
                                <i class='bx bx-star'></i>
                            </div>
                            <span class="rating-number">4.2</span>
                        </div>
                    </div>

                    <div class="profile-forms">
                        <!-- Informações Pessoais -->
                        <div class="profile-section">
                            <h3 class="section-title">
                                <i class='bx bx-user-circle'></i>
                                Informações Pessoais
                            </h3>
                            
                            <form class="profile-form" id="personal-form" method="POST" action="../../backend/controllers/AccountController.php">
                                <input type="hidden" name="action" value="update_personal_info">
                                <div class="form-row">
                                    <div class="input-wrap">
                                        <input type="text" class="input-field" id="name" name="name" 
                                               value="<?php echo htmlspecialchars($motoboyData['name']); ?>" readonly />
                                        <label>Nome Completo</label>
                                    </div>
                                    <div class="input-wrap">
                                        <input type="email" class="input-field" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($motoboyData['email']); ?>" readonly />
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

                        <!-- Informações do Veículo -->
                        <div class="profile-section">
                            <h3 class="section-title">
                                <i class='bx bxs-truck'></i>
                                Informações do Veículo
                            </h3>
                            
                            <form class="profile-form" id="vehicle-form" method="POST" action="../../backend/controllers/AccountController.php">
                                <input type="hidden" name="action" value="update_vehicle_info">
                                <div class="form-row">
                                    <div class="input-wrap">
                                        <select class="input-field" id="vehicle_type" name="vehicle_type" disabled>
                                            <option value="motocicleta" <?php echo ($motoboyData['vehicle_type'] ?? 'motocicleta') === 'motocicleta' ? 'selected' : ''; ?>>Motocicleta</option>
                                            <option value="bicicleta" <?php echo ($motoboyData['vehicle_type'] ?? '') === 'bicicleta' ? 'selected' : ''; ?>>Bicicleta</option>
                                            <option value="carro" <?php echo ($motoboyData['vehicle_type'] ?? '') === 'carro' ? 'selected' : ''; ?>>Carro</option>
                                        </select>
                                        <label>Tipo de Veículo</label>
                                    </div>
                                    <div class="input-wrap">
                                        <input type="text" class="input-field" id="license_plate" name="license_plate" 
                                               value="<?php echo htmlspecialchars($motoboyData['license_plate'] ?? ''); ?>" readonly />
                                        <label>Placa do Veículo</label>
                                    </div>
                                </div>

                                <div class="form-actions">
                                    <button type="button" class="btn-edit" id="edit-vehicle" onclick="toggleEdit('vehicle')">
                                        <i class='bx bx-edit'></i>
                                        Editar
                                    </button>
                                    <button type="submit" class="btn-save hidden" id="save-vehicle">
                                        <i class='bx bx-check'></i>
                                        Salvar
                                    </button>
                                    <button type="button" class="btn-cancel hidden" id="cancel-vehicle" onclick="cancelEdit('vehicle')">
                                        <i class='bx bx-x'></i>
                                        Cancelar
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Estatísticas REAIS -->
                        <div class="profile-section">
                            <h3 class="section-title">
                                <i class='bx bx-bar-chart-alt-2'></i>
                                Estatísticas
                            </h3>
                            
                            <div class="stats-grid">
                                <div class="stat-item">
                                    <div class="stat-number"><?php echo $stats['total_entregas'] ?? '0'; ?></div>
                                    <div class="stat-label">Entregas Realizadas</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number">4.2</div>
                                    <div class="stat-label">Avaliação Média</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number"><?php echo $stats['taxa_sucesso'] ?? '0'; ?>%</div>
                                    <div class="stat-label">Taxa de Sucesso</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number">R$ <?php echo number_format($stats['ganhos_totais'] ?? 0, 2, ',', '.'); ?></div>
                                    <div class="stat-label">Ganhos Totais</div>
                                </div>
                            </div>
                        </div>

                        <!-- Alterar Senha -->
                        <div class="profile-section">
                            <h3 class="section-title">
                                <i class='bx bx-lock'></i>
                                Segurança
                            </h3>
                            
                            <form class="profile-form" id="password-form" method="POST" action="../../backend/controllers/AccountController.php">
                                <input type="hidden" name="action" value="change_password">
                                <div class="form-row">
                                    <div class="input-wrap">
                                        <input type="password" class="input-field" id="current_password" name="current_password" required />
                                        <label>Senha Atual</label>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="input-wrap">
                                        <input type="password" class="input-field" id="new_password" name="new_password" required />
                                        <label>Nova Senha</label>
                                    </div>
                                    <div class="input-wrap">
                                        <input type="password" class="input-field" id="confirm_password" name="confirm_password" required />
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
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="footer-container">
            <div class="footer-links">
                <a href="../Cardápio/index.php">Início</a>
                <a href="../Quem somos/index.php">Sobre</a>
                <a href="#">Suporte</a>
                <a href="#">Contato</a>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 SnackParadise Delivery. Todos os direitos reservados.</p>
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
    
    <script src="script.js"></script>
    <script src="https://vlibras.gov.br/app/vlibras-plugin.js"></script>
    <script>
        new window.VLibras.Widget('https://vlibras.gov.br/app');
    </script>
</body>
</html>