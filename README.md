# Dra. Paula Sampaio — Landing Page

Landing page estática (HTML + CSS, sem framework) para captação de leads da
**Dra. Paula Sampaio**.

---

## 📁 Estrutura de arquivos

```
Paula Sampaio/
├── index.html                      → página principal (hero, sobre, antes/depois, FAQ, formulário)
├── obrigado.html                   → página de agradecimento estática (pós-envio do formulário)
├── obrigado-wordpress-snippet.html → versão da página de obrigado p/ colar num bloco HTML do WordPress
├── styles.css                      → todos os estilos (tokens de tema, layout, responsivo)
├── .htaccess                       → regras de URL (remove .html, serve em subpasta)
├── assets/                         → imagens (doctor.png, hero-bg.png, before-after-*.png)
├── meta-pixel-paula/               → plugin WordPress de Meta Pixel + CAPI
│   └── meta-pixel-paula.php
├── meta-pixel-paula.zip            → o mesmo plugin compactado, pronto p/ instalar no WP
└── .claude/                        → config local do editor (launch.json p/ preview)
```

---

## 🚀 Como o site é publicado (IMPORTANTE)

O site **não fica na raiz do domínio**. Ele é enviado para uma **subpasta** dentro
de `public_html`, ao lado de um **WordPress que ocupa a raiz**. É o mesmo modelo
dos outros projetos (Paula em `/home/`, Geovana em `/beleza/`).

```
public_html/
├── (WordPress: wp-admin, wp-content, wp-includes, ...)  ← raiz do domínio
└── home/             ← AQUI vão os arquivos desta landing
    ├── index.html
    ├── obrigado.html
    ├── styles.css
    ├── .htaccess
    └── assets/
```

Portanto a landing abre em `dominio.com.br/home/` e a página de obrigado em
`dominio.com.br/home/obrigado`. **Testar `/obrigado` na raiz dá 404** — lá é o
WordPress, não esta landing.

> Ao subir arquivos, envie **todos** (incl. `.htaccess`, que é oculto — ligue
> "mostrar arquivos ocultos" no Gerenciador de Arquivos/FTP).

---

## 🔗 .htaccess — URLs limpas

O `.htaccess` usa caminhos **relativos** (funciona em qualquer subpasta e nunca
redireciona pra raiz / WordPress):

1. `/home/index.html` → `/home/` (remove o index)
2. `/home/obrigado.html` → `/home/obrigado` (remove o `.html` preservando a subpasta)
3. Serve internamente o arquivo: `/home/obrigado` entrega `obrigado.html`

Há também um bloco de **forçar HTTPS comentado** no topo — só descomente **depois**
que o SSL estiver instalado no painel da hospedagem (senão o site fica inacessível).

---

## 📨 Formulário → Make → Planilha

O formulário (`#contactForm` no `index.html`) envia por `fetch` (POST JSON) para um
**webhook do Make**:

```
https://hook.us2.make.com/9vsv2sf2xmuu4ddf7ta23g4cm3bt25y8
```

Campos enviados no payload:

| Campo          | Origem                             |
|----------------|------------------------------------|
| `nome`         | input `#form-name`                 |
| `telefone`     | input `#form-whatsapp`             |
| `email`        | input `#form-email`                |
| `procedimento` | select `#form-procedure`           |
| `origem`       | URL da página                      |
| `data`         | data do envio (ex: `12 jun. 2026`) |
| `hora`         | hora do envio (ex: `09h30`)        |

O mapeamento desses campos para as colunas da planilha (Google Sheets) é feito
**dentro do cenário do Make**, não no código.

Após o envio bem-sucedido, o usuário é redirecionado para **`obrigado`** (URL limpa).

---

## 🙏 Página de obrigado

Há **duas versões**:

- `obrigado.html` — página estática (usada na landing em `/home/`), com botão
  **"Falar pelo WhatsApp agora"** (`wa.me/5581984189029`) e "Voltar para o site".
- `obrigado-wordpress-snippet.html` — a mesma página para **colar num bloco
  "HTML personalizado" do WordPress** (estilos isolados com prefixo `.ps-ty` para
  não conflitar com o tema). Use esta se o redirect apontar para uma página
  `/obrigado` do WordPress na raiz.

---

## 🎨 Tema

- Tema **escuro por padrão** (`<html data-theme="dark">`).
- A preferência do usuário é salva em `localStorage` na chave `paula-theme`.
- Botão de alternância (sol/lua) no menu.
- Fonte: **Montserrat** (títulos e texto).

---

## 📊 Rastreamento

- **Google Tag Manager:** container `GTM-KVJVVKHN` no `<head>`.
- **Meta Pixel + Conversions API:** ✅ instalado via plugin WordPress
  `meta-pixel-paula` (na pasta e também como `meta-pixel-paula.zip`).
  - Pixel ID: `1646143013341739`.
  - O plugin injeta o Pixel no `<head>`, dispara **PageView** e **Lead**
    (no submit do formulário) com **deduplicação por `event_id`** (navegador + CAPI),
    faz **hash SHA-256** do e-mail/telefone no navegador e tem **rate limiting** por IP.
  - O **token da CAPI fica só no PHP** (constante `PS_CAPI_TOKEN`), nunca no navegador.

### Instalar o plugin
WP Admin → Plugins → Adicionar novo → Enviar plugin → `meta-pixel-paula.zip` → Ativar.

> ⚠️ O plugin é **WordPress** (usa `wp_head`, `wp_ajax`, etc.), então ele roda no
> **WordPress da raiz** — não dentro da landing estática em `/home/`. Confirmar como
> o Pixel deve cobrir a landing estática (ver Pendências).

---

## ✅ Pendências / a confirmar

- [ ] **Número de WhatsApp da Paula** — confirmar `5581984189029` (usado em
      `obrigado.html` e no snippet).
- [ ] **Domínio + subpasta de produção** — confirmar publicação em `dominio.com.br/home/`.
- [ ] **SSL** — confirmar certificado ativo antes de descomentar o "forçar HTTPS".
- [ ] **GTM** — confirmar se o container `GTM-KVJVVKHN` é mesmo da Paula (é o mesmo
      ID usado em outros projetos; pode ser cópia a revisar).
- [ ] **Token da CAPI** — o `PS_CAPI_TOKEN` apareceu em texto puro; gerar token novo
      no Gerenciador de Eventos e substituir no plugin.
- [ ] **Cobertura do Pixel na landing estática** — o plugin é WordPress (raiz); definir
      se a landing em `/home/` precisa do Pixel direto no HTML ou se o tráfego converte
      via página do WordPress.

---

## 🛠️ Rodar localmente

Há um `.claude/launch.json` configurado para servir a pasta (ex.: `python -m http.server`).
Obs.: o servidor estático local **ignora o `.htaccess`**, então as URLs limpas
(`/obrigado` sem `.html`) só funcionam no servidor de produção (Apache).
