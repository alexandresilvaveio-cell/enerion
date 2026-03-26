/**
 * =====================================================
 * ENERION PLAYER - JAVASCRIPT DASHBOARD ADMIN
 * =====================================================
 * Funcionalidades completas do painel administrativo
 */

// ============================================
// VERIFICAÇÃO DE AUTENTICAÇÃO
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    // Verificar se está autenticado
    // IMPORTANTE: login.html usa 'auth_token', então procuramos por isso
    const userToken = localStorage.getItem('auth_token') || localStorage.getItem('user_token');
    const userType = localStorage.getItem('user_type');
    
    if (!userToken || userType !== 'admin') {
        window.location.href = './login.html';
        return;
    }
    
    // Carregar nome do usuário
    const userName = localStorage.getItem('user_username') || 'Admin';
    document.getElementById('userName').textContent = userName;
    
    // Armazenar token com nome consistente
    localStorage.setItem('user_token', userToken);
    
    // Inicializar dashboard
    initDashboard();
});

// ============================================
// ELEMENTOS DO DOM
// ============================================
const sidebar = document.querySelector('.sidebar');
const menuToggle = document.querySelector('.menu-toggle');
const navItems = document.querySelectorAll('.nav-item');
const pages = document.querySelectorAll('.page');
const modal = document.getElementById('modal');
const modalClose = document.querySelector('.modal-close');
const logoutBtn = document.getElementById('logoutBtn');
const notification = document.getElementById('notification');

// ============================================
// INICIALIZAÇÃO
// ============================================
function initDashboard() {
    setupEventListeners();
    loadDashboard();
}

function setupEventListeners() {
    // Menu toggle mobile
    if (menuToggle) {
        menuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('active');
        });
    }
    
    // Navegação
    navItems.forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            const page = item.dataset.page;
            navigateTo(page);
            sidebar.classList.remove('active');
        });
    });
    
    // Modal close
    if (modalClose) {
        modalClose.addEventListener('click', closeModal);
    }
    
    // Logout
    if (logoutBtn) {
        logoutBtn.addEventListener('click', logout);
    }
    
    // Botões de ação
    document.getElementById('addRevBtn')?.addEventListener('click', openAddRevModal);
    document.getElementById('addCodigoBtn')?.addEventListener('click', openAddCodigoModal);
    
    // Filtros
    document.getElementById('deviceFilter')?.addEventListener('keyup', filterDevices);
    document.getElementById('logFilter')?.addEventListener('change', filterLogs);
}

// ============================================
// NAVEGAÇÃO
// ============================================
function navigateTo(page) {
    // Atualizar nav items
    navItems.forEach(item => item.classList.remove('active'));
    document.querySelector(`[data-page="${page}"]`).classList.add('active');
    
    // Atualizar título
    const titles = {
        'dashboard': 'Dashboard',
        'revendedores': 'Gerenciar Revendedores',
        'dispositivos': 'Monitorar Dispositivos',
        'codigos': 'Gerenciar Códigos DNS',
        'logs': 'Logs do Sistema'
    };
    document.getElementById('pageTitle').textContent = titles[page] || 'Dashboard';
    
    // Mostrar página
    pages.forEach(p => p.classList.remove('active'));
    document.getElementById(`${page}-page`).classList.add('active');
    
    // Carregar dados
    switch(page) {
        case 'revendedores':
            loadRevendedores();
            break;
        case 'dispositivos':
            loadDispositivos();
            break;
        case 'codigos':
            loadCodigos();
            break;
        case 'logs':
            loadLogs();
            break;
    }
}

// ============================================
// DASHBOARD - ESTATÍSTICAS
// ============================================
function loadDashboard() {
    loadStats();
}

