# Login Arins — plugin de tela de login e barra de administração

Status: aprovado em conversa, aguardando revisão do arquivo.
Data: 2026-09-25

## Objetivo

Plugin WordPress standalone (`Login_Arins_WP`) que:

1. Reestiliza a tela de login (`wp-login.php`) do site da Arins com a
   identidade visual do portal (Pagina_Arins), substituindo o visual padrão
   do WordPress.
2. Customiza textos e links da tela de login (logo, mensagens de erro,
   rodapé).
3. Permite ocultar a barra de administração do WordPress (`#wpadminbar`) por
   papel de usuário, configurável em uma tela própria.

Segue o mesmo padrão dos demais plugins da Arins (Pagina_Arins,
Banco_Horas_WP, Portfolio_Captacao_WP): repositório próprio, atualizador
privado via GitHub, build de ZIP com `tools/build-plugin.ps1`, instalação
manual em produção (`arins.policiamilitar.mg.gov.br`).

## Não-objetivos

- Não altera o portal (Pagina_Arins) nem o Banco de Horas.
- Não implementa autenticação própria, 2FA, ou limite de tentativas — usa o
  login padrão do WordPress, só reestilizado.
- Não migra dados nem depende de nenhum dado existente.
- Não cria ambiente de teste local (`wp-local`) como parte deste trabalho —
  o build vai direto para instalação manual em produção, por decisão do
  usuário.

## Identidade do plugin

- Repositório: `Login_Arins_WP` (`Documents\GitHub\Login_Arins_WP`).
- Pasta de instalação / slug: `arins-login`.
- Arquivo principal: `arins-login.php`.
- Constantes: `ARINS_LOGIN_VERSION`, `ARINS_LOGIN_FILE`, `ARINS_LOGIN_DIR`,
  `ARINS_LOGIN_URL`.
- Cabeçalho do plugin segue o modelo do Banco de Horas (Plugin Name,
  Description, Version, Author "Arins/PMMG", Requires at least 6.6,
  Requires PHP 7.4, Text Domain `arins-login`).
- Estrutura de pastas:
  ```
  arins-login.php
  includes/
    class-arins-login-tela.php        (estilo + textos + links do login)
    class-arins-login-barra-admin.php (ocultar #wpadminbar por papel)
    class-arins-login-atualizador.php (atualizador GitHub privado)
  assets/
    css/login.css
    fonts/rawline-400.ttf, rawline-600.ttf, rawline-700.ttf, rawline-900.ttf
    images/escudo-pmmg-mono-gold.png
  tools/build-plugin.ps1
  dist/            (gerado pelo build; não versionado)
  docs/superpowers/specs/
  ```
  Fontes e escudo são cópias locais dos arquivos já usados pelo
  Pagina_Arins (`assets/fonts`, `assets/images/escudo-pmmg-mono-gold.png`),
  para o plugin funcionar de forma independente mesmo que o Pagina_Arins
  não esteja ativo no momento em que `wp-login.php` carrega.

## Tela de login — visual

Paleta e fonte reaproveitadas do `portal.css` do Pagina_Arins:

| Token | Valor |
|---|---|
| `--arins-graphite` | `#14120e` |
| `--arins-gold` | `#c9a227` |
| `--arins-gold-dark` | `#80681e` |
| `--arins-paper` | `#f6f5f1` |
| `--arins-ink` | `#211f1a` |
| `--arins-muted` | `#665f52` |
| `--arins-line` | `#d6d0c3` |

Fonte: Rawline (400/600/700/900), `@font-face` local (mesmos arquivos do
Pagina_Arins).

Layout (`login.css`, enqueued só em `wp-login.php` via hook `login_enqueue_scripts`):

