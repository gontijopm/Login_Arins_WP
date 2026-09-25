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
        add_filter('wp_login_errors', array(__CLASS__, 'mensagens_erro'), 10, 2);
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

    /** Códigos de erro do WordPress tratados como "credenciais inválidas". */
    public static function codigos_credenciais() {
        return array('invalid_username', 'invalid_email', 'incorrect_password');
    }

    /**
     * Dos códigos de erro presentes, quais devem virar a mensagem
     * institucional única. Pura: sem chamadas ao WordPress, por isso é
     * testada sem carregar o WordPress (tests/test-tela.php).
     */
    public static function filtrar_codigos_erro($codigos) {
        return array_values(array_intersect($codigos, self::codigos_credenciais()));
    }

    /**
     * Filtro wp_login_errors: troca só os erros de credencial pela mensagem
     * institucional única. Outros erros (cookie bloqueado, link de
     * redefinição de senha expirado, etc.) passam intactos — só o
     * filtrar_codigos_erro() acima decide isso, e é pura.
     */
    public static function mensagens_erro($errors, $redirect_to = '') {
        if (!is_wp_error($errors)) {
            return $errors;
        }
        $codigos = self::filtrar_codigos_erro($errors->get_error_codes());
        foreach ($codigos as $codigo) {
            $errors->remove($codigo);
        }
        if (!empty($codigos) && !in_array('arins_login_credenciais', $errors->get_error_codes(), true)) {
            $errors->add('arins_login_credenciais', self::texto_erro());
        }
        return $errors;
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
