<?php
/**
 * Script de Teste - Rodar via SSH
 * 
 * Uso:
 * cd /www/wwwroot/enerionplayer.brujah.xyz
 * php test_server.php
 */

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║         TESTE DO SERVIDOR ENERION PLAYER                       ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

// ============================================
// 1. VERIFICAR PHP
// ============================================
echo "1️⃣  PHP VERSION\n";
echo "   PHP: " . phpversion() . "\n";
echo "   ✅ OK\n\n";

// ============================================
// 2. VERIFICAR ARQUIVO CONFIG
// ============================================
echo "2️⃣  VERIFICAR config/config.php\n";
if (file_exists('config/config.php')) {
    echo "   ✅ Arquivo encontrado\n";
    require_once 'config/config.php';
    
    if (defined('DB_HOST')) {
        echo "   DB_HOST: " . DB_HOST . "\n";
        echo "   DB_USER: " . DB_USER . "\n";
        echo "   DB_NAME: " . DB_NAME . "\n";
        echo "   ✅ Constantes definidas\n";
    } else {
        echo "   ❌ Constantes NÃO definidas\n";
    }
} else {
    echo "   ❌ Arquivo NÃO encontrado\n";
}
echo "\n";

// ============================================
// 3. TESTAR CONEXÃO MYSQL
// ============================================
echo "3️⃣  CONEXÃO MYSQL\n";
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        echo "   ❌ Erro: " . $conn->connect_error . "\n";
    } else {
        echo "   ✅ Conectado com sucesso\n";
        echo "   Host: " . DB_HOST . "\n";
        echo "   Banco: " . DB_NAME . "\n";
        
        // ============================================
        // 4. LISTAR TABELAS
        // ============================================
        echo "\n4️⃣  TABELAS DO BANCO\n";
        $result = $conn->query("SHOW TABLES");
        $tables = [];
        
        while ($row = $result->fetch_array()) {
            $tables[] = $row[0];
        }
        
        if (count($tables) > 0) {
            echo "   ✅ Tabelas encontradas:\n";
            foreach ($tables as $table) {
                echo "      - $table\n";
            }
        } else {
            echo "   ❌ Nenhuma tabela encontrada\n";
        }
        
        // ============================================
        // 5. TESTAR FUNÇÃO getDBConnection
        // ============================================
        echo "\n5️⃣  FUNÇÃO getDBConnection()\n";
        if (function_exists('getDBConnection')) {
            echo "   ✅ Função existe\n";
            $test_conn = getDBConnection();
            echo "   ✅ Conexão funcionando\n";
        } else {
            echo "   ❌ Função NÃO existe\n";
        }
        
        // ============================================
        // 6. TESTAR FUNÇÃO respondSuccess
        // ============================================
        echo "\n6️⃣  FUNÇÃO respondSuccess()\n";
        if (function_exists('respondSuccess')) {
            echo "   ✅ Função existe\n";
        } else {
            echo "   ❌ Função NÃO existe\n";
        }
        
        // ============================================
        // 7. TESTAR FUNÇÃO respondError
        // ============================================
        echo "\n7️⃣  FUNÇÃO respondError()\n";
        if (function_exists('respondError')) {
            echo "   ✅ Função existe\n";
        } else {
            echo "   ❌ Função NÃO existe\n";
        }
        
        // ============================================
        // 8. CONTAR REGISTROS
        // ============================================
        echo "\n8️⃣  CONTAGEM DE REGISTROS\n";
        foreach ($tables as $table) {
            $count_result = $conn->query("SELECT COUNT(*) as count FROM $table");
            $count_row = $count_result->fetch_assoc();
            $count = $count_row['count'];
            echo "   $table: $count registros\n";
        }
        
        // ============================================
        // 9. TESTAR API REVENDEDORES
        // ============================================
        echo "\n9️⃣  TESTAR API REVENDEDORES\n";
        if (file_exists('api/admin/revendedores.php')) {
            echo "   ✅ Arquivo encontrado\n";
            
            // Verificar sintaxe
            $output = shell_exec("php -l api/admin/revendedores.php 2>&1");
            if (strpos($output, 'No syntax errors') !== false) {
                echo "   ✅ Sintaxe OK\n";
            } else {
                echo "   ❌ Erro de sintaxe:\n";
                echo "      " . $output . "\n";
            }
        } else {
            echo "   ❌ Arquivo NÃO encontrado\n";
        }
        
        $conn->close();
    }
} catch (Exception $e) {
    echo "   ❌ Erro: " . $e->getMessage() . "\n";
}

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                    TESTE CONCLUÍDO                             ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";
?>
