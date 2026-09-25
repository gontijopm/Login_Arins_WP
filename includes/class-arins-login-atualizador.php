<?php
/**
 * Atualização do plugin pelo repositório privado do GitHub, no mesmo modelo
 * do atualizador do Banco de Horas e da Pagina_Arins.
 *
 * Diferente do Banco de Horas, este repositório ainda não tem um workflow
 * de release automático (.github/workflows/release.yml) nem foi publicado
 * no GitHub. Até que isso exista, publicar uma versão é manual: gerar o ZIP
 * com tools/build-plugin.ps1, renomear para "Login_Arins_WP-X.Y.Z.zip" (é
 * esse nome, e só esse, que ultima_release() reconhece como pacote válido)
 * e anexar a um release do GitHub com a tag "vX.Y.Z". Sem isso, o
 * WordPress nunca mostra "Atualizar agora", mesmo com o token configurado.
 *
 * Quando o workflow existir, o WordPress passa a consultar a última release,
 * comparar com ARINS_LOGIN_VERSION e mostrar "Atualizar agora" em Plugins.
 * O download usa o mesmo token.
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
