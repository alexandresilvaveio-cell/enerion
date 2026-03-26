<?php
/**
 * Enerion Player - License Manager
 * 
 * Gerencia licenças, códigos e dispositivos
 * Otimizado para performance máxima
 */

class LicenseManager {
    private $db;
    private $cache = [];
    private $cache_ttl = 300; // 5 minutos
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Validar código de licença
     */
    public function validateCode($codigo) {
        // Verificar cache primeiro
        $cache_key = "code_{$codigo}";
        if (isset($this->cache[$cache_key])) {
            $cached = $this->cache[$cache_key];
            if (time() - $cached['time'] < $this->cache_ttl) {
                return $cached['data'];
            }
        }
        
        // Buscar no banco
        $code_data = $this->db->fetchOne(
            "SELECT id, rev_id, status, data_expiracao, max_dispositivos, dispositivos_ativos 
             FROM codigos 
             WHERE codigo = ? AND status = 'ativo'
             LIMIT 1",
            [$codigo]
        );
        
        if ($code_data) {
            // Verificar expiração
            if ($code_data['data_expiracao'] && strtotime($code_data['data_expiracao']) < time()) {
                $this->db->update('codigos', ['status' => 'expirado'], 'id = ?', [$code_data['id']]);
                return null;
            }
            
            // Cachear resultado
            $this->cache[$cache_key] = [
                'data' => $code_data,
                'time' => time()
            ];
            
            return $code_data;
        }
        
        return null;
    }
    
    /**
     * Verificar limite de dispositivos
     */
    public function checkDeviceLimit($codigo_id, $max_dispositivos) {
        $count = $this->db->count(
            'dispositivos',
            'codigo_id = ? AND status = "ativo"',
            [$codigo_id]
        );
        
        return $count < $max_dispositivos;
    }
    
    /**
     * Registrar dispositivo
     */
    public function registerDevice($codigo_id, $rev_id, $device_id, $modelo, $plataforma, $app_version, $ip_address) {
        // Verificar se já existe
        $existing = $this->db->fetchOne(
            "SELECT id FROM dispositivos WHERE device_id = ? LIMIT 1",
            [$device_id]
        );
        
        if ($existing) {
            // Atualizar
            return $this->db->update('dispositivos', [
                'codigo_id' => $codigo_id,
                'ip_address' => $ip_address,
                'app_version' => $app_version,
                'data_ultimo_ping' => date('Y-m-d H:i:s'),
                'online' => 1
            ], 'id = ?', [$existing['id']]);
        } else {
            // Criar novo
            return $this->db->insert('dispositivos', [
                'device_id' => $device_id,
                'codigo_id' => $codigo_id,
                'rev_id' => $rev_id,
                'modelo' => $modelo,
                'plataforma' => $plataforma,
                'app_version' => $app_version,
                'ip_address' => $ip_address,
                'status' => 'ativo',
                'online' => 1,
                'data_ativacao' => date('Y-m-d H:i:s'),
                'data_ultimo_ping' => date('Y-m-d H:i:s')
            ]);
        }
    }
    
    /**
     * Atualizar status online do dispositivo
     */
    public function updateDeviceStatus($device_id, $ip_address) {
        $dispositivo = $this->db->fetchOne(
            "SELECT id, rev_id FROM dispositivos WHERE device_id = ? LIMIT 1",
            [$device_id]
        );
        
        if (!$dispositivo) {
            return false;
        }
        
        // Atualizar
        $this->db->update('dispositivos', [
            'ip_address' => $ip_address,
            'data_ultimo_ping' => date('Y-m-d H:i:s'),
            'online' => 1
        ], 'id = ?', [$dispositivo['id']]);
        
        return $dispositivo;
    }
    
