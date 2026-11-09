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

---

## 12. Equipe por turma, retificação e layout escolar

Pedido: *"pode ter mais de um professor por turma; ao lançar presença não poder
mais confirmar para o aluno naquele dia, somente retificar; e ajuste o layout,
deixe mais com cara de ambiente escolar"*.

### Banco (o mesmo `INSTALACAO_COMPLETA.sql`, re-executável)

| Objeto | Para que serve |
|---|---|
| `TB_TURMA_PROFESSORES` | equipe da turma: N professores, `PAPEL` REGENTE/AUXILIAR, `ATIVO` |
| `UX_TURMAPROF_REGENTE` | índice único **filtrado**: no máximo 1 regente ativo por turma |
| `TB_CHAMADA_RETIFICACOES` | antes/depois, justificativas, motivo (`CHECK` não-vazio), autor, data/hora |
| `TB_CHAMADA.CONFIRMADA_EM` / `CONFIRMADA_POR` | quem confirmou originalmente, preservado nas retificações |

Migração automática: o professor único de cada turma vira REGENTE; registros
antigos de chamada recebem `CONFIRMADA_EM = CREATED` e `CONFIRMADA_POR = ID_USUARIO`.
`TB_TURMAS.ID_PROFESSOR` foi mantida como legado e é sincronizada com o regente.

### Alcance por nível (mudou)

Antes, níveis 1 e 2 viam **todas** as unidades. Agora:

- **1** — rede toda;
- **2 (diretor)** — apenas as unidades do seu `TB_ACESSO`;
- **3 (professor)** — apenas as turmas em que é membro ativo da equipe.

Novas funções: `ch_admin`, `ch_diretor`, `ch_professor`, `ch_require_admin`,
`ch_turmas_ids`, `ch_filtro_turma`, `ch_unidades_opcoes`, `ch_turma_professores`,
`ch_turma_equipe_texto`, `ch_na_equipe`, `ch_pode_retificar`, `ch_iniciais`.
Os selects de unidade das telas de cadastro passaram a usar `ch_unidades_opcoes()`,
então o diretor não vê mais a rede inteira.

### Chamada

`salvar_processa.php` deixou de fazer upsert: só insere. Quem já tem registro no
dia é ignorado com aviso. `retificar.php` é o único caminho de correção e exige
motivo. `historico.php` mostra a linha do tempo das retificações.

### Layout

`css/escola.css` (novo), carregado por último no `inc/tema.php`. Paleta alegre,
cantos arredondados, avatares com iniciais, cartões por aluno, painel da turma com
placar, linha do tempo do histórico, responsivo, impressão e
`prefers-reduced-motion`. A geometria do menu original foi preservada.

### Validação desta rodada

- `php -l` em **96 arquivos**: zero erros.
- `sqlfluff --dialect tsql`: **8.715 nós, 0 trechos não interpretados**.
- **82/82** testes funcionais (acesso por nível, equipe, confirmação única,
  retificação, histórico, layout) e **12/12** testes de restrição do banco.
- Varredura de **37 telas**: nenhum warning/notice de runtime.
- Os testes rodaram sobre um adaptador `sqlsrv`→SQLite em `/tmp`, **fora do
  repositório**. Não foram executados em SQL Server real nem em PHP 8.5.12.

---

## 13. Correções de layout: rodapé, menus "Opções" e DataTables

Rodada seguinte ao layout escolar, para corrigir dois recortes visuais
relatados em uso: o fundo do menu lateral "cortando" no encontro com o rodapé e
o menu **Opções** das listagens sendo cortado.

### Causa raiz: Bootstrap carregado três vezes

O achado principal não era CSS. Cada página carregava **três cópias do
JavaScript do Bootstrap** e **duas folhas de estilo**:

| Onde | O que carregava |
|---|---|
| `inc/tema.php` (head) | bundle **5.1.3** |
| `inc/tema.php` (head) | CSS **5.2.0** + CSS "twitter-bootstrap" **5.1.3** |
| `inc/footer.php` | `bootstrap.min.js` **5.2.0** |
| `inc/footer.php` | `bootstrap.bundle.min.js` **5.2.0** |

Cada cópia registra o seu próprio *data-api* de dropdown e sobrescreve o
posicionamento calculado pela anterior. O menu abria, recebia uma posição e era
reposicionado em seguida pela outra instância — daí o recorte, e daí também
nenhuma configuração de Popper ter efeito.

**Correção:** uma única cópia — o bundle **5.2.0**, com `integrity`, no
`inc/tema.php`. As duas tags de script do rodapé e o CSS 5.1.3 foram removidos.
Com uma só instância, o Popper do Bootstrap volta a funcionar sozinho: o menu
"vira" para cima quando não cabe abaixo, sem nenhum código de posicionamento
manual no projeto.

### Menu lateral e rodapé

`css/escola.css`: o canto inferior direito do `.sidebar` passou a raio **0**, de
modo que a lateral encoste no rodapé sem deixar uma faixa do fundo aparecendo
entre os dois. A geometria original (60px, 220px no hover, rodapé fixo) foi
mantida.

### DataTables

- **Cabeçalho legível.** O degradê foi movido de `thead` para `thead > tr` e o
  `--bs-table-bg` de cada `th` foi neutralizado. Antes, o `.table-light` pintava
  cada célula de cinza-claro por cima do degradê, resultando em texto branco
  sobre fundo branco.
- **Invólucro da tabela.** `.table-responsive*` fica com `overflow:visible` em
  telas largas e só ganha `overflow-x:auto` abaixo de 992px. Um invólucro com
  rolagem recorta o menu que abre para fora dele.
- **Coluna Opções sempre visível.** `responsivePriority:1` + `className:'all'` na
  última coluna. O Responsive escondia justamente a coluna das ações primeiro,
  e a engrenagem desaparecia em 1024px.
- **Tradução embutida.** O JSON de pt-BR da CDN falhava silenciosamente e a
  interface ficava em inglês. As traduções agora estão em `js/tabela.js`.
- **Listas vazias.** As linhas `<tr><td colspan="N">` de exemplo foram retiradas
  dos sete `index.php`: elas quebravam o DataTables (`_DT_CellIndex` indefinido).
  A mensagem vem de `data-vazio` na `<table>`, aplicada a
  `language.emptyTable`/`zeroRecords`.
- **Menu alto.** `.dropdown-menu` ganhou altura máxima de `calc(100vh - 24px)`
  com rolagem interna, para o menu de 5 itens de `alunos` nunca sair da janela.

### Outros dois bugs encontrados no caminho

- `js/mask.js` era carregado no `<head>` de todas as páginas, mas só algumas têm
  o campo `#cpfcnpj`: três erros de `null` por página. Agora tem guarda de
  existência e espera o `DOMContentLoaded`. **Bug pré-existente.**
- A validação em JavaScript da chamada exigia marcar **todos** os alunos
  pendentes, enquanto o servidor já aceitava confirmação parcial. O JavaScript
  foi alinhado ao servidor: confirma quem foi marcado, avisa quantos continuam
  pendentes e continua bloqueando falta justificada sem justificativa.

### Validação desta rodada

- `php -l` em **96 arquivos**: zero erros. `node --check` nos dois JS.
- **228/228** verificações de layout: 8 listagens + 6 outras telas × 4 larguras
  (1024, 1366, 1440, 1920), medindo transbordo, alcance do botão Opções e menu
  inteiro dentro da janela.
- **25/25** verificações de geometria (junção lateral/rodapé, cabeçalho, menu,
  tradução, erros de JavaScript).
- **21/21** testes funcionais do que depende do Bootstrap depois da remoção das
  cópias: modal de retificação, gravação no histórico, bloqueio sem motivo,
  confirmação de presença e formulário de equipe.
- Mesma ressalva da seção 12: testes sobre o adaptador `sqlsrv`→SQLite em
  `/tmp`, **não** em SQL Server real nem em PHP 8.5.12.

---

## 14. Menu lateral, cadastro de usuários e permissões

Três relatos de uso: o ícone selecionado desalinhado da faixa amarela com o menu
recolhido, os rótulos de seção aparecendo cortados, e o cadastro de usuários sem
abrir.

### Cadastro de usuários não abria

`usuarios/processa/add_processa.php` era uma **cópia do processador de edição**:

```php
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");   // "Novo Usuário" não tem id -> volta
    exit;
}
```

