#!/usr/bin/env python3
"""
Bateria de testes do Módulo Chamada de Alunos (ambiente de demonstração).

Uso:
    php demo/seed.php                      # recria a base fictícia
    python3 demo/testes.py [porta]         # padrão 8001

Requer o servidor de demonstração no ar (ver demo/README.md).
"""
import sys, re, urllib.request, urllib.parse, http.cookiejar

PORTA = sys.argv[1] if len(sys.argv) > 1 else '8001'
BASE = f'http://127.0.0.1:{PORTA}/Chamada/'
RESULTADOS = []


def sessao(cpf):
    cj = http.cookiejar.CookieJar()
    op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))
    op.open(BASE + 'valida.php', urllib.parse.urlencode({'cpf': cpf, 'senha': 'demo123'}).encode())
    return op


def get(op, u):
    r = op.open(BASE + u)
    return r.geturl(), r.read().decode('utf-8', 'replace')


def post(op, u, d):
    r = op.open(BASE + u, urllib.parse.urlencode(d).encode())
    return r.geturl(), r.read().decode('utf-8', 'replace')


def chk(nome, cond):
    RESULTADOS.append((nome, bool(cond)))


def primeiro_erro(h):
    m = re.search(r'alert alert-danger"><ul class="mb-0"><li>(.*?)</li>', h, re.S)
    return m.group(1) if m else ''


op = sessao('111.111.111-11')

# ---------------------------------------------------------------- navegação
u, h = get(op, 'index.php')
chk('login da gestão e dashboard', 'Dashboard' in h)
for path, esperado in [
    ('periodos/index.php', 'Períodos'), ('turmas/index.php', 'Turmas'),
    ('alunos/index.php', 'Alunos'), ('feriados/index.php', 'Feriados'),
    ('chamada/index.php', 'Chamada de Alunos'), ('observacoes/index.php', 'Observações'),
    ('relatorios/index.php', 'Relatório Anual'),
]:
    u, h = get(op, path)
    chk(f'tela {path}', esperado in h and 'Fatal error' not in h and 'Warning:' not in h)

# ---------------------------------------------------------------- chamada
u, h = get(op, 'chamada/index.php?turma_id=1&data=2026-11-05')
ids = sorted(set(re.findall(r'name="status\[(\d+)\]"', h)), key=int)
body = {'turma_id': '1', 'data': '2026-11-05'}
for i in ids:
    body[f'status[{i}]'] = 'P'
    body[f'justificativa[{i}]'] = ''
body[f'status[{ids[0]}]'] = 'A'
body[f'justificativa[{ids[0]}]'] = 'chegou após a roda de conversa'
u, h = post(op, 'chamada/salvar.php', body)
chk('salvar chamada', 'Chamada salva' in h)
chk('chamada marcada como lançada', 'Chamada já lançada' in h)

b2 = dict(body)
b2[f'status[{ids[1]}]'] = 'J'
b2[f'justificativa[{ids[1]}]'] = ''
u, h = post(op, 'chamada/salvar.php', b2)
chk('falta justificada exige justificativa', 'exige justificativa' in h)

u, h = get(op, 'chamada/index.php?turma_id=1&data=2026-11-02')
chk('feriado integral bloqueia chamada', 'Chamada bloqueada' in h and 'Finados' in h)
u, h = get(op, 'chamada/index.php?turma_id=1&data=2026-10-15')
chk('feriado parcial libera a Manhã', 'Chamada bloqueada' not in h)
u, h = get(op, 'chamada/index.php?turma_id=3&data=2026-10-15')
chk('feriado parcial bloqueia a Tarde', 'Chamada bloqueada' in h)
u, h = get(op, 'chamada/index.php?turma_id=1&data=2026-12-20')
chk('data futura bloqueada', 'data futura' in h)

# ---------------------------------------------------------------- cadastros
u, h = post(op, 'periodos/add.php', {'nome': 'Noturno Teste', 'hora_inicio': '18:00', 'hora_fim': '22:00'})
chk('criar período', 'Período cadastrado' in h)

u, h = post(op, 'turmas/add.php', {'id_unidade': '1', 'nome': 'Turma Teste', 'ano_letivo': '2026',
                                   'id_periodo': '1', 'id_professor': '2', 'capacidade': '10'})
chk('criar turma', 'Turma cadastrada' in h)
u, h = get(op, 'turmas/index.php')
tid = re.search(r'edit\.php\?id=(\d+)', h[h.find('Turma Teste'):]).group(1)

u, h = post(op, 'alunos/add.php', {'id_turma': tid, 'matricula': 'MAT-TESTE', 'nome': 'Aluno de Teste',
                                   'nascimento': '2023-03-10', 'data_matricula': '2026-02-02',
                                   'responsavel': 'Responsável Teste', 'telefone': '(15) 99999-0000', 'email': ''})
