<?php
require_once(dirname(__DIR__, 2) . '/config.php');
require_once(DBAPI);
require_once(ABSPATH . 'inc/chamada.php');
ch_db();

$id = (int)($_GET['id'] ?? 0);
$modoLiberacaoRelatorios = false;

if (ch_diretor() && isset($_GET['relatorios'])) {
    $modoLiberacaoRelatorios = true;
    $usuario = ch_row('SELECT ID, NOME, NIVEL FROM TB_USUARIOS WHERE ID = ?', [$id]);
    if (!$usuario || (int)$usuario['NIVEL'] !== 5) {
        ch_flash('A autorização de relatórios está disponível somente para Secretários Escolares.', 'danger');
        header('Location: ' . ch_url('usuarios/secretarios.php'));
        exit;
    }

    $unidadesDiretor = ch_unidades_ids();
    $unidadesRelatorio = [];
    if ($unidadesDiretor) {
        $ph = implode(',', array_fill(0, count($unidadesDiretor), '?'));
        $unidadesRelatorio = ch_rows(
            "SELECT u.id, u.unidade,
                    ISNULL(p.PERMITIDO, 0) AS PERMITIDO
               FROM TB_ACESSO a
               INNER JOIN TB_UNIDADES u ON u.id = a.id_unidade
               LEFT JOIN TB_SECRETARIO_RELATORIOS p
                      ON p.ID_USUARIO = a.id_usuario AND p.ID_UNIDADE = a.id_unidade
              WHERE a.id_usuario = ? AND ISNULL(a.ativo, 1) = 1
                AND a.id_unidade IN ({$ph})
              ORDER BY u.unidade",
            array_merge([$id], $unidadesDiretor)
        );
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!ch_csrf_ok()) {
            ch_flash('A sessão expirou. Tente novamente.', 'danger');
            header('Location: ' . ch_url('usuarios/acesso.php?id=' . $id . '&relatorios=1'));
            exit;
        }

        $idsPermitidos = array_map(function ($u) { return (int)$u['id']; }, $unidadesRelatorio);
        $permissoesEnviadas = (array)($_POST['permissoes'] ?? []);
        $permissoes = [];
        foreach ($permissoesEnviadas as $idUnidade => $valor) {
            $idUnidade = (int)$idUnidade;
            if (!is_scalar($valor) || !in_array((string)$valor, ['0', '1'], true)
                || !in_array($idUnidade, $idsPermitidos, true)) {
                ch_flash('Uma ou mais permissões estão fora do seu alcance ou são inválidas.', 'danger');
                header('Location: ' . ch_url('usuarios/acesso.php?id=' . $id . '&relatorios=1'));
                exit;
            }
            $permissoes[$idUnidade] = (int)$valor;
        }

        if (!sqlsrv_begin_transaction(ch_db())) {
            error_log('Falha ao iniciar transação para autorização de relatórios: ' . print_r(sqlsrv_errors(), true));
            ch_flash('Não foi possível iniciar a autorização. Tente novamente.', 'danger');
            header('Location: ' . ch_url('usuarios/acesso.php?id=' . $id . '&relatorios=1'));
            exit;
        }
        try {
            foreach ($idsPermitidos as $unidadeId) {
                $permitido = $permissoes[$unidadeId] ?? 0;
                $ok = ch_exec(
                    'IF EXISTS (SELECT 1 FROM TB_SECRETARIO_RELATORIOS WHERE ID_USUARIO = ? AND ID_UNIDADE = ?)
                         UPDATE TB_SECRETARIO_RELATORIOS
                            SET PERMITIDO = ?, ID_DIRETOR = ?, ATUALIZADO_EM = GETDATE()
                          WHERE ID_USUARIO = ? AND ID_UNIDADE = ?
                     ELSE
                         INSERT INTO TB_SECRETARIO_RELATORIOS
                             (ID_USUARIO, ID_UNIDADE, PERMITIDO, ID_DIRETOR, ATUALIZADO_EM)
                         VALUES (?, ?, ?, ?, GETDATE())',
                    [$id, $unidadeId, $permitido, ch_uid(), $id, $unidadeId,
                     $id, $unidadeId, $permitido, ch_uid()]
                );
                if (!$ok) {
                    throw new Exception('Falha ao salvar autorização para a unidade ' . $unidadeId . ': ' . ch_erro_sql());
                }
            }
            if (!sqlsrv_commit(ch_db())) {
                throw new Exception('Falha ao confirmar autorização: ' . ch_erro_sql());
            }
            ch_flash('Permissões de leitura de relatórios atualizadas.');
        } catch (Exception $e) {
            sqlsrv_rollback(ch_db());
            error_log('Autorização de relatórios para usuário ' . $id . ': ' . $e->getMessage());
            ch_flash('Não foi possível atualizar as permissões de relatórios.', 'danger');
        }
        header('Location: ' . ch_url('usuarios/acesso.php?id=' . $id . '&relatorios=1'));
        exit;
    }
    return;
}

