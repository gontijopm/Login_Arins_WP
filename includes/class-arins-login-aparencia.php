<?php
/**
 * Aparência da tela de login configurável em Configurações → Login Arins:
 * logo, cor de destaque, imagens de fundo (uma é sorteada a cada carregamento)
 * e exibição dos links "Esqueci minha senha" e "Voltar para a Página Arins".
 * As regras de sanitização, o sorteio e o CSS gerado são puros (testados em
 * tests/test-aparencia.php); o resto liga essas regras ao WordPress.
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
            'cor_destaque' => '#dfca8c',
            'fundos' => array(),
        );
    }

    public static function sanitizar_cor($valor, $padrao) {
        if (is_string($valor) && preg_match('/^#[0-9a-fA-F]{6}$/', $valor)) {
            return strtolower($valor);
        }
        return $padrao;
    }

    /** Lista de IDs de anexos: só inteiros positivos, sem repetição. */
    public static function sanitizar_ids($valor) {
        if (!is_array($valor)) {
            return array();
        }
        $ids = array();
        foreach ($valor as $item) {
            if (is_scalar($item) && ctype_digit((string) $item) && (int) $item > 0) {
                $ids[] = (int) $item;
            }
        }
        return array_values(array_unique($ids));
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
            'cor_destaque' => self::sanitizar_cor(isset($bruto['cor_destaque']) ? $bruto['cor_destaque'] : null, $padroes['cor_destaque']),
            'fundos' => self::sanitizar_ids(isset($bruto['fundos']) ? $bruto['fundos'] : array()),
        );
    }

    /** Escolhe um ID da lista a partir de um número aleatório; 0 se a lista é vazia. */
    public static function escolher_fundo($ids, $sorteio) {
        if (empty($ids)) {
            return 0;
        }
        return $ids[$sorteio % count($ids)];
    }

    public static function css_variaveis($v) {
        return ':root{--arins-destaque:' . $v['cor_destaque'] . ';}';
    }

    /** Imagem de fundo sorteada; vazio (ou URL com aspas) deixa o fundo escuro liso. */
    public static function css_fundo($url) {
        if ($url === '' || strpos($url, '"') !== false || strpos($url, "\n") !== false) {
            return '';
        }
        return '.arins-login-fundo{background-image:url("' . $url . '");}';
    }

    public static function css_extra($v) {
        return empty($v['exibir_voltar']) ? '.arins-login-rodape{display:none;}' : '';
    }

    // --- WordPress --------------------------------------------------------

    public static function valores() {
        $salvo = get_option(self::OPCAO, array());
        return array_merge(self::padroes(), is_array($salvo) ? $salvo : array());
    }

    public static function url_anexo($logo_id) {
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
        $limpo['fundos'] = array_values(array_filter($limpo['fundos'], 'wp_attachment_is_image'));
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
        $fundo = self::url_anexo(self::escolher_fundo(self::sanitizar_ids($v['fundos']), mt_rand()));
        wp_add_inline_style('arins-login', self::css_variaveis($v) . self::css_fundo($fundo) . self::css_extra($v));
    }

    /** Com o link oculto, a página de recuperação também deixa de existir. */
    public static function bloquear_recuperacao() {
        if (!self::exibir_perdeu_senha()) {
            wp_safe_redirect(wp_login_url());
            exit;
        }
    }

    public static function exibir_voltar() {
        $v = self::valores();
        return !empty($v['exibir_voltar']);
    }

    public static function exibir_perdeu_senha() {
        $v = self::valores();
        return empty($v['ocultar_perdeu_senha']);
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

    var quadroLogo;
    $('#arins-login-logo-escolher').on('click', function (e) {
        e.preventDefault();
        if (!quadroLogo) {
            quadroLogo = wp.media({ title: 'Logo da tela de login', multiple: false, library: { type: 'image' } });
            quadroLogo.on('select', function () {
                var anexo = quadroLogo.state().get('selection').first().toJSON();
                $('#arins-login-logo-id').val(anexo.id);
                $('#arins-login-logo-previa').attr('src', anexo.url).show();
            });
        }
        quadroLogo.open();
    });
    $('#arins-login-logo-remover').on('click', function (e) {
        e.preventDefault();
        $('#arins-login-logo-id').val('0');
        $('#arins-login-logo-previa').hide().attr('src', '');
    });

    var quadroFundos;
    var lista = $('#arins-login-fundos');
    $('#arins-login-fundos-adicionar').on('click', function (e) {
        e.preventDefault();
        if (!quadroFundos) {
            quadroFundos = wp.media({ title: 'Imagens de fundo', multiple: 'add', library: { type: 'image' } });
            quadroFundos.on('select', function () {
                quadroFundos.state().get('selection').each(function (modelo) {
                    var anexo = modelo.toJSON();
                    if (lista.find('li[data-id="' + anexo.id + '"]').length) { return; }
                    var miniatura = (anexo.sizes && anexo.sizes.thumbnail) ? anexo.sizes.thumbnail.url : anexo.url;
                    var item = $('<li>').attr('data-id', anexo.id);
                    item.append($('<img>').attr('src', miniatura));
                    item.append($('<button type="button" class="button-link arins-login-fundo-remover">Remover</button>'));
                    item.append($('<input type="hidden">').attr('name', lista.data('nome')).val(anexo.id));
                    lista.append(item);
                });
            });
        }
        quadroFundos.open();
    });
    lista.on('click', '.arins-login-fundo-remover', function () {
        $(this).closest('li').remove();
    });
});
JS
        );
        wp_add_inline_style('wp-color-picker', '#arins-login-fundos{display:flex;flex-wrap:wrap;gap:12px;margin:0 0 12px;padding:0}#arins-login-fundos li{width:120px;margin:0;text-align:center}#arins-login-fundos img{width:120px;height:80px;object-fit:cover;display:block;margin-bottom:4px}');
    }

    /** Seção própria (form completo) na tela Configurações → Login Arins. */
    public static function secao() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $v = self::valores();
        $logo = self::url_anexo((int) $v['logo_id']);
        $nome = esc_attr(self::OPCAO);
        $fundos = self::sanitizar_ids($v['fundos']);
        ?>
        <h2>Aparência da tela de login</h2>
        <form method="post" action="options.php">
            <?php settings_fields('arins_login_aparencia'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Logo da faixa dourada</th>
                    <td>
                        <input type="hidden" id="arins-login-logo-id" name="<?php echo $nome; ?>[logo_id]" value="<?php echo esc_attr((int) $v['logo_id']); ?>">
                        <img id="arins-login-logo-previa" src="<?php echo esc_url($logo); ?>" alt="" style="max-width:220px;max-height:100px;height:auto;display:<?php echo $logo ? 'block' : 'none'; ?>;margin-bottom:8px;background:#a89562;padding:4px;">
                        <button type="button" class="button" id="arins-login-logo-escolher">Escolher imagem</button>
                        <button type="button" class="button" id="arins-login-logo-remover">Remover logo</button>
                        <p class="description">Aparece inteiro, sem cortes, centralizado na faixa dourada do topo do painel.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Imagens de fundo</th>
                    <td>
                        <ul id="arins-login-fundos" data-nome="<?php echo $nome; ?>[fundos][]">
                            <?php foreach ($fundos as $id) : ?>
                                <li data-id="<?php echo esc_attr($id); ?>">
                                    <?php echo wp_get_attachment_image($id, 'thumbnail'); ?>
                                    <button type="button" class="button-link arins-login-fundo-remover">Remover</button>
                                    <input type="hidden" name="<?php echo $nome; ?>[fundos][]" value="<?php echo esc_attr($id); ?>">
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="button" id="arins-login-fundos-adicionar">Adicionar imagens</button>
                        <p class="description">Com uma imagem, ela fica fixa. Com várias, uma é sorteada a cada vez que a tela de login é aberta. Textos e brasões fazem parte da própria imagem.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Cor de destaque</th>
                    <td>
                        <input type="text" class="arins-login-cor" name="<?php echo $nome; ?>[cor_destaque]" value="<?php echo esc_attr($v['cor_destaque']); ?>">
                        <p class="description">Botão "Entrar", faixa do topo e realces do painel.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Links</th>
                    <td>
                        <label><input type="checkbox" name="<?php echo $nome; ?>[ocultar_perdeu_senha]" value="1" <?php checked(!empty($v['ocultar_perdeu_senha'])); ?>> Ocultar "Esqueci minha senha"</label><br>
                        <label><input type="checkbox" name="<?php echo $nome; ?>[exibir_voltar]" value="1" <?php checked(!empty($v['exibir_voltar'])); ?>> Exibir "Voltar para a Página Arins"</label>
                    </td>
                </tr>
            </table>
            <?php submit_button('Salvar aparência'); ?>
        </form>
        <?php
    }
}
