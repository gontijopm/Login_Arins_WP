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