async function loadStats() {
    try {
        const response = await fetch('../api/admin/stats.php', {
            method: 'GET',
            headers: {
                'Authorization': 'Bearer ' + (localStorage.getItem('auth_token') || localStorage.getItem('user_token')),
                'Content-Type': 'application/json'
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('totalRevs').textContent = data.data.total_revs || 0;
            document.getElementById('devicesOnline').textContent = data.data.devices_online || 0;
            document.getElementById('codigosAtivos').textContent = data.data.codigos_ativos || 0;
            document.getElementById('eventosHoje').textContent = data.data.eventos_hoje || 0;
        }
    } catch (error) {
        console.error('Erro ao carregar estatísticas:', error);
    }
}

// ============================================
// REVENDEDORES
// ============================================
async function loadRevendedores() {
    try {
        const response = await fetch('../api/admin/revendedores.php', {
            method: 'GET',
            headers: {
                'Authorization': 'Bearer ' + (localStorage.getItem('auth_token') || localStorage.getItem('user_token')),
                'Content-Type': 'application/json'
            }
        });
        
        const data = await response.json();
        const tbody = document.getElementById('revsTableBody');
        tbody.innerHTML = '';
        
        if (data.success && data.data.length > 0) {
            data.data.forEach(rev => {
                const row = document.createElement('tr');
                const statusClass = rev.status === 'ativo' ? 'success' : 'danger';
                const statusText = rev.status === 'ativo' ? 'Ativo' : 'Inativo';
                
                row.innerHTML = `
                    <td>${rev.nome}</td>
                    <td>${rev.username}</td>
                    <td>${rev.telefone || '-'}</td>
                    <td><span class="badge badge-${statusClass}">${statusText}</span></td>
                    <td>${rev.apps_count || 0}</td>
                    <td>${rev.codigos_count || 0}</td>
                    <td>
                        <button onclick="editRev(${rev.id})" class="btn-action" title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="deleteRev(${rev.id})" class="btn-action btn-danger" title="Deletar">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 2rem;">Nenhum revendedor encontrado</td></tr>';
        }
    } catch (error) {
        console.error('Erro ao carregar revendedores:', error);
        showNotification('Erro ao carregar revendedores', 'error');
    }
}

function openAddRevModal() {
    const modalBody = document.getElementById('modalBody');
    const modalTitle = document.getElementById('modalTitle');
    
    modalTitle.textContent = 'Novo Revendedor';
    modalBody.innerHTML = `
        <form id="addRevForm" class="form-modal">
            <div class="form-group">
                <label>Nome *</label>
                <input type="text" name="nome" required>
            </div>
            <div class="form-group">
                <label>Usuário *</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-group">
                <label>Senha *</label>
                <input type="password" name="password" required>
            </div>
            <div class="form-group">
                <label>Telefone</label>
                <input type="tel" name="telefone">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email">
            </div>
            <div class="form-group">
                <label>Máximo de Apps</label>
                <input type="number" name="max_apps" value="100">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="ativo">Ativo</option>
                    <option value="inativo">Inativo</option>
                </select>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-primary">Criar Revendedor</button>
                <button type="button" class="btn-secondary" onclick="closeModal()">Cancelar</button>
            </div>
        </form>
    `;
    
    document.getElementById('addRevForm').addEventListener('submit', submitAddRev);
    openModal();
}

async function submitAddRev(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);
    
    try {
        const response = await fetch('../api/admin/revendedores.php', {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + (localStorage.getItem('auth_token') || localStorage.getItem('user_token')),
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Revendedor criado com sucesso', 'success');
            closeModal();
            loadRevendedores();
        } else {
            showNotification(result.message || 'Erro ao criar revendedor', 'error');
        }
    } catch (error) {
        console.error('Erro:', error);
        showNotification('Erro ao criar revendedor', 'error');
    }
}

function editRev(revId) {
    showNotification('Funcionalidade em desenvolvimento', 'info');
}

async function deleteRev(revId) {
    if (!confirm('Tem certeza que deseja deletar este revendedor?')) return;
    
    try {
        const response = await fetch(`../api/admin/revendedores.php?id=${revId}`, {
            method: 'DELETE',
            headers: {
                'Authorization': 'Bearer ' + (localStorage.getItem('auth_token') || localStorage.getItem('user_token')),
                'Content-Type': 'application/json'
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Revendedor deletado com sucesso', 'success');
            loadRevendedores();
        } else {
            showNotification(result.message || 'Erro ao deletar revendedor', 'error');
        }
    } catch (error) {
        console.error('Erro:', error);
        showNotification('Erro ao deletar revendedor', 'error');
    }
}

// ============================================
// DISPOSITIVOS
// ============================================
async function loadDispositivos() {
    try {
        const response = await fetch('../api/admin/dispositivos.php', {
            method: 'GET',
            headers: {
                'Authorization': 'Bearer ' + (localStorage.getItem('auth_token') || localStorage.getItem('user_token')),
                'Content-Type': 'application/json'
            }
        });
        
        const data = await response.json();
        const tbody = document.getElementById('devicesTableBody');
        tbody.innerHTML = '';
        
        if (data.success && data.data.length > 0) {
            data.data.forEach(device => {
                const row = document.createElement('tr');
                const statusClass = device.status === 'online' ? 'success' : 'danger';
                const statusText = device.status === 'online' ? 'Online' : 'Offline';
                
                row.innerHTML = `
                    <td><code>${device.device_id}</code></td>
                    <td>${device.rev_nome}</td>
                    <td>${device.ip_address}</td>
                    <td><span class="badge badge-${statusClass}">${statusText}</span></td>
                    <td>${new Date(device.last_ping).toLocaleString('pt-BR')}</td>
                    <td>
                        <button onclick="viewDevice('${device.device_id}')" class="btn-action" title="Detalhes">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 2rem;">Nenhum dispositivo encontrado</td></tr>';
        }
    } catch (error) {
        console.error('Erro ao carregar dispositivos:', error);
        showNotification('Erro ao carregar dispositivos', 'error');
    }
}

function filterDevices() {
    const filter = document.getElementById('deviceFilter').value.toLowerCase();
    const rows = document.querySelectorAll('#devicesTableBody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
}

function viewDevice(deviceId) {
    showNotification('Detalhes do dispositivo: ' + deviceId, 'info');
}

// ============================================
// CÓDIGOS DNS
// ============================================
async function loadCodigos() {
    try {
        const response = await fetch('../api/admin/codigos.php', {
            method: 'GET',
            headers: {
                'Authorization': 'Bearer ' + (localStorage.getItem('auth_token') || localStorage.getItem('user_token')),
                'Content-Type': 'application/json'
            }
        });
        
        const data = await response.json();
        const tbody = document.getElementById('codigosTableBody');
        tbody.innerHTML = '';
        
        if (data.success && data.data.length > 0) {
            data.data.forEach(codigo => {
                const row = document.createElement('tr');
                const statusClass = codigo.status === 'ativo' ? 'success' : 'danger';
                const statusText = codigo.status === 'ativo' ? 'Ativo' : 'Inativo';
                
                row.innerHTML = `
                    <td><code>${codigo.codigo}</code></td>
                    <td>${codigo.rev_nome}</td>
                    <td><span class="badge badge-${statusClass}">${statusText}</span></td>
                    <td>${codigo.devices_count || 0}</td>
                    <td>${new Date(codigo.created_at).toLocaleString('pt-BR')}</td>
                    <td>
                        <button onclick="deleteCodigo(${codigo.id})" class="btn-action btn-danger" title="Deletar">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 2rem;">Nenhum código encontrado</td></tr>';
        }
    } catch (error) {
        console.error('Erro ao carregar códigos:', error);
        showNotification('Erro ao carregar códigos', 'error');
    }
}

function openAddCodigoModal() {
    const modalBody = document.getElementById('modalBody');
    const modalTitle = document.getElementById('modalTitle');
    
    modalTitle.textContent = 'Novo Código DNS';
    modalBody.innerHTML = `
        <form id="addCodigoForm" class="form-modal">
            <div class="form-group">
                <label>Revendedor *</label>
                <select name="rev_id" id="revSelect" required>
                    <option value="">Selecione um revendedor...</option>
                </select>
            </div>
            <div class="form-group">
                <label>Código (será gerado automaticamente)</label>
                <input type="text" id="codigoPreview" readonly>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="ativo">Ativo</option>
                    <option value="inativo">Inativo</option>
                </select>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-primary">Criar Código</button>
                <button type="button" class="btn-secondary" onclick="closeModal()">Cancelar</button>
            </div>
        </form>
    `;
    
    // Carregar revendedores
    loadRevendedoresSelect();
    
    document.getElementById('addCodigoForm').addEventListener('submit', submitAddCodigo);
    openModal();
}

async function loadRevendedoresSelect() {
    try {
        const response = await fetch('../api/admin/revendedores.php', {
            method: 'GET',
            headers: {
                'Authorization': 'Bearer ' + (localStorage.getItem('auth_token') || localStorage.getItem('user_token')),
                'Content-Type': 'application/json'
            }
        });
        
        const data = await response.json();
        const select = document.getElementById('revSelect');
        
        if (data.success && data.data.length > 0) {
            data.data.forEach(rev => {
                const option = document.createElement('option');
                option.value = rev.id;
                option.textContent = rev.nome;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Erro ao carregar revendedores:', error);
    }
}

async function submitAddCodigo(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);
    
    try {
        const response = await fetch('../api/admin/codigos.php', {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + (localStorage.getItem('auth_token') || localStorage.getItem('user_token')),
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Código criado com sucesso', 'success');
            closeModal();
            loadCodigos();
        } else {
            showNotification(result.message || 'Erro ao criar código', 'error');
        }
    } catch (error) {
        console.error('Erro:', error);
        showNotification('Erro ao criar código', 'error');
    }
}

async function deleteCodigo(codigoId) {
    if (!confirm('Tem certeza que deseja deletar este código?')) return;
    
    try {
        const response = await fetch(`../api/admin/codigos.php?id=${codigoId}`, {
            method: 'DELETE',
            headers: {
                'Authorization': 'Bearer ' + (localStorage.getItem('auth_token') || localStorage.getItem('user_token')),
                'Content-Type': 'application/json'
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Código deletado com sucesso', 'success');
            loadCodigos();
        } else {
            showNotification(result.message || 'Erro ao deletar código', 'error');
        }
    } catch (error) {
        console.error('Erro:', error);
        showNotification('Erro ao deletar código', 'error');
    }
}

// ============================================
// LOGS
// ============================================
async function loadLogs() {
    try {
        const response = await fetch('../api/admin/logs.php', {
            method: 'GET',
            headers: {
                'Authorization': 'Bearer ' + (localStorage.getItem('auth_token') || localStorage.getItem('user_token')),
                'Content-Type': 'application/json'
            }
        });
        
        const data = await response.json();
        const tbody = document.getElementById('logsTableBody');
        tbody.innerHTML = '';
        
        if (data.success && data.data.length > 0) {
            data.data.forEach(log => {
                const row = document.createElement('tr');
                const typeClass = getTipoBadgeClass(log.tipo);
                
                row.innerHTML = `
                    <td><span class="badge badge-${typeClass}">${log.tipo}</span></td>
                    <td>${log.descricao}</td>
                    <td>${log.ip}</td>
                    <td>${new Date(log.created_at).toLocaleString('pt-BR')}</td>
                `;
                tbody.appendChild(row);
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 2rem;">Nenhum log encontrado</td></tr>';
        }
    } catch (error) {
        console.error('Erro ao carregar logs:', error);
        showNotification('Erro ao carregar logs', 'error');
    }
}

function getTipoBadgeClass(tipo) {
    const classes = {
        'LOGIN_ADMIN': 'info',
        'LOGIN_REV': 'info',
        'CREATE_REV': 'success',
        'DELETE_REV': 'danger',
        'CREATE_CODIGO': 'success',
        'DELETE_CODIGO': 'danger'
    };
    return classes[tipo] || 'secondary';
}

function filterLogs() {
    const filter = document.getElementById('logFilter').value;
    const rows = document.querySelectorAll('#logsTableBody tr');
    
    rows.forEach(row => {
        if (!filter) {
            row.style.display = '';
        } else {
            const typeCell = row.querySelector('td:first-child');
            row.style.display = typeCell.textContent.includes(filter) ? '' : 'none';
        }
    });
}

// ============================================
// MODAL
// ============================================
function openModal() {
    modal.classList.add('active');
}

function closeModal() {
    modal.classList.remove('active');
}

window.addEventListener('click', (e) => {
    if (e.target === modal) {
        closeModal();
    }
});

// ============================================
// NOTIFICAÇÕES
// ============================================
function showNotification(message, type = 'info') {
    notification.textContent = message;
    notification.className = `notification show notification-${type}`;
    
    setTimeout(() => {
        notification.classList.remove('show');
    }, 3000);
}

// ============================================
// LOGOUT
// ============================================
function logout() {
    if (confirm('Tem certeza que deseja sair?')) {
        // Limpar localStorage
        localStorage.removeItem('auth_token');
        localStorage.removeItem('user_token');
        localStorage.removeItem('user_type');
        localStorage.removeItem('user_username');
        localStorage.removeItem('user_id');
        localStorage.removeItem('user_email');
        localStorage.removeItem('user_nome');
        
        // Limpar sessionStorage também
        sessionStorage.clear();
        
        // Redirecionar para login
        window.location.href = './login.html';
    }
}
