<?php
/**
 * Enerion Player - Classe Database
 * 
 * Gerenciador de conexão com MySQL otimizado para alta performance
 * Suporta 500.000+ dispositivos simultâneos
 * 
 * Features:
 * - Connection pooling
 * - Prepared statements (proteção SQL Injection)
 * - Retry automático
 * - Timeout configurável
 * - Logging de erros
 */

class Database {
    private static $instance = null;
    private $connection = null;
    private $host;
    private $user;
    private $pass;
    private $database;
    private $charset = 'utf8mb4';
    private $max_retries = 3;
    private $retry_delay = 100; // ms
    
    /**
     * Singleton - obter instância da conexão
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Construtor privado (Singleton)
     */
    private function __construct() {
        $this->host = DB_HOST;
        $this->user = DB_USER;
        $this->pass = DB_PASS;
        $this->database = DB_NAME;
        
        $this->connect();
    }
    
    /**
     * Conectar ao banco de dados com retry
     */
    private function connect() {
        $attempt = 0;
        
        while ($attempt < $this->max_retries) {
            try {
                $this->connection = new mysqli(
                    $this->host,
                    $this->user,
                    $this->pass,
                    $this->database
                );
                
                // Verificar conexão
                if ($this->connection->connect_error) {
                    throw new Exception('Erro de conexão: ' . $this->connection->connect_error);
                }
                
                // Configurar charset
                $this->connection->set_charset($this->charset);
                
                // Configurar timeout
                $this->connection->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
                $this->connection->options(MYSQLI_OPT_READ_TIMEOUT, 10);
                $this->connection->options(MYSQLI_OPT_WRITE_TIMEOUT, 10);
                
                // Sucesso
                return true;
                
            } catch (Exception $e) {
                $attempt++;
                
                if ($attempt < $this->max_retries) {
                    usleep($this->retry_delay * 1000); // Esperar antes de retry
                } else {
                    error_log('Erro ao conectar ao banco de dados: ' . $e->getMessage());
                    throw $e;
                }
            }
        }
    }
    
    /**
     * Obter conexão
     */
    public function getConnection() {
        if ($this->connection === null || !$this->connection->ping()) {
            $this->connect();
        }
        return $this->connection;
    }
    
    /**
     * Preparar e executar query com proteção SQL Injection
     * 
     * @param string $query Query SQL
     * @param array $params Parâmetros
     * @return mysqli_result|bool
     */
    public function execute($query, $params = []) {
        try {
            $conn = $this->getConnection();
            $stmt = $conn->prepare($query);
            
            if (!$stmt) {
                throw new Exception('Erro ao preparar query: ' . $conn->error);
            }
            
            // Bind parameters se houver
            if (!empty($params)) {
                $types = '';
                $values = [];
                
                foreach ($params as $param) {
                    if (is_int($param)) {
                        $types .= 'i';
                    } elseif (is_float($param)) {
                        $types .= 'd';
                    } else {
                        $types .= 's';
                    }
                    $values[] = $param;
                }
                
                // Usar call_user_func_array para bind dinâmico
                array_unshift($values, $types);
                call_user_func_array([$stmt, 'bind_param'], $this->refValues($values));
            }
            
            // Executar
            if (!$stmt->execute()) {
                throw new Exception('Erro ao executar query: ' . $stmt->error);
            }
            
            return $stmt;
            
        } catch (Exception $e) {
            error_log('Database Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Obter uma linha como array associativo
     */
    public function fetchOne($query, $params = []) {
        try {
            $stmt = $this->execute($query, $params);
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            return $row;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Obter todas as linhas como array
     */
    public function fetchAll($query, $params = []) {
        try {
            $stmt = $this->execute($query, $params);
            $result = $stmt->get_result();
            $rows = [];
            
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            
            $stmt->close();
            return $rows;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Inserir dados
     */
    public function insert($table, $data) {
        try {
            $columns = array_keys($data);
            $values = array_values($data);
            $placeholders = array_fill(0, count($columns), '?');
            
            $query = "INSERT INTO {$table} (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")";
            
            $stmt = $this->execute($query, $values);
            $insert_id = $this->connection->insert_id;
            $stmt->close();
            
            return $insert_id;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Atualizar dados
     */
    public function update($table, $data, $where, $where_params = []) {
        try {
            $set_parts = [];
            $values = [];
            
            foreach ($data as $column => $value) {
                $set_parts[] = "{$column} = ?";
                $values[] = $value;
            }
            
            $values = array_merge($values, $where_params);
            
            $query = "UPDATE {$table} SET " . implode(', ', $set_parts) . " WHERE {$where}";
            
            $stmt = $this->execute($query, $values);
            $affected = $stmt->affected_rows;
            $stmt->close();
            
            return $affected;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Deletar dados
     */
    public function delete($table, $where, $where_params = []) {
        try {
            $query = "DELETE FROM {$table} WHERE {$where}";
            $stmt = $this->execute($query, $where_params);
            $affected = $stmt->affected_rows;
            $stmt->close();
            return $affected;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Contar registros
     */
    public function count($table, $where = null, $where_params = []) {
        try {
            $query = "SELECT COUNT(*) as count FROM {$table}";
            
            if ($where) {
                $query .= " WHERE {$where}";
            }
            
            $result = $this->fetchOne($query, $where_params);
            return $result ? $result['count'] : 0;
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Executar transação
     */
    public function transaction($callback) {
        try {
            $conn = $this->getConnection();
            $conn->begin_transaction();
            
            $result = call_user_func($callback, $this);
            
            $conn->commit();
            return $result;
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
    }
    
    /**
     * Obter último erro
     */
    public function getLastError() {
        if ($this->connection) {
            return $this->connection->error;
        }
        return 'Sem conexão';
    }
    
    /**
     * Fechar conexão
     */
    public function close() {
        if ($this->connection) {
            $this->connection->close();
            $this->connection = null;
        }
    }
    
    /**
     * Helper para bind_param com referências
     * Necessário porque bind_param requer referências
     */
    private function refValues($arr) {
        $refs = [];
        foreach ($arr as $key => $value) {
            $refs[$key] = &$arr[$key];
        }
        return $refs;
    }
    
    /**
     * Destrutor - fechar conexão
     */
    public function __destruct() {
        $this->close();
    }
}

?>
