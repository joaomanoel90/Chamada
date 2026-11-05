# Limpeza do repositório

Registro do que foi removido para deixar o repositório apenas com o sistema de
**Chamada de Alunos**. O repositório nasceu como uma cópia do **SupriADM**
(gestão de almoxarifado e pedidos), e a maior parte do conteúdo não era usada.

**Tamanho: 14 MB → 746 KB.**

A limpeza foi feita em duas etapas: primeiro os módulos de estoque, depois a
remoção do ambiente de demonstração e a consolidação dos scripts de banco.

---

## 1. Módulos de estoque removidos

| Pasta | Motivo |
|---|---|
| `pedidos/` | pedidos de material — fora do escopo |
| `produtos/` | catálogo de produtos |
| `fornecedores/` | cadastro de fornecedores |
| `almoxarifados/` | almoxarifados |
| `reserva/` | reserva de estoque |
| `analise/` | análise/aprovação de pedidos e envio de e-mail |
| `xml/` | importação de XML |
| `dashboard/` | `grafico1..6.php` — **código morto**, ninguém incluía |
| `api/` | `check_notificacoes.php` — notificações de pedidos |
| `config_email/` | configuração de SMTP, usada só por `analise/` |
| `inc/PHPMailer/` | biblioteca de e-mail, usada só por `analise/enviar_email.php` |

## 2. Arquivos sem nenhuma referência

| Item | Tamanho | Motivo |
|---|---|---|
| `videos/Pedidos.mp4` | **11 MB** | tutorial em vídeo do módulo de pedidos |
| `css/bootstrap.css` + `.map` | 518 KB | Bootstrap 3 não usado (o tema carrega o 5 via CDN) |
| `css/bootstrap-theme.css` + `.map` + `.min.css` | 85 KB | idem |
| `css/bootstrap.min.css`, `css/select2-bootstrap4.css`, `css/print.css` | 60 KB | zero referências |
| `js/bootstrap.js`, `js/bootstrap.min.js`, `js/jquery.min.js`, `js/npm.js` | 130 KB | jQuery e Bootstrap vêm de CDN |
| `fonts/` (glyphicons) | 224 KB | Bootstrap 3; o sistema usa Font Awesome 6 |
| `images/brasao1.png` | 9,5 KB | duplicata não referenciada |
| `css/_notes`, `js/_notes`, `inc/_notes` | — | metadados do Dreamweaver |
| `sql_inicial/Outras Tabelas/` | 40 KB | schemas de pedidos, produtos, fornecedores e movimento |

## 3. Código órfão e bugado removido

- `usuarios/acesso_almoxarifado.php` e `usuarios/processa/acesso_almoxarifado_processa.php`
  — vínculo de usuário com almoxarifado.
- `unidades/ativa.php`, `unidades/delete.php`,
  `unidades/processa/ativa_processa.php`, `unidades/processa/delete_processa.php`
  — **estavam errados no sistema original**: apesar de estarem em `unidades/`,
  executavam `UPDATE TB_PRODUTOS` e redirecionavam para `produtos`. Não eram
  linkados pela listagem, e `TB_UNIDADES` nem possui coluna `ATIVO`.
  Unidades continuam sendo gerenciadas por **Unidades → Novo / Editar**.

## 4. O que foi reescrito (não apenas apagado)

| Arquivo | Mudança |
|---|---|
| `index.php` | dashboard de pedidos → **dashboard da chamada**: pendências do dia, turmas e alunos ativos, frequência do mês, gráfico mensal, próximos feriados, observações recentes e relatórios pendentes |
| `processa/dashboard_processa.php` | consultas de `TB_PEDIDOS` → consultas de chamada, respeitando o filtro por unidade |
| `inc/header.php` | menu da gestão sem estoque; agora agrupado em **Chamada** e **Cadastros** |
| `inc/header2.php` | menu do professor só com chamada, alunos, observações, relatório e feriados |
| `inc/footer.php` | removido o polling de 10 s em `api/check_notificacoes.php` e o container de toasts de pedidos |
| `usuarios/index.php` | removido o item "Acesso Almox. (Pedidos)" |
| `login.php` | título "SupriADM" → "Chamada de Alunos" |
| `inc/tema.php` | título da aba → "Chamada de Alunos — Prefeitura de Angatuba" |
| `inc/header*.php` | ícone da sidebar: imagem externa de produtos → brasão local |
| `demo/schema.sql`, `demo/seed.php` | removido o stub de `TB_PEDIDOS` que só existia para alimentar o dashboard antigo |

## 5. O que foi mantido de propósito

- `TB_UNIDADES`, `TB_USUARIOS`, `TB_ACESSO` e `VIEW_ACESSOS_USUARIOS` — o módulo
  de chamada usa essas tabelas para unidades, professores e permissões.
- `unidades/`, `usuarios/`, `conta/` — cadastro de unidades, professores e troca de senha.
- `css/style.css`, `css/tabela.css`, `css/accessibility.css`,
  `js/tabela.js`, `js/mask.js`, `js/main.js`, `js/accessibility.js` — todos em uso.
