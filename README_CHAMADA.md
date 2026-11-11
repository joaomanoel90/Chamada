# Módulo Chamada de Alunos — SupriADM

Módulo de chamada, diário escolar, observações e relatório anual de
desenvolvimento da Prefeitura Municipal de Angatuba.

O repositório era originalmente uma cópia do **SupriADM** (almoxarifado/pedidos).
Os módulos de estoque foram removidos — veja [`LIMPEZA.md`](LIMPEZA.md) — e hoje
o sistema atende à chamada e ao diário escolar.

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

- **Nível 1 (administrador):** rede toda e administração de usuários e
  configuração técnica.
- **Nível 2 (Diretor Escolar):** gestão escolar nas unidades vinculadas em
  `TB_ACESSO`.
- **Nível 3 (professor):** vê apenas as turmas em que está na equipe.
  Lança chamada e notas das disciplinas pelas quais responde nas
  turmas da equipe, registra observações e emite relatórios. Os cadastros de
  períodos, turmas, disciplinas e feriados ficam restritos à gestão; gestão e
  equipe podem consultar notas.
- **Nível 4 (Secretaria de Educação):** gestão escolar em toda a rede, sem
  administração de usuários ou acesso à configuração técnica.
- **Nível 5 (Secretário Escolar):** cadastro e transferência de alunos e
  registro de observações somente nas unidades vinculadas; consulta turmas e
  chamadas sem configurá-las ou lançar/retificar presenças. Só consulta os
  relatórios anuais nas unidades liberadas pelo Diretor; não edita o parecer.

> O Diretor Escolar e o Secretário Escolar precisam de unidades vinculadas em
> **Usuários → Acesso Unidades**. O professor precisa estar na equipe da turma;
> o vínculo em `TB_ACESSO` não concede acesso às turmas.
> Para liberar relatórios ao Secretário, o Diretor usa **Permissões de
> relatórios**; sem autorização por unidade, o acesso fica bloqueado.

---

## 2. Instalação

### Passo 1 — banco de dados

Execute no SQL Server o script único:

```
css/escola.css                   tema visual de educação infantil

sql_inicial/INSTALACAO_COMPLETA.sql
sql_inicial/DIAGNOSTICO_LOGIN.sql
sql_inicial/CRIAR_LOGIN_APLICACAO.sql
```

O script é **idempotente**: pode rodar mais de uma vez sem apagar dados. Cria o
banco `db_chamada`, as tabelas, índices, a view `VIEW_ACESSOS_USUARIOS` e os
dados iniciais (períodos, as 20 unidades da rede e o administrador).

### Passo 2 — arquivos

**Copie o pacote inteiro**, não uma seleção de pastas. A pasta do sistema é a
mesma do `config.php`.

> **Não copie só as telas.** Cada tela nova chama funções que moram em
> `inc/chamada.php`, `inc/transferencias.php` e `inc/equipe.php`. Se as telas
> forem novas e o `inc/` ficar antigo, o PHP morre com
> `Call to undefined function eq_tem_saude()` ou parecido — e o erro aponta a
> tela, não o arquivo que realmente ficou velho.

O jeito seguro de atualizar uma instalação que já existe:

1. Pare o Apache.
2. Renomeie `C:\wamp64\www\Chamada` para `Chamada-antigo`.
3. Crie `Chamada` de novo e extraia o pacote inteiro dentro dela.
4. Traga o `config.php` da pasta antiga (ou refaça `DB_NAME`, `DB_USER`,
   `DB_PASSWORD`, `BASEURL`).
5. Rode os scripts de banco que faltarem.
6. Inicie o Apache e abra o verificador do Passo 5.

O Apache com opcache ligado pode continuar servindo código antigo por alguns
segundos depois da cópia. Reiniciar o Apache elimina a dúvida.

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
6. **Diário de Classe** — classifique a turma, habilite notas somente nas etapas
   escolares, cadastre as disciplinas com seus professores responsáveis e lance
   as notas por bimestre.

### Passo 5 — conferir a instalação

Entre como administrador (nível 1) e abra **Verificar instalação** no menu, ou
vá direto em:

```
http://localhost/Chamada/verifica_instalacao.php
```

A página compara cada arquivo com o `inc/manifesto.php` (SHA-256), testa se as
funções esperadas existem e confere se o banco tem as colunas e índices das
atualizações. Ela diz **qual arquivo está atrasado** e **qual script rodar**.

Rode depois de toda atualização. Se aparecer `Call to undefined function` em
qualquer tela, essa página aponta o culpado em segundos.

O `config.php` não é comparado por hash, porque é o arquivo que você edita no
servidor. Só a presença dele é conferida.

