<?php
include_once 'Conexao.php';

class DatabaseManager extends Conectar
{
    // ========== MOTOBOYS ==========
    
    public function getMotoboyById($motoboyId)
    {
        try {
            $sql = "SELECT * FROM motoboys WHERE id = :id";
            $stmt = $this->prepare($sql);
            $stmt->bindParam(':id', $motoboyId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erro getMotoboyById: " . $e->getMessage());
            return false;
        }
    }

    public function updateMotoboyPersonalInfo($motoboyId, $data)
    {
        try {
            $sql = "UPDATE motoboys SET name = :name, email = :email WHERE id = :id";
            $stmt = $this->prepare($sql);
            $stmt->bindParam(':name', $data['name']);
            $stmt->bindParam(':email', $data['email']);
            $stmt->bindParam(':id', $motoboyId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Erro updateMotoboyPersonalInfo: " . $e->getMessage());
            return false;
        }
    }

    public function updateMotoboyVehicleInfo($motoboyId, $data)
    {
        try {
            // Validar placa
            if (!$this->validatePlate($data['license_plate'])) {
                throw new Exception('Formato de placa inválido. Use formato ABC-1234.');
            }

            $sql = "UPDATE motoboys SET vehicle_type = :vehicle_type, license_plate = :license_plate WHERE id = :id";
            $stmt = $this->prepare($sql);
            $stmt->bindParam(':vehicle_type', $data['vehicle_type']);
            $stmt->bindParam(':license_plate', $data['license_plate']);
            $stmt->bindParam(':id', $motoboyId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Erro updateMotoboyVehicleInfo: " . $e->getMessage());
            return false;
        }
    }

    public function updateMotoboyStatus($motoboyId, $status)
    {
        try {
            $sql = "UPDATE motoboys SET status = :status WHERE id = :id";
            $stmt = $this->prepare($sql);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':id', $motoboyId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Erro updateMotoboyStatus: " . $e->getMessage());
            return false;
        }
    }

    // ========== ESTATÍSTICAS REAIS ==========

    public function getMotoboyStats($motoboyId)
    {
        try {
            // Total de entregas confirmadas
            $sql = "SELECT COUNT(*) as total_entregas FROM registro WHERE motoboy_id = :motoboy_id AND confirmar = 1";
            $stmt = $this->prepare($sql);
            $stmt->bindParam(':motoboy_id', $motoboyId, PDO::PARAM_INT);
            $stmt->execute();
            $entregas = $stmt->fetch(PDO::FETCH_ASSOC);

            // Taxa de sucesso (entregas confirmadas / total de entregas)
            $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN confirmar = 1 THEN 1 ELSE 0 END) as entregues
                    FROM registro 
                    WHERE motoboy_id = :motoboy_id";
            $stmt = $this->prepare($sql);
            $stmt->bindParam(':motoboy_id', $motoboyId, PDO::PARAM_INT);
            $stmt->execute();
            $taxa = $stmt->fetch(PDO::FETCH_ASSOC);

            $taxa_sucesso = 0;
            if ($taxa['total'] > 0) {
                $taxa_sucesso = round(($taxa['entregues'] / $taxa['total']) * 100);
            }

            // Calcular ganhos totais baseado nas entregas (R$ 5 por entrega como exemplo)
            $ganhos_totais = ($entregas['total_entregas'] ?? 0) * 5;

            return [
                'total_entregas' => $entregas['total_entregas'] ?? 0,
                'taxa_sucesso' => $taxa_sucesso,
                'ganhos_totais' => $ganhos_totais
            ];
        } catch (Exception $e) {
            error_log("Erro getMotoboyStats: " . $e->getMessage());
            return [
                'total_entregas' => 0,
                'taxa_sucesso' => 0,
                'ganhos_totais' => 0
            ];
        }
    }

    public function getRecentDeliveries($motoboyId, $limit = 10)
    {
        try {
            $sql = "SELECT r.*, p.itens, p.endereco, p.pagamento
                    FROM registro r 
                    JOIN pedidos p ON r.pedido_id = p.id 
                    WHERE r.motoboy_id = :motoboy_id 
                    ORDER BY r.criado_em DESC 
                    LIMIT :limit";
            $stmt = $this->prepare($sql);
            $stmt->bindParam(':motoboy_id', $motoboyId, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erro getRecentDeliveries: " . $e->getMessage());
            return [];
        }
    }

    // ========== SENHA E FOTOS ==========

    public function changePasswordUser($userId, $currentPassword, $newPassword)
    {
        try {
            // Verificar senha atual
            $sql = "SELECT senha FROM users WHERE id = :id";
            $stmt = $this->prepare($sql);
            $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !password_verify($currentPassword, $user['senha'])) {
                throw new Exception('Senha atual incorreta.');
            }

            // Atualizar senha
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET senha = :senha WHERE id = :id";
            $stmt = $this->prepare($sql);
            $stmt->bindParam(':senha', $newPasswordHash);
            $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Erro changePasswordUser: " . $e->getMessage());
            return false;
        }
    }

    public function changePasswordMotoboy($motoboyId, $currentPassword, $newPassword)
    {
        try {
            // Verificar senha atual
            $sql = "SELECT senha FROM motoboys WHERE id = :id";
            $stmt = $this->prepare($sql);
            $stmt->bindParam(':id', $motoboyId, PDO::PARAM_INT);
            $stmt->execute();
            $motoboy = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$motoboy || !password_verify($currentPassword, $motoboy['senha'])) {
                throw new Exception('Senha atual incorreta.');
            }

            // Atualizar senha
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $sql = "UPDATE motoboys SET senha = :senha WHERE id = :id";
            $stmt = $this->prepare($sql);
            $stmt->bindParam(':senha', $newPasswordHash);
            $stmt->bindParam(':id', $motoboyId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Erro changePasswordMotoboy: " . $e->getMessage());
            return false;
        }
    }

    public function updateProfilePictureUser($userId, $filename)
    {
        try {
            $sql = "UPDATE users SET profile_picture = :profile_picture WHERE id = :id";
            $stmt = $this->prepare($sql);
            $stmt->bindParam(':profile_picture', $filename);
            $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Erro updateProfilePictureUser: " . $e->getMessage());
            return false;
        }
    }

    public function updateProfilePictureMotoboy($motoboyId, $filename)
    {
        try {
            $sql = "UPDATE motoboys SET profile_picture = :profile_picture WHERE id = :id";
            $stmt = $this->prepare($sql);
            $stmt->bindParam(':profile_picture', $filename);
            $stmt->bindParam(':id', $motoboyId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Erro updateProfilePictureMotoboy: " . $e->getMessage());
            return false;
        }
    }

    // ========== MÉTODOS DE PAGINAÇÃO PARA MUITOS PEDIDOS ==========
    
    public function getAllPedidosPaginated($page = 1, $limit = 10)
    {
        try {
            $offset = ($page - 1) * $limit;
            
            $sql = "SELECT p.id, p.usuario_id, u.username as cliente_nome, p.itens, p.endereco, p.pagamento, p.criado_em, p.status, p.motoboy_id
                    FROM pedidos p
                    LEFT JOIN users u ON p.usuario_id = u.id
                    WHERE p.status NOT IN ('entregue', 'cancelado', 'oculto')
                    ORDER BY p.criado_em DESC
                    LIMIT :limit OFFSET :offset";
            
            $stmt = $this->prepare($sql);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($pedidos as &$pedido) {
                $itens = json_decode($pedido['itens'], true);
                if (is_array($itens)) {
                    $pedido['itens_array'] = $itens;
                    $total = 0;
                    foreach ($itens as $item) {
                        $preco = isset($item['preco']) ? floatval($item['preco']) : 0;
                        $qtd = isset($item['quantidade']) ? intval($item['quantidade']) : 1;
                        $total += $preco * $qtd;
                    }
                    $pedido['total'] = $total;
                } else {
                    $pedido['itens_array'] = explode(',', $pedido['itens']);
                    $pedido['total'] = null;
                }
            }
            
            return $pedidos;
        } catch (Exception $e) {
            error_log('Erro getAllPedidosPaginated: ' . $e->getMessage());
            return [];
        }
    }

    public function getTotalPedidosAtivos()
    {
        try {
            $sql = "SELECT COUNT(*) as total FROM pedidos WHERE status NOT IN ('entregue', 'cancelado', 'oculto')";
            $stmt = $this->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['total'] ?? 0;
        } catch (Exception $e) {
            error_log('Erro getTotalPedidosAtivos: ' . $e->getMessage());
            return 0;
        }
    }

    public function getPedidosFiltrados($filtroStatus = 'todos', $limit = 20)
    {
        try {
            $sql = "SELECT p.id, p.usuario_id, u.username as cliente_nome, p.itens, p.endereco, p.pagamento, p.criado_em, p.status, p.motoboy_id
                    FROM pedidos p
                    LEFT JOIN users u ON p.usuario_id = u.id
                    WHERE 1=1";
            
            $params = [];
            
            if ($filtroStatus === 'ativos') {
                $sql .= " AND p.status NOT IN ('entregue', 'cancelado', 'oculto')";
            } elseif ($filtroStatus !== 'todos') {
                $sql .= " AND p.status = :status";
                $params[':status'] = $filtroStatus;
            }
            
            $sql .= " ORDER BY p.criado_em DESC LIMIT :limit";
            $params[':limit'] = $limit;
            
            $stmt = $this->prepare($sql);
            
            foreach ($params as $key => $value) {
                if ($key === ':limit') {
                    $stmt->bindValue($key, $value, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($key, $value);
                }
            }
            
            $stmt->execute();
            $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($pedidos as &$pedido) {
                $itens = json_decode($pedido['itens'], true);
                if (is_array($itens)) {
                    $pedido['itens_array'] = $itens;
                    $total = 0;
                    foreach ($itens as $item) {
                        $preco = isset($item['preco']) ? floatval($item['preco']) : 0;
                        $qtd = isset($item['quantidade']) ? intval($item['quantidade']) : 1;
                        $total += $preco * $qtd;
                    }
                    $pedido['total'] = $total;
                } else {
                    $pedido['itens_array'] = explode(',', $pedido['itens']);
                    $pedido['total'] = null;
                }
            }
            
            return $pedidos;
        } catch (Exception $e) {
            error_log('Erro getPedidosFiltrados: ' . $e->getMessage());
            return [];
        }
    }
    public function getPedidoHistorico($pedido_id)
    {
        try {
            $sql = "SELECT * FROM pedido_historico WHERE pedido_id = :pedido_id ORDER BY criado_em DESC";
            $stmt = $this->prepare($sql);
            $stmt->bindParam(':pedido_id', $pedido_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erro getPedidoHistorico: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Verificar se motoboy pode aceitar mais pedidos
     */
    public function canMotoboyAcceptOrder($motoboy_id)
    {
        try {
            $sql = "SELECT COUNT(*) as pedidos_ativos FROM pedidos WHERE motoboy_id = :motoboy_id AND status = 'em_entrega'";
            $stmt = $this->prepare($sql);
            $stmt->bindParam(':motoboy_id', $motoboy_id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Limite de 3 pedidos simultâneos
            return ($result['pedidos_ativos'] ?? 0) < 3;
        } catch (Exception $e) {
            error_log("Erro canMotoboyAcceptOrder: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Buscar estatísticas do motoboy
     */
    public function getMotoboyDeliveryStats($motoboy_id)
    {
        try {
            $sql = "SELECT 
                    COUNT(*) as total_pedidos,
                    SUM(CASE WHEN status = 'entregue' THEN 1 ELSE 0 END) as entregues,
                    SUM(CASE WHEN status = 'em_entrega' THEN 1 ELSE 0 END) as em_entrega,
                    SUM(CASE WHEN status = 'cancelado' THEN 1 ELSE 0 END) as cancelados
                    FROM pedidos 
                    WHERE motoboy_id = :motoboy_id";
            
            $stmt = $this->prepare($sql);
            $stmt->bindParam(':motoboy_id', $motoboy_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erro getMotoboyDeliveryStats: " . $e->getMessage());
            return ['total_pedidos' => 0, 'entregues' => 0, 'em_entrega' => 0, 'cancelados' => 0];
        }
    }

    public function getPedidosDisponiveisMotoboy($filtro_status = null)
    {
        try {
            error_log("Buscando pedidos disponíveis para motoboy");
            
            $sql = "SELECT 
                    p.id, 
                    p.usuario_id, 
                    u.username as cliente_nome, 
                    u.telefone,
                    p.itens, 
                    p.endereco, 
                    p.pagamento, 
                    p.criado_em, 
                    p.status,
                    p.motoboy_id,
                    TIMESTAMPDIFF(MINUTE, p.criado_em, NOW()) as minutos_atras
                    FROM pedidos p
                    LEFT JOIN users u ON p.usuario_id = u.id
                    WHERE p.status IN ('pronto', 'preparando')
                    AND (p.motoboy_id IS NULL OR p.motoboy_id = '')";
            
            $params = [];
            
            // Filtro por status específico
            if ($filtro_status && in_array($filtro_status, ['pronto', 'preparando'])) {
                $sql .= " AND p.status = :status";
                $params[':status'] = $filtro_status;
            }
            
            $sql .= " ORDER BY 
                     CASE 
                         WHEN p.status = 'pronto' THEN 1
                         WHEN p.status = 'preparando' THEN 2
                         ELSE 3
                     END,
                     p.criado_em ASC";
            
            $stmt = $this->prepare($sql);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();
            $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("Pedidos disponíveis encontrados: " . count($pedidos));
            
            // Processar itens e calcular totais
            foreach ($pedidos as &$pedido) {
                // Tentar decodificar JSON dos itens
                $itens_json = json_decode($pedido['itens'], true);
                if (is_array($itens_json)) {
                    $pedido['itens_array'] = $itens_json;
                    // Calcular total
                    $total = 0;
                    foreach ($itens_json as $item) {
                        $preco = isset($item['preco']) ? floatval($item['preco']) : 0;
                        $qtd = isset($item['quantidade']) ? intval($item['quantidade']) : 1;
                        $total += $preco * $qtd;
                    }
                    $pedido['total'] = $total;
                    
                    // Criar descrição dos itens
                    $itens_desc = [];
                    foreach ($itens_json as $item) {
                        $qtd = $item['quantidade'] ?? 1;
                        $nome = $item['nome'] ?? $item['produto'] ?? 'Item';
                        $itens_desc[] = $qtd . 'x ' . $nome;
                    }
                    $pedido['itens_descricao'] = implode(', ', $itens_desc);
                } else {
                    $pedido['itens_array'] = [];
                    $pedido['total'] = 0;
                    $pedido['itens_descricao'] = $pedido['itens']; // Fallback
                }
                
                // Adicionar flag de urgência (pedidos com mais de 15 minutos)
                $pedido['urgente'] = ($pedido['minutos_atras'] > 15);
                $pedido['tempo_espera'] = $this->formatarTempoEspera($pedido['minutos_atras']);
            }
            
            return $pedidos;
        } catch (Exception $e) {
            error_log("Erro getPedidosDisponiveisMotoboy: " . $e->getMessage());
            return [];
        }
    }
    
        /**
         * Buscar pedidos ativos do motoboy (em entrega)
         */
        public function getPedidosAtivosMotoboy($motoboy_id)
        {
            try {
                error_log("Buscando pedidos ativos para motoboy: " . $motoboy_id);
                
                $sql = "SELECT 
                        p.id, 
                        p.usuario_id, 
                        u.username as cliente_nome, 
                        u.telefone,
                        p.itens, 
                        p.endereco, 
                        p.pagamento, 
                        p.criado_em, 
                        p.status,
                        p.motoboy_id,
                        TIMESTAMPDIFF(MINUTE, p.criado_em, NOW()) as minutos_atras
                        FROM pedidos p
                        LEFT JOIN users u ON p.usuario_id = u.id
                        WHERE p.motoboy_id = :motoboy_id 
                        AND p.status IN ('em_entrega', 'aceito')
                        ORDER BY p.criado_em DESC";
                
                $stmt = $this->prepare($sql);
                $stmt->bindParam(':motoboy_id', $motoboy_id, PDO::PARAM_INT);
                $stmt->execute();
                
                $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                error_log("Pedidos ativos encontrados: " . count($pedidos));
                
                foreach ($pedidos as &$pedido) {
                    $pedido['tempo_entrega'] = $this->formatarTempoEspera($pedido['minutos_atras']);
                    
                    // Criar descrição dos itens
                    $itens_json = json_decode($pedido['itens'], true);
                    if (is_array($itens_json)) {
                        $itens_desc = [];
                        foreach ($itens_json as $item) {
                            $qtd = $item['quantidade'] ?? 1;
                            $nome = $item['nome'] ?? $item['produto'] ?? 'Item';
                            $itens_desc[] = $qtd . 'x ' . $nome;
                        }
                        $pedido['itens_descricao'] = implode(', ', $itens_desc);
                        
                        // Calcular total
                        $total = 0;
                        foreach ($itens_json as $item) {
                            $preco = isset($item['preco']) ? floatval($item['preco']) : 0;
                            $qtd = isset($item['quantidade']) ? intval($item['quantidade']) : 1;
                            $total += $preco * $qtd;
                        }
                        $pedido['total'] = $total;
                    } else {
                        $pedido['itens_descricao'] = $pedido['itens'];
                        $pedido['total'] = 0;
                    }
                    
                    // Debug individual
                    error_log("Pedido Ativo - ID: " . $pedido['id'] . 
                             " | Status: " . $pedido['status'] . 
                             " | Motoboy: " . $pedido['motoboy_id']);
                }
                
                return $pedidos;
            } catch (Exception $e) {
                error_log("Erro getPedidosAtivosMotoboy: " . $e->getMessage());
                return [];
            }
        }
    
        /**
         * Buscar estatísticas rápidas para dashboard do motoboy
         */
        public function getEstatisticasMotoboyVision($motoboy_id)
        {
            try {
                // Pedidos disponíveis
                $sql = "SELECT 
                        COUNT(*) as total_disponiveis,
                        SUM(CASE WHEN status = 'pronto' THEN 1 ELSE 0 END) as prontos,
                        SUM(CASE WHEN status = 'preparando' THEN 1 ELSE 0 END) as preparando
                        FROM pedidos 
                        WHERE motoboy_id IS NULL 
                        AND status IN ('pronto', 'preparando')";
                
                $stmt = $this->prepare($sql);
                $stmt->execute();
                $disponiveis = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Pedidos ativos do motoboy
                $ativos = $this->getPedidosAtivosMotoboy($motoboy_id);
                
                // Histórico do dia
                $sql = "SELECT COUNT(*) as entregues_hoje 
                        FROM pedidos 
                        WHERE motoboy_id = :motoboy_id 
                        AND status = 'entregue' 
                        AND DATE(criado_em) = CURDATE()";
                
                $stmt = $this->prepare($sql);
                $stmt->bindParam(':motoboy_id', $motoboy_id, PDO::PARAM_INT);
                $stmt->execute();
                $hoje = $stmt->fetch(PDO::FETCH_ASSOC);
                
                return [
                    'disponiveis' => $disponiveis,
                    'ativos' => count($ativos),
                    'entregues_hoje' => $hoje['entregues_hoje'] ?? 0,
                    'total_disponiveis' => $disponiveis['total_disponiveis'] ?? 0
                ];
            } catch (Exception $e) {
                error_log("Erro getEstatisticasMotoboyVision: " . $e->getMessage());
                return [
                    'disponiveis' => ['total_disponiveis' => 0, 'prontos' => 0, 'preparando' => 0],
                    'ativos' => 0,
                    'entregues_hoje' => 0,
                    'total_disponiveis' => 0
                ];
            }
        }
    
        /**
         * Buscar TODOS os pedidos do motoboy (independente do status)
         */
        public function getAllPedidosMotoboy($motoboy_id)
        {
            try {
                $sql = "SELECT 
                        p.id, 
                        p.usuario_id, 
                        u.username as cliente_nome, 
                        p.itens, 
                        p.endereco, 
                        p.pagamento, 
                        p.criado_em, 
                        p.status,
                        p.motoboy_id
                        FROM pedidos p
                        LEFT JOIN users u ON p.usuario_id = u.id
                        WHERE p.motoboy_id = :motoboy_id 
                        ORDER BY p.criado_em DESC";
                
                $stmt = $this->prepare($sql);
                $stmt->bindParam(':motoboy_id', $motoboy_id, PDO::PARAM_INT);
                $stmt->execute();
                
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                error_log("Erro getAllPedidosMotoboy: " . $e->getMessage());
                return [];
            }
        }
    
        /**
         * Formatar tempo de espera para exibição
         */
        private function formatarTempoEspera($minutos)
        {
            if ($minutos < 1) {
                return 'Agora mesmo';
            } elseif ($minutos < 60) {
                return "{$minutos} min";
            } else {
                $horas = floor($minutos / 60);
                $minutos_rest = $minutos % 60;
                return "{$horas}h {$minutos_rest}min";
            }
        }
    
        /**
         * Verificar se pedido ainda está disponível
         */
        public function isPedidoDisponivel($pedido_id)
        {
            try {
                $sql = "SELECT id FROM pedidos WHERE id = :id AND motoboy_id IS NULL AND status IN ('pronto', 'preparando')";
                $stmt = $this->prepare($sql);
                $stmt->bindParam(':id', $pedido_id, PDO::PARAM_INT);
                $stmt->execute();
                return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
            } catch (Exception $e) {
                error_log("Erro isPedidoDisponivel: " . $e->getMessage());
                return false;
            }
        }
    
        /**
         * DEBUG: Método para verificar pedidos ativos com informações detalhadas
         */
        public function debugPedidosAtivos($motoboy_id)
        {
            try {
                error_log("DEBUG: Buscando pedidos ativos para motoboy_id: " . $motoboy_id);
                
                $sql = "SELECT 
                        p.id, 
                        p.usuario_id, 
                        p.motoboy_id,
                        p.status,
                        u.username as cliente_nome, 
                        u.telefone,
                        p.itens, 
                        p.endereco, 
                        p.pagamento, 
                        p.criado_em
                        FROM pedidos p
                        LEFT JOIN users u ON p.usuario_id = u.id
                        WHERE p.motoboy_id = :motoboy_id 
                        ORDER BY p.criado_em DESC";
                
                $stmt = $this->prepare($sql);
                $stmt->bindParam(':motoboy_id', $motoboy_id, PDO::PARAM_INT);
                $stmt->execute();
                
                $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                error_log("DEBUG: Total de pedidos encontrados: " . count($pedidos));
                
                foreach ($pedidos as $pedido) {
                    error_log("DEBUG Pedido: ID=" . $pedido['id'] . 
                             " | Status=" . $pedido['status'] . 
                             " | Motoboy=" . $pedido['motoboy_id'] .
                             " | Cliente=" . $pedido['cliente_nome']);
                }
                
                return $pedidos;
            } catch (Exception $e) {
                error_log("DEBUG Erro: " . $e->getMessage());
                return [];
            }
        }
    
    // ========== VALIDAÇÕES ==========

    private function validatePlate($plate)
    {
        // Aceita formato antigo ABC-1234 ou sem hífen (ABC1234)
        $oldRegex = '/^[A-Z]{3}-?\d{4}$/';

        // Aceita padrão Mercosul (ex: BRA2E19)
        $mercosulRegex = '/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/';

        return preg_match($oldRegex, strtoupper($plate)) || preg_match($mercosulRegex, strtoupper($plate));
    }

    // ========== USUÁRIOS ==========
    
    public function getUserById($userId)
    {
        try {
            $sql = "SELECT id, username, email, profile_picture, partner, cep, endereco, bairro, cidade 
                    FROM users WHERE id = :id";
            $stmt = $this->prepare($sql);
            $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erro getUserById: " . $e->getMessage());
            return false;
        }
    }

    // ========== USUÁRIOS ==========

public function getUserOrdersPaginated($userId, $limit, $offset) {
    try {
        $sql = "
            SELECT p.*, u.username as cliente_nome
            FROM pedidos p
            LEFT JOIN users u ON u.id = p.usuario_id
            WHERE p.usuario_id = :usuario_id
            ORDER BY p.id DESC
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $this->prepare($sql);

        // Alguns wrappers PDO exigem bindValue com PDO::PARAM_INT para LIMIT/OFFSET
        $stmt->bindValue(':usuario_id', (int)$userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Erro getUserOrdersPaginated: " . $e->getMessage());
        return [];
    }
}

public function countUserOrders($userId) {
    try {
        $sql = "SELECT COUNT(*) as total FROM pedidos WHERE usuario_id = :usuario_id";
        $stmt = $this->prepare($sql);
        $stmt->bindValue(':usuario_id', (int)$userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($row['total'] ?? 0);
    } catch (Exception $e) {
        error_log("Erro countUserOrders: " . $e->getMessage());
        return 0;
    }
}


    public function updateUserPersonalInfo($userId, $data)
    {
        try {
            $sql = "UPDATE users SET username = :username, email = :email WHERE id = :id";
            $stmt = $this->prepare($sql);
            $stmt->bindParam(':username', $data['username']);
            $stmt->bindParam(':email', $data['email']);
            $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Erro updateUserPersonalInfo: " . $e->getMessage());
            return false;
        }
    }

    public function updateUserAddress($userId, $data)
    {
        try {
            $sql = "UPDATE users 
                    SET cep = :cep, endereco = :endereco, bairro = :bairro, cidade = :cidade 
                    WHERE id = :id";
            $stmt = $this->prepare($sql);
            $stmt->bindParam(':cep', $data['cep']);
            $stmt->bindParam(':endereco', $data['endereco']);
            $stmt->bindParam(':bairro', $data['bairro']);
            $stmt->bindParam(':cidade', $data['cidade']);
            $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Erro updateUserAddress: " . $e->getMessage());
            return false;
        }
    }

    // ========== PEDIDOS ==========

    public function getUserOrders($userId)
    {
        try {
            $sql = "SELECT p.*, 
                    GROUP_CONCAT(CONCAT(pi.quantidade, 'x ', pi.produto, ' - R$ ', pi.preco) SEPARATOR ', ') as itens_descricao,
                    SUM(pi.preco * pi.quantidade) as total
                    FROM pedidos p
                    LEFT JOIN pedido_itens pi ON p.id = pi.pedido_id
                    WHERE p.usuario_id = :usuario_id
                    GROUP BY p.id
                    ORDER BY p.criado_em DESC 
                    LIMIT 10";
            $stmt = $this->prepare($sql);
            $stmt->bindParam(':usuario_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erro getUserOrders: " . $e->getMessage());
            return [];
        }
    }

    public function createPedido($userId, $endereco, $pagamento, $itens) {
        try {
            // Inserir pedido principal
            $sql = "INSERT INTO pedidos (usuario_id, endereco, pagamento, status) 
                    VALUES (:usuario_id, :endereco, :pagamento, 'pendente')";
            $stmt = $this->prepare($sql);
            $stmt->bindParam(':usuario_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':endereco', $endereco);
            $stmt->bindParam(':pagamento', $pagamento);
            $stmt->execute();
            
            $pedidoId = $this->lastInsertId();

            // Inserir itens do pedido
            foreach ($itens as $item) {
                $sql = "INSERT INTO pedido_itens (pedido_id, produto, quantidade, preco) 
                        VALUES (:pedido_id, :produto, :quantidade, :preco)";
                $stmt = $this->prepare($sql);
                $stmt->bindParam(':pedido_id', $pedidoId, PDO::PARAM_INT);
                $stmt->bindParam(':produto', $item['nome']);
                $stmt->bindParam(':quantidade', $item['quantidade'], PDO::PARAM_INT);
                $stmt->bindParam(':preco', $item['preco']);
                $stmt->execute();
            }

            return $pedidoId;
        } catch (Exception $e) {
            error_log("Erro createPedido: " . $e->getMessage());
            return false;
        }
    }

    // ========== REGISTRO DE ENTREGAS ==========

    public function getDeliveryHistory($userId)
    {
        try {
            $sql = "SELECT r.*, m.name as motoboy_name 
                    FROM registro r
                    LEFT JOIN motoboys m ON r.motoboy_id = m.id
                    WHERE r.cliente_id = :cliente_id
                    ORDER BY r.criado_em DESC 
                    LIMIT 10";
            $stmt = $this->prepare($sql);
            $stmt->bindParam(':cliente_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erro getDeliveryHistory: " . $e->getMessage());
            return [];
        }
    }

    // ========== AVALIAÇÕES ==========

    public function getUserReviews($userId)
    {
        try {
            $sql = "SELECT r.*, m.name as motoboy_name, p.id as pedido_id
                    FROM reviews r
                    LEFT JOIN motoboys m ON r.motoboy_id = m.id
                    LEFT JOIN pedidos p ON r.pedido_id = p.id
                    WHERE r.usuario_id = :usuario_id
                    ORDER BY r.criado_em DESC";
            $stmt = $this->prepare($sql);
            $stmt->bindParam(':usuario_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Erro getUserReviews: " . $e->getMessage());
            return [];
        }
    }

    public function addReview($userId, $pedidoId, $motoboyId, $nota, $comentario)
    {
        try {
            $sql = "INSERT INTO reviews (pedido_id, motoboy_id, usuario_id, nota, comentario) 
                    VALUES (:pedido_id, :motoboy_id, :usuario_id, :nota, :comentario)";
            $stmt = $this->prepare($sql);
            $stmt->bindParam(':pedido_id', $pedidoId, PDO::PARAM_INT);
            $stmt->bindParam(':motoboy_id', $motoboyId, PDO::PARAM_INT);
            $stmt->bindParam(':usuario_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':nota', $nota, PDO::PARAM_INT);
            $stmt->bindParam(':comentario', $comentario);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Erro addReview: " . $e->getMessage());
            return false;
        }
    }

    // ========== PEDIDOS E ENTREGAS ==========

public function getPendingOrders()
{
    try {
        $sql = "SELECT p.*, u.username as cliente_nome, u.cep, u.endereco, u.bairro, u.cidade,
                GROUP_CONCAT(CONCAT(pi.quantidade, 'x ', pi.produto) SEPARATOR ', ') as itens_descricao,
                SUM(pi.preco * pi.quantidade) as total
                FROM pedidos p
                JOIN users u ON p.usuario_id = u.id
                LEFT JOIN pedido_itens pi ON p.id = pi.pedido_id
                WHERE p.status IN ('pronto', 'preparando')
                AND p.motoboy_id IS NULL
                GROUP BY p.id
                ORDER BY p.criado_em ASC";
        $stmt = $this->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Erro getPendingOrders: " . $e->getMessage());
        return [];
    }
}

public function getAvailableMotoboys()
{
    try {
        $sql = "SELECT * FROM motoboys 
                WHERE status = 'disponivel' 
                ORDER BY created_at ASC";
        $stmt = $this->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Erro getAvailableMotoboys: " . $e->getMessage());
        return [];
    }
}

public function assignOrderToMotoboy($pedidoId, $motoboyId)
{
    try {
        // Iniciar transação
        $this->beginTransaction();
        
        // Atualizar pedido
        $sql = "UPDATE pedidos SET motoboy_id = :motoboy_id, status = 'em_entrega' WHERE id = :pedido_id";
        $stmt = $this->prepare($sql);
        $stmt->bindParam(':motoboy_id', $motoboyId, PDO::PARAM_INT);
        $stmt->bindParam(':pedido_id', $pedidoId, PDO::PARAM_INT);
        $stmt->execute();
        
        // Atualizar status do motoboy
        $sql = "UPDATE motoboys SET status = 'ocupado' WHERE id = :motoboy_id";
        $stmt = $this->prepare($sql);
        $stmt->bindParam(':motoboy_id', $motoboyId, PDO::PARAM_INT);
        $stmt->execute();
        
        // Registrar na tabela de assignments
        $sql = "INSERT INTO pedido_assignments (pedido_id, motoboy_id, status) 
                VALUES (:pedido_id, :motoboy_id, 'assigned')";
        $stmt = $this->prepare($sql);
        $stmt->bindParam(':pedido_id', $pedidoId, PDO::PARAM_INT);
        $stmt->bindParam(':motoboy_id', $motoboyId, PDO::PARAM_INT);
        $stmt->execute();
        
        // Commit da transação
        $this->commit();
        return true;
        
    } catch (Exception $e) {
        $this->rollBack();
        error_log("Erro assignOrderToMotoboy: " . $e->getMessage());
        return false;
    }
}

public function updateOrderStatus($pedidoId, $status)
{
    try {
        $sql = "UPDATE pedidos SET status = :status WHERE id = :pedido_id";
        $stmt = $this->prepare($sql);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':pedido_id', $pedidoId, PDO::PARAM_INT);
        return $stmt->execute();
    } catch (Exception $e) {
        error_log("Erro updateOrderStatus: " . $e->getMessage());
        return false;
    }
}

public function completeDelivery($pedidoId, $motoboyId)
{
    try {
        // Iniciar transação
        $this->beginTransaction();
        
        // Atualizar pedido
        $sql = "UPDATE pedidos SET status = 'entregue' WHERE id = :pedido_id";
        $stmt = $this->prepare($sql);
        $stmt->bindParam(':pedido_id', $pedidoId, PDO::PARAM_INT);
        $stmt->execute();
        
        // Liberar motoboy
        $sql = "UPDATE motoboys SET status = 'disponivel' WHERE id = :motoboy_id";
        $stmt = $this->prepare($sql);
        $stmt->bindParam(':motoboy_id', $motoboyId, PDO::PARAM_INT);
        $stmt->execute();
        
        // Atualizar assignment
        $sql = "UPDATE pedido_assignments SET status = 'completed' 
                WHERE pedido_id = :pedido_id AND motoboy_id = :motoboy_id";
        $stmt = $this->prepare($sql);
        $stmt->bindParam(':pedido_id', $pedidoId, PDO::PARAM_INT);
        $stmt->bindParam(':motoboy_id', $motoboyId, PDO::PARAM_INT);
        $stmt->execute();
        
        // Registrar no registro de entregas
        $sql = "INSERT INTO registro (pedido_id, cliente_id, motoboy_id, itens, endereco, pagamento, confirmar) 
                SELECT p.id, p.usuario_id, p.motoboy_id, 
                       GROUP_CONCAT(CONCAT(pi.quantidade, 'x ', pi.produto) SEPARATOR ', '),
                       p.endereco, p.pagamento, 1
                FROM pedidos p
                LEFT JOIN pedido_itens pi ON p.id = pi.pedido_id
                WHERE p.id = :pedido_id
                GROUP BY p.id";
        $stmt = $this->prepare($sql);
        $stmt->bindParam(':pedido_id', $pedidoId, PDO::PARAM_INT);
        $stmt->execute();
        
        $this->commit();
        return true;
        
    } catch (Exception $e) {
        $this->rollBack();
        error_log("Erro completeDelivery: " . $e->getMessage());
        return false;
    }
}

public function getMotoboyActiveDelivery($motoboyId)
{
    try {
        $sql = "SELECT p.*, u.username as cliente_nome, u.telefone, u.cep, u.endereco, u.bairro, u.cidade,
                GROUP_CONCAT(CONCAT(pi.quantidade, 'x ', pi.produto) SEPARATOR ', ') as itens_descricao,
                SUM(pi.preco * pi.quantidade) as total
                FROM pedidos p
                JOIN users u ON p.usuario_id = u.id
                LEFT JOIN pedido_itens pi ON p.id = pi.pedido_id
                WHERE p.motoboy_id = :motoboy_id 
                AND p.status = 'em_entrega'
                GROUP BY p.id
                LIMIT 1";
        $stmt = $this->prepare($sql);
        $stmt->bindParam(':motoboy_id', $motoboyId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Erro getMotoboyActiveDelivery: " . $e->getMessage());
        return null;
    }
}

public function updateMotoboyLocation($motoboyId, $latitude, $longitude)
{
    try {
        $sql = "UPDATE motoboys SET latitude = :latitude, longitude = :longitude WHERE id = :motoboy_id";
        $stmt = $this->prepare($sql);
        $stmt->bindParam(':latitude', $latitude);
        $stmt->bindParam(':longitude', $longitude);
        $stmt->bindParam(':motoboy_id', $motoboyId, PDO::PARAM_INT);
        return $stmt->execute();
    } catch (Exception $e) {
        error_log("Erro updateMotoboyLocation: " . $e->getMessage());
        return false;
    }
}

public function getUserActiveDelivery($userId) {
    try {
        $sql = "SELECT p.*, m.name as motoboy_name 
                FROM pedidos p 
                LEFT JOIN motoboys m ON p.motoboy_id = m.id 
                WHERE p.usuario_id = :usuario_id 
                AND p.status = 'em_entrega' 
                ORDER BY p.criado_em DESC 
                LIMIT 1";
        $stmt = $this->prepare($sql);
        $stmt->bindParam(':usuario_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Erro getUserActiveDelivery: " . $e->getMessage());
        return null;
    }
}

public function getAllPedidos()
{
    try {
        $sql = "SELECT p.id, p.usuario_id, u.username as cliente_nome, p.itens, p.endereco, p.pagamento, p.criado_em, p.status
                FROM pedidos p
                LEFT JOIN users u ON p.usuario_id = u.id
                ORDER BY p.criado_em DESC";
        $stmt = $this->prepare($sql);
        $stmt->execute();
        $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($pedidos as &$pedido) {
            // Tentar decodificar itens como JSON, senão separar por vírgula
            $itens = json_decode($pedido['itens'], true);
            if (is_array($itens)) {
                $pedido['itens_array'] = $itens;
                // Calcular total
                $total = 0;
                foreach ($itens as $item) {
                    $preco = isset($item['preco']) ? floatval($item['preco']) : 0;
                    $qtd = isset($item['quantidade']) ? intval($item['quantidade']) : 1;
                    $total += $preco * $qtd;
                }
                $pedido['total'] = $total;
            } else {
                $pedido['itens_array'] = explode(',', $pedido['itens']);
                $pedido['total'] = null; // Não é possível calcular
            }
        }
        return $pedidos;
    } catch (Exception $e) {
        error_log('Erro getAllPedidos: ' . $e->getMessage());
        return [];
    }
}

    public function getPedidoById($pedidoId)
    {
        try {
            $sql = "SELECT p.*, u.username as cliente_nome FROM pedidos p LEFT JOIN users u ON p.usuario_id = u.id WHERE p.id = :id";
            $stmt = $this->prepare($sql);
            $stmt->bindParam(':id', $pedidoId, PDO::PARAM_INT);
            $stmt->execute();
            $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($pedido) {
                $itens = json_decode($pedido['itens'], true);
                if (is_array($itens)) {
                    $pedido['itens_array'] = $itens;
                    $total = 0;
                    foreach ($itens as $item) {
                        $preco = isset($item['preco']) ? floatval($item['preco']) : 0;
                        $qtd = isset($item['quantidade']) ? intval($item['quantidade']) : 1;
                        $total += $preco * $qtd;
                    }
                    $pedido['total'] = $total;
                } else {
                    $pedido['itens_array'] = explode(',', $pedido['itens']);
                    $pedido['total'] = null;
                }
            }
            return $pedido;
        } catch (Exception $e) {
            error_log('Erro getPedidoById: ' . $e->getMessage());
            return false;
        }
    }

    public function updatePedidoBasic($pedidoId, $data)
    {
        try {
            $sql = "UPDATE pedidos SET endereco = :endereco, pagamento = :pagamento, status = :status WHERE id = :id";
            $stmt = $this->prepare($sql);
            $stmt->bindParam(':endereco', $data['endereco']);
            $stmt->bindParam(':pagamento', $data['pagamento']);
            $stmt->bindParam(':status', $data['status']);
            $stmt->bindParam(':id', $pedidoId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log('Erro updatePedidoBasic: ' . $e->getMessage());
            return false;
        }
    }

    public function deletePedido($pedidoId)
    {
        try {
            $this->beginTransaction();

            // Remover registros dependentes para evitar violação de FK
            $sql = "DELETE FROM pedido_assignments WHERE pedido_id = :id";
            $stmt = $this->prepare($sql);
            $stmt->bindParam(':id', $pedidoId, PDO::PARAM_INT);
            $stmt->execute();

            $sql = "DELETE FROM registro WHERE pedido_id = :id";
            $stmt = $this->prepare($sql);
            $stmt->bindParam(':id', $pedidoId, PDO::PARAM_INT);
            $stmt->execute();

            $sql = "DELETE FROM reviews WHERE pedido_id = :id";
            $stmt = $this->prepare($sql);
            $stmt->bindParam(':id', $pedidoId, PDO::PARAM_INT);
            $stmt->execute();

            // Itens do pedido
            $sql = "DELETE FROM pedido_itens WHERE pedido_id = :id";
            $stmt = $this->prepare($sql);
            $stmt->bindParam(':id', $pedidoId, PDO::PARAM_INT);
            $stmt->execute();

            // Por fim apagar o pedido
            $sql = "DELETE FROM pedidos WHERE id = :id";
            $stmt = $this->prepare($sql);
            $stmt->bindParam(':id', $pedidoId, PDO::PARAM_INT);
            $stmt->execute();

            $this->commit();
            return true;
        } catch (Exception $e) {
            try { $this->rollBack(); } catch (Exception $ex) {}
            error_log('Erro deletePedido: ' . $e->getMessage());
            return false;
        }
    }

    public function hidePedido($pedidoId)
    {
        try {
            $sql = "UPDATE pedidos SET status = 'oculto' WHERE id = :id";
            $stmt = $this->prepare($sql);
            $stmt->bindParam(':id', $pedidoId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log('Erro hidePedido: ' . $e->getMessage());
            return false;
        }
    }

public function getPedidosByMotoboy($motoboyId)
{
    try {
        $sql = "SELECT p.*, u.username as cliente_nome,
                GROUP_CONCAT(CONCAT(pi.quantidade, 'x ', pi.produto) SEPARATOR ', ') as itens_descricao,
                SUM(pi.preco * pi.quantidade) as total
                FROM pedidos p
                LEFT JOIN users u ON p.usuario_id = u.id
                LEFT JOIN pedido_itens pi ON p.id = pi.pedido_id
                WHERE p.motoboy_id = :motoboy_id
                GROUP BY p.id
                ORDER BY p.criado_em DESC";
        $stmt = $this->prepare($sql);
        $stmt->bindParam(':motoboy_id', $motoboyId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Erro getPedidosByMotoboy: " . $e->getMessage());
        return [];
    }

    }

    // ========== DÚVIDAS ==========

    public function getAllDuvidas()
    {
        try {
            $sql = "SELECT d.*, u.username as usuario_nome FROM duvidas d LEFT JOIN users u ON d.usuario_id = u.id ORDER BY d.criado_em DESC";
            $stmt = $this->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('Erro getAllDuvidas: ' . $e->getMessage());
            return [];
        }
    }

    public function getDuvidaById($duvidaId)
    {
        try {
            $sql = "SELECT d.*, u.username as usuario_nome FROM duvidas d LEFT JOIN users u ON d.usuario_id = u.id WHERE d.id = :id LIMIT 1";
            $stmt = $this->prepare($sql);
            $stmt->bindParam(':id', $duvidaId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('Erro getDuvidaById: ' . $e->getMessage());
            return false;
        }
    }

    public function getDuvidasByUser($userId)
    {
        try {
            $sql = "SELECT * FROM duvidas WHERE usuario_id = :usuario_id ORDER BY criado_em DESC";
            $stmt = $this->prepare($sql);
            $stmt->bindParam(':usuario_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('Erro getDuvidasByUser: ' . $e->getMessage());
            return [];
        }
    }

    public function getDuvidasByEmail($email)
    {
        try {
            $sql = "SELECT * FROM duvidas WHERE email = :email ORDER BY criado_em DESC";
            $stmt = $this->prepare($sql);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('Erro getDuvidasByEmail: ' . $e->getMessage());
            return [];
        }
    }

    public function deleteDuvida($duvidaId)
    {
        try {
            $sql = "DELETE FROM duvidas WHERE id = :id";
            $stmt = $this->prepare($sql);
            $stmt->bindParam(':id', $duvidaId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log('Erro deleteDuvida: ' . $e->getMessage());
            return false;
        }
    }

    public function replyDuvida($duvidaId, $resposta)
    {
        try {
            $sql = "UPDATE duvidas SET resposta = :resposta, respondida = 1, resposta_em = NOW() WHERE id = :id";
            $stmt = $this->prepare($sql);
            $stmt->bindParam(':resposta', $resposta);
            $stmt->bindParam(':id', $duvidaId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log('Erro replyDuvida: ' . $e->getMessage());
            return false;
        }
    }

}
?>