if (ch_diretor() && isset($_GET['observacoes'])) {
    $modoLiberacaoObservacoes = true;
    $usuario = ch_row('SELECT ID, NOME, NIVEL FROM TB_USUARIOS WHERE ID = ?', [$id]);
    if (!$usuario || (int)$usuario['NIVEL'] !== 5) {
        ch_flash('A autorização de observações está disponível somente para Secretários Escolares.', 'danger');
        header('Location: ' . ch_url('usuarios/secretarios.php?observacoes=1'));
        exit;
    }

    $unidadesDiretor = ch_unidades_ids();
    $unidadesObservacoes = [];
    if ($unidadesDiretor) {
        $ph = implode(',', array_fill(0, count($unidadesDiretor), '?'));
        $unidadesObservacoes = ch_rows(
            "SELECT u.id, u.unidade,
                    ISNULL(p.PERMITIDO, 0) AS PERMITIDO
               FROM TB_ACESSO a
               INNER JOIN TB_UNIDADES u ON u.id = a.id_unidade
               LEFT JOIN TB_SECRETARIO_OBSERVACOES p
                      ON p.ID_USUARIO = a.id_usuario AND p.ID_UNIDADE = a.id_unidade
              WHERE a.id_usuario = ? AND ISNULL(a.ativo, 1) = 1
                AND a.id_unidade IN ({$ph})
              ORDER BY u.unidade",
            array_merge([$id], $unidadesDiretor)
        );
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!ch_csrf_ok()) {
            ch_flash('A sessão expirou. Tente novamente.', 'danger');
            header('Location: ' . ch_url('usuarios/acesso.php?id=' . $id . '&observacoes=1'));
            exit;
        }

        $idsPermitidos = array_map(function ($u) { return (int)$u['id']; }, $unidadesObservacoes);
        $permissoesEnviadas = (array)($_POST['permissoes'] ?? []);
        $permissoes = [];
        foreach ($permissoesEnviadas as $idUnidade => $valor) {
            $idUnidade = (int)$idUnidade;
            if (!is_scalar($valor) || !in_array((string)$valor, ['0', '1', '2'], true)) {
                ch_flash('Uma ou mais permissões são inválidas.', 'danger');
                header('Location: ' . ch_url('usuarios/acesso.php?id=' . $id . '&observacoes=1'));
                exit;
            }
            $valor = (int)$valor;
            if (!in_array($idUnidade, $idsPermitidos, true) || !in_array($valor, [0, 1, 2], true)) {
                ch_flash('Uma ou mais permissões estão fora do seu alcance ou são inválidas.', 'danger');
                header('Location: ' . ch_url('usuarios/acesso.php?id=' . $id . '&observacoes=1'));
                exit;
            }
            $permissoes[$idUnidade] = $valor;
        }
        if (!sqlsrv_begin_transaction(ch_db())) {
            error_log('Falha ao iniciar transação para autorização de observações: ' . print_r(sqlsrv_errors(), true));
            ch_flash('Não foi possível iniciar a autorização. Tente novamente.', 'danger');
            header('Location: ' . ch_url('usuarios/acesso.php?id=' . $id . '&observacoes=1'));
            exit;
        }
        try {
            foreach ($idsPermitidos as $unidadeId) {
                $permitido = $permissoes[$unidadeId] ?? 0;
                $ok = ch_exec(
                    'IF EXISTS (SELECT 1 FROM TB_SECRETARIO_OBSERVACOES WHERE ID_USUARIO = ? AND ID_UNIDADE = ?)
                         UPDATE TB_SECRETARIO_OBSERVACOES
                            SET PERMITIDO = ?, ID_DIRETOR = ?, ATUALIZADO_EM = GETDATE()
                          WHERE ID_USUARIO = ? AND ID_UNIDADE = ?
                     ELSE
                         INSERT INTO TB_SECRETARIO_OBSERVACOES
                             (ID_USUARIO, ID_UNIDADE, PERMITIDO, ID_DIRETOR, ATUALIZADO_EM)
                         VALUES (?, ?, ?, ?, GETDATE())',
                    [$id, $unidadeId, $permitido, ch_uid(), $id, $unidadeId,
                     $id, $unidadeId, $permitido, ch_uid()]
                );
                if (!$ok) {
                    throw new Exception('Falha ao salvar autorização para a unidade ' . $unidadeId . ': ' . ch_erro_sql());
                }
            }
            if (!sqlsrv_commit(ch_db())) {
                throw new Exception('Falha ao confirmar autorização: ' . ch_erro_sql());
            }
            ch_flash('Permissões para registrar observações atualizadas.');
        } catch (Exception $e) {
            sqlsrv_rollback(ch_db());
            error_log('Autorização de observações para usuário ' . $id . ': ' . $e->getMessage());
            ch_flash('Não foi possível atualizar as permissões de observações.', 'danger');
        }
        header('Location: ' . ch_url('usuarios/acesso.php?id=' . $id . '&observacoes=1'));
        exit;
    }
    return;
}

