<?php
/**
 * Reestiliza wp-login.php com a identidade visual da Pagina_Arins: formulário
 * centralizado, logo, textos e link do rodapé.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Arins_Login_Tela {

    public static function init() {
        add_action('login_enqueue_scripts', array(__CLASS__, 'estilos'));
        add_filter('login_headerurl', array(__CLASS__, 'url_cabecalho'));
        add_filter('login_headertext', array(__CLASS__, 'texto_cabecalho'));
        add_filter('login_display_language_dropdown', '__return_false');
        add_filter('gettext', array(__CLASS__, 'texto_botao'), 10, 2);
        add_filter('wp_login_errors', array(__CLASS__, 'mensagens_erro'), 10, 2);
        add_action('login_header', array(__CLASS__, 'abrir_layout'));
        add_action('login_form', array(__CLASS__, 'link_recuperar_senha'));
        add_action('login_footer', array(__CLASS__, 'fechar_layout'));
    }

    public static function estilos() {
        wp_enqueue_style('arins-login', ARINS_LOGIN_URL . 'assets/css/login.css', array(), ARINS_LOGIN_VERSION);
    }

    /** O botão do formulário de login passa de "Acessar" para "Entrar". */
    public static function texto_botao($traducao, $original) {
        return $original === 'Log In' ? 'Entrar' : $traducao;
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
        $v = Arins_Login_Aparencia::valores();
        $estilo2 = Arins_Login_Aparencia::sanitizar_estilo($v['estilo']) === 2;
        ?>
        <div class="arins-login-wrap">
            <div class="arins-login-fundo" role="presentation"></div>
            <div class="arins-login-painel">
                <?php if ($estilo2) : self::marca($v); else : self::topo($v); endif; ?>
                <div class="arins-login-conteudo">
                    <?php if (!$estilo2 && (string) $v['titulo'] !== '') : ?>
                        <h2 class="arins-login-titulo"><?php echo esc_html($v['titulo']); ?></h2>
                    <?php endif; ?>
        <?php
    }

    /** Estilo 1: faixa dourada com a barra de chevrons e o brasão. */
    private static function topo($v) {
        $logo = Arins_Login_Aparencia::url_anexo((int) $v['logo_id']);
        ?>
        <div class="arins-login-topo">
            <?php if ($logo !== '') : ?>
                <img src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
            <?php endif; ?>
        </div>
        <?php
    }

    /** Estilo 2: linha dourada, logo, título e subtítulo, exatamente como digitados. */
    private static function marca($v) {
        $logo = Arins_Login_Aparencia::url_anexo((int) $v['marca_logo_id']);
        if ($logo === '') {
            $logo = ARINS_LOGIN_URL . 'assets/images/escudo-pmmg-mono-gold.png';
        }
        ?>
        <div class="arins-login-marca">
            <img src="<?php echo esc_url($logo); ?>" alt="">
            <div class="arins-login-marca-textos">
                <?php if ((string) $v['marca_titulo'] !== '') : ?>
                    <p class="arins-login-marca-titulo"><?php echo esc_html($v['marca_titulo']); ?></p>
                <?php endif; ?>
                <?php if ((string) $v['marca_subtitulo'] !== '') : ?>
                    <p class="arins-login-marca-subtitulo"><?php echo esc_html($v['marca_subtitulo']); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /** "Esqueci minha senha" ao lado do botão Entrar, dentro do formulário. */
    public static function link_recuperar_senha() {
        if (!Arins_Login_Aparencia::exibir_perdeu_senha()) {
            return;
        }
        ?>
        <a class="arins-login-esqueci" href="<?php echo esc_url(wp_lostpassword_url()); ?>">Esqueci minha senha</a>
        <?php
    }

    public static function fechar_layout() {
        ?>
                    <p class="arins-login-rodape"><a href="<?php echo esc_url(home_url('/')); ?>">Voltar para a Página Arins</a></p>
                </div>
            </div>
        </div>
        <script>
        (function () {
            var usuario = document.getElementById('user_login');
            var senha = document.getElementById('user_pass');
            if (usuario) {
                usuario.setAttribute('placeholder', 'Usuário');
                usuario.parentNode.classList.add('arins-campo-usuario');
            }
            if (senha) {
                senha.setAttribute('placeholder', 'Senha');
                senha.parentNode.classList.add('arins-campo-senha');
            }
        })();
        </script>
        <?php
    }
}
