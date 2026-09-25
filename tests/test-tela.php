<?php
/**
 * Filtragem pura dos códigos de erro do login — só credenciais inválidas
 * viram a mensagem institucional única; erros de cookie bloqueado, link de
 * redefinição expirado etc. passam intactos, e a mensagem institucional
 * nunca revela se foi o usuário ou a senha.
 */

function test_tela_filtra_usuario_invalido() {
    assert_same(array('invalid_username'), Arins_Login_Tela::filtrar_codigos_erro(array('invalid_username')));
}

function test_tela_filtra_senha_incorreta() {
    assert_same(array('incorrect_password'), Arins_Login_Tela::filtrar_codigos_erro(array('incorrect_password')));
}

function test_tela_nao_filtra_cookie_bloqueado() {
    assert_same(array(), Arins_Login_Tela::filtrar_codigos_erro(array('test_cookie')));
}

function test_tela_nao_filtra_link_de_redefinicao_expirado() {
    assert_same(array(), Arins_Login_Tela::filtrar_codigos_erro(array('expiredkey')));
}

function test_tela_filtra_so_os_codigos_de_credencial_da_lista_mista() {
    $resultado = Arins_Login_Tela::filtrar_codigos_erro(array('invalid_username', 'test_cookie', 'incorrect_password'));
    assert_same(array('invalid_username', 'incorrect_password'), $resultado);
}

function test_tela_texto_erro_nao_menciona_campo_especifico() {
    assert_true(strpos(Arins_Login_Tela::texto_erro(), 'incorreta') === false);
}
