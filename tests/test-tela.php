<?php
/**
 * Reescrita pura da mensagem de erro do login — sem chamadas ao WordPress,
 * para não revelar se foi o usuário ou a senha que errou.
 */

function test_tela_mensagem_erro_mantem_vazio_sem_erro() {
    assert_same('', Arins_Login_Tela::mensagem_erro(''));
}

function test_tela_mensagem_erro_troca_por_texto_institucional() {
    $resultado = Arins_Login_Tela::mensagem_erro('<strong>ERRO</strong>: usuário desconhecido.');
    assert_contains('Usuário institucional ou senha inválidos.', $resultado);
}

function test_tela_mensagem_erro_nao_revela_qual_campo_errou() {
    $resultado = Arins_Login_Tela::mensagem_erro('<strong>ERRO</strong>: a senha informada está incorreta.');
    assert_true(strpos($resultado, 'senha informada está incorreta') === false);
}