$conn = ch_db();

/* REMOVIDO: adjustIdentitySeed(), que rodava SELECT MAX(id) + DBCC CHECKIDENT
   RESEED depois de cada acesso gravado. Dois problemas: (1) DBCC CHECKIDENT
   exige permissão de ALTER na tabela, e o usuário da aplicação tem só
   db_datareader/db_datawriter — a chamada falhava e o código dava die(),
   quebrando a liberação de unidades; (2) resemear IDENTITY numa tabela em uso
   pode gerar chave duplicada com dois usuários gravando ao mesmo tempo.
   Coluna IDENTITY já se incrementa sozinha: não há nada para ajustar. */

if ($id) {
    if (!ch_pode_gerenciar_vinculos_usuario($id)) {
        ch_flash('A Secretaria de Educação só pode gerenciar unidades de Diretores e Secretários Escolares.', 'danger');
        header('Location: ' . (ch_secretaria_educacao()
            ? ch_url('usuarios/vinculos.php')
            : ch_url('index.php')));
        exit;
    }

    // 🔹 USUÁRIO
    $sql_usuario = "SELECT * FROM TB_USUARIOS WHERE ID = ?";
    $stmt_usuario = sqlsrv_query($conn, $sql_usuario, array($id));
    if (!$stmt_usuario) {
        error_log('Falha ao consultar usuário para vínculo: ' . print_r(sqlsrv_errors(), true));
        ch_flash('Não foi possível consultar os dados do usuário.', 'danger');
        header('Location: ' . (ch_secretaria_educacao()
            ? ch_url('usuarios/vinculos.php')
            : ch_url('usuarios/index.php')));
        exit;
    }
    $rows_usuario = sqlsrv_has_rows($stmt_usuario);
    if (!$rows_usuario) {
        ch_flash('Usuário não encontrado.', 'danger');
        header('Location: ' . (ch_secretaria_educacao()
            ? ch_url('usuarios/vinculos.php')
            : ch_url('usuarios/index.php')));
        exit;
    }

    // 🔹 UNIDADES
    $sql_unidades = "SELECT id, unidade FROM TB_UNIDADES";
    $stmt_unidades = sqlsrv_query($conn, $sql_unidades);

    // 🔹 ACESSOS
    $sql_acessos = "SELECT ID_ACESSO, NOME_UNIDADE 
                    FROM VIEW_ACESSOS_USUARIOS 
                    WHERE ID_USUARIO = ? AND ATIVO = 1";

    $stmt_acessos = sqlsrv_query($conn, $sql_acessos, array($id));
    $rows_acessos = sqlsrv_has_rows($stmt_acessos);

    // ============================
    // 🔹 ADICIONAR TODAS
    // ============================
    if (isset($_POST['add_all'])) {
        if (!ch_admin()) {
            ch_flash('A ação de adicionar todas as unidades está disponível somente para o administrador.', 'danger');
            header('Location: ' . ch_url('usuarios/acesso.php?id=' . $id));
            exit;
        }
        if (!ch_csrf_ok()) { header('Location: acesso.php?id=' . $id); exit; }

        $sql_insert_all = "
            INSERT INTO TB_ACESSO (id_usuario, id_unidade, ativo)
            SELECT ?, u.id, 1
            FROM TB_UNIDADES u
            WHERE NOT EXISTS (
                SELECT 1 FROM TB_ACESSO a
                WHERE a.id_usuario = ?
                AND a.id_unidade = u.id
                AND a.ativo = 1
            )
        ";

        $params = array($id, $id);
        $stmt_insert_all = sqlsrv_query($conn, $sql_insert_all, $params);
        if (!$stmt_insert_all) {
            error_log('Falha ao vincular usuário ' . $id . ' a todas as unidades: ' . print_r(sqlsrv_errors(), true));
            ch_flash('Não foi possível vincular o usuário às unidades.', 'danger');
            header('Location: ' . ch_url('usuarios/acesso.php?id=' . $id));
            exit;
        }

        ch_flash('Unidades vinculadas com sucesso.');
        header('Location: ' . ch_url('usuarios/acesso.php?id=' . $id));
        exit;
    }

    // ============================
    // 🔹 ADICIONAR INDIVIDUAL
    // ============================
    if (!empty($_POST['unidade'])) {
        if (!ch_csrf_ok()) { header('Location: acesso.php?id=' . $id); exit; }

        $id_unidade = filter_var($_POST['unidade'], FILTER_VALIDATE_INT);
        if (!$id_unidade || !ch_val('SELECT id FROM TB_UNIDADES WHERE id = ?', [$id_unidade])) {
            ch_flash('Selecione uma unidade válida.', 'danger');
            header('Location: ' . ch_url('usuarios/acesso.php?id=' . $id));
            exit;
        }

        $sql_check = 'SELECT ID, ISNULL(ATIVO, 1) AS ATIVO FROM TB_ACESSO
                      WHERE id_usuario = ? AND id_unidade = ?';

        $params_check = array($id, $id_unidade);
        $stmt_check = sqlsrv_query($conn, $sql_check, $params_check);
        if (!$stmt_check) {
            error_log('Falha ao verificar vínculo de unidade: ' . print_r(sqlsrv_errors(), true));
            ch_flash('Não foi possível verificar o vínculo existente.', 'danger');
            header('Location: ' . ch_url('usuarios/acesso.php?id=' . $id));
            exit;
        }

        $vinculoExistente = sqlsrv_fetch_array($stmt_check, SQLSRV_FETCH_ASSOC);
        if ($vinculoExistente && (int)$vinculoExistente['ATIVO'] === 1) {
            sqlsrv_free_stmt($stmt_check);
            header('Location: ' . ch_url('usuarios/acesso.php?id=' . $id . '&erro=duplicado'));
            exit;
        }

        if ($vinculoExistente) {
            $stmt_insert = sqlsrv_query(
                $conn,
                'UPDATE TB_ACESSO SET ATIVO = 1, DESATIVADO = NULL WHERE ID = ?',
                [(int)$vinculoExistente['ID']]
            );
        } else {
            $stmt_insert = sqlsrv_query(
                $conn,
                'INSERT INTO TB_ACESSO (id_usuario, id_unidade, ativo) VALUES (?, ?, 1)',
                [$id, $id_unidade]
            );
        }
        sqlsrv_free_stmt($stmt_check);
        if (!$stmt_insert) {
            error_log('Falha ao vincular usuário ' . $id . ' à unidade ' . $id_unidade . ': ' . print_r(sqlsrv_errors(), true));
            ch_flash('Não foi possível vincular o usuário à unidade.', 'danger');
            header('Location: ' . ch_url('usuarios/acesso.php?id=' . $id));
            exit;
        }

        ch_flash('Unidade vinculada com sucesso.');
        header('Location: ' . ch_url('usuarios/acesso.php?id=' . $id));
        exit;
    }
} else {
    if (!ch_admin() && !ch_secretaria_educacao()) {
        ch_flash('Acesso restrito à gestão de vínculos.', 'danger');
        header('Location: ' . ch_url('index.php'));
        exit;
    }
    if (!ch_admin()) {
        ch_flash('Selecione um Diretor ou Secretário Escolar para gerenciar as unidades.', 'info');
        header('Location: ' . ch_url('usuarios/vinculos.php'));
        exit;
    }
}
?>