Como `add.php` não tem `id`, o botão **Novo Usuário** sempre voltava para a
listagem. E no POST o arquivo fazia `UPDATE`, não `INSERT`, e nunca gravava o
`NIVEL`. **Bug pré-existente**, presente desde o `Initial commit` (`83b7f43`).

Reescrito como cadastro: `INSERT` com nome, senha, nível, CPF, e-mail, situação,
data e autor. A validação ficou em `usuarios/processa/comum.php`, separada do
`add_processa.php` de propósito — aquele executa o INSERT quando incluído, então
o `edit_processa.php` não pode reaproveitá-lo para pegar só a função de validar,
senão uma edição também cadastraria outro usuário.

O CPF passou a ser gravado **com máscara** (`000.000.000-00`), porque o login
compara o texto digitado com o que está no banco; sem isso o usuário cadastrado
não conseguiria entrar. A conferência de duplicidade compara apenas os dígitos,
para que `12345678901` e `123.456.789-01` não passem por pessoas diferentes.

### Permissões que estavam só na aparência

- `usuarios/edit.php` e `acesso.php` checavam o nível **depois** de incluir o
  cabeçalho. Como a página já tinha começado a sair, o `header()` não tinha
  efeito e a tela era renderizada — e o processador, que roda antes, não checava
  nada: um professor conseguia alterar usuários por URL direta. A trava
  (`ch_require_admin()`) foi para o processador de todas as telas do módulo.
- Nenhum formulário do módulo conferia o token CSRF, embora o `add.php` gerasse
  um. Agora todos conferem, via `ch_csrf_ok()`.
- `delete_acesso_processa.php` redirecionava para `$_SERVER['HTTP_REFERER']`,
  valor que vem do navegador. O destino agora é sempre uma tela do sistema.
- O login não olhava o campo `ATIVO`: usuário desativado continuava entrando.
  `valida.php` passou a barrar, com `ISNULL(ATIVO,1)` para não trancar registros
  antigos que tenham o campo nulo.
- Duas proteções novas contra ficar sem acesso: o administrador não altera o
  próprio nível nem se desativa, e o último administrador ativo não pode ser
  rebaixado.

### Menu lateral: dois menus que divergiam

`inc/header.php` tinha o menu completo e `inc/header2.php` um reduzido, escolhido
por nível (`ch_gestor() ? HEADER_TEMPLATE : HEADER_TEMPLATE2`). Funcionava, mas
os dois arquivos mantinham cópias separadas da mesma lista, e o reduzido mostrava
ao **diretor** o link *Usuários*, que é só do administrador — o link levava a um
bloqueio.

Os dois cabeçalhos passaram a incluir **`inc/sidebar.php`**, fonte única, com os
links filtrados por nível e conferidos contra a trava real de cada módulo:

| Item | Quem vê | Trava do módulo |
|---|---|---|
| Dashboard, Chamada, Observações, Relatório Anual | todos | filtro por turma/unidade |
| Alunos, Feriados | todos (leitura) | cadastro/edição exigem gestão |
| Turmas, Períodos, Unidades | gestão (1 e 2) | `ch_require_gestor()` |
| Usuários | administrador (1) | `ch_require_admin()` |

Medido seguindo todos os links do menu com quatro usuários: antes o diretor tinha
**1 link barrado**; agora **0 em todos os níveis**, e o professor mantém os mesmos
7 itens de antes.

### Ícone ativo desalinhado

Com o menu recolhido a faixa amarela tem 44 px (60 − 8 − 8 de margem) e padding
de 12 px de cada lado, sobrando 20 px de miolo — mas `style.css` dá
`min-width: 24px` ao `<i>`. O ícone não cabia e avançava 4 px, deixando o centro
em 32 px contra 30 px da faixa: **2 px de desvio**.

Recolhido, o link agora centraliza o conteúdo, o `<i>` tem largura fixa de 22 px
sem `min-width`, e o `<span>` sai do fluxo (só transparentar não resolvia: o texto
invisível continuava empurrando o ícone). Desvio medido: **0 px**. O
`translateX(3px)` do hover também foi desligado no estado recolhido, porque
tirava o ícone do centro outra vez.

### Rótulos cortados e o botão ☰

- `CHAMADA` e `CADASTROS` só tinham `opacity` nos `<span>` dos links; os `<p>`
  ficavam visíveis e cortados. Agora têm altura zero quando recolhido e voltam
  ao expandir. O `<p>` vazio do fim do menu (só comentários PHP e um `<br>`) foi
  removido.
- O botão ☰ adicionava a classe `.active`, mas essa classe só tinha regra dentro
  do `@media` de celular e o botão era `display:none` no desktop: no computador
  ele não existia. Agora aparece no pé do trilho — em cima cobriria o brasão — e
  fixa o menu aberto, lembrando a escolha entre telas via `localStorage`. O
  `padding-bottom` do menu reserva o espaço para nenhum item ficar embaixo dele.
- O item ativo era marcado comparando `link.href.includes(location.pathname)`, o
  que só funcionava na listagem: em `usuarios/add.php` nada ficava aceso. Agora
  compara o **módulo**, então o menu continua marcado nas telas de cadastro.
- `var projeto1Prefixo = <?php echo BASEURL; ?>;` saía sem aspas e virava uma
  expressão regular em JavaScript, não uma string. Corrigido nos dois cabeçalhos.

### Validação desta rodada

- `php -l` em **98 arquivos**: zero erros. `node --check` nos JS.
- **44/44** verificações das correções (menu por nível, cadastro, validações,
  login do usuário novo, travas, alinhamento, rótulos, ☰).
- **16/16** das proteções novas (último administrador, usuário inativo, CSRF,
  edição gravando sem duplicar).
- **216/216** da varredura de layout em 4 larguras — as correções da seção 13
  continuam de pé.
- **22/22** de regressão: gaveta do menu no celular (390 px), chamada,
  retificação, equipe, acesso a unidades e **27 telas sem aviso de PHP**.
- Mesma ressalva: adaptador `sqlsrv`→SQLite em `/tmp`, **não** SQL Server real
  nem PHP 8.5.12.

### Pendência anotada, não corrigida

`js/accessibility.js` é servido como arquivo estático, mas tem
`<?php echo ... ?>` dentro de strings. É JavaScript válido — o texto do PHP é
que fica literal —, então os nomes dos cookies de zoom e contraste saem iguais
para todos os usuários, em vez de um por usuário. Não afeta as três correções
desta rodada; fica registrado para uma passada futura.

---

## 15. Turma que não salvava, tela de usuários cortada e ajustes do menu

Quatro relatos: "Não foi possível salvar a turma", a tela de cadastro de usuários
cortada, o texto do menu aberto aparecendo só com o mouse em cima, e o botão ☰
visível em telas grandes.

### "Não foi possível salvar a turma" — defeito de SQL Server

`turmas/processa/add_processa.php` gravava a turma e buscava o ID gerado em uma
**segunda chamada**:

```php
$ok = ch_exec('INSERT INTO TB_TURMAS (...) VALUES (?, ...)');
$novoId = $ok ? (int)ch_val('SELECT SCOPE_IDENTITY() AS ID') : 0;
```

`SCOPE_IDENTITY()` vale apenas dentro do **mesmo lote** do INSERT. Cada
`sqlsrv_query` é um lote novo — e com parâmetros o driver ainda usa
`sp_executesql`, que é outro escopo. Na segunda chamada a função devolve **NULL**,
`$novoId` ficava 0, a transação era desfeita e a tela mostrava a mensagem genérica.
Ou seja: **no SQL Server o cadastro de turma nunca funcionou**, desde que a equipe
por turma foi introduzida (`c8fea72`).

Correção: o próprio INSERT devolve o ID, com `OUTPUT INSERTED.ID`, lido pela nova
função `ch_insert_id()`. Nada de segunda consulta.

```php
$novoId = ch_insert_id(
    'INSERT INTO TB_TURMAS (...) OUTPUT INSERTED.ID VALUES (?, ...)', [...]);
```

**Por que os testes não pegaram:** o adaptador `sqlsrv`→SQLite traduzia
`SCOPE_IDENTITY()` para `last_insert_rowid()`, que é da sessão e não do lote —
funcionava no teste e falhava no servidor. O adaptador passou a traduzir para
`NULL`, como o SQL Server faz, e o defeito foi reproduzido antes de corrigir.
O arquivo do adaptador agora avisa, no cabeçalho, que o SQLite não reproduz o
comportamento de escopo do servidor.

A mensagem de erro também deixou de ser cega: quando quem está logado é
administrador, o texto traz o detalhe devolvido pelo banco (`ch_erro_sql()`), para
não ser preciso adivinhar na próxima vez.