- **Duas colunas** em telas ≥ 900px:
  - Esquerda: fundo `--arins-graphite`, escudo PMMG dourado centralizado,
    frase curta no estilo do hero do portal ("Relações institucionais que
    aproximam a PMMG da sociedade."), linha dourada horizontal abaixo do
    texto com um brilho sutil (`shimmer`) feito só em CSS
    (`background: linear-gradient` animado com `background-position`,
    `prefers-reduced-motion` respeitado — desliga a animação se o usuário
    pedir menos movimento).
  - Direita: cartão sobre fundo `--arins-paper`, formulário padrão do WP
    reestilizado (campos com borda `--arins-line`, foco em
    `--arins-gold`, botão "Entrar" em `--arins-graphite` com hover
    `--arins-gold`), tipografia Rawline.
- **Coluna única** abaixo de 900px: painel esquerdo colapsa para um
  cabeçalho compacto (escudo pequeno + nome "Arins") acima do cartão de
  login.
- Sem imagens de fundo externas, sem JS além do que o próprio WP injeta;
  toda a "inovação" visual é CSS (gradientes, sombra, transição de
  `opacity`/`transform` na entrada do cartão).

## Mensagens e links customizados

Em `class-arins-login-tela.php`:

- `login_headerurl` → aponta para `home_url()` (a home do site, onde roda o
  Pagina_Arins) em vez de wordpress.org.
- `login_headertext` → nome do site.
- `login_errors` (filtro) → substitui a mensagem padrão de credenciais
  inválidas por um texto institucional único ("Usuário institucional ou
  senha inválidos."), sem diferenciar "usuário não existe" de "senha
  errada" (mantém a prática de segurança já usada pelo WordPress recente).
- Rodapé abaixo do cartão de login: link "Voltar para a Página Arins".

## Barra de administração por papel

Tela própria em **Configurações → Login Arins**
(`add_options_page`, capability `manage_options`), com:

- Lista de todos os papéis do WP (`wp_roles()->roles`), incluindo os
  customizados já existentes no site (ex.: `arins_bh_admin` e os papéis
  usados pelo Banco de Horas), cada um com uma checkbox "ocultar barra de
  administração para este papel".
- Opção salva como array de slugs de papel:
  `arins_login_papeis_sem_barra` (`register_setting`).
- Implementação: filtro `show_admin_bar` — para o usuário atual, se
  qualquer um dos seus papéis estiver na lista salva, retorna `false`.
  Usuários com múltiplos papéis: basta um papel marcado para ocultar.
  Nenhuma marcação por padrão (todos os papéis mantêm a barra, igual ao
  comportamento padrão do WP) até o administrador configurar.

## Atualizador

Mesmo modelo do `Arins_BH_Atualizador` (Banco_Horas_WP), adaptado:

- Repositório: `gontijopm/Login_Arins_WP`.
- Slug: `arins-login`.
- Token: constante `ARINS_LOGIN_GITHUB_TOKEN` no `wp-config.php` →
  campo próprio em **Configurações → Login Arins** →
  reaproveita o token do Pagina_Arins (`arins_portal_options.github_token`)
  se nenhum dos dois primeiros estiver definido.
- Cache da release por 6 horas em site transient.
- Pacote esperado: `Login_Arins_WP-*.zip` (nome de asset da release do
  GitHub), mesma convenção do Banco de Horas.

## Build

`tools/build-plugin.ps1`: mesmo padrão do Banco_Horas_WP — grava o ZIP
manualmente com `System.IO.Compression.ZipFile` (nunca
`Compress-Archive`, para não gerar caminhos com barra invertida
incompatíveis com o Linux de produção), pasta raiz `arins-login/`,
conteúdo: `arins-login.php`, `includes/`, `assets/`.

## Testes

- Checagem de sintaxe PHP com `php74` (ferramenta portátil já usada no
  Banco de Horas) em todos os arquivos `.php`.
- Sem suíte automatizada de integração nesta primeira versão (não há
  regra de negócio complexa o bastante para justificar; o filtro
  `show_admin_bar` e os textos de login são verificados manualmente após
  a instalação em produção).
- Validação manual após a instalação em produção: tela de login em
  desktop e mobile, e ao menos um papel configurado para confirmar que a
  barra de administração some.

## Plano de release

1. Versão inicial `0.1.0`.
2. Build do ZIP, instalação manual em produção (Plugins → Adicionar
   plugin → Enviar plugin), como os demais plugins da Arins.
3. Após o repositório estar publicado no GitHub, configurar o token fino
   para habilitar atualizações automáticas — mesmo lembrete já pendente
   para o Banco de Horas.
