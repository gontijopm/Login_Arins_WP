# Login Arins Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a standalone WordPress plugin (`Login_Arins_WP`) that reskins `wp-login.php` with the Arins/PMMG visual identity, customizes its texts and links, and lets an admin hide the WordPress admin bar per user role.

**Architecture:** A single plugin bootstrap file wires three independent classes — `Arins_Login_Tela` (login page visuals/copy), `Arins_Login_Barra_Admin` (admin-bar-per-role), `Arins_Login_Atualizador` (private GitHub updater, copy-adapted from `Banco_Horas_WP`'s `Arins_BH_Atualizador`). Pure decision logic (which roles hide the bar, the error-message rewrite) lives in WordPress-independent static methods so it can be unit-tested with the same no-WordPress test runner already used by `Banco_Horas_WP` (`tests/run.php`, `test_*` functions, `assert_same`/`assert_true`/`assert_contains`). Everything else (hooks, settings screens, HTTP calls) is checked with `php74 -l` syntax checks and, after the ZIP is installed, manual verification in production — no local WordPress test environment for this plugin.

**Tech Stack:** WordPress plugin (PHP 7.4+, no Composer/PHPUnit — matches `Banco_Horas_WP`/`Pagina_Arins`), portable PHP at `C:\Users\p134283\tools\php74\php.exe` for lint/tests, PowerShell `tools/build-plugin.ps1` (hand-written ZIP via `System.IO.Compression.ZipFile`) for packaging.

**Spec:** `docs/superpowers/specs/2026-09-25-arins-login-design.md`

## Global Constraints

- Plugin folder/slug: `arins-login`; main file `arins-login.php`; text domain `arins-login`.
- Plugin header: `Requires at least: 6.6`, `Requires PHP: 7.4`, `Author: Arins/PMMG`.
- Repository: `gontijopm/Login_Arins_WP`; first release version `0.1.0`.
- Never build the release ZIP with `Compress-Archive` — only `tools/build-plugin.ps1` (writes entries with `/`, never `\`, so the Linux production server can read them).
- No local WordPress test environment for this project — build goes straight to a ZIP for manual install in production (`arins.policiamilitar.mg.gov.br`), per explicit user decision.
- No automated integration suite. Only: (a) pure-logic unit tests via `tests/run.php` (no WordPress loaded), and (b) `php74 -l` syntax checks on every `.php` file.
- Palette (exact hex, from `Pagina_Arins/assets/css/portal.css`): graphite `#14120e`, gold `#c9a227`, gold-dark `#80681e`, paper `#f6f5f1`, ink `#211f1a`, muted `#665f52`, line `#d6d0c3`. Font: Rawline (400/600/700/900), self-hosted `.ttf`.
- Login error message text is exactly: `Usuário institucional ou senha inválidos.` — must never reveal whether the username or the password was wrong.
- Any CSS animation (gold-line shimmer, card entrance) must be disabled under `@media (prefers-reduced-motion: reduce)`.
- Option names are exact: `arins_login_papeis_sem_barra` (admin-bar roles), `arins_login_github_token` (updater token).
- Settings screen: **Configurações → Login Arins** (`options-general.php?page=arins-login`), capability `manage_options`.

## Review Focus

- A user holding several roles where only one is marked "ocultar" must still lose the admin bar — pinned by `test_barra_oculta_se_qualquer_papel_multiplo_bater` (Task 2).
- Fresh install with no role configured yet must leave the admin bar visible for everyone, not hide it by default — pinned by `test_barra_nao_oculta_lista_vazia` (Task 2).
- A role slug saved earlier and later deleted (or never valid) must not match any real user and must not error when rendering the settings screen or filtering the bar — pinned by `test_barra_papel_removido_nao_afeta_outros_usuarios` (Task 2).
- The login-error rewrite must never leak which field (user vs. password) was wrong, even though the original WordPress message does — pinned by `test_tela_mensagem_erro_nao_revela_qual_campo_errou` (Task 3).
- Malformed input to the role sanitizer (non-array, or an array containing a non-string entry) must not fatal and must still only keep valid role slugs — pinned by `test_barra_sanitizar_papeis_ignora_valor_nao_array` and `test_barra_sanitizar_papeis_ignora_entradas_invalidas` (Task 2).

---

## Task 1: Plugin scaffold and shared assets

**Files:**
- Create: `arins-login.php`
- Create: `assets/fonts/rawline-400.ttf`, `assets/fonts/rawline-600.ttf`, `assets/fonts/rawline-700.ttf`, `assets/fonts/rawline-900.ttf` (copied from `Pagina_Arins/assets/fonts/`)
- Create: `assets/images/escudo-pmmg-mono-gold.png` (copied from `Pagina_Arins/assets/images/`)

**Interfaces:**
- Consumes: nothing (first task).
- Produces: constants `ARINS_LOGIN_VERSION` (`'0.1.0'`), `ARINS_LOGIN_FILE`, `ARINS_LOGIN_DIR`, `ARINS_LOGIN_URL`. `require_once` + `::init()` calls for `Arins_Login_Tela`, `Arins_Login_Barra_Admin`, `Arins_Login_Atualizador` (classes created in Tasks 2-4 — their file paths and class names below must match exactly, or the syntax check in Task 5 fails).

- [ ] **Step 1: Create the main plugin file**

```php
<?php
/**
 * Plugin Name: Login Arins
 * Description: Tela de login personalizada e controle da barra de administração da Assessoria de Relações Institucionais da PMMG.
 * Version: 0.1.0
 * Author: Arins/PMMG
 * Requires at least: 6.6
 * Requires PHP: 7.4
 * Text Domain: arins-login
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ARINS_LOGIN_VERSION', '0.1.0');
define('ARINS_LOGIN_FILE', __FILE__);
define('ARINS_LOGIN_DIR', plugin_dir_path(__FILE__));
define('ARINS_LOGIN_URL', plugin_dir_url(__FILE__));

require_once ARINS_LOGIN_DIR . 'includes/class-arins-login-tela.php';
require_once ARINS_LOGIN_DIR . 'includes/class-arins-login-barra-admin.php';
require_once ARINS_LOGIN_DIR . 'includes/class-arins-login-atualizador.php';

Arins_Login_Tela::init();
Arins_Login_Barra_Admin::init();
Arins_Login_Atualizador::init();
```

Save this as `arins-login.php` at the repository root.

- [ ] **Step 2: Lint the main file**

Run: `"C:/Users/p134283/tools/php74/php.exe" -l arins-login.php`
Expected: `No syntax errors detected in arins-login.php` (the `require_once` targets don't exist yet — `-l` only parses, it never executes the file, so this is fine).

- [ ] **Step 3: Copy the shared brand assets from Pagina_Arins**

Run:
```bash
mkdir -p assets/fonts assets/images
cp "/c/Users/p134283/Documents/GitHub/Pagina_Arins/assets/fonts/rawline-400.ttf" assets/fonts/
cp "/c/Users/p134283/Documents/GitHub/Pagina_Arins/assets/fonts/rawline-600.ttf" assets/fonts/
cp "/c/Users/p134283/Documents/GitHub/Pagina_Arins/assets/fonts/rawline-700.ttf" assets/fonts/
cp "/c/Users/p134283/Documents/GitHub/Pagina_Arins/assets/fonts/rawline-900.ttf" assets/fonts/
cp "/c/Users/p134283/Documents/GitHub/Pagina_Arins/assets/images/escudo-pmmg-mono-gold.png" assets/images/
```

- [ ] **Step 4: Verify the assets landed**

Run: `ls assets/fonts assets/images`
Expected: the four `.ttf` files and `escudo-pmmg-mono-gold.png` are listed.

- [ ] **Step 5: Commit**

```bash
git add arins-login.php assets/fonts assets/images
git commit -m "Adiciona esqueleto do plugin e ativos visuais compartilhados"
```

---

## Task 2: Barra de administração por papel

**Files:**
- Create: `includes/class-arins-login-barra-admin.php`
- Create: `tests/run.php`
- Create: `tests/test-barra-admin.php`

**Interfaces:**
- Consumes: nothing directly (registers a callback on the action `arins_login_configuracao_depois`, which Task 4 fires — order-independent, since `add_action` just registers a callback for a string hook name).
- Produces: class `Arins_Login_Barra_Admin` with `init()`, `deve_ocultar(array $papeis_usuario, array $papeis_configurados): bool`, `sanitizar_papeis($valor, array $papeis_validos): array`, `sanitizar_opcao($valor)`, `filtrar_barra($mostrar)`, `registrar_opcao()`, `secao()`. Constant `Arins_Login_Barra_Admin::OPCAO === 'arins_login_papeis_sem_barra'`. Also produces the shared test runner `tests/run.php`, reused by Task 3.

- [ ] **Step 1: Write the test runner (no WordPress loaded)**

```php
<?php
/**
 * Executor mínimo dos testes, sem Composer nem PHPUnit: carrega cada
 * tests/test-*.php e roda toda função cujo nome começa com `test_`.
 *
 * Uso: "C:/Users/p134283/tools/php74/php.exe" tests/run.php [filtro]
 *
 * O WordPress não é carregado. Só as classes puras são testadas aqui; o que
 * depende do WordPress é conferido manualmente após a instalação em
 * produção.
 */

define('ABSPATH', __DIR__ . '/');

require __DIR__ . '/../includes/class-arins-login-barra-admin.php';
require __DIR__ . '/../includes/class-arins-login-tela.php';

class Arins_Login_Falha_De_Teste extends Exception {}

function assert_same($esperado, $obtido, $mensagem = '') {
    if ($esperado !== $obtido) {
        throw new Arins_Login_Falha_De_Teste(
            ($mensagem !== '' ? $mensagem . "\n" : '')
            . 'esperado: ' . var_export($esperado, true) . "\n"
            . 'obtido:   ' . var_export($obtido, true)
        );
    }
}

function assert_true($condicao, $mensagem = 'condição falsa') {
    assert_same(true, $condicao, $mensagem);
}

function assert_contains($agulha, $palheiro) {
    if (strpos($palheiro, $agulha) === false) {
        throw new Arins_Login_Falha_De_Teste('texto não encontrado: ' . $agulha);
    }
}

$antes = get_defined_functions()['user'];
foreach (glob(__DIR__ . '/test-*.php') as $arquivo) {
    require $arquivo;
}
$testes = array_values(array_filter(
    array_diff(get_defined_functions()['user'], $antes),
    function ($nome) {
        return strpos($nome, 'test_') === 0;
    }
));

$filtro = isset($argv[1]) ? $argv[1] : '';
$falhas = 0;
$executados = 0;
foreach ($testes as $teste) {
    if ($filtro !== '' && strpos($teste, $filtro) === false) {
        continue;
    }
    $executados++;
    try {
        $teste();
        echo '.';
    } catch (Throwable $erro) {
        $falhas++;
        echo "\nFALHOU: {$teste}\n" . $erro->getMessage() . "\n";
    }
}

echo "\n{$executados} testes, {$falhas} falhas\n";
exit($falhas > 0 ? 1 : 0);
```

This `require`s `class-arins-login-tela.php` too (Task 3's file) because both classes share one runner. It doesn't exist yet — that's fine for this task; Task 3 creates it before the runner is executed again with both test files present. For now, temporarily comment out that one `require` line so this task's run works in isolation:

```php
require __DIR__ . '/../includes/class-arins-login-barra-admin.php';
// require __DIR__ . '/../includes/class-arins-login-tela.php'; // adicionado na Tarefa 3
```

Save as `tests/run.php`.

- [ ] **Step 2: Write the failing tests**

```php
<?php
/**
 * Lógica pura de quem vê a barra de administração e de que papéis são
 * aceitos ao salvar a configuração.
 */

function test_barra_deve_ocultar_quando_papel_esta_na_lista() {
    assert_true(Arins_Login_Barra_Admin::deve_ocultar(array('subscriber'), array('subscriber', 'author')));
}

function test_barra_nao_oculta_quando_papel_fora_da_lista() {
    assert_same(false, Arins_Login_Barra_Admin::deve_ocultar(array('administrator'), array('subscriber')));
}

function test_barra_oculta_se_qualquer_papel_multiplo_bater() {
    assert_true(Arins_Login_Barra_Admin::deve_ocultar(array('editor', 'arins_bh_admin'), array('arins_bh_admin')));
}

function test_barra_nao_oculta_lista_vazia() {
    assert_same(false, Arins_Login_Barra_Admin::deve_ocultar(array('administrator'), array()));
}

function test_barra_papel_removido_nao_afeta_outros_usuarios() {
    assert_same(false, Arins_Login_Barra_Admin::deve_ocultar(array('subscriber'), array('papel-que-nao-existe-mais')));
}

function test_barra_sanitizar_papeis_remove_invalidos() {
    $resultado = Arins_Login_Barra_Admin::sanitizar_papeis(array('subscriber', 'papel-inexistente'), array('subscriber', 'administrator'));
    assert_same(array('subscriber'), $resultado);
}

function test_barra_sanitizar_papeis_remove_duplicados() {
    $resultado = Arins_Login_Barra_Admin::sanitizar_papeis(array('subscriber', 'subscriber'), array('subscriber'));
    assert_same(array('subscriber'), $resultado);
}

function test_barra_sanitizar_papeis_ignora_valor_nao_array() {
    assert_same(array(), Arins_Login_Barra_Admin::sanitizar_papeis('subscriber', array('subscriber')));
}

function test_barra_sanitizar_papeis_ignora_entradas_invalidas() {
    $resultado = Arins_Login_Barra_Admin::sanitizar_papeis(array('subscriber', array('x')), array('subscriber'));
    assert_same(array('subscriber'), $resultado);
}
```

Save as `tests/test-barra-admin.php`.

- [ ] **Step 3: Run the tests to verify they fail**

Run: `"C:/Users/p134283/tools/php74/php.exe" tests/run.php barra`
Expected: a fatal error — `Arins_Login_Barra_Admin` doesn't exist yet (the required file is empty/missing its class).

- [ ] **Step 4: Implement the class**

```php
<?php
/**
 * Oculta a barra preta de administração do WordPress para os papéis de
 * usuário marcados em Configurações → Login Arins.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Arins_Login_Barra_Admin {

    const OPCAO = 'arins_login_papeis_sem_barra';

    public static function init() {
        add_action('admin_init', array(__CLASS__, 'registrar_opcao'));
        add_action('arins_login_configuracao_depois', array(__CLASS__, 'secao'));
        add_filter('show_admin_bar', array(__CLASS__, 'filtrar_barra'));
    }

    /** Algum papel do usuário está entre os configurados para ocultar a barra? */
    public static function deve_ocultar($papeis_usuario, $papeis_configurados) {
        return count(array_intersect($papeis_usuario, $papeis_configurados)) > 0;
    }

    /** Mantém só papéis presentes em $papeis_validos, sem duplicados. Pura: sem chamadas ao WordPress. */
    public static function sanitizar_papeis($valor, $papeis_validos) {
        if (!is_array($valor)) {
            return array();
        }
        return array_values(array_intersect(array_unique($valor), $papeis_validos));
    }

    public static function sanitizar_opcao($valor) {
        return self::sanitizar_papeis($valor, array_keys(wp_roles()->roles));
    }

    public static function filtrar_barra($mostrar) {
        if (!is_user_logged_in()) {
            return $mostrar;
        }
        $papeis_configurados = get_option(self::OPCAO, array());
        $usuario = wp_get_current_user();
        if (self::deve_ocultar($usuario->roles, $papeis_configurados)) {
            return false;
        }
        return $mostrar;
    }

    public static function registrar_opcao() {
        register_setting('arins_login_barra_admin', self::OPCAO, array(
            'type' => 'array',
            'sanitize_callback' => array(__CLASS__, 'sanitizar_opcao'),
            'default' => array(),
        ));
    }

    /** Seção própria (form completo) na tela Configurações → Login Arins. */
    public static function secao() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $selecionados = get_option(self::OPCAO, array());
        $papeis = wp_roles()->roles;
        ?>
        <h2>Barra de administração</h2>
        <p>Papéis marcados não veem a barra preta de administração do WordPress no site.</p>
        <form method="post" action="options.php">
            <?php settings_fields('arins_login_barra_admin'); ?>
            <table class="form-table" role="presentation">
                <?php foreach ($papeis as $slug => $papel) : ?>
                    <tr>
                        <th scope="row"><?php echo esc_html($papel['name']); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(self::OPCAO); ?>[]" value="<?php echo esc_attr($slug); ?>" <?php checked(in_array($slug, $selecionados, true)); ?>>
                                Ocultar barra de administração
                            </label>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <?php submit_button('Salvar barra de administração'); ?>
        </form>
        <?php
    }
}
```

Save as `includes/class-arins-login-barra-admin.php`.

- [ ] **Step 5: Run the tests to verify they pass**

Run: `"C:/Users/p134283/tools/php74/php.exe" tests/run.php barra`
Expected: `9 testes, 0 falhas` (9 `test_barra_*` functions are defined above; if the total differs, recheck for a typo in a test function name before continuing).

- [ ] **Step 6: Lint the new file**

Run: `"C:/Users/p134283/tools/php74/php.exe" -l includes/class-arins-login-barra-admin.php`
Expected: `No syntax errors detected`

- [ ] **Step 7: Commit**

```bash
git add includes/class-arins-login-barra-admin.php tests/run.php tests/test-barra-admin.php
git commit -m "Adiciona controle da barra de administração por papel, com testes puros"
```

---

## Task 3: Tela de login — visual e mensagens

**Files:**
- Create: `includes/class-arins-login-tela.php`
- Create: `assets/css/login.css`
- Modify: `tests/run.php` (uncomment the `class-arins-login-tela.php` require added as a comment in Task 2)
- Create: `tests/test-tela.php`

**Interfaces:**
- Consumes: `ARINS_LOGIN_URL`, `ARINS_LOGIN_VERSION` (Task 1).
- Produces: class `Arins_Login_Tela` with `init()`, `estilos()`, `url_cabecalho()`, `texto_cabecalho()`, `mensagem_erro($mensagem_original): string`, `texto_erro(): string`, `abrir_layout()`, `fechar_layout()`.

- [ ] **Step 1: Uncomment the require in the test runner**

In `tests/run.php`, change:
```php
require __DIR__ . '/../includes/class-arins-login-barra-admin.php';
// require __DIR__ . '/../includes/class-arins-login-tela.php'; // adicionado na Tarefa 3
```
to:
```php
require __DIR__ . '/../includes/class-arins-login-barra-admin.php';
require __DIR__ . '/../includes/class-arins-login-tela.php';
```

- [ ] **Step 2: Write the failing tests**

```php
<?php
/**
 * Reescrita pura da mensagem de erro do login — sem chamadas ao WordPress,
 * para não revelar se foi o usuário ou a senha que errou.
 */

function test_tela_mensagem_erro_mantem_vazio_sem_erro() {
    assert_same('', Arins_Login_Tela::mensagem_erro(''));
}

function test_tela_mensagem_erro_troca_por_texto_institucional() {
    $resultado = Arins_Login_Tela::mensagem_erro('<strong>ERRO</strong>: usuário desconhecido.');
    assert_contains('Usuário institucional ou senha inválidos.', $resultado);
}

function test_tela_mensagem_erro_nao_revela_qual_campo_errou() {
    $resultado = Arins_Login_Tela::mensagem_erro('<strong>ERRO</strong>: a senha informada está incorreta.');
    assert_true(strpos($resultado, 'senha informada está incorreta') === false);
}
```

Save as `tests/test-tela.php`.

- [ ] **Step 3: Run the tests to verify they fail**

Run: `"C:/Users/p134283/tools/php74/php.exe" tests/run.php tela`
Expected: a fatal error — `Arins_Login_Tela` doesn't exist yet.

- [ ] **Step 4: Implement the class**

```php
<?php
/**
 * Reestiliza wp-login.php com a identidade visual da Pagina_Arins: layout
 * em duas colunas, logo, textos e link do rodapé.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Arins_Login_Tela {

    public static function init() {
        add_action('login_enqueue_scripts', array(__CLASS__, 'estilos'));
        add_filter('login_headerurl', array(__CLASS__, 'url_cabecalho'));
        add_filter('login_headertext', array(__CLASS__, 'texto_cabecalho'));
        add_filter('login_errors', array(__CLASS__, 'mensagem_erro'));
        add_action('login_header', array(__CLASS__, 'abrir_layout'));
        add_action('login_footer', array(__CLASS__, 'fechar_layout'));
    }

    public static function estilos() {
        wp_enqueue_style('arins-login', ARINS_LOGIN_URL . 'assets/css/login.css', array(), ARINS_LOGIN_VERSION);
    }

    public static function url_cabecalho() {
        return home_url('/');
    }

    public static function texto_cabecalho() {
        return get_bloginfo('name');
    }

    /** Texto fixo institucional, sem indicar se foi o usuário ou a senha. */
    public static function texto_erro() {
        return 'Usuário institucional ou senha inválidos.';
    }

    /** Filtro login_errors. Pura: não chama funções do WordPress. */
    public static function mensagem_erro($mensagem_original) {
        if ($mensagem_original === '') {
            return $mensagem_original;
        }
        return '<p class="message">' . self::texto_erro() . '</p>';
    }

    public static function abrir_layout() {
        ?>
        <div class="arins-login-wrap">
            <div class="arins-login-aside">
                <img src="<?php echo esc_url(ARINS_LOGIN_URL . 'assets/images/escudo-pmmg-mono-gold.png'); ?>" alt="Escudo da PMMG" width="96" height="96">
                <p>Relações institucionais que aproximam a PMMG da sociedade.</p>
                <div class="arins-login-linha"></div>
            </div>
            <div class="arins-login-main">
        <?php
    }

    public static function fechar_layout() {
        ?>
                <p class="arins-login-rodape"><a href="<?php echo esc_url(home_url('/')); ?>">Voltar para a Página Arins</a></p>
            </div>
        </div>
        <?php
    }
}
```

Save as `includes/class-arins-login-tela.php`.

- [ ] **Step 5: Run the tests to verify they pass**

Run: `"C:/Users/p134283/tools/php74/php.exe" tests/run.php` (no filter this time, to also re-confirm Task 2's tests still pass)
Expected: `12 testes, 0 falhas` (9 from Task 2 + 3 from Task 3 — recount if the total differs and find the missing/extra test name in the output before continuing).

- [ ] **Step 6: Write the CSS**

```css
:root {
    --arins-graphite: #14120e;
    --arins-gold: #c9a227;
    --arins-gold-dark: #80681e;
    --arins-paper: #f6f5f1;
    --arins-ink: #211f1a;
    --arins-muted: #665f52;
    --arins-line: #d6d0c3;
}

@font-face { font-family: Rawline; src: url("../fonts/rawline-400.ttf") format("truetype"); font-weight: 400; font-display: swap; }
@font-face { font-family: Rawline; src: url("../fonts/rawline-600.ttf") format("truetype"); font-weight: 600; font-display: swap; }
@font-face { font-family: Rawline; src: url("../fonts/rawline-700.ttf") format("truetype"); font-weight: 700; font-display: swap; }
@font-face { font-family: Rawline; src: url("../fonts/rawline-900.ttf") format("truetype"); font-weight: 900; font-display: swap; }

body.login {
    background: var(--arins-paper);
    font-family: Rawline, sans-serif;
}

body.login #login {
    padding: 0;
    width: 100%;
    max-width: 360px;
}

.arins-login-wrap {
    display: flex;
    min-height: 100vh;
    align-items: stretch;
}

.arins-login-aside {
    display: none;
    flex: 1 1 55%;
    background: var(--arins-graphite);
    color: var(--arins-paper);
    flex-direction: column;
    justify-content: center;
    align-items: flex-start;
    padding: 4rem;
    box-sizing: border-box;
}

.arins-login-aside img {
    width: 96px;
    height: 96px;
    margin-bottom: 2rem;
}

.arins-login-aside p {
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 1.3;
    max-width: 26rem;
    margin: 0 0 2rem;
}

.arins-login-linha {
    width: 6rem;
    height: 3px;
    background: linear-gradient(90deg, var(--arins-gold-dark), var(--arins-gold), var(--arins-gold-dark));
    background-size: 200% 100%;
    animation: arins-login-brilho 3s ease-in-out infinite;
}

@keyframes arins-login-brilho {
    0% { background-position: 0% 50%; }
    100% { background-position: 200% 50%; }
}

.arins-login-main {
    flex: 1 1 45%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    box-sizing: border-box;
}

@media (min-width: 900px) {
    .arins-login-aside { display: flex; }
}

body.login h1 a {
    background-image: url("../images/escudo-pmmg-mono-gold.png");
    background-size: 80px;
    width: 80px;
    height: 80px;
    margin: 0 auto 1.5rem;
}

body.login form {
    background: #fff;
    border: 1px solid var(--arins-line);
    border-radius: 8px;
    box-shadow: 0 10px 30px rgba(20, 18, 14, 0.08);
    padding: 2rem;
    opacity: 0;
    transform: translateY(8px);
    animation: arins-login-entrada 0.4s ease-out forwards;
}

@keyframes arins-login-entrada {
    to { opacity: 1; transform: translateY(0); }
}

@media (prefers-reduced-motion: reduce) {
    .arins-login-linha { animation: none; }
    body.login form { animation: none; opacity: 1; transform: none; }
}

body.login label {
    color: var(--arins-ink);
    font-weight: 600;
}

body.login input[type="text"],
body.login input[type="password"] {
    border: 1px solid var(--arins-line);
    border-radius: 4px;
    font-family: Rawline, sans-serif;
}

body.login input[type="text"]:focus,
body.login input[type="password"]:focus {
    border-color: var(--arins-gold);
    box-shadow: 0 0 0 1px var(--arins-gold);
}

body.login .button-primary {
    background: var(--arins-graphite);
    border-color: var(--arins-graphite);
    text-shadow: none;
    box-shadow: none;
    border-radius: 4px;
    font-family: Rawline, sans-serif;
    font-weight: 600;
}

body.login .button-primary:hover,
body.login .button-primary:focus {
    background: var(--arins-gold);
    border-color: var(--arins-gold-dark);
    color: var(--arins-graphite);
}

.arins-login-rodape {
    margin-top: 1.5rem;
    text-align: center;
}

.arins-login-rodape a {
    color: var(--arins-muted);
    font-size: 0.85rem;
}
```

Save as `assets/css/login.css`.

- [ ] **Step 7: Lint the PHP file**

Run: `"C:/Users/p134283/tools/php74/php.exe" -l includes/class-arins-login-tela.php`
Expected: `No syntax errors detected`

- [ ] **Step 8: Commit**

```bash
git add includes/class-arins-login-tela.php assets/css/login.css tests/run.php tests/test-tela.php
git commit -m "Adiciona a tela de login com a identidade visual da Arins"
```

---

## Task 4: Atualizador GitHub privado

**Files:**
- Create: `includes/class-arins-login-atualizador.php`

**Interfaces:**
- Consumes: `ARINS_LOGIN_VERSION`, `ARINS_LOGIN_FILE` (Task 1).
- Produces: class `Arins_Login_Atualizador` with `init()`. Fires the action `arins_login_configuracao_depois` inside `pagina_de_configuracao()`, which Task 2's `Arins_Login_Barra_Admin::secao()` is already listening for. Registers the settings page **Configurações → Login Arins** at `options-general.php?page=arins-login`.

This class mirrors `Banco_Horas_WP/includes/class-arins-bh-atualizador.php` with names swapped for this plugin; it has no pure logic to unit-test (same as the original — `Banco_Horas_WP` has no `test-atualizador.php` either), so this task is verified with a syntax check only.

- [ ] **Step 1: Implement the class**

```php
<?php
/**
 * Atualização do plugin pelo repositório privado do GitHub, no mesmo modelo
 * do atualizador do Banco de Horas e da Pagina_Arins.
 *
 * Uma tag vX.Y.Z publica a release com o ZIP (.github/workflows/release.yml).
 * O WordPress consulta a última release, compara com ARINS_LOGIN_VERSION e
 * mostra "Atualizar agora" em Plugins. O download usa o mesmo token.
 *
 * O token (fino, só leitura em Contents e Metadata) vem, nesta ordem:
 * - da constante ARINS_LOGIN_GITHUB_TOKEN no wp-config.php;
 * - do campo em Configurações → Login Arins;
 * - do token da Pagina_Arins, para que um token liberado para os dois
 *   repositórios sirva aos dois plugins.
 * Sem token, nada é consultado.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Arins_Login_Atualizador {

    const REPOSITORIO = 'gontijopm/Login_Arins_WP';
    const URL_RELEASE = 'https://api.github.com/repos/gontijopm/Login_Arins_WP/releases/latest';
    const PREFIXO_PACOTE = 'https://api.github.com/repos/gontijopm/Login_Arins_WP/releases/assets/';
    const SLUG = 'arins-login';
    const OPCAO_TOKEN = 'arins_login_github_token';
    const CACHE = 'arins_login_github_release';

    public static function init() {
        add_filter('pre_set_site_transient_update_plugins', array(__CLASS__, 'verificar'));
        add_filter('upgrader_pre_download', array(__CLASS__, 'baixar'), 10, 2);
        add_filter('plugins_api', array(__CLASS__, 'informacoes'), 20, 3);
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_init', array(__CLASS__, 'registrar_opcao'));
        add_filter('plugin_action_links_' . self::arquivo_do_plugin(), array(__CLASS__, 'link_configurar'));
    }

    public static function arquivo_do_plugin() {
        return plugin_basename(ARINS_LOGIN_FILE);
    }

    public static function token() {
        if (defined('ARINS_LOGIN_GITHUB_TOKEN') && ARINS_LOGIN_GITHUB_TOKEN) {
            return (string) ARINS_LOGIN_GITHUB_TOKEN;
        }
        $proprio = (string) get_option(self::OPCAO_TOKEN, '');
        if ($proprio !== '') {
            return $proprio;
        }
        $portal = get_option('arins_portal_options', array());
        return is_array($portal) && !empty($portal['github_token']) ? (string) $portal['github_token'] : '';
    }

    private static function cabecalhos($download = false) {
        $cabecalhos = array(
            'Accept' => $download ? 'application/octet-stream' : 'application/vnd.github+json',
            'User-Agent' => 'Login-Arins-WordPress-Updater',
            'X-GitHub-Api-Version' => '2022-11-28',
        );
        if (self::token()) {
            $cabecalhos['Authorization'] = 'Bearer ' . self::token();
        }
        return $cabecalhos;
    }

    /** A última release (versão, pacote, página), em cache por 6 horas. */
    public static function ultima_release() {
        $cache = get_site_transient(self::CACHE);
        if (is_array($cache)) {
            return $cache;
        }
        if (!self::token()) {
            return array();
        }
        $resposta = wp_safe_remote_get(self::URL_RELEASE, array('headers' => self::cabecalhos(), 'timeout' => 15));
        if (is_wp_error($resposta) || wp_remote_retrieve_response_code($resposta) !== 200) {
            return array();
        }
        $release = json_decode(wp_remote_retrieve_body($resposta), true);
        if (!is_array($release) || empty($release['tag_name']) || empty($release['assets'])) {
            return array();
        }
        $pacote = '';
        foreach ($release['assets'] as $asset) {
            if (!empty($asset['name']) && preg_match('/^Login_Arins_WP-.*\.zip$/', $asset['name']) && !empty($asset['url'])) {
                $pacote = esc_url_raw($asset['url']);
                break;
            }
        }
        if (!$pacote) {
            return array();
        }
        $dados = array(
            'version' => ltrim((string) $release['tag_name'], 'vV'),
            'package' => $pacote,
            'url' => !empty($release['html_url']) ? esc_url_raw($release['html_url']) : 'https://github.com/' . self::REPOSITORIO,
        );
        set_site_transient(self::CACHE, $dados, 6 * HOUR_IN_SECONDS);
        return $dados;
    }

    /** Filtro pre_set_site_transient_update_plugins. */
    public static function verificar($transient) {
        if (!is_object($transient) || empty($transient->checked)) {
            return $transient;
        }
        $arquivo = self::arquivo_do_plugin();
        $release = self::ultima_release();
        if ($release && version_compare(ARINS_LOGIN_VERSION, $release['version'], '<')) {
            $transient->response[$arquivo] = (object) array(
                'slug' => self::SLUG,
                'plugin' => $arquivo,
                'new_version' => $release['version'],
                'url' => $release['url'],
                'package' => $release['package'],
                'requires' => '6.6',
                'requires_php' => '7.4',
            );
        } else {
            unset($transient->response[$arquivo]);
        }
        return $transient;
    }

    /** Filtro upgrader_pre_download: baixa o ZIP privado com o token. */
    public static function baixar($resposta, $pacote) {
        if (strpos((string) $pacote, self::PREFIXO_PACOTE) !== 0) {
            return $resposta;
        }
        $temporario = wp_tempnam('arins-login.zip');
        if (!$temporario) {
            return new WP_Error('arins_login_temp', 'Não foi possível criar o arquivo temporário da atualização.');
        }
        $download = wp_safe_remote_get($pacote, array(
            'headers' => self::cabecalhos(true),
            'timeout' => 300,
            'stream' => true,
            'filename' => $temporario,
        ));
        if (is_wp_error($download) || wp_remote_retrieve_response_code($download) !== 200) {
            @unlink($temporario);
            return new WP_Error('arins_login_download', 'Não foi possível baixar a atualização do Login Arins no GitHub.');
        }
        return $temporario;
    }

    /** Filtro plugins_api: a janela "Ver detalhes" da atualização. */
    public static function informacoes($resultado, $acao, $args) {
        if ($acao !== 'plugin_information' || empty($args->slug) || $args->slug !== self::SLUG) {
            return $resultado;
        }
        $release = self::ultima_release();
        if (!$release) {
            return $resultado;
        }
        return (object) array(
            'name' => 'Login Arins',
            'slug' => self::SLUG,
            'version' => $release['version'],
            'author' => 'Arins/PMMG',
            'homepage' => 'https://github.com/' . self::REPOSITORIO,
            'download_link' => $release['package'],
            'requires' => '6.6',
            'requires_php' => '7.4',
            'sections' => array('description' => 'Tela de login personalizada e controle da barra de administração da Assessoria de Relações Institucionais da PMMG.'),
        );
    }

    // --- configuração -----------------------------------------------------

    /** Campo vazio mantém o token salvo; trocar o token limpa o cache. */
    public static function sanitizar_token($valor) {
        $valor = trim(sanitize_text_field((string) $valor));
        if ($valor === '') {
            return (string) get_option(self::OPCAO_TOKEN, '');
        }
        delete_site_transient(self::CACHE);
        delete_site_transient('update_plugins');
        return $valor;
    }

    public static function registrar_opcao() {
        register_setting('arins_login_configuracao', self::OPCAO_TOKEN, array(
            'type' => 'string',
            'sanitize_callback' => array(__CLASS__, 'sanitizar_token'),
            'default' => '',
        ));
    }

    public static function menu() {
        add_options_page('Login Arins', 'Login Arins', 'manage_options', 'arins-login', array(__CLASS__, 'pagina_de_configuracao'));
    }

    public static function link_configurar($links) {
        array_unshift($links, '<a href="' . esc_url(admin_url('options-general.php?page=arins-login')) . '">Configurar</a>');
        return $links;
    }

    public static function pagina_de_configuracao() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $tem_proprio = get_option(self::OPCAO_TOKEN, '') !== '';
        $origem = $tem_proprio ? 'Token próprio configurado.' : (self::token() ? 'Usando o token da Pagina_Arins (Site Arins).' : 'Nenhum token configurado: as atualizações não são consultadas.');
        $release = self::ultima_release();
        ?>
        <div class="wrap">
            <h1>Login Arins</h1>
            <p>Versão instalada: <strong><?php echo esc_html(ARINS_LOGIN_VERSION); ?></strong>.
                <?php if ($release) : ?>Última versão no GitHub: <strong><?php echo esc_html($release['version']); ?></strong>.<?php endif; ?></p>
            <form method="post" action="options.php">
                <?php settings_fields('arins_login_configuracao'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="arins-login-token">Atualizações privadas</label></th>
                        <td>
                            <input class="regular-text" type="password" id="arins-login-token" name="<?php echo esc_attr(self::OPCAO_TOKEN); ?>" value="" autocomplete="new-password" placeholder="<?php echo $tem_proprio ? 'Token configurado — deixe vazio para manter' : 'Token fino do GitHub'; ?>">
                            <p class="description"><?php echo esc_html($origem); ?></p>
                            <p class="description">Use um token fino com acesso ao repositório Login_Arins_WP, somente com Contents: Read-only e Metadata: Read-only. Se o token da Pagina_Arins também tiver acesso a este repositório, deixe este campo vazio.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <?php do_action('arins_login_configuracao_depois'); ?>
        </div>
        <?php
    }
}
```

Save as `includes/class-arins-login-atualizador.php`.

- [ ] **Step 2: Lint the file**

Run: `"C:/Users/p134283/tools/php74/php.exe" -l includes/class-arins-login-atualizador.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add includes/class-arins-login-atualizador.php
git commit -m "Adiciona o atualizador privado via GitHub, no modelo do Banco de Horas"
```

---

## Task 5: Build do pacote e verificação final

**Files:**
- Create: `tools/build-plugin.ps1`
- Verify (no changes): `arins-login.php`, all of `includes/`

**Interfaces:**
- Consumes: every file from Tasks 1-4.
- Produces: `dist/arins-login.zip` (not committed — same as `Banco_Horas_WP/dist/`, build output).

- [ ] **Step 1: Full syntax sweep**

Run (from the repository root):
```bash
for f in arins-login.php includes/*.php; do
  "C:/Users/p134283/tools/php74/php.exe" -l "$f" || echo "FALHOU: $f"
done
```
Expected: every line reads `No syntax errors detected in <file>`, no `FALHOU:` lines. Now that all three `includes/*.php` files exist, this also confirms the `require_once` targets in `arins-login.php` (Task 1) point at real files with the exact class names it calls `::init()` on.

- [ ] **Step 2: Run the full unit test suite**

Run: `"C:/Users/p134283/tools/php74/php.exe" tests/run.php`
Expected: `12 testes, 0 falhas`.

- [ ] **Step 3: Write the build script**

```powershell
$ErrorActionPreference = 'Stop'

# Monta dist/arins-login.zip com a pasta arins-login/ na raiz.
#
# Não usa Compress-Archive: no Windows PowerShell 5.1 ele grava os caminhos
# com barra invertida, e o WordPress num servidor Linux não reconhece as
# pastas ("O arquivo do plugin não existe"). Aqui cada entrada é gravada à
# mão com barra normal, qualquer que seja a versão do PowerShell.

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$repositoryRoot = Split-Path -Parent $PSScriptRoot
$distributionDirectory = Join-Path $repositoryRoot 'dist'
$archivePath = Join-Path $distributionDirectory 'arins-login.zip'
$pluginFolder = 'arins-login'
$contents = @('arins-login.php', 'includes', 'assets')

New-Item -ItemType Directory -Path $distributionDirectory -Force | Out-Null
if (Test-Path -LiteralPath $archivePath) {
    Remove-Item -LiteralPath $archivePath -Force
}

$archive = [System.IO.Compression.ZipFile]::Open($archivePath, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    foreach ($item in $contents) {
        $source = Join-Path $repositoryRoot $item
        $files = if (Test-Path -LiteralPath $source -PathType Container) {
            Get-ChildItem -LiteralPath $source -Recurse -File
        } else {
            Get-Item -LiteralPath $source
        }
        foreach ($file in $files) {
            $relative = $file.FullName.Substring($repositoryRoot.Length).TrimStart('\', '/') -replace '\\', '/'
            [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
                $archive, $file.FullName, "$pluginFolder/$relative",
                [System.IO.Compression.CompressionLevel]::Optimal
            ) | Out-Null
        }
    }
} finally {
    $archive.Dispose()
}

Write-Output $archivePath
```

Save as `tools/build-plugin.ps1`.

- [ ] **Step 4: Run the build**

Run (PowerShell tool): `./tools/build-plugin.ps1`
Expected: prints the path to `dist/arins-login.zip`, no errors.

- [ ] **Step 5: Verify the ZIP has forward-slash paths and the right contents**

Run (PowerShell):
```powershell
Add-Type -AssemblyName System.IO.Compression.FileSystem
$zip = [System.IO.Compression.ZipFile]::OpenRead((Resolve-Path 'dist/arins-login.zip'))
$entries = $zip.Entries.FullName
$zip.Dispose()
$entries | Where-Object { $_ -match '\\' }
$entries -contains 'arins-login/arins-login.php'
$entries -contains 'arins-login/includes/class-arins-login-tela.php'
$entries -contains 'arins-login/includes/class-arins-login-barra-admin.php'
$entries -contains 'arins-login/includes/class-arins-login-atualizador.php'
$entries -contains 'arins-login/assets/css/login.css'
```
Expected: the `Where-Object` line prints nothing (no backslashes in any entry), and every `-contains` check prints `True`.

- [ ] **Step 6: Commit**

```bash
git add tools/build-plugin.ps1
git commit -m "Adiciona o build do pacote de distribuição"
```

- [ ] **Step 7: Manual verification after production install (not automatable here)**

After uploading `dist/arins-login.zip` in **Plugins → Adicionar plugin → Enviar plugin** on `arins.policiamilitar.mg.gov.br` and activating it:
- Open `/wp-login.php` on desktop: confirm the two-column layout, PMMG shield, gold shimmer line, and that a failed login shows exactly "Usuário institucional ou senha inválidos." without naming the field.
- Open `/wp-login.php` on a narrow (mobile-width) browser window: confirm it collapses to a single column.
- Confirm the footer link ("Voltar para a Página Arins") sits below the login card, not beside it.
- Try the lost-password screen with an unknown username: confirm it does NOT show the institutional credentials message (that flow has its own, unrelated errors, which this plugin leaves untouched).
- Follow an expired password-reset link: confirm the "link expired" message shows as-is, not the institutional credentials message.
- If the site has more than one language installed, confirm the language switcher (shown below the login form) doesn't get the white-card styling meant for the login form itself.
- In **Configurações → Login Arins**, mark one role to hide the admin bar, save, and log in as a user with only that role: confirm the black admin bar is gone on the front end. Log in as an administrator: confirm the bar still shows.
- Confirm no PHP notices/warnings appear (check the site's PHP error log if accessible) for a logged-out visitor browsing the front end.

**Note on the GitHub updater:** until `Login_Arins_WP` is published on GitHub with a release workflow (see the comment atop `includes/class-arins-login-atualizador.php`, corrected after the final review), configuring a token in **Configurações → Login Arins** will not surface "Atualizar agora" — there is nothing for it to find yet. This is the same deferred step already pending for `Banco_Horas_WP`.