### DBCC CHECKIDENT sem permissão

`usuarios/processa/acesso_processa.php` chamava `adjustIdentitySeed()` depois de
cada acesso gravado: `SELECT MAX(id)` seguido de `DBCC CHECKIDENT ... RESEED`.
Dois problemas:

1. `DBCC CHECKIDENT` exige permissão de ALTER na tabela. O usuário da aplicação
   recebe só `db_datareader`, `db_datawriter` e `EXECUTE`
   (`sql_inicial/CRIAR_LOGIN_APLICACAO.sql`), então a chamada falhava — e o código
   dava `die()` em qualquer erro que não fosse o aviso `01000`, quebrando a
   liberação de unidades para o diretor.
2. Resemear IDENTITY numa tabela em uso pode gerar chave duplicada com duas
   pessoas gravando ao mesmo tempo.

A função foi removida. Coluna IDENTITY se incrementa sozinha; não há o que
ajustar depois de um INSERT comum.

### Tela de cadastro de usuários cortada

`css/escola.css` define `.card-escola` com `overflow: hidden` e **sem padding** —
o recuo interno mora em `.card-escola-corpo`. As telas novas de usuário usavam só
`.card-escola`, então a linha de campos do Bootstrap (`.row`, com margens
`0 -12px`) avançava 12 px para fora do cartão em cada lado e era **cortada** pelo
`overflow: hidden`.

Correção: o conteúdo das duas telas foi para dentro de `.card-escola-corpo`, como
já fazia `turmas/formulario.php`. Medido em 1024, 1280, 1440 e 1920 px: nenhum
campo, botão ou aviso fora do cartão.

### Texto do menu aberto e o botão ☰

- O `style.css` deixa o texto dos links e o nome da prefeitura com `opacity: 0` e
  só zera isso em `.sidebar:hover`. Com o menu fixado pela classe `.active`, o
  texto continuava invisível até passar o mouse. Agora `.sidebar:hover` **e**
  `.sidebar.active` mostram o texto.
- O botão ☰ tinha sido liberado no desktop na rodada anterior. Voltou a ser
  **só de tela pequena** (até 768 px), onde o menu é uma gaveta que começa
  fechada; no computador o menu abre passando o mouse. O estado "fixado" guardado
  no navegador só é restaurado no celular e é desfeito ao ampliar a janela — sem
  isso, um menu fixado no celular ficaria aberto para sempre no desktop, sem
  botão para fechar.

### Validação desta rodada

- `php -l` em **98 arquivos**: zero erros. `node --check` nos JS.
- **32/32** verificações dirigidas aos quatro relatos: cadastrar turma com equipe
  de dois professores, trocar o regente, conferir o regente único e o histórico
  preservado, nenhuma tela de usuário cortada em 4 larguras, ☰ oculto em
  1024/1440/1920 e visível em 768/390, texto visível com a gaveta aberta.
- **169/169** da regressão geral: layout em 4 larguras, chamada e retificação,
  equipe, cadastro e travas de usuários, nenhum link de menu barrado em 4 níveis
  e **27 telas sem aviso de PHP**.
- O ambiente de teste passou a ficar em `/home/user/.harness`, **fora do
  repositório**, para não ser perdido a cada reinício do sandbox.
- Ressalva de sempre: adaptador `sqlsrv`→SQLite, **não** SQL Server real nem
  PHP 8.5.12. Justamente por isso o defeito do `SCOPE_IDENTITY` escapou das
  rodadas anteriores — a correção foi verificada com o adaptador reproduzindo o
  comportamento do servidor, mas a confirmação final é cadastrar uma turma no
  seu servidor.

---

## 16. Scripts para popular e limpar o banco

Pedido: um script para popular o banco. Foram criados dois, mais um guia.

| Arquivo | Função |
|---|---|
| `sql_inicial/POPULAR_DEMONSTRACAO.sql` | Insere dados fictícios completos |
| `sql_inicial/LIMPAR_DEMONSTRACAO.sql` | Remove só esses dados |
| `sql_inicial/LEIA-ME.md` | Ordem de execução dos 5 scripts e o que esperar |

### Como o script foi construído

**Tudo em uma transação**, com `SET XACT_ABORT ON` e `TRY/CATCH`: se qualquer
passo falhar, nada é gravado. Os parâmetros (unidades, turmas por unidade, alunos
por turma, meses de histórico) ficam num bloco `CONFIGURAÇÃO` no começo.

**IDs capturados com `OUTPUT INSERTED.ID`**, nunca com `SELECT SCOPE_IDENTITY()`
em outra chamada — exatamente o defeito corrigido na seção 15. Aqui o `OUTPUT`
vai direto para a tabela de marcação: `OUTPUT 'TB_X', INSERTED.ID INTO
dbo.TB_DEMO_IDS`, set-based, sem laço e sem segunda consulta.

**Marcação para poder desfazer.** Cada registro criado é anotado em
`dbo.TB_DEMO_IDS (TABELA, REGISTRO_ID)`. A limpeza lê essa lista e apaga na ordem
das chaves estrangeiras, depois derruba a própria tabela de marcação. Assim o
script não precisa marcar nome de aluno nem de turma com prefixo feio, e ainda
dá para saber exatamente o que é demonstração.

**Nada de dado real é sobrescrito.** Unidades não são criadas: o script usa as que
a instalação já cadastrou, preferindo CMEI/CEMEIF/EMEIF. Períodos, turmas, alunos,
CPF e feriados só são inseridos com `WHERE NOT EXISTS`. Rodar duas vezes não
duplica: a trava no começo detecta a marcação e sai.

**Faixas reservadas** para não colidir com dado verdadeiro: CPF dos usuários de
demonstração em `900.000.0xx-xx` (dígitos verificadores inválidos de propósito) e
matrícula dos alunos em `<ano>9xxxx`.

### Cuidados de T-SQL que valeram a pena

- **`ABS(CHECKSUM(x) % N)` e não `ABS(CHECKSUM(x)) % N`.** O `CHECKSUM` pode
  devolver −2147483648, cujo `ABS` estoura o `int`. O sorteio de idade, telefone e
  status usa a primeira forma.
- **Dia da semana sem depender de idioma.** `DATEPART(WEEKDAY, ...)` muda com
  `SET DATEFIRST` e com o idioma da sessão. O calendário usa
  `DATEDIFF(DAY, '19000101', data) % 7 < 5`: 01/01/1900 foi segunda, então o
  resultado é 0=seg … 6=dom em qualquer configuração.
- **Variáveis de tabela declaradas todas no topo**, fora dos `IF`. Em T-SQL o
  escopo é o lote, mas se o `DECLARE` não executar a variável não existe — e o
  bloco de observações, que roda sempre, usa a tabela de alunos populada dentro
  do `IF` do histórico.
- **A tabela de marcação é criada em lote separado**, antes do lote dos `INSERT`,
  para já existir quando o `OUTPUT ... INTO` for compilado.
- **Páscoa pelo algoritmo de Meeus/Jones/Butcher**, para Carnaval, Sexta-feira
  Santa e Corpus Christi saírem certos em qualquer ano. Conferido: 2025-04-20,
  2026-04-05, 2027-03-28.
- **Regente único.** Se a turma aproveitada já tiver regente ativo, o nosso entra
  como `AUXILIAR`, respeitando o índice filtrado `UX_TURMAPROF_REGENTE`. O
  `ID_PROFESSOR` legado é atualizado a partir do regente que ficou de fato.

### A limpeza e o dado real

A limpeza remove também o que o usuário lançou **dentro das turmas de
demonstração** durante os testes, porque esses registros dependem das turmas que
serão apagadas. Antes de apagar qualquer coisa ela verifica se algum usuário de
demonstração lançou chamada, observação ou relatório em aluno de turma **real**;
se sim, **para e lista**, em vez de apagar dado real ou quebrar a integridade.
Também nunca apaga usuário de nível 1, e só apaga período criado pelo script se
nenhuma turma o estiver usando.

### Um defeito que o teste de tela pegou

A primeira versão datava as observações em `hoje − 17, 34, 51 e 68 dias`. A tela
de Observações abre filtrando o **mês corrente** (`date('Y-m-01')` até hoje), então
em novembro todas caíam fora do filtro e a tela abria vazia — parecendo defeito do
sistema. As observações passaram a ser **mensais**, a mais recente no dia 1º do
mês corrente, garantindo que a tela abra com conteúdo em qualquer dia do mês.

