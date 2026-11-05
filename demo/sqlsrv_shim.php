<?php
/* ============================================================================
   ADAPTADOR sqlsrv -> PDO/SQLite — SOMENTE DEMONSTRAÇÃO, NÃO USAR EM PRODUÇÃO
   Carregado por demo/bootstrap.php via require_once.
   ============================================================================ */

class DemoStmt
{
    public array $rows = [];
    public int $pos = 0;
    public bool $ok = true;
    public int $affected = 0;
}

function demo_pdo(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $arquivo = __DIR__ . '/demo.sqlite';
        $pdo = new PDO('sqlite:' . $arquivo);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
    return $pdo;
}

/** Traduz o subconjunto de T-SQL usado pelo módulo para SQLite. */
function demo_sql(string $sql): string
{
    $sql = preg_replace('/CONVERT\s*\(\s*varchar\s*\(\s*10\s*\)\s*,\s*(.+?)\s*,\s*23\s*\)/i', 'substr($1,1,10)', $sql);
    $sql = preg_replace('/\bGETDATE\s*\(\s*\)/i', "datetime('now','localtime')", $sql);
    $sql = preg_replace('/\bISNULL\s*\(/i', 'IFNULL(', $sql);
    $sql = preg_replace('/\bYEAR\s*\(([^()]+)\)/i', "CAST(strftime('%Y',\$1) AS INTEGER)", $sql);
    $sql = preg_replace('/\bMONTH\s*\(([^()]+)\)/i', "CAST(strftime('%m',\$1) AS INTEGER)", $sql);

    if (preg_match('/\bSELECT\s+TOP\s+(\d+)\s+/i', $sql, $m)) {
        $sql = preg_replace('/\bSELECT\s+TOP\s+\d+\s+/i', 'SELECT ', $sql, 1);
        $sql = rtrim($sql) . ' LIMIT ' . (int)$m[1];
    }
    return $sql;
}

function sqlsrv_connect($server, $opts = []) { return demo_pdo(); }
function sqlsrv_close($conn) { return true; }

function sqlsrv_query($conn, $sql, $params = [])
{
    $stmt = new DemoStmt();
    try {
        $pdo = demo_pdo();
        $q = $pdo->prepare(demo_sql($sql));
        // sqlsrv preserva o tipo PHP do parâmetro. O PDO, por padrão, manda tudo
        // como texto — e no SQLite "IFNULL(col,0) = '0'" é falso. Amarre por tipo.
        foreach (array_values($params ?? []) as $i => $v) {
            if (is_null($v))      $tipo = PDO::PARAM_NULL;
            elseif (is_int($v))   $tipo = PDO::PARAM_INT;
            elseif (is_bool($v))  { $tipo = PDO::PARAM_INT; $v = (int)$v; }
            else                  $tipo = PDO::PARAM_STR;
            $q->bindValue($i + 1, $v, $tipo);
        }
        $q->execute();
        $comando = strtoupper(strtok(ltrim($sql), " \t\n("));
        if ($comando === 'SELECT' || $comando === 'WITH' || $comando === 'PRAGMA') {
            $stmt->rows = $q->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt->affected = $q->rowCount();
        }
        return $stmt;
    } catch (Throwable $e) {
        $GLOBALS['demo_erro'] = $e->getMessage() . ' | SQL: ' . $sql;
        error_log('DEMO SQL: ' . $GLOBALS['demo_erro']);
        return false;
    }
}

function sqlsrv_fetch_array($stmt, $tipo = SQLSRV_FETCH_ASSOC)
{
    if (!$stmt instanceof DemoStmt) return null;
    if ($stmt->pos >= count($stmt->rows)) return null;
    return $stmt->rows[$stmt->pos++];
}

function sqlsrv_has_rows($stmt) { return $stmt instanceof DemoStmt && count($stmt->rows) > 0; }
function sqlsrv_free_stmt($stmt) { return true; }
function sqlsrv_rows_affected($stmt) { return $stmt instanceof DemoStmt ? $stmt->affected : -1; }

function sqlsrv_begin_transaction($conn) { try { return demo_pdo()->beginTransaction(); } catch (Throwable $e) { return false; } }
function sqlsrv_commit($conn)            { try { return demo_pdo()->commit(); }        catch (Throwable $e) { return false; } }
function sqlsrv_rollback($conn)          { try { return demo_pdo()->rollBack(); }      catch (Throwable $e) { return false; } }

function sqlsrv_errors($x = null)
{
    return isset($GLOBALS['demo_erro']) ? [['message' => $GLOBALS['demo_erro']]] : null;
}