**Se nem o sistema abre**, use o `diagnostico.php`:

```
http://localhost/Chamada/diagnostico.php
```

Ele é um arquivo único que **não depende de mais nada** — nem do `config.php`,
nem do banco, nem do manifesto. Lê o texto dos arquivos em vez de executá-los,
então consegue diagnosticar justamente o arquivo que, se fosse carregado,
derrubaria a página. Mostra o tamanho e a **data** de cada arquivo de `inc/`
(a prova mais direta de qual ficou velho), o estado do opcache com botão para
limpar, e a linha de `require_once` que está faltando. Responde só para a
máquina local e rede interna. **Apague depois de usar.**

Ao publicar uma versão nova, regenere o manifesto antes:

```
php ferramentas/gera_manifesto.php
```

---

## 3. Regras de negócio

### Equipe da turma

Uma turma tem **vários professores** (`TB_TURMA_PROFESSORES`), cada um com
**papel** (`REGENTE`, `AUXILIAR`, `SUBSTITUTO`) e **turno** (`MANHA`, `TARDE`,
`INTEGRAL`).

- **Um responsável por turno.** O índice `UX_TURMAPROF_RESP_TURNO` é único em
  `(ID_TURMA, TURNO)` para `REGENTE` ativo. A sobreposição entre `INTEGRAL` e um
  turno específico não é expressável em índice e é conferida em
  `eq_validar_equipe()` (`inc/equipe.php`).
- **O turno do professor é padrão**, não trava: `TB_USUARIOS.TURNO` preenche a
  linha, e a gestão pode mudar por turma.
- **Substituto tem prazo**: `DATA_INICIO`, `DATA_FIM`, `ID_SUBSTITUIDO` e `MOTIVO`
  são obrigatórios para o papel, e proibidos para os outros
  (`CK_TURMAPROF_SUBSTITUTO`). O prazo é conferido em `ch_turmas_ids()` /
  `ch_na_equipe()`, que delegam para `eq_turmas_ids_em()` / `eq_na_equipe_em()`:
  fora dele o substituto não alcança a turma; dentro, lança chamada e observação
  como quem cobre — **mas a leitura das observações dos outros é separada**
  (ver abaixo). O professor substituído continua na equipe.

Todos os membros ativos e vigentes lançam chamada, observações e retificações.
Remover alguém da equipe desativa o vínculo (`ATIVO = 0`), sem apagar o histórico.
`TB_TURMAS.ID_PROFESSOR` permanece como coluna legada, igual ao responsável que
cobre o turno da turma, para não quebrar consultas antigas.

### Substituto e observações do professor

Por padrão o substituto **não lê** o que os outros professores escreveram. Ele
vê sempre as observações **que ele mesmo registrou**, e continua registrando
observações e lançando chamada normalmente — a restrição é só de leitura.

A gestão libera a leitura na tela da turma (`turmas/edit.php`), na linha do
substituto: **"Pode ler as observações do professor"**.

- **A liberação vale por substituição**, não por professor nem por turma. Cada
  novo vínculo `SUBSTITUTO` nasce bloqueado (`VER_OBSERVACOES = 0`); liberar uma
  turma não libera outra, mesmo para a mesma pessoa.
- **Quem liberou e quando** ficam em `OBS_LIBERADA_POR` / `OBS_LIBERADA_EM`.
  Salvar a turma de novo não reescreve a autoria (o `UPDATE` usa `ISNULL`).
  Desligar zera os dois juntos.
- Duas CHECK garantem a coerência: `CK_TURMAPROF_VEROBS` (a permissão só existe
  em `SUBSTITUTO`) e `CK_TURMAPROF_OBSLIB_JUNTAS` (os dois campos de auditoria
  ou ambos preenchidos, ou ambos nulos). Ao trocar o papel para regente/auxiliar,
  `eq_ler_post()` zera a permissão para não estourar a CHECK.
- **A regra é aplicada em todos os pontos de leitura**, não só no relatório
  anual e nas observações diárias: listagem de observações, ficha do aluno,
  relatório anual (tela e impressão), contador do dia na chamada, contagem da
  lista de relatórios e os dois cartões do painel. Fechar só duas telas deixaria
  o conteúdo visível por outro caminho.
- A permissão é avaliada **na data de hoje**, não na data de cada observação:
  liberada a leitura, o substituto lê o histórico da turma enquanto estiver no
  prazo; bloqueada, volta a ver só as dele.
- Quem é membro fixo da turma em outro papel não é afetado. A gestão escolar
  (níveis 1, 2 e 4) lê tudo dentro do seu escopo; o Secretário Escolar (nível
  5) consulta os registros das unidades vinculadas.

