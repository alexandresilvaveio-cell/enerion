# 🚀 DEPLOY NO RAILWAY - PASSO A PASSO

## ✅ PASSO 1: Criar Conta no Railway

1. Acesse: https://railway.app
2. Clique em **"Sign Up"**
3. Escolha **"Sign up with GitHub"**
4. Autorize o Railway a acessar sua conta GitHub
5. Pronto! Conta criada

---

## ✅ PASSO 2: Criar Novo Projeto

1. No dashboard do Railway, clique em **"Create New Project"**
2. Escolha **"Deploy from GitHub repo"**
3. Selecione o repositório: **`enerion`**
4. Railway vai detectar automaticamente que é PHP
5. Clique em **"Deploy"**

---

## ✅ PASSO 3: Configurar Variáveis de Ambiente

Após o deploy, vá em **"Variables"** e adicione:

```
DB_HOST=seu-mysql-host
DB_USER=seu-usuario
DB_PASS=sua-senha
DB_NAME=enerion
```

Se não tiver banco de dados ainda, Railway pode criar um MySQL para você:
1. Clique em **"+ Add Service"**
2. Escolha **"MySQL"**
3. Railway cria automaticamente e injeta as variáveis

---

## ✅ PASSO 4: Configurar Domínio

1. Vá em **"Settings"**
2. Procure por **"Domains"**
3. Clique em **"Add Domain"**
4. Escolha entre:
   - **Domínio Railway** (grátis): `seu-app.railway.app`
   - **Domínio Custom** (seu próprio domínio)

---

## ✅ PASSO 5: Verificar Deploy

1. Clique em **"Deployments"**
2. Veja o status do deploy
3. Se estiver **"Success"**, acesse a URL do seu app

---

## 🔧 ESTRUTURA ESPERADA

Railway vai procurar por:
- `public/index.html` ou `index.php` como entry point
- Ou arquivo `railway.json` (opcional)

Como seu projeto tem a estrutura:
```
ENERION_PLAYER_DOWNLOAD/
├── public/index.html
├── api/
├── admin/
└── config/
```

Railway vai rodar tudo automaticamente!

---

## 📝 ARQUIVO railway.json (Opcional)

Se quiser customizar, crie na raiz do projeto:

```json
{
  "build": {
    "builder": "nixpacks"
  },
  "deploy": {
    "startCommand": "php -S 0.0.0.0:$PORT",
    "restartPolicyType": "on_failure",
    "restartPolicyMaxRetries": 5
  }
}
```

Mas Railway detecta PHP automaticamente, então não é necessário.

---

## 🆘 TROUBLESHOOTING

### Erro: "Cannot find module"
- Verifique se `config/config.php` existe
- Verifique se as variáveis de ambiente estão corretas

### Erro: "Connection refused"
- Adicione MySQL service no Railway
- Configure as variáveis de banco de dados

### Erro: "Permission denied"
- Railway roda como usuário não-root
- Certifique-se que os arquivos têm permissões corretas

---

## ✨ PRONTO!

Seu app estará rodando em:
```
https://seu-app.railway.app
```

E será atualizado automaticamente quando você fizer push no GitHub! 🎉

---

## 📞 SUPORTE

Se tiver problemas:
1. Verifique os logs no Railway: **"Logs"** tab
2. Verifique as variáveis de ambiente
3. Teste localmente primeiro

**Sucesso!** 🚀
