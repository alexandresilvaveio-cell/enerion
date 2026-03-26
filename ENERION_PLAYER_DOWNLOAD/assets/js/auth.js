/**
 * =====================================================
 * ENERION PLAYER - JAVASCRIPT DE AUTENTICAÇÃO
 * =====================================================
 * Funções de autenticação e gerenciamento de sessão
 */

// ============================================
// NOTIFICAÇÕES
// ============================================
function showNotification(message, type = 'info', duration = 3000) {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, duration);
}

// ============================================
// VERIFICAÇÃO DE AUTENTICAÇÃO
// ============================================
function isAuthenticated() {
    const token = localStorage.getItem('auth_token');
    const type = localStorage.getItem('user_type');
    return !!(token && type);
}

function getUserInfo() {
    return {
        token: localStorage.getItem('auth_token'),
        type: localStorage.getItem('user_type'),
        id: localStorage.getItem('user_id'),
        username: localStorage.getItem('user_username')
    };
}

// ============================================
// LOGIN - ADMIN
// ============================================
async function loginAdmin(username, password) {
    try {
        const response = await fetch('/api/auth/login-admin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ username, password })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Armazenar dados da sessão com nome consistente 'auth_token'
            localStorage.setItem('auth_token', data.token);
            localStorage.setItem('user_type', 'admin');
            localStorage.setItem('user_id', data.admin.id);
            localStorage.setItem('user_username', data.admin.username);
            localStorage.setItem('user_email', data.admin.email || '');
            
            // Redirecionar para dashboard
            window.location.href = './dashboard.html';
            return true;
        } else {
            showNotification(data.message || 'Erro ao fazer login', 'error');
            return false;
        }
    } catch (error) {
        console.error('Erro:', error);
        showNotification('Erro ao conectar com o servidor', 'error');
        return false;
    }
}

// ============================================
// LOGIN - REVENDEDOR
// ============================================
async function loginRevendedor(username, password) {
    try {
        const response = await fetch('/api/auth/login-revendedor.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ username, password })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Armazenar dados da sessão
            localStorage.setItem('auth_token', data.token);
            localStorage.setItem('user_type', 'revendedor');
            localStorage.setItem('user_id', data.revendedor.id);
            localStorage.setItem('user_username', data.revendedor.username);
            localStorage.setItem('user_nome', data.revendedor.nome || '');
            
            // Redirecionar para dashboard
            window.location.href = '../revendedor/dashboard.html';
            return true;
        } else {
            showNotification(data.message || 'Erro ao fazer login', 'error');
            return false;
        }
    } catch (error) {
        console.error('Erro:', error);
        showNotification('Erro ao conectar com o servidor', 'error');
        return false;
    }
}

// ============================================
// LOGOUT - FUNCIONAL E SEGURO
// ============================================
function logout() {
    // Confirmar logout
    if (!confirm('Tem certeza que deseja sair?')) {
        return false;
    }
    
    // Limpar localStorage
    localStorage.removeItem('auth_token');
    localStorage.removeItem('user_token');
    localStorage.removeItem('user_type');
    localStorage.removeItem('user_id');
    localStorage.removeItem('user_username');
    localStorage.removeItem('user_email');
    localStorage.removeItem('user_nome');
    
    // Limpar sessionStorage também
    sessionStorage.clear();
    
    // Redirecionar para login
    window.location.href = './login.html';
    
    return true;
}

// ============================================
// REQUISIÇÕES À API
// ============================================
async function apiRequest(endpoint, options = {}) {
    const defaultOptions = {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json'
        }
    };
    
    // Adicionar token se existir
    const userInfo = getUserInfo();
    if (userInfo.token) {
        defaultOptions.headers['Authorization'] = `Bearer ${userInfo.token}`;
    }
    
    const finalOptions = { ...defaultOptions, ...options };
    
    try {
        const response = await fetch(endpoint, finalOptions);
        
        // Se 401, fazer logout
        if (response.status === 401) {
            logout();
            throw new Error('Sessão expirada');
        }
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        return await response.json();
    } catch (error) {
        console.error('Erro na requisição:', error);
        throw error;
    }
}

// ============================================
// VERIFICAÇÃO DE AUTENTICAÇÃO AO CARREGAR
// ============================================
function checkAuthOnLoad() {
    const currentPage = window.location.pathname;
    const isLoginPage = currentPage.includes('login.html');
    const isPublicPage = currentPage.includes('index.html') || isLoginPage;
    
    // Se está em página pública, não fazer nada
    if (isPublicPage) {
        return;
    }
    
    // Se não está autenticado, redirecionar para login
    if (!isAuthenticated()) {
        // Determinar qual login baseado na URL
        if (currentPage.includes('admin')) {
            window.location.href = './login.html';
        } else if (currentPage.includes('revendedor')) {
            window.location.href = '../revendedor/login.html';
        } else {
            window.location.href = './login.html';
        }
    }
}

// Executar verificação ao carregar
document.addEventListener('DOMContentLoaded', checkAuthOnLoad);

// ============================================
// EXPORTAR FUNÇÕES GLOBAIS
// ============================================
window.showNotification = showNotification;
window.isAuthenticated = isAuthenticated;
window.getUserInfo = getUserInfo;
window.logout = logout;
window.apiRequest = apiRequest;
window.loginAdmin = loginAdmin;
window.loginRevendedor = loginRevendedor;
