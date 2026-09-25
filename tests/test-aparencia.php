<?php
/**
 * Lógica pura da aparência da tela de login: cores hexadecimais, logo,
 * opções de ocultar links e as variáveis CSS geradas.
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
        'cor_pagina' => '#FFFFFF',
        'cor_cartao' => '#eeeeee',
        'cor_texto' => '#000000',
    ));
    assert_same(42, $r['logo_id']);
    assert_same(true, $r['ocultar_perdeu_senha']);
    assert_same(true, $r['exibir_voltar']);
    assert_same('#ffffff', $r['cor_pagina']);
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

function test_aparencia_css_variaveis_usa_as_tres_cores() {
    $css = Arins_Login_Aparencia::css_variaveis(array(
        'cor_pagina' => '#111111', 'cor_cartao' => '#222222', 'cor_texto' => '#333333',
    ));
    assert_contains('--arins-paper:#111111', $css);
    assert_contains('--arins-card:#222222', $css);
    assert_contains('--arins-ink:#333333', $css);
}

function test_aparencia_css_logo_vazio_nao_gera_regra() {
    assert_same('', Arins_Login_Aparencia::css_logo(''));
}

function test_aparencia_css_logo_gera_regra_com_url() {
    assert_contains('url("https://x.test/logo.png")', Arins_Login_Aparencia::css_logo('https://x.test/logo.png'));
}

function test_aparencia_css_logo_recusa_url_com_aspas() {
    assert_same('', Arins_Login_Aparencia::css_logo('https://x.test/a".png'));
}

function test_aparencia_css_ocultar_perdeu_senha() {
    assert_contains('#nav', Arins_Login_Aparencia::css_extra(array('ocultar_perdeu_senha' => true, 'exibir_voltar' => true)));
    assert_same(false, strpos(Arins_Login_Aparencia::css_extra(array('ocultar_perdeu_senha' => false, 'exibir_voltar' => true)), '#nav') !== false);
}

function test_aparencia_css_oculta_voltar_quando_nao_exibir() {
    assert_contains('.arins-login-rodape', Arins_Login_Aparencia::css_extra(array('ocultar_perdeu_senha' => false, 'exibir_voltar' => false)));
}
