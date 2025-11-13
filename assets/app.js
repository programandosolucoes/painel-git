// assets/app.js - Com tratamento inteligente de porta SSH

document.addEventListener('DOMContentLoaded', () => {

    // --- CONFIGURAÇÃO LIDA DO HTML ---
    const bodyData = document.body.dataset;
    const IS_ADMIN = JSON.parse(bodyData.isAdmin || 'false');
    const LOGGED_IN_USER = bodyData.loggedInUser || null;
    const SSH_CONFIG = JSON.parse(bodyData.sshConfig || '{}');

    // --- ELEMENTOS DO DOM ---
    const alertContainer = document.querySelector('.alert-container');
    const repoList = document.getElementById('repo-list');
    const repoModal = document.getElementById('repo-modal');
    const modalRepoName = document.getElementById('modal-repo-name');
    const refSelector = document.getElementById('ref-selector');
    const repoFiles = document.getElementById('repo-files');
    const downloadBtn = document.getElementById('download-zip-btn');
    const helpBtn = document.getElementById('help-btn');
    const helpModal = document.getElementById('help-modal');
    const sshForm = document.getElementById('ssh-form');
    const sshKeyList = document.getElementById('ssh-key-list');
    const createForm = document.getElementById('create-form');
    const importForm = document.getElementById('import-form');
    const addUserForm = document.getElementById('add-user-form');
    const permissionsGrid = document.getElementById('permissions-grid');
    const savePermissionsBtn = document.getElementById('save-permissions-btn');

    // --- FUNÇÕES ---
    function showAlert(message, type = 'success') { const alertDiv = document.createElement('div'); const bgColor = type === 'success' ? 'var(--success)' : 'var(--danger)'; alertDiv.className = 'alert'; alertDiv.style.backgroundColor = bgColor; alertDiv.textContent = message; alertContainer.prepend(alertDiv); setTimeout(() => { alertDiv.style.transition = 'opacity 0.5s'; alertDiv.style.opacity = '0'; setTimeout(() => alertDiv.remove(), 500); }, 5000); }
    function copyToClipboard(text, button) { navigator.clipboard.writeText(text).then(() => { const originalText = button.innerHTML; button.innerHTML = '<i class="fas fa-check"></i> Copiado!'; button.classList.add('btn-success'); setTimeout(() => { button.innerHTML = originalText; button.classList.remove('btn-success'); }, 2000); }).catch(err => { showAlert('Falha ao copiar comando.', 'danger'); }); }
    async function apiRequest(formData) { try { const response = await fetch('api.php', { method: 'POST', body: formData }); const result = await response.json(); if (!response.ok) throw new Error(result.message || 'Erro no servidor'); return result; } catch (error) { console.error('API Request Error:', error); return { success: false, message: error.message }; } }

    async function loadRepos() {
        if (!repoList) return;
        repoList.innerHTML = '<p>Carregando...</p>';
        const formData = new FormData(); formData.append('action', 'list_repos');
        const result = await apiRequest(formData);
        
        if (!result.success) { repoList.innerHTML = `<p style="color:red;">Erro ao carregar repositórios: ${result.message}</p>`; return; }
        
        repoList.innerHTML = '';
        
        // URL Base HTTP
        const baseUrl = window.location.href.split('?')[0].replace(/\/(index\.php|login\.php|panel\.php)?$/, '').replace(/\/$/, '');

        if (result.repos && result.repos.length > 0) {
            result.repos.forEach(repoName => {
                const li = document.createElement('li'); li.className = 'repo-item';
                
                // --- LÓGICA DA PORTA SSH ---
                // Se a porta for 22 ou estiver vazia, não adiciona nada.
                // Se for diferente (ex: 65002), adiciona :65002
                let portStr = '';
                if (SSH_CONFIG.ssh_port && SSH_CONFIG.ssh_port !== '22') {
                    portStr = `:${SSH_CONFIG.ssh_port}`;
                }
                
                // Montagem das URLs
                const sshUrl = `ssh://${SSH_CONFIG.ssh_user}@${SSH_CONFIG.ssh_host}${portStr}${SSH_CONFIG.repo_base_path}${repoName}.git`;
                const httpUrl = `${baseUrl}/repos/${repoName}.git`;
                
                // Botões de Admin
                let adminButtons = IS_ADMIN ? `<button class="btn-danger" onclick="deleteRepo('${repoName}')"><i class="fas fa-trash"></i> Excluir</button>` : '';
                
                // Renderiza HTML
                li.innerHTML = `
                    <div class="item-header">
                        <span><i class="fas fa-folder"></i> <strong>${repoName}</strong></span>
                        <div>
                            <button class="btn-secondary" onclick="viewRepo('${repoName}')"><i class="fas fa-eye"></i> Visualizar</button>
                            ${adminButtons}
                        </div>
                    </div>
                    <div>
                        <div class="protocol-selector">
                            <label class="protocol-label">
                                <input type="radio" name="proto_${repoName}" value="${sshUrl}" checked onchange="updateCloneInput(this, 'input_${repoName}')"> SSH
                            </label>
                            <label class="protocol-label">
                                <input type="radio" name="proto_${repoName}" value="${httpUrl}" onchange="updateCloneInput(this, 'input_${repoName}')"> HTTP(S)
                            </label>
                        </div>
                        <div class="clone-command-wrapper">
                            <input type="text" id="input_${repoName}" value="${sshUrl}" readonly onclick="this.select()">
                            <button onclick="copyInput('input_${repoName}', this)"><i class="fas fa-copy"></i> Copiar</button>
                        </div>
                    </div>`;
                repoList.appendChild(li);
            });
        } else { repoList.innerHTML = '<p>Nenhum repositório para exibir.</p>'; }
    }

    // Funções Globais Auxiliares
    window.updateCloneInput = function(radio, inputId) {
        document.getElementById(inputId).value = radio.value;
    }

    window.copyInput = function(inputId, btnElement) {
        const input = document.getElementById(inputId);
        copyToClipboard(input.value, btnElement);
    }

    async function viewRepo(repoName) { if (!repoModal) return; repoModal.style.display = 'block'; modalRepoName.textContent = `Repositório: ${repoName}`; repoFiles.textContent = 'Carregando...'; repoFiles.classList.remove('error'); refSelector.innerHTML = ''; refSelector.disabled = true; downloadBtn.style.pointerEvents = 'none'; downloadBtn.style.opacity = '0.5'; await fetchRepoDetails(repoName, 'HEAD'); }
    async function fetchRepoDetails(repoName, ref) {
        try {
            const response = await fetch(`api.php?action=get_repo_details&repo=${repoName}&ref=${ref}`);
            const result = await response.json();
            if (!result.success) throw new Error(result.message);
            repoFiles.classList.remove('error'); repoFiles.textContent = result.files;
            refSelector.innerHTML = '';
            if (result.is_empty) { refSelector.disabled = true; downloadBtn.style.pointerEvents = 'none'; downloadBtn.style.opacity = '0.5'; return; }
            refSelector.disabled = false; downloadBtn.style.pointerEvents = 'auto'; downloadBtn.style.opacity = '1';
            const branchesGroup = document.createElement('optgroup'); branchesGroup.label = 'Branches';
            result.branches.forEach(branch => { const option = document.createElement('option'); option.value = option.textContent = branch; if (branch === ref || (ref === 'HEAD' && (result.branches.includes('main') || result.branches[0] === branch))) option.selected = true; branchesGroup.appendChild(option); });
            refSelector.appendChild(branchesGroup);
            const tagsGroup = document.createElement('optgroup'); tagsGroup.label = 'Tags';
            result.tags.forEach(tag => { const option = document.createElement('option'); option.value = option.textContent = tag; if (tag === ref) option.selected = true; tagsGroup.appendChild(option); });
            refSelector.appendChild(tagsGroup);
            refSelector.onchange = () => fetchRepoDetails(repoName, refSelector.value);
            downloadBtn.href = `download.php?repo=${repoName}&ref=${refSelector.value}`;
        } catch (error) { showAlert(error.message, 'danger'); repoFiles.textContent = `Erro ao carregar detalhes:\n\n${error.message}`; repoFiles.classList.add('error'); refSelector.disabled = true; downloadBtn.style.pointerEvents = 'none'; downloadBtn.style.opacity = '0.5'; }
    }

    function closeModal(modalId) { const modal = document.getElementById(modalId); if(modal) modal.style.display = 'none'; }
    async function deleteRepo(repoName) { if (!confirm(`Tem certeza que deseja excluir o repositório "${repoName}"?`)) return; const formData = new FormData(); formData.append('action', 'delete_repo'); formData.append('name', repoName); const result = await apiRequest(formData); showAlert(result.message, result.success ? 'success' : 'danger'); if (result.success) { await loadRepos(); if(IS_ADMIN) await loadAccessManagementData(); } }
    
    async function loadSshKeys() {
        if (!IS_ADMIN || !sshKeyList) return;
        sshKeyList.innerHTML = '<p>Carregando chaves...</p>';
        const formData = new FormData(); formData.append('action', 'get_ssh_keys');
        const result = await apiRequest(formData);
        if (result.success === false && result.error_diagnostic) { const diag = result.error_diagnostic; sshKeyList.innerHTML = `<div class="diagnostic warning" style="border:1px solid;padding:15px;margin-top:10px;"><small>${diag.solution}</small><code style="display:block;margin-top:10px;">${diag.command}</code></div>`; return; }
        if (!result.success) { sshKeyList.innerHTML = `<p style="color:red;">Erro: ${result.message}</p>`; return; }
        sshKeyList.innerHTML = '';
        if (result.keys && result.keys.length > 0) {
            result.keys.forEach(key => {
                const li = document.createElement('li'); li.className = 'item-header';
                let keyDisplay = key;
                if (typeof key === 'string' && key.length > 45) { keyDisplay = key.substring(0, 20) + '...' + key.substring(key.length - 20); }
                li.innerHTML = `<span title="${key}"><i class="fas fa-key"></i> ${keyDisplay}</span><button class="btn-danger" onclick="deleteSshKey(this)" title="Excluir Chave"><i class="fas fa-trash"></i></button>`;
                li.dataset.fullKey = key;
                sshKeyList.appendChild(li);
            });
        } else { sshKeyList.innerHTML = '<p>Nenhuma chave SSH autorizada encontrada.</p>'; }
    }

    async function deleteSshKey(buttonElement) {
        const keyToDelete = buttonElement.parentElement.dataset.fullKey;
        if (!confirm('Tem certeza que deseja remover esta chave SSH?')) return;
        const formData = new FormData(); formData.append('action', 'delete_ssh_key'); formData.append('key', keyToDelete);
        const result = await apiRequest(formData);
        showAlert(result.message, result.success ? 'success' : 'danger');
        if (result.success) await loadSshKeys();
    }

    async function loadAccessManagementData() {
        if(!permissionsGrid) return;
        permissionsGrid.innerHTML = '<p>Carregando usuários e repositórios...</p>';
        const formData = new FormData(); formData.append('action', 'get_users_and_permissions');
        const dataPromise = apiRequest(formData);
        const formDataRepos = new FormData(); formDataRepos.append('action', 'list_repos');
        const reposPromise = apiRequest(formDataRepos);
        const [data, reposResult] = await Promise.all([dataPromise, reposPromise]);
        if (!data.success || !reposResult.success) { permissionsGrid.innerHTML = `<p style="color:red;">Erro ao carregar dados de acesso: ${data.message || reposResult.message}</p>`; return; }
        const repos = reposResult.repos || [];
        if (repos.length === 0) { permissionsGrid.innerHTML = '<p>Nenhum repositório criado para definir permissões.</p>'; return; }
        if (data.users.length === 0) { permissionsGrid.innerHTML = '<p>Nenhum usuário comum encontrado. Adicione um usuário para gerenciar permissões.</p>'; return; }
        permissionsGrid.innerHTML = '';
        const table = document.createElement('table'); table.id = 'permissions-table';
        let headers = '<th>Usuário</th>';
        repos.forEach(repo => headers += `<th>${repo}</th>`);
        headers += '<th>Ação</th>';
        table.innerHTML = `<thead><tr>${headers}</tr></thead>`;
        const tbody = document.createElement('tbody');
        data.users.forEach(user => {
            let row = `<td>${user}</td>`;
            repos.forEach(repo => { const isChecked = data.permissions[user] && data.permissions[user].includes(repo) ? 'checked' : ''; row += `<td><input type="checkbox" data-user="${user}" data-repo="${repo}" ${isChecked}></td>`; });
            row += `<td><button class="btn-danger" onclick="deleteUser('${user}')" title="Excluir Usuário"><i class="fas fa-user-minus"></i></button></td>`;
            tbody.innerHTML += `<tr>${row}</tr>`;
        });
        table.appendChild(tbody);
        permissionsGrid.appendChild(table);
    }

    async function savePermissions() { const button = document.getElementById('save-permissions-btn'); button.disabled = true; const newPermissions = {}; document.querySelectorAll('#permissions-grid input[type="checkbox"]').forEach(cb => { if (!newPermissions[cb.dataset.user]) newPermissions[cb.dataset.user] = []; if (cb.checked) newPermissions[cb.dataset.user].push(cb.dataset.repo); }); const formData = new FormData(); formData.append('action', 'save_permissions'); formData.append('permissions', JSON.stringify(newPermissions)); const result = await apiRequest(formData); showAlert(result.message, result.success ? 'success' : 'danger'); button.disabled = false; }
    async function addUser() { const form = document.getElementById('add-user-form'); const button = form.querySelector('button'); button.disabled = true; const formData = new FormData(); formData.append('action', 'add_user'); formData.append('username', document.getElementById('new-username').value); formData.append('password', document.getElementById('new-password').value); const result = await apiRequest(formData); if (result.success) { form.reset(); showAlert("Usuário adicionado! A página será recarregada."); setTimeout(() => location.reload(), 2500); } else { showAlert(result.message, 'danger'); button.disabled = false; } }
    async function deleteUser(username) { if (!confirm(`Tem certeza que deseja remover o usuário '${username}'?`)) return; const formData = new FormData(); formData.append('action', 'delete_user'); formData.append('username', username); const result = await apiRequest(formData); if (result.success) { showAlert("Usuário removido! A página será recarregada."); setTimeout(() => location.reload(), 2500); } else { showAlert(result.message, 'danger'); } }

    // ==========================================================
    // ===== EXPOSIÇÃO GLOBAL =====
    // ==========================================================
    window.viewRepo = viewRepo;
    window.deleteRepo = deleteRepo;
    window.deleteSshKey = deleteSshKey;
    window.deleteUser = deleteUser;
    window.closeModal = closeModal;
    window.copyToClipboard = copyToClipboard;
    
    // --- EVENT LISTENERS ---
    if (LOGGED_IN_USER) {
        if (IS_ADMIN) {
            sshForm.addEventListener('submit', async (e) => { e.preventDefault(); const btn = e.target.querySelector('button'); btn.disabled = true; const formData = new FormData(); formData.append('action', 'add_ssh_key'); formData.append('key', document.getElementById('ssh-key').value); const result = await apiRequest(formData); showAlert(result.message, result.success ? 'success' : 'danger'); if(result.success) e.target.reset(); await loadSshKeys(); btn.disabled = false; });
            createForm.addEventListener('submit', async (e) => { e.preventDefault(); const btn = e.target.querySelector('button'); btn.disabled = true; const formData = new FormData(); formData.append('action', 'create_repo'); formData.append('name', document.getElementById('repo-name').value); const result = await apiRequest(formData); showAlert(result.message, result.success ? 'success' : 'danger'); if(result.success) { e.target.reset(); await Promise.all([loadRepos(), loadAccessManagementData()]); } btn.disabled = false; });
            importForm.addEventListener('submit', async (e) => { e.preventDefault(); const btn = e.target.querySelector('button'); btn.disabled = true; const formData = new FormData(); formData.append('action', 'import_repo'); formData.append('url', document.getElementById('import-url').value); const result = await apiRequest(formData); showAlert(result.message, result.success ? 'success' : 'danger'); if(result.success) { e.target.reset(); await Promise.all([loadRepos(), loadAccessManagementData()]); } btn.disabled = false; });
            addUserForm.addEventListener('submit', (e) => { e.preventDefault(); addUser(); });
            savePermissionsBtn.addEventListener('click', savePermissions);
        }
        helpBtn.addEventListener('click', () => {
            const exampleRepoName = 'projeto-exemplo';
            const exampleUrl = `ssh://${SSH_CONFIG.ssh_user}@${SSH_CONFIG.ssh_host}:${SSH_CONFIG.ssh_port}${SSH_CONFIG.repo_base_path}${exampleRepoName}.git`;
            const remoteAddEl = document.getElementById('remote-add-example');
            if (remoteAddEl) { remoteAddEl.textContent = `git remote add origin ${exampleUrl}`; }
            helpModal.style.display = 'block';
        });
        window.onclick = function(event) { if (event.target == repoModal) closeModal('repo-modal'); if (event.target == helpModal) closeModal('help-modal'); }
        
        loadRepos();
        if (IS_ADMIN) {
            loadAccessManagementData();
            loadSshKeys();
        }
    }
});
