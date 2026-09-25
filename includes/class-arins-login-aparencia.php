<?php
/**
 * Aparência da tela de login configurável em Configurações → Login Arins:
 * logo, cores, e exibição dos links "Perdeu a senha?" e "Voltar para a
 * Página Arins". As regras de sanitização e o CSS gerado são puros (testados
 * em tests/test-aparencia.php); o resto liga essas regras ao WordPress.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Arins_Login_Aparencia {

    const OPCAO = 'arins_login_aparencia';

    public static function init() {
        add_action('admin_init', array(__CLASS__, 'registrar_opcao'));
        add_action('arins_login_configuracao_depois', array(__CLASS__, 'secao'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'scripts_admin'));
        add_action('login_enqueue_scripts', array(__CLASS__, 'estilos_login'), 20);
        add_action('login_form_lostpassword', array(__CLASS__, 'bloquear_recuperacao'));
        add_action('login_form_retrievepassword', array(__CLASS__, 'bloquear_recuperacao'));
    }

    // --- regras puras -----------------------------------------------------

    public static function padroes() {
        return array(
            'logo_id' => 0,
            'ocultar_perdeu_senha' => false,
            'exibir_voltar' => true,
            'cor_pagina' => '#f6f5f1',
            'cor_cartao' => '#ffffff',
            'cor_texto' => '#211f1a',
        );
    }

    public static function sanitizar_cor($valor, $padrao) {
        if (is_string($valor) && preg_match('/^#[0-9a-fA-F]{6}$/', $valor)) {
            return strtolower($valor);
        }
        return $padrao;
    }

    /** Aceita só o formato conhecido; qualquer outra coisa cai no padrão. */
    public static function sanitizar($bruto) {
        $padroes = self::padroes();
        if (!is_array($bruto)) {
            return $padroes;
        }
        $logo = isset($bruto['logo_id']) ? $bruto['logo_id'] : 0;
        return array(
            'logo_id' => (is_scalar($logo) && ctype_digit((string) $logo)) ? (int) $logo : 0,
            'ocultar_perdeu_senha' => !empty($bruto['ocultar_perdeu_senha']),
            'exibir_voltar' => !empty($bruto['exibir_voltar']),
            'cor_pagina' => self::sanitizar_cor(isset($bruto['cor_pagina']) ? $bruto['cor_pagina'] : null, $padroes['cor_pagina']),
            'cor_cartao' => self::sanitizar_cor(isset($bruto['cor_cartao']) ? $bruto['cor_cartao'] : null, $padroes['cor_cartao']),
            'cor_texto' => self::sanitizar_cor(isset($bruto['cor_texto']) ? $bruto['cor_texto'] : null, $padroes['cor_texto']),
        );
    }

    public static function css_variaveis($v) {
        return ':root{--arins-paper:' . $v['cor_pagina'] . ';--arins-card:' . $v['cor_cartao'] . ';--arins-ink:' . $v['cor_texto'] . ';}';
    }

    /** Logo personalizado; vazio (ou URL com aspas) mantém o escudo padrão. */
    public static function css_logo($url) {
        if ($url === '' || strpos($url, '"') !== false || strpos($url, "\n") !== false) {
            return '';
        }
        return 'body.login h1 a{background-image:url("' . $url . '");}';
    }

    public static function css_extra($v) {
        $css = '';
        if (!empty($v['ocultar_perdeu_senha'])) {
            $css .= 'body.login-action-login #nav{display:none;}';
        }
        if (empty($v['exibir_voltar'])) {
            $css .= '.arins-login-rodape{display:none;}';
        }
        return $css;
    }

    // --- WordPress --------------------------------------------------------

    public static function valores() {
        $salvo = get_option(self::OPCAO, array());
        return array_merge(self::padroes(), is_array($salvo) ? $salvo : array());
    }

    public static function url_logo($logo_id) {
        if ($logo_id <= 0) {
            return '';
        }
        $url = wp_get_attachment_image_url($logo_id, 'full');
        return $url ? esc_url_raw($url) : '';
    }

    public static function sanitizar_opcao($bruto) {
        $limpo = self::sanitizar($bruto);
        if ($limpo['logo_id'] > 0 && !wp_attachment_is_image($limpo['logo_id'])) {
            $limpo['logo_id'] = 0;
        }
        return $limpo;
    }

    public static function registrar_opcao() {
        register_setting('arins_login_aparencia', self::OPCAO, array(
            'type' => 'array',
            'sanitize_callback' => array(__CLASS__, 'sanitizar_opcao'),
            'default' => self::padroes(),
        ));
    }

    public static function estilos_login() {
        $v = self::valores();
        $css = self::css_variaveis($v) . self::css_logo(self::url_logo((int) $v['logo_id'])) . self::css_extra($v);
        wp_add_inline_style('arins-login', $css);
    }

    /** Com o link oculto, a página de recuperação também deixa de existir. */
    public static function bloquear_recuperacao() {
        $v = self::valores();
        if (!empty($v['ocultar_perdeu_senha'])) {
            wp_safe_redirect(wp_login_url());
            exit;
        }
    }

    public static function exibir_voltar() {
        $v = self::valores();
        return !empty($v['exibir_voltar']);
    }

    public static function scripts_admin($hook) {
        if ($hook !== 'settings_page_arins-login') {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_add_inline_script('wp-color-picker', <<<'JS'
jQuery(function ($) {
    $('.arins-login-cor').wpColorPicker();
    var quadro;
    $('#arins-login-logo-escolher').on('click', function (e) {
        e.preventDefault();
        if (!quadro) {
            quadro = wp.media({ title: 'Logo da tela de login', multiple: false, library: { type: 'image' } });
            quadro.on('select', function () {
                var anexo = quadro.state().get('selection').first().toJSON();
                $('#arins-login-logo-id').val(anexo.id);
                $('#arins-login-logo-previa').attr('src', anexo.url).show();
            });
        }
        quadro.open();
    });
    $('#arins-login-logo-remover').on('click', function (e) {
        e.preventDefault();
        $('#arins-login-logo-id').val('0');
        $('#arins-login-logo-previa').hide().attr('src', '');
    });
});
JS
        );
    }

    /** Seção própria (form completo) na tela Configurações → Login Arins. */
    public static function secao() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $v = self::valores();
        $logo = self::url_logo((int) $v['logo_id']);
        $nome = esc_attr(self::OPCAO);
        ?>
        <h2>Aparência da tela de login</h2>
        <form method="post" action="options.php">
            <?php settings_fields('arins_login_aparencia'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Logo</th>
                    <td>
                        <input type="hidden" id="arins-login-logo-id" name="<?php echo $nome; ?>[logo_id]" value="<?php echo esc_attr((int) $v['logo_id']); ?>">
                        <img id="arins-login-logo-previa" src="<?php echo esc_url($logo); ?>" alt="" style="max-width:220px;max-height:100px;height:auto;display:<?php echo $logo ? 'block' : 'none'; ?>;margin-bottom:8px;background:#f0f0f1;padding:4px;">
                        <button type="button" class="button" id="arins-login-logo-escolher">Escolher imagem</button>
                        <button type="button" class="button" id="arins-login-logo-remover">Usar o escudo padrão</button>
                        <p class="description">A imagem é exibida inteira, sem cortes, com largura de até 220 px.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Cor de fundo da página</th>
                    <td><input type="text" class="arins-login-cor" name="<?php echo $nome; ?>[cor_pagina]" value="<?php echo esc_attr($v['cor_pagina']); ?>"></td>
                </tr>
                <tr>
                    <th scope="row">Cor de fundo do formulário</th>
                    <td><input type="text" class="arins-login-cor" name="<?php echo $nome; ?>[cor_cartao]" value="<?php echo esc_attr($v['cor_cartao']); ?>"></td>
                </tr>
                <tr>
                    <th scope="row">Cor das letras</th>
                    <td><input type="text" class="arins-login-cor" name="<?php echo $nome; ?>[cor_texto]" value="<?php echo esc_attr($v['cor_texto']); ?>"></td>
                </tr>
                <tr>
                    <th scope="row">Links</th>
                    <td>
                        <label><input type="checkbox" name="<?php echo $nome; ?>[ocultar_perdeu_senha]" value="1" <?php checked(!empty($v['ocultar_perdeu_senha'])); ?>> Ocultar "Perdeu a senha?"</label><br>
                        <label><input type="checkbox" name="<?php echo $nome; ?>[exibir_voltar]" value="1" <?php checked(!empty($v['exibir_voltar'])); ?>> Exibir "Voltar para a Página Arins"</label>
                    </td>
                </tr>
            </table>
            <?php submit_button('Salvar aparência'); ?>
        </form>
        <?php
    }
}
