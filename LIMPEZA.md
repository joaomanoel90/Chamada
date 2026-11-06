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
