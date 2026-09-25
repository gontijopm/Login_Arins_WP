<?php
/**
 * Plugin Name: Login Arins
 * Description: Tela de login personalizada e controle da barra de administração da Assessoria de Relações Institucionais da PMMG.
 * Version: 0.4.1
 * Author: Arins/PMMG
 * Requires at least: 6.6
 * Requires PHP: 7.4
 * Text Domain: arins-login
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ARINS_LOGIN_VERSION', '0.4.1');
define('ARINS_LOGIN_FILE', __FILE__);
define('ARINS_LOGIN_DIR', plugin_dir_path(__FILE__));
define('ARINS_LOGIN_URL', plugin_dir_url(__FILE__));

require_once ARINS_LOGIN_DIR . 'includes/class-arins-login-tela.php';
require_once ARINS_LOGIN_DIR . 'includes/class-arins-login-aparencia.php';
require_once ARINS_LOGIN_DIR . 'includes/class-arins-login-barra-admin.php';
require_once ARINS_LOGIN_DIR . 'includes/class-arins-login-atualizador.php';

Arins_Login_Tela::init();
Arins_Login_Aparencia::init();
Arins_Login_Barra_Admin::init();
Arins_Login_Atualizador::init();
