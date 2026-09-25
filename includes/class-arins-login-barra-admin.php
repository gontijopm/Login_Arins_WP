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