- `images/brasao.png` — usado no login, na sidebar e no rodapé.

## 6. Verificação

Depois da limpeza: **40/40 testes passaram** (`python3 demo/testes.py 8001`),
incluindo login dos dois perfis, todas as telas, gravação de chamada, bloqueios
por feriado, relatório anual, CSVs, permissões por unidade e arquivos estáticos.

Também foi verificado que **nenhuma página restante contém link para módulo removido**.

## 7. Como reverter

Nada foi perdido: o histórico do Git mantém tudo no commit inicial.

```bash
git log --oneline                          # veja os commits
git show 83b7f43:pedidos/index.php         # recupere um arquivo específico
git checkout 83b7f43 -- pedidos/           # restaure uma pasta inteira
```

## 8. Problema pré-existente (não corrigido)

`usuarios/`, `unidades/` e `conta/` referenciam arquivos que **nunca existiram**
neste repositório (`css/all.min.css`, `css/dataTables.bootstrap5.min.css`,
`js/jquery.dataTables.min.js`, `js/select2.full.min.js`, entre outros). Essas
telas funcionam porque `inc/tema.php` já carrega tudo via CDN; os links locais
geram 404 no console. Ficaram como estavam para não alterar o comportamento
atual — vale limpar essas referências em uma próxima passada.

---

# Segunda etapa — script único e remoção da demo

## 9. Ambiente de demonstração removido

A pasta `demo/` foi excluída por decisão do usuário:

| Arquivo | O que era |
|---|---|
| `bootstrap.php`, `sqlsrv_shim.php` | adaptador que emulava `sqlsrv_*` sobre PDO/SQLite |
| `schema.sql`, `seed.php` | schema SQLite e dados fictícios |
| `router.php` | roteador do servidor embutido do PHP |
| `testes.py` | bateria de 40 verificações |
| `demo.sqlite`, `sess_*` | base e sessões geradas (não versionadas) |

**Consequência:** o sistema agora exige SQL Server real para rodar. Não há mais
como subir um preview sem o banco.

## 10. Scripts de banco consolidados em um só

Removidos:

```
sql_inicial/TB_UNIDADES.sql
sql_inicial/TB_USUARIOS.sql
sql_inicial/TB_ACESSO.sql
sql_inicial/VIEW_ACESSOS_USUARIOS.sql
sql_inicial/Modulo Chamada/MODULO_CHAMADA.sql
```

Substituídos por **`sql_inicial/INSTALACAO_COMPLETA.sql`**, que cria banco,
10 tabelas, chaves, índices, view e dados iniciais, de forma idempotente.

### Bugs do schema original corrigidos no script novo

1. **`VIEW_ACESSOS_USUARIOS` nunca era criada.** O arquivo antigo continha apenas
   um `SELECT`, sem `CREATE VIEW`. Como `inc/functions.php` e
   `usuarios/processa/acesso_processa.php` consultam essa view, a tela de acesso
   por unidade quebrava numa base recém-instalada. Agora há `CREATE OR ALTER VIEW`.
2. **`TB_USUARIOS.EMAIL` não existia.** A coluna era usada em 4 comandos SQL de
   `usuarios/`, mas estava ausente do schema — editar usuário gerava erro. O
   script cria a coluna e a adiciona em bases antigas.
3. **`TB_USUARIOS.ATIVO` vinha `NULL`** no seed original. Agora é `1`, e registros
   antigos com `NULL` são normalizados.
4. **`CPF` era `nvarchar(11)`**, mas o formato `000.000.000-00` tem 14 caracteres.
   Ampliado para `nvarchar(14)`.
5. **Feriados duplicados de abrangência geral.** Um `UNIQUE (DATA, ID_UNIDADE)`
   comum não impede duplicatas quando `ID_UNIDADE` é `NULL` no SQL Server. Foram
   criados dois índices filtrados, cobrindo os dois casos.
6. **Falta justificada com texto em branco.** O `CHECK` agora rejeita
   justificativa vazia ou só com espaços, não apenas `NULL`.

### Validação do script

- **Sintaxe T-SQL:** analisada com parser (dialeto `tsql`), 6.877 nós de árvore,
  zero trechos não interpretados.
- **Lógica do schema:** 17/17 verificações passaram em execução equivalente —
  ordem de criação das tabelas, chaves estrangeiras, unicidade de matrícula,
  turma, CPF e vínculo, bloqueio de chamada duplicada no mesmo dia, `CHECK` de
  status e de justificativa, e os índices filtrados de feriado.
- **Não executado em SQL Server real:** o ambiente não tinha o SGBD. Rode em
  homologação antes da produção.

## 11. Estado final

16 pastas, 746 KB:

```
alunos  chamada  conta  css  feriados  images  inc  js
observacoes  periodos  processa  relatorios  sql_inicial  turmas
unidades  usuarios
```