### Validação

- **sqlfluff tsql**: os dois scripts interpretados por inteiro, **0 nós não
  interpretados**.
- **57/57** verificações do conjunto de dados. O SQL Server não roda neste
  ambiente (Debian 13, 1,9 GB de RAM, abaixo do mínimo), então a lógica de geração
  foi portada para SQLite com **as mesmas CHECK, UNIQUE e FK do esquema real** —
  inclusive as que faltavam no esquema de teste e foram acrescentadas
  (`CK_ALUNOS_NASC`, `CK_FERIADOS_HORA`, os dois índices únicos de feriado e
  `CK_RETIF_STATUS`). O teste confere integridade referencial, regente único,
  idade dentro da faixa da turma em 31/03, matrícula e CPF únicos, nenhum fim de
  semana ou feriado integral com chamada, feriado parcial **com** chamada, hoje em
  aberto, toda falta justificada com justificativa, e que a limpeza **preserva**
  turma, aluno, chamada, observação, feriado e período reais criados de propósito
  no meio dos dados de demonstração.
- **21/21** verificações de tela com o banco populado: login de professor e de
  diretor de demonstração, alcance de cada nível, painel de chamada de hoje em
  aberto, dia anterior confirmado, observações listadas, relatório anual com
  frequência, feriados com o parcial identificado, admin vendo os 28 usuários.
- **169/169** da regressão geral do sistema e `php -l` em 98 arquivos, zero erros.
- Ressalva: a **execução** do T-SQL no SQL Server não foi testada aqui — o que se
  validou foi a sintaxe (sqlfluff) e o conjunto de dados que o script produz
  (porta em SQLite com as restrições reais). O `CHECKSUM` foi substituído por
  `crc32` no teste: os valores sorteados diferem, as faixas e regras não.

---

## 17. Dois erros de T-SQL que só apareceram no servidor — e o SQL Server de teste

O usuário executou o `POPULAR_DEMONSTRACAO.sql` e recebeu:

```
[42000] É necessário declarar a variável escalar "@g". (137)
... "@i", "@h", "@mes", "@pascoa"
```

Erro de **compilação**: o lote inteiro nem chegou a executar, então nada foi
gravado. Três defeitos foram corrigidos nesta rodada, todos invisíveis ao
sqlfluff (que valida sintaxe, não semântica) e à porta em SQLite.

### 1. Variável usada no próprio DECLARE que a declara

```sql
DECLARE @g int = (@b - @f + 1) / 3, @h int = (19 * @a + @b - @d - @g + 15) % 30;
```

No T-SQL a variável só entra em escopo **depois que o comando DECLARE inteiro
termina**. Agrupar várias num só comando é permitido, mas um inicializador não
pode depender de outro do mesmo comando. O `@g` "não existia" para o `@h`, o `@i`
e o `@k` não existiam para o `@l`, e o resto caiu em cascata (`@h`, `@mes`,
`@pascoa`). Corrigido: **uma variável por DECLARE** no algoritmo da Páscoa.

### 2. PRINT não aceita subconsulta

```sql
PRINT 'Turmas: ' + CAST((SELECT COUNT(*) FROM @turmas) AS varchar(10));
```

`PRINT` exige expressão escalar — subconsulta dá o erro 1046. A contagem passou a
ir primeiro para uma variável.

### 3. Coluna inexistente na variável de tabela

Na lista de bloqueios da limpeza, `@usuarios` guarda só o `id`, mas o SELECT
pedia `u.NOME`. Faltava juntar `dbo.TB_USUARIOS`. Corrigido nos quatro ramos do
`UNION ALL`, e a data passou por `CONVERT(varchar(10), ..., 23)` para o `UNION`
não misturar tipos.

### O SQL Server de teste — a fraqueza das rodadas anteriores foi resolvida

Consegui instalar **SQL Server 2022 real** neste ambiente (Debian 13, 1982 MB de
RAM, abaixo do mínimo de 2000 — o motor sobe com `MSSQL_MEMORY_LIMIT_MB=1024`),
com o **driver `sqlsrv` de verdade** e o **ODBC Driver 17**, o mesmo do servidor de
produção. O passo a passo está em `/home/user/.harness/COMO-TESTAR-SQLSERVER.md`,
fora do repositório.

Detalhes que custaram tempo: o `sqlservr` pede OpenLDAP 2.5 e o Debian 13 traz a
2.6 (as duas bibliotecas ficam em `/opt/mssql-libs`, usadas só por ele via
`LD_LIBRARY_PATH`, e fora da home porque o usuário `mssql` não lê uma home 0700);
e o Driver **18** não serve, porque liga criptografia por padrão e o
`inc/database.php` não envia `TrustServerCertificate`.

Com isso, os cinco scripts foram **executados** e o sistema foi exercitado no
banco real:

| Verificação | Resultado |
|---|---|
| `INSTALACAO_COMPLETA.sql` | 25 lotes, 0 erros |
| `POPULAR_DEMONSTRACAO.sql` | 5 lotes, 0 erros |
| `CRIAR_LOGIN_APLICACAO.sql` | 12 lotes, 0 erros |
| `DIAGNOSTICO_LOGIN.sql` | 8 lotes, 0 erros |
| `LIMPAR_DEMONSTRACAO.sql` | 0 erros, nos 3 cenários |
| `verifica_real.py` — dados gerados | **44/44** |
| `testa_limpeza_real.py` — limpeza e trava | **27/27** |
| `testa_sistema_real.py` — sistema pelo `sqlsrv` | **26/26** |
| `verifica_escopo.py` — escopo de variáveis | 0 problemas |

O teste do sistema cobre justamente os dois defeitos das seções 15 e 16: cadastrar
turma com equipe (**o `SCOPE_IDENTITY`**) e liberar unidade para o diretor
(**o `DBCC CHECKIDENT`**). Ambos gravaram corretamente no servidor real. Também
passaram: trocar regente mantendo um só, lançar e confirmar chamada, retificar com
motivo e histórico, observação, relatório anual, feriado parcial e CSV com BOM.

Os três testes recriam o banco do zero a cada execução, então são repetíveis e
independentes da ordem.

### Verificador de escopo

`/home/user/.harness/verifica_escopo.py` foi escrito para pegar essa classe de
erro sem precisar do servidor: divide o arquivo em lotes no `GO`, acompanha os
`DECLARE` em ordem e acusa variável usada no inicializador do próprio comando,
variável nunca declarada e variável usada em lote diferente. Foi conferido contra
o trecho quebrado original: reproduz exatamente os `@g`, `@i` e `@k` do relato.

### Achado operacional

**Recriar ou restaurar o banco desfaz o mapeamento do login.** O `CREATE LOGIN` é
do servidor; o `CREATE USER` é do banco. Depois de um `DROP DATABASE` o
`user_chamada` existe no servidor e não no banco, e o sistema mostra "Erro na
conexão com SQL Server". É preciso rodar `CRIAR_LOGIN_APLICACAO.sql` de novo.
Isso está anotado no `sql_inicial/LEIA-ME.md`, junto com a ressalva do Driver 18.

---

## 18. A impressão do relatório saía em folha em branco

### A causa

`inc/header.php` e `inc/header2.php` abriam o corpo assim:

```php
<body onload='document.form1.senha.focus()' class="no-print">
```

E `inc/tema.php` tem:

```css
@media print { .no-print { display: none !important; } }
```

Ou seja: **na impressão, o `<body>` inteiro era escondido**. Não saía nada — nem o
relatório, nem qualquer outra tela. Confirmado com Playwright: o PDF de
`relatorios/ver.php` tinha **1 página e 0 caracteres**, e o `body` calculado em
`media=print` dava `display: none`.

Isso também derrubava o botão **Imprimir** das tabelas (`js/tabela.js`).

A classe foi removida dos dois cabeçalhos. Ela continua existindo para os elementos
que realmente não devem sair no papel (menu, botões, rodapé).

### O resto da folha

Mesmo com o corpo visível, o relatório não sairia legível. Corrigido em
`relatorios/ver.php`:

