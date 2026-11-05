# Plano de adaptação — Módulo Chamada de Alunos no SupriADM

## 1. O que foi encontrado no repositório

| Item | Situação atual |
|---|---|
| Stack | PHP procedural + SQL Server via `sqlsrv` |
| Banco | `db_chamada`, usuário `user_chamada`, host `127.0.0.1:1433` |
| Base URL | `/Chamada/` (constante `BASEURL`) |
| Front-end | Bootstrap 5.2, jQuery, DataTables, Select2, Chart.js, Font Awesome |
| Layout | `inc/tema.php` (head) + `inc/header.php` (menu gestão) + `inc/header2.php` (menu unidade) + `inc/footer.php` |
| Login | `login.php` → `valida.php`, por **CPF + MD5**, tabela `TB_USUARIOS` |
| Sessão | `usuarioId`, `usuarioNome`, `usuarioNiveisAcessoId`, `usuarioCPF` |
| Níveis | `1` e `2` = gestão (menu completo); `3` = unidade (menu reduzido) |
| Trava de auth | `config.php` bloqueia todo script que não seja `login/valida/sair` |
| Padrão de módulo | `<modulo>/index.php`, `add.php`, `edit.php` + `<modulo>/processa/*_processa.php` |
| Nomenclatura | tabelas `TB_*`; consultas sempre parametrizadas com `sqlsrv_query` |

## 2. Decisão: reaproveitar o que já existe

O sistema **já tem** o cadastro das unidades de ensino de Angatuba (CMEI Vó Carmela, CMEI Vó Iá,
CEMEIF Vó Virgínia etc.), o cadastro de usuários e o vínculo usuário↔unidade. Recriar isso seria
retrabalho e criaria duas fontes de verdade. Portanto:

| Necessidade da chamada | Reaproveita | Observação |
|---|---|---|
| Creche / escola | **`TB_UNIDADES`** | já populada com as unidades reais |
| Professor / gestor | **`TB_USUARIOS`** | login CPF+MD5 mantido como está |
| Vínculo professor↔unidade | **`TB_ACESSO`** | já usado por `VIEW_ACESSOS_USUARIOS` |
| Perfil de acesso | **`NIVEL`** | 1/2 = gestão; 3 = professor da unidade |

**Nada do módulo de pedidos/estoque é alterado.** O módulo de chamada é aditivo.

## 3. Tabelas novas (7)

Todas seguem o padrão `TB_*` com coluna `ID` identity:

- `TB_PERIODOS` — Manhã, Tarde, Integral (hora início/fim)
- `TB_TURMAS` — turma por unidade, ano letivo, período, professor e capacidade
- `TB_ALUNOS` — matrícula, nome, nascimento, responsável, telefone, turma
- `TB_FERIADOS` — data, descrição, tipo `INTEGRAL` ou `PARCIAL` (com faixa de horário)
- `TB_CHAMADA` — presença por aluno/dia: `P`, `F`, `J`, `A`
- `TB_OBSERVACOES` — observação do professor por aluno, com categoria
- `TB_RELATORIOS_ANUAIS` — parecer e encaminhamentos por aluno/ano

## 4. Telas novas (7 módulos)

Seguindo o padrão `index.php` / `add.php` / `edit.php` / `processa/`:

| Pasta | Função | Acesso |
|---|---|---|
| `periodos/` | cadastro de períodos | gestão |
| `turmas/` | cadastro de turmas | gestão |
| `alunos/` | cadastro de alunos + ficha | gestão e professor (só suas unidades) |
| `feriados/` | feriados integrais e parciais | gestão (professor só consulta) |
| `chamada/` | lançamento da chamada do dia | gestão e professor |
| `observacoes/` | observações por aluno | gestão e professor |
| `relatorios/` | relatório anual de desenvolvimento | gestão e professor |

## 5. Regras de negócio

- Status: `P` presente, `F` falta, `J` falta justificada (**exige justificativa**), `A` atraso.
- Uma chamada por aluno por dia (índice único `ID_ALUNO + DATA`).
- Não permite chamada em **data futura**.
- Não permite chamada em **feriado integral**.
- Feriado **parcial** bloqueia apenas as turmas cujo período cruza a faixa sem aula.
- Ano da chamada precisa bater com o ano letivo da turma.
- Professor (nível 3) só enxerga unidades vinculadas em `TB_ACESSO`.
- Observação só é editada pelo autor ou pela gestão.
- Relatório anual calcula frequência **só sobre dias com chamada lançada**, com quebra mês a mês,
  reúne as observações do ano e permite parecer + encaminhamentos, com versão para impressão.
- Inativação é lógica (`ATIVO = 0`), preservando o histórico.

## 6. Arquivos alterados no sistema atual

Apenas dois, e só com inclusão de links de menu:

- `inc/header.php` — bloco "Chamada de Alunos" no menu da gestão
- `inc/header2.php` — bloco equivalente no menu do professor

## 7. Segurança — dívida técnica encontrada (não alterada)

Registrado para decisão futura, **sem mudança nesta entrega** para não quebrar o login atual:

1. Senhas em **MD5** sem salt (`valida.php`). Recomenda-se migrar para `password_hash`.
2. Formulários sem **token CSRF**.
3. `config.php` com credenciais em texto plano no repositório.
