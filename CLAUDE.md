# Login Arins — plugin WordPress

Plugin `arins-login.php` (versão no cabeçalho). Repositório pequeno: não há README; a documentação está em `docs/superpowers/` (specs e planos), que deve ser aberta só quando a tarefa exigir.

## Comandos

```
php tests/run.php [filtro]    # executor mínimo: carrega tests/test-*.php e roda as funções test_* (sem carregar o WordPress)
./tools/build-plugin.ps1      # empacota o plugin
```

Testes existentes: aparência, barra de administração e tela de login.

## Regras

- Função do plugin: tela de login personalizada e controle da barra de administração (classes `tela`, `aparencia`, `barra-admin` e `atualizador` em `includes/`).
- Preservar alterações não relacionadas no worktree; commitar só os arquivos da tarefa.
