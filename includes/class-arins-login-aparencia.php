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
        add_filter('login_body_class', array(__CLASS__, 'classe_do_corpo'));
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
            'titulo' => 'ARINS PMMG',
            'estilo' => 1,
            'marca_logo_id' => 0,
            'marca_titulo' => 'Arins',
            'marca_subtitulo' => 'ASSESSORIA DE RELAÇÕES INSTITUCIONAIS',
            'fonte_titulo' => 20,
            'fonte_marca_titulo' => 36,
            'fonte_marca_subtitulo' => 14,
            'fonte_formulario' => 15,
        );
    }

    /** Limites (em px) dos tamanhos de fonte configuráveis. */
    public static function limites_fonte() {
        return array(
            'fonte_titulo' => array(12, 48),
            'fonte_marca_titulo' => array(16, 72),
            'fonte_marca_subtitulo' => array(10, 32),
            'fonte_formulario' => array(12, 24),
        );
    }

    /** Inteiro em px dentro de [min, max]; vazio ou inválido volta ao padrão. */
    public static function sanitizar_tamanho($valor, $padrao, $min, $max) {
        if (!is_scalar($valor) || !preg_match('/^\d{1,3}$/', trim((string) $valor))) {
            return $padrao;
        }
        return max($min, min($max, (int) $valor));
    }

    /** Estilo 1 (painel com faixa dourada) ou 2 (painel preto com a marca). */
    public static function sanitizar_estilo($valor) {
        return (is_scalar($valor) && (string) $valor === '2') ? 2 : 1;
    }

    /** Texto puro (sem HTML), até $max caracteres, mantendo maiúsculas e minúsculas; vazio oculta o texto. */
    public static function sanitizar_titulo($valor, $padrao, $max = 60) {
        if (!is_string($valor)) {
            return $padrao;
        }
        $valor = trim(preg_replace('/\s+/', ' ', strip_tags($valor)));
        return mb_substr($valor, 0, $max);
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
            'titulo' => self::sanitizar_titulo(isset($bruto['titulo']) ? $bruto['titulo'] : null, $padroes['titulo']),
            'estilo' => self::sanitizar_estilo(isset($bruto['estilo']) ? $bruto['estilo'] : null),
            'marca_logo_id' => (isset($bruto['marca_logo_id']) && is_scalar($bruto['marca_logo_id']) && ctype_digit((string) $bruto['marca_logo_id'])) ? (int) $bruto['marca_logo_id'] : 0,
            'marca_titulo' => self::sanitizar_titulo(isset($bruto['marca_titulo']) ? $bruto['marca_titulo'] : null, $padroes['marca_titulo']),
            'marca_subtitulo' => self::sanitizar_titulo(isset($bruto['marca_subtitulo']) ? $bruto['marca_subtitulo'] : null, $padroes['marca_subtitulo'], 100),
        ) + self::sanitizar_fontes($bruto, $padroes);
    }

    private static function sanitizar_fontes($bruto, $padroes) {
        $fontes = array();
        foreach (self::limites_fonte() as $chave => $limite) {
            $fontes[$chave] = self::sanitizar_tamanho(isset($bruto[$chave]) ? $bruto[$chave] : null, $padroes[$chave], $limite[0], $limite[1]);
        }
        return $fontes;
    }

    /** Escolhe um ID da lista a partir de um número aleatório; 0 se a lista é vazia. */
    public static function escolher_fundo($ids, $sorteio) {
        if (empty($ids)) {
            return 0;
        }
        return $ids[$sorteio % count($ids)];
    }

    public static function css_variaveis($v) {
        $v = array_merge(self::padroes(), $v);
        return ':root{--arins-destaque:' . $v['cor_destaque']
            . ';--arins-fs-titulo:' . (int) $v['fonte_titulo'] . 'px'
            . ';--arins-fs-marca-titulo:' . (int) $v['fonte_marca_titulo'] . 'px'
            . ';--arins-fs-marca-subtitulo:' . (int) $v['fonte_marca_subtitulo'] . 'px'
            . ';--arins-fs-form:' . (int) $v['fonte_formulario'] . 'px;}';
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
        if ($limpo['marca_logo_id'] > 0 && !wp_attachment_is_image($limpo['marca_logo_id'])) {
            $limpo['marca_logo_id'] = 0;
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

    public static function classe_do_corpo($classes) {
        $v = self::valores();
        if (self::sanitizar_estilo($v['estilo']) === 2) {
            $classes[] = 'arins-estilo-2';
        }
        return $classes;
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

    $('.arins-login-logo').each(function () {
        var caixa = $(this), quadro;
        caixa.find('.arins-login-logo-escolher').on('click', function (e) {
            e.preventDefault();
            if (!quadro) {
                quadro = wp.media({ title: 'Escolher logo', multiple: false, library: { type: 'image' } });
                quadro.on('select', function () {
                    var anexo = quadro.state().get('selection').first().toJSON();
                    caixa.find('.arins-login-logo-id').val(anexo.id);
                    caixa.find('.arins-login-logo-previa').attr('src', anexo.url).show();
                });
            }
            quadro.open();
        });
        caixa.find('.arins-login-logo-remover').on('click', function (e) {
            e.preventDefault();
            caixa.find('.arins-login-logo-id').val('0');
            caixa.find('.arins-login-logo-previa').hide().attr('src', '');
        });
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
        $marca_logo = self::url_anexo((int) $v['marca_logo_id']);
        $nome = esc_attr(self::OPCAO);
        $fundos = self::sanitizar_ids($v['fundos']);
        $estilo = self::sanitizar_estilo($v['estilo']);
        ?>
        <h2>Aparência da tela de login</h2>
        <form method="post" action="options.php">
            <?php settings_fields('arins_login_aparencia'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Estilo</th>
                    <td>
                        <label><input type="radio" name="<?php echo $nome; ?>[estilo]" value="1" <?php checked($estilo, 1); ?>> <strong>Estilo 1</strong> — painel com faixa dourada, barra de chevrons e brasão</label><br>
                        <label><input type="radio" name="<?php echo $nome; ?>[estilo]" value="2" <?php checked($estilo, 2); ?>> <strong>Estilo 2</strong> — painel todo preto, com a marca (linha dourada, logo, título e subtítulo)</label>
                        <p class="description">Nos dois estilos, a foto de fundo fica à esquerda e o formulário à direita.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Logo da faixa dourada <em>(Estilo 1)</em></th>
                    <td>
                        <div class="arins-login-logo">
                            <input type="hidden" class="arins-login-logo-id" name="<?php echo $nome; ?>[logo_id]" value="<?php echo esc_attr((int) $v['logo_id']); ?>">
                            <img class="arins-login-logo-previa" src="<?php echo esc_url($logo); ?>" alt="" style="max-width:220px;max-height:100px;height:auto;display:<?php echo $logo ? 'block' : 'none'; ?>;margin-bottom:8px;background:#a89562;padding:4px;">
                            <button type="button" class="button arins-login-logo-escolher">Escolher imagem</button>
                            <button type="button" class="button arins-login-logo-remover">Remover logo</button>
                            <p class="description">Aparece inteiro, sem cortes, centralizado na faixa dourada do topo do painel.</p>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Logo da marca <em>(Estilo 2)</em></th>
                    <td>
                        <div class="arins-login-logo">
                            <input type="hidden" class="arins-login-logo-id" name="<?php echo $nome; ?>[marca_logo_id]" value="<?php echo esc_attr((int) $v['marca_logo_id']); ?>">
                            <img class="arins-login-logo-previa" src="<?php echo esc_url($marca_logo); ?>" alt="" style="max-width:220px;max-height:100px;height:auto;display:<?php echo $marca_logo ? 'block' : 'none'; ?>;margin-bottom:8px;background:#111;padding:4px;">
                            <button type="button" class="button arins-login-logo-escolher">Escolher imagem</button>
                            <button type="button" class="button arins-login-logo-remover">Usar o escudo padrão</button>
                            <p class="description">Sem imagem escolhida, usa o escudo dourado do plugin.</p>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="arins-login-marca-titulo">Título da marca <em>(Estilo 2)</em></label></th>
                    <td>
                        <input type="text" class="regular-text" id="arins-login-marca-titulo" maxlength="60" name="<?php echo $nome; ?>[marca_titulo]" value="<?php echo esc_attr($v['marca_titulo']); ?>">
                        <p class="description">Texto grande em dourado. Maiúsculas e minúsculas são exibidas exatamente como digitadas.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="arins-login-marca-subtitulo">Subtítulo da marca <em>(Estilo 2)</em></label></th>
                    <td>
                        <input type="text" class="large-text" id="arins-login-marca-subtitulo" maxlength="100" name="<?php echo $nome; ?>[marca_subtitulo]" value="<?php echo esc_attr($v['marca_subtitulo']); ?>">
                        <p class="description">Maiúsculas e minúsculas são exibidas exatamente como digitadas.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Tamanho das fontes <em>(px)</em></th>
                    <td>
                        <?php
                        $rotulos = array(
                            'fonte_titulo' => 'Título do painel (Estilo 1)',
                            'fonte_marca_titulo' => 'Título da marca (Estilo 2)',
                            'fonte_marca_subtitulo' => 'Subtítulo da marca (Estilo 2)',
                            'fonte_formulario' => 'Campos, botão e links do formulário',
                        );
                        foreach (self::limites_fonte() as $chave => $limite) : ?>
                            <p>
                                <label>
                                    <input type="number" class="small-text" min="<?php echo (int) $limite[0]; ?>" max="<?php echo (int) $limite[1]; ?>" step="1" name="<?php echo $nome; ?>[<?php echo esc_attr($chave); ?>]" value="<?php echo esc_attr((int) $v[$chave]); ?>">
                                    <?php echo esc_html($rotulos[$chave]); ?> <span class="description">(<?php echo (int) $limite[0]; ?> a <?php echo (int) $limite[1]; ?>)</span>
                                </label>
                            </p>
                        <?php endforeach; ?>
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
                    <th scope="row"><label for="arins-login-titulo">Título do painel <em>(Estilo 1)</em></label></th>
                    <td>
                        <input type="text" class="regular-text" id="arins-login-titulo" maxlength="60" name="<?php echo $nome; ?>[titulo]" value="<?php echo esc_attr($v['titulo']); ?>">
                        <p class="description">Texto acima dos campos de login. Deixe vazio para não exibir título.</p>
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