Helpers em `inc/equipe.php`: `eq_obs_turmas_bloqueadas()`, `eq_obs_tem_bloqueio()`,
`eq_obs_filtro()`, `eq_obs_filtro_aluno()`, `eq_obs_aviso_bloqueio()`. O único
caminho de escrita da permissão é o formulário da turma.

Para aluno transferido, `eq_obs_filtro_aluno()` é conservador: se **qualquer**
turma do histórico do aluno estiver bloqueada para o leitor, mostra só as
observações dele. Evita que a transferência vire uma porta de entrada.

No relatório impresso o aviso usa `.aviso-obs-parcial` em vez de `.alert`,
porque a folha de impressão esconde `.alert` — sem isso quem recebesse o papel
não teria como saber que o relatório está parcial.

Migração de banco já existente: `sql_inicial/ATUALIZAR_OBS_SUBSTITUTO.sql`
(idempotente; pode rodar mais de uma vez). Instalação do zero: já incluída em
`INSTALACAO_COMPLETA.sql`, seção 20.

### Saúde do aluno

`TB_ALUNOS.ALERGIAS`, `RESTRICOES_ALIMENTARES` e `MEDICACAO` são texto livre
(até 1000 caracteres cada). `eq_caixa_saude()` monta o aviso da chamada e da
ficha; `eq_saude_ficha()` monta a versão do relatório, que **não** usa `.alert`
porque o CSS de impressão esconde essa classe.

### Confirmação e retificação

- A presença é **confirmada uma vez** por aluno/dia. `salvar_processa.php` só
  faz `INSERT`; se o registro já existe, ele **não sobrescreve** e orienta a usar
  a retificação. Há rechecagem dentro da transação contra gravação simultânea.
- Corrigir exige **retificação** (`retificar.php`), com **motivo obrigatório**.
  O antes/depois vai para `TB_CHAMADA_RETIFICACOES` com autor e data/hora.
- `CONFIRMADA_EM` / `CONFIRMADA_POR` guardam quem confirmou originalmente e não
  mudam nas retificações; `ID_USUARIO` passa a ser quem retificou por último.
- Retificar sem mudança efetiva é recusado.
- Falta justificada exige justificativa tanto na confirmação quanto na
  retificação (validado em PHP e por `CHECK` no banco).

### Alcance por nível

| Nível | Alcance |
|---|---|
| 1 | todas as unidades |
| 2 | unidades vinculadas em `TB_ACESSO` |
| 3 | turmas em que é membro ativo da equipe |

`ch_filtro_turma()` aplica o recorte nas listagens; `ch_pode_turma()` e
`ch_pode_aluno()` barram o acesso direto por URL.

### Chamada

| Código | Situação | Observação |
|---|---|---|
| `P` | Presente | conta como presença |
| `A` | Atraso | conta como presença |
| `F` | Falta | não conta |
| `J` | Falta justificada | **exige justificativa**; não conta |

- Um registro por aluno por dia (índice único `ID_ALUNO + DATA`). Depois de
  confirmado, salvar de novo **não** atualiza: a correção é por retificação.
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
turmas/       index add edit formulario (equipe) + processa/{index,add,edit,salvar,toggle}
alunos/       index add edit view formulario + processa/{index,add,edit,view,salvar,toggle}
feriados/     index add edit formulario + processa/{index,add,edit,salvar,delete}
chamada/      index salvar retificar historico exportar + processa/{index,salvar,retificar,historico}
diario/       index + processa/index_processa
observacoes/  index add edit formulario + processa/{index,add,edit,salvar}
relatorios/   index ver salvar exportar + processa/{index,ver,salvar}

sql_inicial/INSTALACAO_COMPLETA.sql
```

---


## 5. Segurança — pontos herdados do sistema

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

## 6. Limitações desta entrega

- O ambiente de demonstração (`demo/`, adaptador SQLite) foi **removido** do
  repositório. Não há mais como rodar o sistema sem SQL Server.
- O `INSTALACAO_COMPLETA.sql` teve a sintaxe T-SQL validada por parser e a lógica
  do schema (chaves, unicidade, CHECK e índices filtrados) validada por execução
  equivalente. Ainda assim, **execute primeiro em homologação**: o SQL Server
  real não estava disponível no ambiente de desenvolvimento.
- As listas de alunos e turmas usam DataTables (paginação no navegador). Para
  volumes muito grandes, vale migrar para paginação no servidor.
- `usuarios/`, `unidades/` e `conta/` têm referências a arquivos CSS/JS locais que
  não existem no repositório; funcionam pelo CDN do `inc/tema.php`. Ver `LIMPEZA.md`.
