<?php
session_start();
include_once '../../backend/config/DatabaseManager.php';
$db = new DatabaseManager();

// Recupera ID do motoboy da sessão
$motoboyId = isset($_SESSION['motoboy']['id']) ? $_SESSION['motoboy']['id'] : 1;

// Pedidos do motoboy logado
$pedidosMotoboy = $motoboyId ? $db->getPedidosByMotoboy($motoboyId) : [];

// Todos os pedidos (para área de admin/motoboy ver todos)
$allPedidos = $db->getAllPedidos();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Área Restrita - Snack Paradise</title>
  <link rel="stylesheet" href="style.css">
  <link rel="shortcut icon" href="../imgs/Logo.png" type="image/x-icon">
</head>
<body>
  <!-- HEADER -->
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
      <a href="../Menu/index.php" class="menu-item">Menu</a>
      <div class="menu-item cardapio-btn" id="cardapioBtn">
        Cardápio
        <div class="submenu" id="submenu">
          <a href="../Cardápio/index.php" class="submenu-item">Hambúrgueres</a>
          <a href="../Cardápio/index.php#acompanhamentos" class="submenu-item">Acompanhamentos</a>
          <a href="../Cardápio/index.php#bebidas" class="submenu-item">Bebidas</a>
        </div>
      </div>
      <a href="pontos.php" class="menu-item">Promoções</a>
      <a href="../Quem somos/index.php" class="menu-item">Sobre Nós</a>
    </div>

    <a href="../Tela de login/index.php" class="btn-conta">Conta</a>
  </header>

  <!-- MENU LATERAL -->
  <nav class="menu-lateral" id="menuLateral">
    <a href="../Menu/index.php" class="menu-lateral-item">Início</a>
    <a href="../PerfilUser/index.php" class="menu-lateral-item">Perfil</a>
    <a href="../Acumular Pontos/pontos.php" class="menu-lateral-item active">Pontos</a>
    <a href="../SejaParceiro/index.php" class="menu-lateral-item">Seja Parceiro</a>
    <a href="../Feedback/index.php" class="menu-lateral-item">Avaliações</a>
    <a href="../Quem somos/index.php" class="menu-lateral-item">Sobre nós</a>
    <a href="../Duvidas/index.php" class="menu-lateral-item">Dúvidas</a>
    <a href="../Auxílio Preferencial/auxilio.php" class="menu-lateral-item">Auxílio Preferencial</a>
  </nav>

  <!-- CONTEÚDO PRINCIPAL -->
  <div class="fundo">
    <main role="main">
      <div class="motomsg">
        

      <section class="area-restrita-section" aria-labelledby="titulo-area-restrita">
        <h1 id="titulo-area-restrita" style="display:none;">Área restrita - Gerenciamento de Pedidos</h1>

        <!-- TODOS OS PEDIDOS -->
        <div id="all-orders">
          <h2 class="tit">Todos os Pedidos</h2>
          <div class="orders-grid">
            <?php if (empty($allPedidos)): ?>
              <p style="color:orange;">Nenhum pedido encontrado.</p>
            <?php else: ?>
              <?php foreach ($allPedidos as $order): ?>
                <div class="order-card">
                  <h3>Pedido #<?php echo htmlspecialchars($order['id']); ?></h3>
                  <p><strong>Cliente:</strong> <?php echo htmlspecialchars($order['cliente_nome'] ?? '—'); ?></p>

                  <div><strong>Itens:</strong>
                    <ul style="margin:6px 0 0 18px; padding:0;">
                      <?php 
                        if (!empty($order['itens'])): 
                          $itens = json_decode($order['itens'], true);
                          if (is_array($itens)):
                            foreach ($itens as $item):
                              $nomeItem = $item['nome'] ?? $item['produto'] ?? 'Item';
                              $quantidade = $item['quantidade'] ?? 1;
                              $preco = isset($item['preco']) ? ' - R$ ' . number_format($item['preco'], 2, ',', '.') : '';
                              echo "<li>" . htmlspecialchars("{$quantidade}x {$nomeItem}{$preco}") . "</li>";
                            endforeach;
                          else:
                            echo "<li>" . htmlspecialchars($order['itens']) . "</li>";
                          endif;
                        endif;
                      ?>
                    </ul>
                  </div>

                  <p><strong>Total:</strong> R$ <?php echo isset($order['total']) ? number_format($order['total'], 2, ',', '.') : '—'; ?></p>
                  <p><strong>Endereço:</strong> <?php echo htmlspecialchars($order['endereco']); ?></p>
                  <p><strong>Pagamento:</strong> <?php echo htmlspecialchars($order['pagamento']); ?></p>

                  <?php $status = $order['status'] ?? ''; ?>
                  <p><strong>Status:</strong> 
                    <span class="order-status status-<?php echo htmlspecialchars($status); ?>">
                      <?php echo htmlspecialchars(str_replace('_',' ',$status)); ?>
                    </span>
                  </p>

                  <p><strong>Data:</strong> <?php echo htmlspecialchars($order['criado_em']); ?></p>

                  <!-- Botões -->
                  <div class="order-actions">
                    <?php if ($status === 'em_entrega'): ?>
                      <button class="btn-disabled" disabled>Em Entrega</button>
                    <?php else: ?>
                      <form method="POST" action="../../backend/controllers/aceitar_pedido_form.php" style="display:inline-block;">
                        <input type="hidden" name="pedido_id" value="<?php echo htmlspecialchars($order['id']); ?>">
                        <button type="submit" class="btn-aceitar">Iniciar Entrega</button>
                      </form>
                      <form method="POST" action="../../backend/controllers/recusar_pedido_form.php" style="display:inline-block;">
                        <input type="hidden" name="pedido_id" value="<?php echo htmlspecialchars($order['id']); ?>">
                        <button type="submit" class="btn-recusar">Recusar Pedido</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </section>
    </main>
  </div>

  <!-- FOOTER -->
  <footer role="contentinfo">
    <div class="footer-container">
      <div class="footer-links">
        <a href="../../Menu/index.html">Início</a>
        <a href="../Quem somos/index.html">Sobre</a>
        <a href="../Auxílio Preferencial/auxilio.html">Serviços</a>
        <a href="https://www.instagram.com/_snackparadise_/" target="_blank" rel="noopener">Contato</a>
      </div>
      <div class="footer-bottom">
        <p>&copy; 2024 Snack Paradise. Todos os direitos reservados.</p>
      </div>
    </div>
  </footer>

  <!-- Plugin de acessibilidade VLibras -->
  <div vw class="enabled">
    <div vw-access-button class="active"></div>
    <div vw-plugin-wrapper>
      <div class="vw-plugin-top-wrapper"></div>
    </div>
  </div>
  <script src="https://vlibras.gov.br/app/vlibras-plugin.js"></script>
  <script>
    new window.VLibras.Widget('https://vlibras.gov.br/app');
  </script>

  <!-- Scripts -->
  <script src="menu.js"></script>
</body>
</html>