| Problema | Correção |
|---|---|
| Parecer e encaminhamentos só existiam dentro de `<textarea>` — no papel saíam cortados em 8 e 4 linhas | Blocos só-de-impressão com o texto completo |
| Lista de faltas presa em `max-height:180px; overflow:auto` — imprimia só as primeiras linhas | Altura liberada na impressão |
| `.card.h-100` mantém altura fixa e **cortava** o conteúdo mais longo | `height:auto` na impressão |
| Selo "Falta"/"Justificada" é texto branco sobre fundo colorido; sem "gráficos de fundo" ligado, sumia | Texto preto com contorno |
| Cabeçalho de tabela é texto branco sobre gradiente azul; virava branco no branco | Fundo branco, texto preto, régua embaixo |
| Cartão partido entre duas páginas | `break-inside:avoid`, liberado só nos blocos longos |
| Sem tamanho de página definido | `@page { size: A4 portrait; margin: 14mm }` |
| Sem timbre nem identificação de quem emitiu | Cabeçalho com brasão e rodapé com data, usuário e matrícula |
| Campo de edição do professor aparecia no papel | Formulário escondido na impressão |
| Sem espaço para assinatura | Duas linhas ao final |

**Quebra de página conforme o tamanho.** Bloco curto tem de sair inteiro; bloco que
pode passar de uma página precisa poder continuar, senão o navegador corta na borda.
Então `.cartao-longo` (`break-inside:auto`) só é aplicado quando o conteúdo é mesmo
longo: mais de 8 faltas, mais de 4 observações, ou parecer + encaminhamentos acima de
1.200 caracteres. Com isso a 1ª página fecha em um bloco completo em vez de começar a
lista de faltas com uma linha solta no pé da folha.

Na tela, o relatório ganhou uma coisa: a lista de faltas virou um cartão próprio,
com cabeçalho **Data / Situação / Justificativa**. Antes era uma lista solta dentro
do cartão de frequência, sem título de coluna.

### Como foi verificado

`.harness/verifica_impressao.py` — **37/37**. Usa um parecer longo de propósito, para
pegar corte de texto, e confere o PDF de verdade com `pypdf`:

- impressão **sem** gráficos de fundo (o padrão de todo navegador) e **com**;
- parecer e encaminhamentos inteiros no PDF, não só o começo;
- todas as faltas e observações presentes;
- menu, botões, formulário e rodapé do sistema ausentes;
- timbre e assinaturas presentes;
- relatório **sem parecer preenchido** também imprime, com o aviso;
- impressão aberta pelo **professor** (`header2.php`), não só pelo administrador;
- a tela continua igual, com o campo de edição e o botão Imprimir;
- nenhum erro de JavaScript.

Antes: **1 página, 0 caracteres**. Depois: **3 páginas, 4.722 caracteres**, com o
conteúdo completo.

Regressão: `testa_sistema_real.py` **26/26**, `regressao.py` **169/169**, `php -l`
**98 arquivos, 0 erros**.

### Armadilha de teste que vale registrar

`page.pdf()` do Playwright respeita um `emulate_media('screen')` anterior e passa a
gerar o PDF com o CSS de **tela**. Os primeiros PDFs de teste saíam com a interface
impressa por causa disso — não era defeito do sistema. É preciso chamar
`emulate_media(media='print')` antes de gerar.

---

## 19. Transferência de aluno entre escolas e turmas

### O que havia antes

A turma do aluno era um `<select>` no formulário de edição. Trocar de escola era
possível, mas era uma troca cega:

- não ficava registrado **quem** mudou, **quando**, nem **por quê**;
- não havia **data de efeito**: como `TB_CHAMADA`, `TB_OBSERVACOES` e
  `TB_RELATORIOS_ANUAIS` referenciam `ID_ALUNO`, e a chamada do dia listava por
  `TB_ALUNOS.ID_TURMA`, o aluno **sumia da turma antiga e aparecia na nova
  inclusive em datas anteriores à troca**. O histórico mudava de escola junto;
- não havia como a escola de destino participar da decisão;
- o módulo de alunos estava **sem CSRF** em todos os formulários;
- `alunos/processa/toggle_processa.php` não conferia alcance: qualquer gestor
  inativava aluno de outra escola mandando o ID no POST.

### O fluxo novo, em duas etapas

Como a rede trabalha:

| Etapa | Quem | Onde | O que decide |
|---|---|---|---|
| 1 | Gestão da escola **de origem** | *Alunos → o aluno → Transferir* | a **escola de destino** e o **motivo** (obrigatório) |
| 2 | Gestão da escola **de destino** | *Transferências → Aguardando* | a **turma** e a **data de início** |

Entre as duas etapas a transferência fica `PENDENTE` e **o aluno não sai do
lugar**: a chamada segue normal na turma atual. Qualquer uma das pontas pode
cancelar a pendência, com motivo obrigatório.

`SITUACAO` é `PENDENTE`, `CONCLUIDA` ou `CANCELADA`, e três `CHECK` no banco
impedem estado coerente pela metade: concluída sem turma/data/autor, pendente com
turma já escolhida, cancelada sem motivo. Um índice único **filtrado**
(`UX_TRANSFER_PENDENTE`) permite no máximo uma pendência por aluno, sem limitar o
histórico de concluídas.

### A data de início, e por que ela resolve o histórico

Até a **véspera** do início o aluno pertence à turma de origem; daquele dia em
diante, à de destino. Quem aplica isso é a `VW_ALUNO_VINCULO`, que reconstrói os
períodos a partir das transferências concluídas:

```
ID_ALUNO | ID_TURMA | DATA_INICIO | DATA_FIM
    2    |    1     |    NULL     | 2026-09-30     <- desde a matrícula
    2    |    5     | 2026-10-01  | NULL           <- até hoje
```

`DATA_INICIO NULL` = desde o início; `DATA_FIM NULL` = período em aberto. Aluno
sem transferência tem uma linha só, com a turma atual.

A chamada do dia, o CSV e o histórico de retificações passaram a usar
`tr_filtro_na_data()` em vez de `a.ID_TURMA = ?`. Assim um registro antigo nunca
muda de escola, e a turma antiga não consegue lançar chamada de quem já saiu.

**Não há segunda fonte de verdade.** A turma atual continua em
`TB_ALUNOS.ID_TURMA`; o resto é derivado. Nada de chamada, observação ou parecer é
movido ou apagado — as três tabelas referenciam `ID_ALUNO`. O relatório anual
segue sendo do ano inteiro e ganha o quadro *Escolas e turmas em <ano>*.

### Leitura e escrita são permissões diferentes

Este foi o ponto delicado. Para a escola de origem continuar consultando quem foi
seu aluno, `ch_pode_aluno()` / `ch_exige_aluno()` passaram a aceitar quem já teve
vínculo com o aluno. Isso **amplia leitura** — e por isso vieram junto:

- `ch_pode_aluno_atual()` e `ch_exige_aluno_atual()`, que exigem a turma **atual**;
- usadas em editar aluno, lançar/editar observação, gravar parecer e inativar.

Sem essa separação, a escola de origem conseguia **editar** o aluno depois de
transferido — foi exatamente o que o teste acusou (71/72) antes da correção.

### Outros pontos

- A turma saiu do formulário de edição (virou texto só de leitura, com link para
  a transferência) e o `UPDATE` de edição **nem toca** em `ID_TURMA`.
- A conferência de lotação só roda quando o aluno está **entrando** na turma.
  Antes ela também rodava na edição, e impedia corrigir o nome de um aluno numa
  turma já cheia.
- CSRF em `add`, `edit` e `toggle` de alunos, e em todos os formulários novos.
- `ch_afetadas()` devolve quantas linhas o `UPDATE` atingiu. Aceitar e cancelar
  usam `WHERE ID = ? AND SITUACAO = 'PENDENTE'`: se outra pessoa tratou a
  transferência um instante antes, nenhuma linha muda e a tela avisa em vez de
  gravar por cima. `ch_exec()` devolve `true` mesmo com zero linhas.
- Aviso de pendências no **menu** (contador) e no **painel inicial** da escola de
  destino, que é onde a pessoa cai depois do login.
- O select de escolas de destino **não** aplica o alcance do usuário de
  propósito: o diretor precisa indicar uma escola que não é a dele. Só a escola
  atual do aluno é excluída.
- `POPULAR_DEMONSTRACAO.sql` criava turmas com lotação acima da capacidade
  (18 alunos em turma de 15). Além de mostrar "18/15" na tela, deixava o banco de
  demonstração sem vaga para testar transferência. A capacidade agora é sempre
  maior que a lotação criada, com 2 vagas de folga.

### Como foi verificado

`.harness/testa_transferencias.py` — **76/76** no SQL Server real, conferindo o
banco depois de cada ação: as duas etapas, aluno parado durante a pendência,
chamada antes/depois da data, CSV, tentativa de lançar chamada na turma antiga,
os seis campos da linha do tempo, relatório com as duas escolas, quem vê e quem
edita depois da saída, cancelamento, permissões de professor e de terceira
escola, e as quatro travas do banco (índice filtrado + três CHECK).

