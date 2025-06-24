# WHMCS Hooks Collection

Coleção de hooks úteis para WHMCS - Perfeito para empresas iniciantes

## 📁 Como usar

1. Faça o download dos arquivos `.php` desejados
2. Copie para o diretório `/includes/hooks/` do seu WHMCS
3. Os hooks são ativados automaticamente

## ⚠️ Importante

- Sempre faça backup antes de instalar
- Teste em ambiente de desenvolvimento primeiro
- Alguns hooks podem precisar de configuração adicional

## 📊 Compatibilidade

- WHMCS 7.0+
- PHP 7.4+

## 🎨 Compatibilidade com Lagom Theme

Para usar o hook do CloudFlare Turnstile no tema Lagom, adicione o código abaixo em:
RSThemes > Styles > Nome do Tema > Custom Code

```css
.cf-turnstile {
    height: 65px;
    margin-bottom: 12px;
    margin-left: auto;
    margin-right: auto;
    display: block;
    width: fit-content;
}
.register-page .form-actions{
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    align-items: flex-start;
    align-items: center;
}
.page-contact .form-actions{
    flex-direction: column;
}
.page-supportticketsubmit-steptwo.lagom-not-portal .form-actions{
    display: block;
    text-align: center;
}
.page-supportticketsubmit-steptwo .form-actions {
    display: block;
    text-align: center;
}
.turnstile-popup-alert {
    position: fixed;
    bottom: 20px;
    left: 20px;
    z-index: 99999;
    max-width: 100% !important;
    animation: slideUp 0.3s ease-out;
    overflow-x: hidden !important;
}
@keyframes slideUp {
    from {
        transform: translateY(100%);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}
@keyframes slideDown {
    from {
        transform: translateY(0);
        opacity: 1;
    }
    to {
        transform: translateY(100%);
        opacity: 0;
    }
}
```

## 📖 Créditos

CloudFlare Turnstile Hook: https://github.com/hybula/whmcs-turnstile

## 🆘 Suporte

**Discord**: @ianchamba

---

*Hooks desenvolvidos para facilitar a vida de empresas que estão começando com WHMCS* 🚀
