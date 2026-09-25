<?php
/**
 * Executor mínimo dos testes, sem Composer nem PHPUnit: carrega cada
 * tests/test-*.php e roda toda função cujo nome começa com `test_`.
 *
 * Uso: "C:/Users/p134283/tools/php74/php.exe" tests/run.php [filtro]
 *
 * O WordPress não é carregado. Só as classes puras são testadas aqui; o que
 * depende do WordPress é conferido manualmente após a instalação em
 * produção.
 */

define('ABSPATH', __DIR__ . '/');

require __DIR__ . '/../includes/class-arins-login-barra-admin.php';
require __DIR__ . '/../includes/class-arins-login-tela.php';

class Arins_Login_Falha_De_Teste extends Exception {}

function assert_same($esperado, $obtido, $mensagem = '') {
    if ($esperado !== $obtido) {
        throw new Arins_Login_Falha_De_Teste(
            ($mensagem !== '' ? $mensagem . "\n" : '')
            . 'esperado: ' . var_export($esperado, true) . "\n"
            . 'obtido:   ' . var_export($obtido, true)
        );
    }
}

function assert_true($condicao, $mensagem = 'condição falsa') {
    assert_same(true, $condicao, $mensagem);
}

function assert_contains($agulha, $palheiro) {
    if (strpos($palheiro, $agulha) === false) {
        throw new Arins_Login_Falha_De_Teste('texto não encontrado: ' . $agulha);
    }
}

$antes = get_defined_functions()['user'];
foreach (glob(__DIR__ . '/test-*.php') as $arquivo) {
    require $arquivo;
}
$testes = array_values(array_filter(
    array_diff(get_defined_functions()['user'], $antes),
    function ($nome) {
        return strpos($nome, 'test_') === 0;
    }
));

$filtro = isset($argv[1]) ? $argv[1] : '';
$falhas = 0;
$executados = 0;
foreach ($testes as $teste) {
    if ($filtro !== '' && strpos($teste, $filtro) === false) {
        continue;
    }
    $executados++;
    try {
        $teste();
        echo '.';
    } catch (Throwable $erro) {
        $falhas++;
        echo "\nFALHOU: {$teste}\n" . $erro->getMessage() . "\n";
    }
}

echo "\n{$executados} testes, {$falhas} falhas\n";
exit($falhas > 0 ? 1 : 0);