`.harness/telas_transferencia.py` — **25/25**: as sete telas novas em 1440 px e
390 px sem estouro horizontal, nenhum erro de JavaScript, e o PDF do aluno
transferido com o quadro de escolas (3 páginas, 4.228 caracteres).

Regressão: `verifica_real.py` **44/44**, `testa_limpeza_real.py` **27/27**,
`testa_sistema_real.py` **26/26**, `verifica_impressao.py` **37/37**,
`regressao.py` **169/169**, `verifica4.py` **32/32**, `testa_popular.py` **57/57**,
`verifica_telas.py` **21/21**. Escopo de variáveis T-SQL: 0 problemas nos 6
scripts. `sqlfluff`: 0 trechos não interpretados. `php -l`: **111 arquivos, 0
erros**.

### Duas armadilhas de ambiente que custaram tempo

1. **`config.php` do repositório tem a senha vazia**, de propósito. Copiar a árvore
   para o ambiente de teste sem reaplicar a senha derruba o login com **18456** —
   que parece problema de permissão do banco, mas é senha em branco. O refresh
   agora é `atualiza_app_real.sh`, que faz o lint, copia e reaplica a senha.
2. **Aspas simples de T-SQL dentro de string PHP de aspas simples.** O subquery
   `... SITUACAO = 'PENDENTE'` quebrou o arquivo inteiro, e o sintoma na tela era
   "falha de permissão", não erro de sintaxe. O refresh roda `php -l` **antes** de
   copiar, e aborta se algum arquivo estiver quebrado.

---

## 20. Turno do professor, responsável por turno, substituto com prazo e saúde do aluno

Seis pedidos da rede, todos na mesma área do sistema: quem está com a turma, quando,
e o que a criança precisa que a professora saiba.

### O que foi pedido

1. o professor tem período (manhã, tarde e integral);
2. a turma também pode ter período;
3. pode mais de um professor por turma — às vezes um fica responsável de manhã e
   outro à tarde;
4. às vezes o professor de apoio fica o dia todo;
5. colocar opção de substituto;
6. colocar opção de alergias na ficha do aluno.

### As quatro decisões que moldaram a solução

Perguntadas antes de escrever código, porque cada uma muda o desenho do banco:

| Pergunta | Decisão | O que isso implicou |
|----------|---------|---------------------|
| Um responsável por turma ou por turno? | **Por turno** | O índice único passou de `(ID_TURMA)` para `(ID_TURMA, TURNO)`. |
| Substituto é só um papel ou tem prazo? | **Com prazo** | `DATA_INICIO`, `DATA_FIM`, `ID_SUBSTITUIDO` e `MOTIVO`, e o prazo virou porta de entrada. |
| O turno do professor é padrão ou trava? | **Padrão** | Nada impede a professora da manhã de cobrir uma tarde. |
| Saúde é só alergia? | **Completo, com alerta** | Três campos, aviso na chamada e bloco no relatório impresso. |

### Onde o turno mora — e por que em três lugares

```
TB_PERIODOS.TURNO           classifica o horário de funcionamento
TB_USUARIOS.TURNO           turno de trabalho do professor (NULL para a gestão)
TB_TURMA_PROFESSORES.TURNO  turno que ele cobre NAQUELA turma
```

`TURNO` é um valor fechado (`MANHA`/`TARDE`/`INTEGRAL`), **não** é chave
estrangeira de `TB_PERIODOS`. A primeira versão do script de atualização criou
essa FK por engano: turno e período são coisas diferentes — período é cadastrável
e guarda horas; turno é "que parte do dia". Sem separar os dois, "um responsável
pela manhã e outro pela tarde" dependeria do nome que cada escola deu ao horário.

### A regra que o banco não consegue expressar

Um índice único filtrado resolve "dois responsáveis da manhã":

```sql
CREATE UNIQUE INDEX UX_TURMAPROF_RESP_TURNO
    ON dbo.TB_TURMA_PROFESSORES (ID_TURMA, TURNO)
    WHERE PAPEL = 'REGENTE' AND ATIVO = 1;
```

Mas ele **não** resolve `INTEGRAL` ao lado de `MANHA`: são valores diferentes na
coluna, então o índice aceita — e o integral já responde pela manhã. Comparar
turnos que se sobrepõem exige olhar duas linhas ao mesmo tempo, o que uma
`CHECK` de linha não faz. Essa parte ficou em `eq_validar_equipe()`, em
`inc/equipe.php`, por onde toda escrita passa. O comentário do índice no script
avisa disso, para ninguém supor que o banco resolve sozinho.

### O prazo do substituto é a porta de entrada

Em vez de espalhar a conferência de datas pelas telas, `ch_turmas_ids()` e
`ch_na_equipe()` passaram a delegar para `eq_turmas_ids_em()` / `eq_na_equipe_em()`,
que filtram no SQL:

```sql
AND (PAPEL <> 'SUBSTITUTO'
     OR (? >= DATA_INICIO AND ? <= DATA_FIM))
```

Como todo acesso de professor passa por esses dois helpers, o prazo vale de uma
vez para chamada, observações, relatório e retificação — e também para o
`ch_pode_retificar()`, que deriva de `ch_pode_turma()`. Fora do prazo ele não vê a
turma; dentro, tem os poderes de quem cobre. Quem está sendo coberto continua na
equipe: a substituição soma, não troca.

### Duas armadilhas na gravação da equipe

1. **A ordem dos passos importa.** Para trocar o responsável de um turno é preciso
   rebaixar os responsáveis atuais **antes** de promover os novos, senão o índice
   único estoura no meio da transação. `turma_equipe_salvar()` faz isso em três
   passos: desativa quem saiu, rebaixa todos os `REGENTE` ativos, grava a equipe
   nova.
2. **Só `REGENTE` pode ser rebaixado.** A `CK_TURMAPROF_SUBSTITUTO` exige prazo em
   quem é substituto e proíbe prazo em quem não é. Rebaixar um `SUBSTITUTO` para
   `AUXILIAR` sem limpar as datas violaria a constraint. Por isso o passo 2 mexe
   apenas em `REGENTE`, e o passo 3 grava papel, turno e prazo **na mesma
   sentença** — nunca em dois UPDATEs.

### A saúde que desapareceria do papel

O CSS de impressão de `relatorios/ver.php` esconde `.alert` de propósito: alerta
Bootstrap é interface, não conteúdo. Um aviso de saúde feito com `.alert`
apareceria na tela e **sumiria do PDF**. Por isso existe `eq_saude_ficha()`, que
devolve um bloco de conteúdo com classe própria (`.eq-saude-ficha`) e regra de
impressão com moldura preta — legível mesmo com "gráficos de fundo" desligado.
A suíte de telas confere isso com `emulate_media('print')` e medindo a altura do
bloco, não só a presença do HTML.

### Um defeito antigo que esta rodada descobriu

`usuarios/edit.php` **quebrava com HTTP 500 no SQL Server real**:

```
Object of class DateTime could not be converted to string
```

A tela faz `substr((string)$usuario['CREATED'], 0, 10)`, e o `sqlsrv` devolve
objeto `DateTime` para coluna `datetime` — que não aceita cast para string. O
simulador SQLite devolve texto, então o defeito nunca apareceu nos testes de
simulação, e as suítes antigas do servidor real não abriam essa tela. A correção
é a regra que o projeto já usa: converter no `SELECT`
(`CONVERT(varchar(10), CREATED, 23)`), em vez de `SELECT *`.

Foi a única ocorrência do padrão no projeto — uma varredura por casts de string
em colunas de data não achou outra.

### Arquivos

**Novos:** `inc/equipe.php` (turno, papel, validação, acesso com prazo, saúde),
`sql_inicial/ATUALIZAR_EQUIPE_SAUDE.sql`.

**Alterados:** `inc/chamada.php` (4 helpers delegando + `ch_aluno` com saúde),
`turmas/formulario.php` (equipe por linha, reescrito),
`turmas/processa/{salvar,add,edit,index}_processa.php`, `turmas/index.php`,
`usuarios/{add,edit}.php` + `usuarios/processa/{comum,add,edit}_processa.php`,
`periodos/{add,edit,index}.php` + `periodos/processa/{add,edit,index}_processa.php`,
`alunos/formulario.php` + `alunos/{view,index}.php` +
`alunos/processa/{salvar,add,edit,index}_processa.php`,
`chamada/index.php` + `chamada/processa/index_processa.php`,
`relatorios/ver.php`, `css/escola.css`,
`sql_inicial/{INSTALACAO_COMPLETA,POPULAR_DEMONSTRACAO,LEIA-ME}`.