chk('criar aluno', 'Aluno cadastrado' in h)
u, h = get(op, 'alunos/index.php?busca=MAT-TESTE')
aid = re.search(r'view\.php\?id=(\d+)', h).group(1)
u, h = get(op, f'alunos/view.php?id={aid}')
chk('ficha do aluno', 'Dados do aluno' in h)
u, h = post(op, 'alunos/add.php', {'id_turma': tid, 'matricula': 'MAT-TESTE', 'nome': 'Outro',
                                   'nascimento': '2023-01-01', 'data_matricula': '2026-02-02',
                                   'responsavel': 'X', 'telefone': '1', 'email': ''})
chk('bloqueia matrícula duplicada', 'Já existe um aluno com essa matrícula' in h)

u, h = post(op, 'feriados/add.php', {'data': '2026-12-30', 'descricao': 'Feriado Teste', 'tipo': 'INTEGRAL', 'id_unidade': '0'})
chk('criar feriado integral', 'Feriado cadastrado' in h)
u, h = post(op, 'feriados/add.php', {'data': '2026-12-31', 'descricao': 'Parcial Teste', 'tipo': 'PARCIAL',
                                     'hora_inicio': '12:00', 'hora_fim': '18:00', 'id_unidade': '0'})
chk('criar feriado parcial', 'Feriado cadastrado' in h)
u, h = post(op, 'feriados/add.php', {'data': '2026-11-02', 'descricao': 'Duplicado', 'tipo': 'INTEGRAL', 'id_unidade': '0'})
chk('bloqueia feriado duplicado', 'Já existe um feriado' in primeiro_erro(u and h))
u, h = post(op, 'feriados/add.php', {'data': '2026-11-04', 'descricao': 'Sobre chamada', 'tipo': 'INTEGRAL', 'id_unidade': '0'})
chk('bloqueia feriado sobre chamada existente', 'Já existem' in primeiro_erro(h))

# ---------------------------------------------------------------- observações
u, h = post(op, 'observacoes/add.php', {'aluno_id': '1', 'data': '2026-11-05',
                                        'categoria': 'Linguagem e comunicação',
                                        'texto': 'Teste: ampliou o vocabulário na roda de conversa.'})
chk('criar observação', 'Observação registrada' in h)

# ---------------------------------------------------------------- relatório
u, h = get(op, 'relatorios/ver.php?aluno_id=1&ano=2026')
chk('relatório anual abre', 'Relatório Anual 2026' in h)
chk('frequência mês a mês', 'Frequência mês a mês' in h)
chk('observações no relatório', 'Observações do desenvolvimento' in h)
u, h = post(op, 'relatorios/salvar.php', {'aluno_id': '1', 'ano': '2026',
                                          'parecer': 'Apresentou avanços consistentes ao longo do ano.',
                                          'encaminhamentos': 'Manter estímulo à leitura em casa.'})
chk('salvar parecer', 'Parecer do relatório anual salvo' in h)
chk('parecer persistiu', 'avanços consistentes' in h)

# ---------------------------------------------------------------- exportação
c = op.open(BASE + 'chamada/exportar.php?turma_id=1&data=2026-11-05').read()
chk('CSV da chamada (BOM + ;)', c.startswith(b'\xef\xbb\xbf') and b'MAT-' in c)
c = op.open(BASE + 'relatorios/exportar.php?aluno_id=1&ano=2026').read()
chk('CSV do relatório', b'Fevereiro' in c)

# ---------------------------------------------------------------- permissões
prof = sessao('222.222.222-22')
u, h = get(prof, 'chamada/index.php?turma_id=1&data=2026-11-05')
chk('professor acessa a própria turma', 'Chamada de Alunos' in h)
u, h = get(prof, 'chamada/index.php?turma_id=4&data=2026-11-05')
chk('professor bloqueado em turma de outra unidade', 'não tem acesso' in h)
u, h = get(prof, 'periodos/index.php')
chk('professor redirecionado de tela da gestão', u.endswith('/Chamada/index.php'))
u, h = get(prof, 'relatorios/ver.php?aluno_id=1&ano=2026')
chk('professor vê relatório da sua unidade', 'Relatório Anual 2026' in h)

# ---------------------------------------------------------------- resultado
print()
for nome, res in RESULTADOS:
    print(('  OK    ' if res else ' FALHA  ') + nome)
falhas = sum(1 for _, r in RESULTADOS if not r)
print(f'\n{len(RESULTADOS) - falhas}/{len(RESULTADOS)} verificações passaram.')
sys.exit(1 if falhas else 0)
