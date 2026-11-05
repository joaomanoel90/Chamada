# Módulo Chamada de Alunos — SupriADM

Módulo de chamada, observações e relatório anual de desenvolvimento da
Prefeitura Municipal de Angatuba.

O repositório era originalmente uma cópia do **SupriADM** (almoxarifado/pedidos).
Os módulos de estoque foram removidos — veja [`LIMPEZA.md`](LIMPEZA.md) — e hoje
o sistema é só de chamada.

Foi escrito seguindo os padrões que já existem no projeto: PHP procedural,
SQL Server via `sqlsrv`, Bootstrap 5 + DataTables, e a estrutura
`<modulo>/index.php` + `add.php` + `edit.php` + `processa/*_processa.php`.

---

## 1. O que foi aproveitado do sistema atual

Nenhuma tabela de alunos, professores ou unidades foi duplicada. O módulo usa o
que já existe:

| Necessidade | Tabela reaproveitada |
|---|---|
| Creche / escola | `TB_UNIDADES` |
| Professor e gestão | `TB_USUARIOS` |
| Vínculo professor ↔ unidade | `TB_ACESSO` |
| Perfil de acesso | `TB_USUARIOS.NIVEL` |
| Login | o mesmo `login.php` / `valida.php` (CPF + senha) |
| Layout e menu | `inc/tema.php`, `inc/header.php`, `inc/header2.php`, `inc/footer.php` |

**Perfis:**

- **Nível 1 e 2 (gestão):** acesso a tudo, incluindo os cadastros de períodos,
  turmas, alunos e feriados, em todas as unidades.
- **Nível 3 (professor / unidade):** vê apenas as unidades vinculadas em
  `TB_ACESSO`. Lança chamada, registra observações e emite relatórios. Os
  cadastros de períodos, turmas e feriados ficam restritos à gestão.

> Para um professor aparecer nas telas, ele precisa ter registro em `TB_ACESSO`
> com `ativo = 1` para a unidade dele. Isso é feito em **Usuários → Acesso por Unidade**.

---

## 2. Instalação

### Passo 1 — banco de dados

Execute no SQL Server, na base `db_chamada`:

```
sql_inicial/Modulo Chamada/MODULO_CHAMADA.sql
```

O script é **idempotente**: pode rodar mais de uma vez sem apagar dados. Ele cria
7 tabelas e já insere os períodos padrão (Manhã, Tarde e Integral).

Pré-requisito: `TB_UNIDADES`, `TB_USUARIOS` e `TB_ACESSO` já existirem.

### Passo 2 — arquivos

Copie para a raiz do sistema (a mesma pasta do `config.php`):

```
inc/chamada.php
inc/chamada_categorias.php
periodos/        turmas/        alunos/        feriados/
chamada/         observacoes/   relatorios/
```

### Passo 3 — menu e dashboard

`inc/header.php` e `inc/header2.php` já têm o menu só da chamada, e `index.php`
com `processa/dashboard_processa.php` formam o dashboard da chamada (pendências
do dia, frequência do mês, próximos feriados, observações recentes).

### Passo 4 — primeiro uso

1. **Períodos** — confira Manhã / Tarde / Integral e ajuste os horários.
2. **Turmas** — cadastre as turmas, escolhendo unidade, ano letivo, período,
   professor e capacidade.
3. **Alunos** — matricule os alunos nas turmas.
4. **Feriados** — cadastre os feriados do ano.
5. **Chamada do Dia** — lance a presença.

---

## 3. Regras de negócio

### Chamada

| Código | Situação | Observação |
|---|---|---|
| `P` | Presente | conta como presença |
| `A` | Atraso | conta como presença |
| `F` | Falta | não conta |
| `J` | Falta justificada | **exige justificativa**; não conta |

- Um registro por aluno por dia (índice único `ID_ALUNO + DATA`). Salvar de novo
  atualiza o registro existente.
- **Data futura** é bloqueada.
- A data precisa pertencer ao **ano letivo da turma**.
- Turma inativa não aceita novos lançamentos.
- A gravação é transacional: ou salva a turma inteira, ou não grava nada.

### Feriados

- **Integral:** bloqueia a chamada de todas as turmas do dia.
- **Parcial:** guarda a faixa **sem aula** e bloqueia só as turmas cujo período
  cruza essa faixa.
  Exemplo: sem aula das 12:00 às 18:00 → bloqueia Tarde e Integral, libera Manhã.