A demonstração passou a mostrar tudo isso sem ninguém cadastrar nada: professores
com turno, uma turma com responsável de manhã e de tarde, apoio integral, um
substituto em andamento e alunos com alergia, restrição e medicação.

### CSRF onde faltava

Os formulários de **turma** e de **período** não tinham token CSRF. Como as duas
telas foram reescritas nesta rodada, o token entrou junto — `ch_csrf_campo()` no
formulário e `ch_csrf_ok()` no processador, no mesmo padrão do resto do sistema.

### Verificação

| Suíte | Resultado |
|-------|-----------|
| `testa_equipe_saude.py` (nova, SQL Server real) | **95/95** |
| `telas_equipe_saude.py` (nova, Playwright + PDF) | **35/35** |
| `regressao.py` | **171/171** |
| `verifica_real.py` | **48/48** |
| `testa_sistema_real.py` | **26/26** |
| `testa_limpeza_real.py` | **27/27** |
| `testa_transferencias.py` | **76/76** |
| `verifica_impressao.py` | **37/37** |
| `verifica4.py` | **33/33** |
| `testa_popular.py` | **57/57** |
| `verifica_telas.py` | **21/21** |
| `php -l` | **112 arquivos, 0 erros** |
| Escopo de variáveis T-SQL / sqlfluff | **0 problemas** |

As suítes antigas que afirmavam "exatamente um regente por turma" foram
atualizadas para a regra nova — não relaxadas: agora verificam **um responsável
por turno**, que `ID_PROFESSOR` é um dos responsáveis, e que quem confirmou a
chamada pertence à equipe (o `JOIN` antigo multiplicaria as linhas com dois
responsáveis e acusaria erro falso).

Rodado em SQL Server 2022 (16.0.4305.5), ODBC Driver 17, `sqlsrv` 5.13.3,
PHP 8.4.27. **Não** foi testado em PHP 8.5.12.

---

## 21. "Call to undefined function" depois de atualizar — e o verificador de instalação

### O sintoma

Depois de copiar a versão com turno/substituto/saúde para um WAMP que já tinha
o sistema, duas telas morreram:

```
Call to undefined function eq_tem_saude()    in alunos/index.php:55
Call to undefined function eq_papel_rotulo() in turmas/index.php:65
```

### A causa, e por que o erro engana

As funções `eq_*` moram em `inc/equipe.php`, que é carregado por uma linha no
fim do `inc/chamada.php`:

```php
require_once __DIR__ . '/equipe.php';
```

Foram copiadas as telas novas, mas o `inc/chamada.php` ficou na versão antiga.
O arquivo `inc/equipe.php` estava lá, correto, e nunca era incluído. O PHP então
reclama na **tela** que chamou a função, não no **include** que deixou de
carregá-la. Quem lê o erro vai olhar `alunos/index.php`, que está certo.

Reproduzido em laboratório para separar os dois casos:

| Cenário | Erro |
|---|---|
| `inc/chamada.php` antigo + telas novas | `Call to undefined function eq_tem_saude()` |
| `inc/equipe.php` ausente + `chamada.php` novo | `Failed opening required .../inc/equipe.php` |

Os erros são diferentes. O primeiro significa **include atrasado**; o segundo,
**arquivo faltando**. Saber distinguir economiza a investigação inteira.

O `README_CHAMADA.md` contribuiu para o problema: o Passo 2 mandava copiar uma
lista de pastas, não o pacote inteiro. Foi corrigido.

### A correção estrutural: `verifica_instalacao.php`

Página de conferência, só para nível 1, no menu **Verificar instalação**. Ela:

1. **Arquivos** — compara cada arquivo com `inc/manifesto.php` (SHA-256) e
   separa *faltando* de *versão diferente*. Detectar arquivo **atrasado** é o
   que importa aqui: conferir só existência não pegaria esse defeito.
2. **Funções** — carrega o `inc/chamada.php` e testa se cada função esperada
   existe. Também procura, no texto do `chamada.php`, a linha que inclui cada
   módulo: é o teste que aponta direto a causa acima.
3. **Banco** — confere tabelas, colunas e índices por rodada, e diz **qual
   script** rodar (`ATUALIZAR_TRANSFERENCIAS.sql` ou
   `ATUALIZAR_EQUIPE_SAUDE.sql`).

O manifesto é gerado por `php ferramentas/gera_manifesto.php`. Rode antes de
publicar. O `config.php` fica fora da comparação de hash de propósito: é o
arquivo que o usuário edita no servidor.

Três cuidados na construção do verificador, cada um porque a versão ingênua
dava resposta errada:

- **Sessão.** O sistema usa nome de sessão próprio, derivado de `BASEURL`
  (`session_name(str_replace('/', '', BASEURL))`). Um `session_start()` solto
  no verificador criaria *outra* sessão e o login nunca seria reconhecido. Quem
  abre a sessão tem de ser o `config.php`, carregado com `require_once` — o que
  também evita que ele rode de novo quando o `chamada.php` for carregado.
- **`nvarchar(max)` vira stream.** `sys.check_constraints.definition` é
  `nvarchar(max)`, e o `sqlsrv` devolve tipos *max* como recurso de stream, não
  como texto. Sem `CAST(definition AS nvarchar(4000))` a comparação sempre
  falha.
- **`VIEW DEFINITION` não é concedido ao usuário da aplicação.** Sem essa
  permissão o SQL Server mostra a linha da constraint mas devolve
  `definition = NULL`. Isso é o comportamento correto e mais seguro. O
  verificador trata `NULL` como "não legível", não como erro — senão acusaria
  banco desatualizado em toda instalação bem configurada. As colunas e o
  índice, visíveis com permissão de `SELECT`, já provam que a atualização rodou.

### Opcache: por que "copiei e não mudou"

O PHP do WAMP normalmente roda com `opcache.validate_timestamps=On` e
`opcache.revalidate_freq=2`. Depois de sobrescrever um arquivo, o Apache pode
servir a versão antiga por até 2 segundos — e com `validate_timestamps=Off`
(ele comum em produção), para sempre, até reiniciar.

Nos testes isso apareceu como falso negativo: trocou-se o `chamada.php` por a
versão antiga e a tela continuou funcionando, porque veio do cache. **Reinicie
o Apache depois de copiar arquivos**, e só então conclua qualquer coisa.

### Regra

> Atualização é o pacote inteiro mais o script de banco. Copiar só as telas
> deixa o `inc/` antigo e produz `Call to undefined function` numa tela que
> está correta. Depois de copiar, reinicie o Apache e abra
> `verifica_instalacao.php`.

### O segundo relato, e o `diagnostico.php`

O erro voltou com uma terceira tela incluída:

```
Call to undefined function eq_professores_opcoes() in turmas/processa/salvar_processa.php:159
```

Mesma causa. A cadeia é `turmas/add.php` → `add_processa.php` (carrega o
`inc/chamada.php` na linha 4) → `salvar_processa.php` → `turma_opcoes()` →
`eq_professores_opcoes()`, que existe em `inc/equipe.php:445`. Três telas
diferentes, um único arquivo atrasado.

Dois aprendizados viraram código:

**1. A lista de funções conferidas não pode ser escrita à mão.** O
`verifica_instalacao.php` conferia 8 funções `eq_*` de um total de 31 — não
incluía justamente a `eq_professores_opcoes`. Uma lista fixa envelhece junto com
o código. Agora a lista é **extraída do próprio arquivo** com
`preg_match_all('/^function\s+([a-z0-9_]+)\s*\(/mi', ...)`, e toda função nova
entra na conferência sozinha. São 31 `eq_*` e 18 `tr_*`.

**2. O verificador dependia do sistema que ele deveria diagnosticar.** Ele
precisa do `config.php`, da sessão e do `inc/manifesto.php`. Numa cópia parcial
esses podem ser justamente os arquivos velhos. Por isso foi criado o
`diagnostico.php`: arquivo único, sem nenhuma dependência, que **lê o texto**
dos arquivos em vez de carregá-los — assim diagnostica um arquivo que, se fosse
executado, mataria a página com erro fatal.

O que ele faz e por quê:

- Procura a linha de `require`/`include` do módulo dentro do `chamada.php` com
  regex, não o nome solto (que apareceria em comentário e daria falso positivo).
