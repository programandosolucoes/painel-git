# Painel Git PHP (Hostinger / Shared Hosting) v1.0

Um painel de controle robusto e leve, escrito em PHP puro, para criar e gerenciar repositórios Git privados em ambientes de hospedagem compartilhada (como Hostinger, cPanel) ou VPS.

Diferente de outras soluções, este projeto implementa um **Proxy Git Smart HTTP** em PHP, permitindo clonar, enviar (push) e baixar (pull) repositórios via `http://` ou `https://` mesmo sem acesso root ao Apache/Nginx.

## 🚀 Funcionalidades

- **Protocolos Duplos:** Suporte completo a **SSH** e **HTTP/HTTPS**.
- **Smart HTTP Proxy:** Script `git-server.php` que atua como intermediário (CGI wrapper), compatível com servidores LiteSpeed e Apache em modo compartilhado.
- **Interface Web Moderna:**
    - Seletor dinâmico de protocolo (SSH vs HTTP).
    - Visualizador de arquivos e histórico de commits.
    - Download de branches/tags como `.zip`.
- **Sistema de Permissões:**
    - Usuário `root` (Admin) com controle total.
    - Usuários comuns com acesso restrito a repositórios específicos.
- **Gestão de Repositórios:**
    - Criar repositórios `bare` do zero.
    - Importar de URLs externas (GitHub, GitLab, etc).
    - Upload de projetos via `.zip` (Deploy via painel).
    - Criação de Tags via interface.
- **Segurança:**
    - Autenticação integrada ao Git (Basic Auth para HTTP).
    - Proteção de pastas sensíveis (`lib`, `_private`) via `.htaccess`.
    - Senhas hash (Bcrypt).

## 📋 Requisitos

- PHP 7.4 ou superior.
- Git instalado no servidor (`/usr/bin/git` ou similar).
- Módulo `mod_rewrite` ativo no Apache/LiteSpeed (padrão na maioria das hospedagens).

## 📂 Estrutura de Arquivos

```text
/git/
├── .htaccess           # Roteamento e Proteção
├── api.php             # Backend AJAX
├── git-server.php      # Proxy Smart HTTP (CGI Handler)
├── index.php           # Controller Principal
├── download.php        # Gerador de ZIP
├── criar_repo.sh       # Script Auxiliar (Terminal)
├── assets/             # CSS e JS (com Cache Busting)
├── lib/
│   ├── auth.php        # Autenticação e ACL
│   ├── config.php      # Configurações de Caminho
│   └── git.php         # Wrapper de Comandos Git
├── views/              # Templates HTML
├── _private/           # DADOS SENSÍVEIS (Protegido)
│   ├── .htpasswd       # Banco de Usuários
│   ├── config.json     # Configurações Gerais
│   └── permissions.json # Regras de Acesso
└── repos/              # Seus Repositórios (.git)