- Abrangência pode ser **todas as unidades** ou uma unidade específica.
- Não é permitido cadastrar feriado por cima de chamada já lançada — apague a
  chamada antes.
- Excluir um feriado **não** apaga chamadas já lançadas.

### Observações

- Categorias prontas: desenvolvimento geral, linguagem, coordenação motora,
  convívio social, alimentação, sono, saúde, autonomia, contato com a família.
- Só o **autor** ou a **gestão** podem editar uma observação.
- Data não pode ser futura. Limite de 5.000 caracteres.

### Relatório anual

- A frequência é calculada **somente sobre os dias com chamada lançada** — dias
  sem lançamento não entram na conta, para não distorcer o percentual.
- Traz o consolidado **mês a mês**, a lista de faltas, e **todas as observações
  do ano** agrupadas por área.
- Tem campos de **parecer descritivo** e **encaminhamentos**, salvos por
  aluno/ano.
- Botão **Imprimir** com CSS próprio (esconde menu e rodapé, inclui campo de
  assinatura do professor e do responsável) e exportação **CSV**.

### Inativação

Alunos, turmas e períodos são **inativados**, nunca apagados, preservando o
histórico. Um período em uso por turmas ativas não pode ser inativado.

---

## 4. Estrutura de arquivos

```
inc/chamada.php                  funções compartilhadas (acesso, datas, regras)
inc/chamada_categorias.php       categorias de observação

periodos/     index add edit formulario + processa/{index,add,edit,salvar,toggle}
turmas/       index add edit formulario + processa/{index,add,edit,salvar,toggle}
alunos/       index add edit view formulario + processa/{index,add,edit,view,salvar,toggle}
feriados/     index add edit formulario + processa/{index,add,edit,salvar,delete}
chamada/      index salvar exportar + processa/{index,salvar}
observacoes/  index add edit formulario + processa/{index,add,edit,salvar}
relatorios/   index ver salvar exportar + processa/{index,ver,salvar}

sql_inicial/Modulo Chamada/MODULO_CHAMADA.sql
```

---

## 5. Demonstração local (sem SQL Server)

A pasta `demo/` permite rodar o módulo em uma máquina sem SQL Server, usando um
adaptador que emula `sqlsrv_*` sobre SQLite. **Não use em produção.**

```bash
php demo/seed.php
php -d auto_prepend_file=$PWD/demo/bootstrap.php \
    -S 0.0.0.0:8001 -t . demo/router.php
```

Acesse `http://localhost:8001/Chamada/login.php`:

| Perfil | CPF | Senha |
|---|---|---|
| Gestão | `111.111.111-11` | `demo123` |
| Professor | `222.222.222-22` | `demo123` |

Testes automatizados: `python3 demo/testes.py 8001` — 36 verificações.

---

## 6. Segurança — pontos herdados do sistema

O módulo **não alterou** o mecanismo de login para não quebrar o sistema atual.
Ficam registrados, para decisão futura:

1. **Senhas em MD5 sem salt** (`valida.php`). Recomenda-se migrar para
   `password_hash()` / `password_verify()`, com rehash no primeiro login.
2. **Ausência de token CSRF** nos formulários, inclusive nos novos. Recomenda-se
   adicionar um token por sessão em todos os `POST`.
3. **Credenciais em texto plano** no `config.php` versionado. Recomenda-se mover
   para variáveis de ambiente e fora do controle de versão.

O módulo já aplica, por conta própria: consultas 100% parametrizadas, escape de
saída com `htmlspecialchars`, validação de escopo por unidade em toda tela e
transações nas gravações.

---

## 7. Limitações desta entrega

- Validação executada em **PHP 8.4 + SQLite** através do adaptador de
  demonstração. O caminho de produção (**SQL Server + `sqlsrv`**) não pôde ser
  executado aqui por falta do servidor e do driver no ambiente.
- O script `MODULO_CHAMADA.sql` foi escrito para SQL Server 2019+, mas recomenda-se
  rodar primeiro em uma base de homologação.
- As listas de alunos e turmas usam DataTables (paginação no navegador). Para
  volumes muito grandes, vale migrar para paginação no servidor.
- `usuarios/`, `unidades/` e `conta/` têm referências a arquivos CSS/JS locais que
  não existem no repositório; funcionam pelo CDN do `inc/tema.php`. Ver `LIMPEZA.md`.