- Mostra **tamanho e data** de cada arquivo de `inc/`. Se as telas são de um dia
  e os includes de outro, a cópia parcial fica visível sem interpretação.
- Mostra o estado do opcache e oferece `opcache_reset()`, porque "copiei e o erro
  continuou" tem o cache como causa frequente.
- Restringe a resposta a localhost e rede privada, e manda apagar o arquivo
  depois. Não lê banco nem mostra credencial.

Testado nos três cenários, com o PHP **sem banco de dados nenhum**:
`chamada.php` antigo → aponta o `chamada.php`; `equipe.php` ausente → aponta a
ausência; `equipe.php` antigo → lista as 31 funções que faltam.

### O caso real: arquivo certo no disco, bytecode velho na memória

Depois de dois rounds mandando recopiar arquivos, o `.bat` de verificação
mostrou que a pasta do usuário estava **correta**. A prova foi aritmética: cada
arquivo tinha o tamanho do repositório **mais um byte por linha**.

| Arquivo | LF (repo) | +CRLF | no PC |
|---|---|---|---|
| `inc/chamada.php` | 23.076 | 23.767 | 23.767 |
| `inc/equipe.php` | 22.126 | 22.702 | 22.702 |
| `inc/transferencias.php` | 12.211 | 12.513 | 12.513 |

Os 12 arquivos de `inc/` batiam. Ele tinha dado `git clone` no Windows, e o
`core.autocrlf` converteu LF em CRLF. A linha 691 tinha o `require`, e as três
funções estavam nas linhas 108, 445 e 502 — idênticas ao repositório.

**Dois defeitos meus apareceram aí:**

1. **O verificador comparava hash de bytes brutos.** Num clone Windows isso
   acusa a instalação inteira. Medido: hash bruto batia **0 de 135** arquivos;
   com CRLF e BOM normalizados, **135 de 135**. Corrigido no
   `gera_manifesto.php` e no `verifica_instalacao.php` (`vi_hash()`). O
   manifesto guarda o hash do texto com LF.
2. **A sonda chamava `opcache_get_status(false)`.** Com `false` o PHP **não
   devolve** a lista de scripts, então ela imprimia "0 em cache" como se fosse
   informação. Agora usa `true`.

#### A assinatura que identifica bytecode velho

A sonda de runtime, rodando no Apache do usuário (PHP **8.5.12**,
`apache2handler`), devolveu:

```
CARREGOU inc/chamada.php? SIM
eq_*  -> 12 funções AUSENTES
tr_*  -> EXISTEM
arquivos incluídos: chamada.php, database.php, transferencias.php
                    (equipe.php NÃO entrou)
```

Os dois `require` estão em sequência, linhas 685 e 691, no mesmo nível. O 685
executou e o 691 não — sem erro nenhum. Como o arquivo em disco **tem** a linha
691, o que rodou foi uma versão compilada anterior, em que o `require` do
`equipe.php` ainda não existia. Ou seja: **opcache servindo bytecode antigo**.

O `opcache` também informou **memória usada negativa** (−111,8 MB), o que é
anômalo e reforça que ele não estava confiável naquele PHP.

#### O experimento que separa as duas causas

A sonda v2 faz duas etapas:

- **A** — carrega o `inc/chamada.php` como as telas fazem e conta as funções.
- **B** — se faltou alguma, carrega o `inc/equipe.php` **na mão**.

| Resultado de B | Conclusão |
|---|---|
| funções aparecem | o `equipe.php` está bom; o `chamada.php` rodou bytecode velho → **opcache** |
| continuam ausentes | o arquivo em disco não define as funções |
| lança erro | o erro é mostrado com arquivo e linha |
| vaza texto para a página | o arquivo perdeu a tag `<?php` ou foi salvo em outra codificação |

A captura de saída com `ob_start()` em volta do `require` é o que pega o último
caso: sem a tag de abertura o PHP trata o arquivo como HTML, imprime o conteúdo
e não define função nenhuma — **sem dar erro**.

#### A correção

`opcache_invalidate($arquivo, true)` **antes** do include força a recompilação;
é o que o botão "Forçar recompilação" faz, e por isso ele já serve de teste. Se
o problema voltar, `opcache.enable=0` no `php.ini` **do Apache**
(`C:\wamp64\bin\apache\apache2.4.65\bin\php.ini`, não o do CLI) e reiniciar.

Para desenvolvimento o certo é `opcache.validate_timestamps=1` com
`opcache.revalidate_freq=0`: confere o arquivo a cada acesso. Com
`revalidate_freq=2` ainda há uma janela de 2 segundos, e com
`validate_timestamps=0` o arquivo novo nunca é visto.

> **Regra:** antes de concluir "arquivo antigo", compare tamanho **e** número de
> linhas — CRLF do Windows muda o tamanho sem mudar o código. E antes de
> concluir "código errado", confira o que foi **realmente incluído**
> (`get_included_files()`), não o que está no disco.

---

## 22. O aviso que o servidor de teste escondia

Depois de resolver o opcache, o usuário recebeu:

```
Warning: Undefined array key "turno" in usuarios/add.php
```

### A causa

`usuarios/processa/add_processa.php` inicializava:

```php
$d = ['nome' => '', 'email' => '', 'cpf' => '', 'nivel' => 0, 'ativo' => 1];
```

e a view lê `$d['turno']` para marcar a opção selecionada. No primeiro GET,
antes de qualquer POST, a chave não existe. O `edit_processa.php` **tinha**
`'turno' => $usuario['TURNO'] ?? ''`; o `add` ficou sem quando a rodada do turno
foi implementada. Assimetria clássica entre cadastro e edição.

Correção: acrescentar `'turno' => ''` ao inicializador.

### Por que nenhuma suite pegou — o defeito era do ambiente de teste

O `bootstrap_real.php` não definia `error_reporting` nem `display_errors`, então
valia o `php.ini`, que vem com **`display_errors=Off`**. As suites procuravam
`Warning:` no HTML e não achavam nada, porque o PHP não imprimia. Elas
"passavam" enquanto o usuário via o aviso na tela.

Agora o bootstrap liga:

```php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
```

> Aviso em tela é defeito a corrigir, não ruído a esconder. Um teste que roda
> com `display_errors=Off` não está verificando ausência de avisos — está
> verificando ausência de *impressão* de avisos, que é outra coisa.

### A varredura nova

`.harness/varre_avisos.py` abre **37 telas × 3 níveis = 111 páginas** com `E_ALL`
ligado e procura `Fatal error`, `Parse error`, `Warning`, `Notice`, `Deprecated`
e `Uncaught`. Cobre os três níveis porque o alcance muda o que a tela consulta:
o administrador vê todas as unidades, o diretor só as liberadas, o professor só
as turmas da equipe — e cada caminho pode deixar uma variável sem inicializar.

Resultado depois das correções: **111/111 limpas**.

### Fragmento de tela não é página

A varredura achou também `Call to undefined function ch_h()` em
`transferencias/quadro_vinculos.php`. Não era defeito de uso: esse arquivo é um
**fragmento**, incluído por `alunos/view.php`, `relatorios/ver.php` e
`transferencias/ver.php`. Aberto direto na URL, não tem `ch_h()` nem as
variáveis que espera.

O `quadro_historico.php` tinha o mesmo problema e a varredura **não** pegou:
sem dados ele imprime "Este aluno nunca foi transferido" e não chega a chamar
`ch_h()`. Ausência de erro num caminho não prova que os outros estão limpos.

Os nove fragmentos ganharam a mesma guarda:

```php
if (!defined('ABSPATH')) {
	http_response_code(404);
	exit;
}
```

`ABSPATH` só existe depois do `config.php`, que toda página real carrega — é um
sinal confiável de "vim de dentro". São eles: `alunos/formulario.php`,
`turmas/formulario.php`, `feriados/formulario.php`, `observacoes/formulario.php`,
`unidades/modal.php`, `unidades/modal_ativa.php`, `usuarios/modal_acesso.php`,
`transferencias/quadro_vinculos.php`, `transferencias/quadro_historico.php`.

Três deles começam em HTML puro, sem `<?php` na primeira linha; nesses a guarda
entra num bloco PHP próprio, antes do HTML.

### Regra

> Ao acrescentar um campo a um formulário, inicialize a chave nos **dois**
> processadores, o de cadastro e o de edição. E rode a varredura com `E_ALL`
> visível antes de entregar: `python3 .harness/varre_avisos.py`.
