#!/bin/bash

# ==============================================================================
# Script para Gerenciar Repositórios Git (Criar, Atualizar, Reverter)
# Versão 5.0 - Adiciona --help e comando de emergência --revert-last
# ==============================================================================

# --- CONFIGURAÇÃO ---
set -e # Termina o script se um comando falhar

GIT_DIR=$(pwd)
REPOS_DIR="$GIT_DIR/repos"
LOCK_FILE_DIR="$GIT_DIR/_private"
LOCK_FILE="$LOCK_FILE_DIR/script.lock"
DEFAULT_BRANCH="main" # Mude para "master" se preferir

# --- FUNÇÕES AUXILIARES ---

function mostrar_ajuda() {
    echo ""
    echo "Script para Gerenciar Repositórios Git (v5.0)"
    echo "------------------------------------------------"
    echo "Este script facilita a criação, atualização e manutenção de repositórios Git bare."
    echo ""
    echo "USO:"
    echo "  ./gerenciar_repo.sh <nome-do-repo>               # Modo Padrão: Cria ou atualiza o repo."
    echo "  ./gerenciar_repo.sh <nome-do-repo> --revert-last # Modo de Emergência: Desfaz o último commit."
    echo "  ./gerenciar_repo.sh --help                       # Exibe esta mensagem de ajuda."
    echo ""
    echo "MODO PADRÃO:"
    echo "  Na primeira execução com um <nome-do-repo>, o script cria uma pasta de trabalho"
    echo "  temporária e pausa, pedindo que você copie os arquivos do seu projeto para dentro dela."
    echo "  Após pressionar Enter, ele cria o repositório e faz o commit inicial."
    echo "  Em execuções futuras, ele detecta que o repositório já existe e aplica suas"
    echo "  alterações como uma atualização."
    echo ""
    echo "MODO DE EMERGÊNCIA (--revert-last):"
    echo "  Este comando desfaz o último commit realizado em um repositório, tanto localmente"
    echo "  quanto no servidor. É útil para corrigir envios feitos por engano."
    echo "  UMA CONFIRMAÇÃO SERÁ SOLICITADA ANTES DA EXECUÇÃO."
    echo ""
    echo "EXEMPLOS:"
    echo "  ./gerenciar_repo.sh meu-projeto        # Inicia a criação/atualização do 'meu-projeto'"
    echo "  ./gerenciar_repo.sh projeto-errado --revert-last"
    echo ""
}

function reverter_ultimo_commit() {
    local repo_nome="$1"
    local repo_bare_path="$REPOS_DIR/$repo_nome.git"

    echo "==> MODO DE EMERGÊNCIA: REVERTER ÚLTIMO COMMIT <=="
    
    if [ ! -d "$repo_bare_path" ]; then
        echo "ERRO: O repositório '$repo_nome' não foi encontrado em '$REPOS_DIR'."
        exit 1
    fi

    local revert_workspace="$GIT_DIR/tmp_revert_$(date +%s)"
    # Garante que a pasta temporária seja limpa ao sair
    trap 'rm -rf "$revert_workspace"' EXIT

    echo "=> Preparando ambiente seguro em: $revert_workspace"
    git clone "$repo_bare_path" "$revert_workspace"
    cd "$revert_workspace"

    if [ $(git rev-list --count HEAD) -lt 2 ]; then
        echo "AVISO: O repositório tem apenas 1 commit ou está vazio. Não há o que reverter."
        exit 0
    fi
    
    local branch_name=$(git rev-parse --abbrev-ref HEAD)
    local last_commit_info=$(git log -1 --pretty=format:'%h (%an): %s')

    echo ""
    echo "O último commit no branch '$branch_name' é:"
    echo "  -> $last_commit_info"
    echo ""
    echo "Esta ação irá REMOVER PERMANENTEMENTE este commit do histórico do repositório."
    read -p "Você tem certeza absoluta que deseja continuar? [s/N]: " confirmacao

    if [[ "$confirmacao" =~ ^[sS]$ ]]; then
        echo "=> Confirmado. Revertendo o commit localmente..."
        git reset --hard HEAD~1
        
        echo "=> Sincronizando com o repositório remoto (forçando de forma segura)..."
        git push origin "$branch_name" --force-with-lease
        
        echo ""
        echo "SUCESSO! O último commit foi removido do repositório '$repo_nome'."
    else
        echo "Operação cancelada pelo usuário."
    fi
    
    # O 'trap' cuidará da limpeza da pasta
    exit 0
}


# --- LÓGICA PRINCIPAL ---

# 1. Processa comandos especiais (--help, --revert-last)
if [[ "$1" == "--help" || "$1" == "-h" ]]; then
    mostrar_ajuda
    exit 0
fi

if [[ "$2" == "--revert-last" ]]; then
    reverter_ultimo_commit "$1"
    # A função reverter_ultimo_commit já encerra o script
fi

# 2. Valida o argumento para o fluxo padrão
if [ -z "$1" ]; then
    echo "ERRO: Forneça o nome do repositório como um argumento."
    echo "Uso: ./gerenciar_repo.sh nome-do-projeto"
    echo "Use ./gerenciar_repo.sh --help para mais detalhes."
    exit 1
