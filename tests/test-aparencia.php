<?php
/**
 * Lógica pura da aparência da tela de login: cor de destaque, logo, imagens
 * de fundo (sorteio), opções de ocultar links e o CSS gerado.
 */

function test_aparencia_cor_valida_e_normalizada_para_minuscula() {
    assert_same('#aabbcc', Arins_Login_Aparencia::sanitizar_cor('#AABBCC', '#000000'));
}

function test_aparencia_cor_invalida_volta_ao_padrao() {
    assert_same('#123456', Arins_Login_Aparencia::sanitizar_cor('vermelho', '#123456'));
    assert_same('#123456', Arins_Login_Aparencia::sanitizar_cor('#fff', '#123456'));
    assert_same('#123456', Arins_Login_Aparencia::sanitizar_cor('#12345g', '#123456'));
    assert_same('#123456', Arins_Login_Aparencia::sanitizar_cor(array('#ffffff'), '#123456'));
}

function test_aparencia_sanitizar_nao_array_devolve_padroes() {
    assert_same(Arins_Login_Aparencia::padroes(), Arins_Login_Aparencia::sanitizar('lixo'));
}

function test_aparencia_sanitizar_aceita_valores_validos() {
    $r = Arins_Login_Aparencia::sanitizar(array(
        'logo_id' => '42',
        'ocultar_perdeu_senha' => '1',
        'exibir_voltar' => '1',
        'cor_destaque' => '#DFCA8C',
        'fundos' => array('7', '9'),
    ));
    assert_same(42, $r['logo_id']);
    assert_same(true, $r['ocultar_perdeu_senha']);
    assert_same(true, $r['exibir_voltar']);
    assert_same('#dfca8c', $r['cor_destaque']);
    assert_same(array(7, 9), $r['fundos']);
}

function test_aparencia_caixa_desmarcada_vira_false() {
    $r = Arins_Login_Aparencia::sanitizar(array('logo_id' => 0));
    assert_same(false, $r['ocultar_perdeu_senha']);
    assert_same(false, $r['exibir_voltar']);
}

function test_aparencia_logo_id_invalido_vira_zero() {
    assert_same(0, Arins_Login_Aparencia::sanitizar(array('logo_id' => '-5'))['logo_id']);
    assert_same(0, Arins_Login_Aparencia::sanitizar(array('logo_id' => 'abc'))['logo_id']);
}

function test_aparencia_fundos_remove_invalidos_e_duplicados() {
    $r = Arins_Login_Aparencia::sanitizar_ids(array('7', 'x', '-1', '0', '7', array(3), '12'));
    assert_same(array(7, 12), $r);
}

function test_aparencia_fundos_nao_array_vira_lista_vazia() {
    assert_same(array(), Arins_Login_Aparencia::sanitizar_ids('7'));
}

function test_aparencia_escolher_fundo_lista_vazia_devolve_zero() {
    assert_same(0, Arins_Login_Aparencia::escolher_fundo(array(), 5));
}

function test_aparencia_escolher_fundo_uma_imagem_sempre_ela() {
    assert_same(7, Arins_Login_Aparencia::escolher_fundo(array(7), 123));
}

function test_aparencia_escolher_fundo_usa_o_sorteio_dentro_da_lista() {
    assert_same(9, Arins_Login_Aparencia::escolher_fundo(array(7, 9, 12), 1));
    assert_same(7, Arins_Login_Aparencia::escolher_fundo(array(7, 9, 12), 3));
}

function test_aparencia_css_variaveis_usa_cor_de_destaque() {
    assert_contains('--arins-destaque:#111111', Arins_Login_Aparencia::css_variaveis(array('cor_destaque' => '#111111')));
}

function test_aparencia_css_fundo_vazio_nao_gera_regra() {
    assert_same('', Arins_Login_Aparencia::css_fundo(''));
}

function test_aparencia_css_fundo_gera_regra_com_url() {
    assert_contains('url("https://x.test/f.jpg")', Arins_Login_Aparencia::css_fundo('https://x.test/f.jpg'));
}

function test_aparencia_css_fundo_recusa_url_com_aspas() {
    assert_same('', Arins_Login_Aparencia::css_fundo('https://x.test/a".png'));
}

function test_aparencia_css_oculta_voltar_quando_nao_exibir() {
    assert_contains('.arins-login-rodape', Arins_Login_Aparencia::css_extra(array('exibir_voltar' => false)));
    assert_same('', Arins_Login_Aparencia::css_extra(array('exibir_voltar' => true)));
}