    /**
     * Obter estatísticas do revendedor
     */
    public function getRevendedorStats($rev_id) {
        $cache_key = "stats_{$rev_id}";
        
        // Verificar cache
        if (isset($this->cache[$cache_key])) {
            $cached = $this->cache[$cache_key];
            if (time() - $cached['time'] < $this->cache_ttl) {
                return $cached['data'];
            }
        }
        
        // Total de códigos
        $total_codigos = $this->db->count('codigos', 'rev_id = ?', [$rev_id]);
        
        // Códigos ativos
        $codigos_ativos = $this->db->count(
            'codigos',
            'rev_id = ? AND status = "ativo" AND (data_expiracao IS NULL OR data_expiracao >= CURDATE())',
            [$rev_id]
        );
        
        // Total de dispositivos
        $total_dispositivos = $this->db->count('dispositivos', 'rev_id = ?', [$rev_id]);
        
        // Dispositivos online
        $dispositivos_online = $this->db->count(
            'dispositivos',
            'rev_id = ? AND online = 1',
            [$rev_id]
        );
        
        $stats = [
            'total_codigos' => $total_codigos,
            'codigos_ativos' => $codigos_ativos,
            'total_dispositivos' => $total_dispositivos,
            'dispositivos_online' => $dispositivos_online
        ];
        
        // Cachear
        $this->cache[$cache_key] = [
            'data' => $stats,
            'time' => time()
        ];
        
        return $stats;
    }
    
    /**
     * Obter dispositivos do revendedor
     */
    public function getRevendedorDevices($rev_id, $limit = 100, $offset = 0) {
        return $this->db->fetchAll(
            "SELECT d.*, c.codigo 
             FROM dispositivos d
             INNER JOIN codigos c ON d.codigo_id = c.id
             WHERE d.rev_id = ?
             ORDER BY d.data_ultimo_ping DESC
             LIMIT ? OFFSET ?",
            [$rev_id, $limit, $offset]
        );
    }
    
    /**
     * Obter códigos do revendedor
     */
    public function getRevendedorCodes($rev_id, $limit = 100, $offset = 0) {
        return $this->db->fetchAll(
            "SELECT id, codigo, status, data_criacao, data_expiracao, 
                    max_dispositivos, dispositivos_ativos, ultimo_uso
             FROM codigos
             WHERE rev_id = ?
             ORDER BY data_criacao DESC
             LIMIT ? OFFSET ?",
            [$rev_id, $limit, $offset]
        );
    }
    
    /**
     * Criar código de licença
     */
    public function createCode($rev_id, $max_dispositivos = 5, $dias_validade = 30) {
        // Gerar código único
        $codigo = $this->generateCode();
        
        // Verificar se já existe
        while ($this->db->count('codigos', 'codigo = ?', [$codigo]) > 0) {
            $codigo = $this->generateCode();
        }
        
        // Data de expiração
        $data_expiracao = date('Y-m-d', strtotime("+{$dias_validade} days"));
        
        // Inserir
        return $this->db->insert('codigos', [
            'codigo' => $codigo,
            'rev_id' => $rev_id,
            'status' => 'ativo',
            'data_criacao' => date('Y-m-d H:i:s'),
            'data_expiracao' => $data_expiracao,
            'max_dispositivos' => $max_dispositivos,
            'dispositivos_ativos' => 0
        ]);
    }
    
    /**
     * Gerar código aleatório
     */
    private function generateCode() {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';
        
        for ($i = 0; $i < 12; $i++) {
            $code .= $chars[rand(0, strlen($chars) - 1)];
        }
        
        return $code;
    }
    
    /**
     * Desativar código
     */
    public function deactivateCode($codigo_id) {
        return $this->db->update('codigos', [
            'status' => 'inativo'
        ], 'id = ?', [$codigo_id]);
    }
    
    /**
     * Bloquear dispositivo
     */
    public function blockDevice($device_id) {
        return $this->db->update('dispositivos', [
            'status' => 'bloqueado'
        ], 'device_id = ?', [$device_id]);
    }
    
    /**
     * Desbloquear dispositivo
     */
    public function unblockDevice($device_id) {
        return $this->db->update('dispositivos', [
            'status' => 'ativo'
        ], 'device_id = ?', [$device_id]);
    }
    
    /**
     * Limpar cache
     */
    public function clearCache() {
        $this->cache = [];
    }
}

?>