fi
NOME_DO_REPO="$1"
REPO_BARE_PATH="$REPOS_DIR/$NOME_DO_REPO.git"

# O restante do script (fluxo de criação/atualização) continua aqui...
# (O código da Versão 4.0 permanece inalterado daqui para baixo)

# 3. Verifica se existe uma sessão anterior interrompida
if [ -f "$LOCK_FILE" ]; then
    TMP_WORKSPACE=$(cat "$LOCK_FILE")
    echo "AVISO: Sessão anterior interrompida encontrada."
    echo "Continuando o trabalho na pasta: $TMP_WORKSPACE"
    
    if [ ! -d "$TMP_WORKSPACE" ]; then
        echo "ERRO: A pasta de trabalho não foi encontrada. Removendo o lock."
        rm -f "$LOCK_FILE"
        exit 1
    fi
    
    cd "$TMP_WORKSPACE"

else
    # --- FLUXO NORMAL (PRIMEIRA EXECUÇÃO) ---
    echo "=> Preparando a pasta de trabalho temporária..."
    TMP_WORKSPACE="$GIT_DIR/tmp_workspace_$(date +%s)_$NOME_DO_REPO"
    mkdir -p "$TMP_WORKSPACE"
    mkdir -p "$LOCK_FILE_DIR"
    echo "$TMP_WORKSPACE" > "$LOCK_FILE"
    echo "Pasta temporária criada em: $TMP_WORKSPACE"
    echo ""

    # --- PAUSA PARA O USUÁRIO ---
    echo "========================================================================"
    echo ">>> AÇÃO NECESSÁRIA <<<"
    echo "Agora, copie todos os arquivos do seu projeto para a pasta:"
    echo "$TMP_WORKSPACE"
    read -p "Após copiar os arquivos, pressione [Enter] para continuar..."
    # -----------------------------
    
    cd "$TMP_WORKSPACE"
fi

# 4. Verifica se o usuário copiou algum arquivo
if [ -z "$(ls -A .)" ]; then
    echo "Nenhum arquivo encontrado na pasta de trabalho. Abortando e limpando..."
    cd ..
    rm -rf "$TMP_WORKSPACE"
    rm -f "$LOCK_FILE"
    exit 1
fi

# 5. DECISÃO AUTOMÁTICA: CRIAR OU ATUALIZAR?
if [ -d "$REPO_BARE_PATH" ] && [ $(git -C "$REPO_BARE_PATH" rev-list --count --all) -gt 0 ]; then
    
    # --- FLUXO DE ATUALIZAÇÃO ---
    echo "==> Repositório existente detectado. Sincronizando arquivos..."
    
    USER_FILES_PATH=$(pwd)
    CLONE_DIR="$GIT_DIR/tmp_clone_$(date +%s)"
    
    echo "=> PASSO A: Clonando o projeto em uma área separada..."
    git clone "$REPO_BARE_PATH" "$CLONE_DIR"
    
    echo "=> PASSO B: Sincronizando seus arquivos com o projeto clonado..."
    rsync -av --delete --exclude=".git/" "$USER_FILES_PATH/" "$CLONE_DIR/"
    
    cd "$CLONE_DIR"
    
    if git diff-index --quiet HEAD --; then
        echo "Nenhuma nova alteração detectada. O projeto já está atualizado."
    else
        echo "=> PASSO C: Adicionando, commitando e enviando as alterações..."
        git add .
        git commit -m "Atualização de conteúdo via script"
        git push
    fi
else

    # --- FLUXO DE CRIAÇÃO ---
    echo "==> Repositório novo ou vazio. Procedendo com o commit inicial..."

    if [ ! -d "$REPO_BARE_PATH" ]; then
        echo "=> PASSO A: Criando o repositório bare..."
        mkdir -p "$(dirname "$REPO_BARE_PATH")"
        git init --bare --initial-branch="$DEFAULT_BRANCH" "$REPO_BARE_PATH"
    fi

    echo "=> PASSO B: Adicionando, commitando e enviando os arquivos..."
    git init
    git add .
    git commit -m "Commit inicial do projeto $NOME_DO_REPO"
    git branch -M "$DEFAULT_BRANCH"
    git remote add origin "$REPO_BARE_PATH"
    git push -u origin "$DEFAULT_BRANCH"
fi

# --- LIMPEZA FINAL ---
echo ""
echo "=> PASSO FINAL: Limpando a área de trabalho..."
cd "$GIT_DIR"
rm -rf "$TMP_WORKSPACE"
if [ -d "$CLONE_DIR" ]; then
    rm -rf "$CLONE_DIR"
fi
rm -f "$LOCK_FILE"
echo "Limpeza concluída."
echo ""
echo "========================================================================"
echo "PROCESSO CONCLUÍDO COM SUCESSO!"
echo "O repositório '$NOME_DO_REPO' foi criado/atualizado."
echo "========================================================================"
