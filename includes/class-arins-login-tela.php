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
