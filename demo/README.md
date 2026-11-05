# Pasta `demo/` — ambiente de demonstração

**Não usar em produção.** Esta pasta existe só para demonstrar e testar o módulo
em uma máquina que não tem SQL Server nem o driver `sqlsrv`.

## O que tem aqui

| Arquivo | Função |
|---|---|
| `bootstrap.php` | emula as funções `sqlsrv_*` sobre PDO/SQLite e traduz o T-SQL usado pelo módulo |
| `schema.sql` | schema SQLite equivalente ao `MODULO_CHAMADA.sql` |
| `seed.php` | cria a base com dados fictícios |
| `router.php` | serve o sistema sob `/Chamada/`, como no IIS/Apache |
| `testes.py` | bateria de 36 verificações ponta a ponta |

## Como rodar

```bash
php demo/seed.php

php -d auto_prepend_file=$PWD/demo/bootstrap.php \
    -d session.save_path=$PWD/demo \
    -S 0.0.0.0:8001 -t . demo/router.php
```

Abra `http://localhost:8001/Chamada/login.php`.

| Perfil | CPF | Senha |
|---|---|---|
| Gestão (nível 1) | `111.111.111-11` | `demo123` |
| Professor (nível 3) | `222.222.222-22` | `demo123` |

Outros professores: `333.333.333-33` e `444.444.444-44`, mesma senha.

## Dados fictícios gerados

5 unidades (CMEIs de Angatuba), 4 usuários, 5 turmas, 29 alunos,
~5.400 registros de chamada de fevereiro a novembro de 2026, ~112 observações
e 14 feriados (2 deles parciais).

## Testes

```bash
php demo/seed.php          # base limpa
python3 demo/testes.py 8001
```

## Importante

Em produção nada desta pasta é carregado: basta **não** usar o
`auto_prepend_file`. O sistema continua usando `inc/database.php` com
`sqlsrv_connect` apontando para o SQL Server.

Você pode simplesmente não publicar esta pasta no servidor.
