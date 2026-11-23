<?php
/**
 * Necessidades de inclusão e marcadores autorizados para o crachá.
 * As categorias nunca são impressas como texto; o crachá recebe somente
 * pictogramas e faixas de cor quando a autorização foi marcada.
 */
function aluno_inclusao_opcoes()
{
    return [
        'FISICA'       => 'Deficiência física ou motora — pictograma de acesso',
        'VISUAL'       => 'Deficiência visual — pictograma visual',
        'AUDITIVA'     => 'Deficiência auditiva — pictograma auditivo',
        'INTELECTUAL'  => 'Deficiência intelectual ou do desenvolvimento — ícone genérico',
        'NEURODIVERGENCIA' => 'Neurodivergência — infinito colorido',
        'TEA'          => 'TEA / autismo — infinito ou símbolo escolhido pela família',
        'TDAH'         => 'TDAH — infinito colorido',
        'OCULTA'       => 'Deficiência oculta / não aparente — girassol',
        'MULTIPLA'     => 'Deficiência múltipla — ícone genérico',
        'OUTRA'        => 'Outra necessidade de inclusão — ícone genérico',
    ];
}

/** Converte o array do formulário ou o JSON do banco em códigos permitidos. */
function aluno_inclusao_codigos($valor)
{
    if (is_string($valor)) {
        $decodificado = json_decode($valor, true);
        $valor = is_array($decodificado) ? $decodificado : [];
    }
    if (!is_array($valor)) {
        return [];
    }

    $permitidos = array_keys(aluno_inclusao_opcoes());
    $selecionados = [];
    foreach ($valor as $codigo) {
        if (is_string($codigo) && in_array($codigo, $permitidos, true)) {
            $selecionados[$codigo] = $codigo;
        }
    }
    return array_values($selecionados);
}

/** Guarda as caixas marcadas como JSON compacto, ou NULL quando estão vazias. */
function aluno_inclusao_json($valor)
{
    $codigos = aluno_inclusao_codigos($valor);
    if (!$codigos) {
        return null;
    }
    $json = json_encode($codigos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return is_string($json) ? $json : null;
}

/**
 * Determina os pictogramas da frente do crachá.
 * O mapa não contém o nome da condição na saída impressa.
 */
function aluno_inclusao_marcadores($aluno)
{
    if (!is_array($aluno) || (int)($aluno['AUTORIZA_MARCADOR_INCLUSAO'] ?? 0) !== 1) {
        return [];
    }

    $codigos = aluno_inclusao_codigos($aluno['DEFICIENCIAS_INCLUSAO'] ?? null);
    if (!$codigos) {
        return [];
    }

    $marcadores = [];
    $adicionar = function ($tipo) use (&$marcadores) {
        $definicoes = [
            'ACESSO' => ['rotulo' => 'Símbolo de acesso', 'classe' => 'acesso'],
            'VISUAL' => ['rotulo' => 'Símbolo de acessibilidade visual', 'classe' => 'visual'],
            'AUDITIVA' => ['rotulo' => 'Símbolo de acessibilidade auditiva', 'classe' => 'auditiva'],
            'GERAL' => ['rotulo' => 'Símbolo de inclusão', 'classe' => 'geral'],
            'GIRASSOL' => ['rotulo' => 'Símbolo de deficiência ou necessidade não aparente', 'classe' => 'girassol'],
            'INFINITO' => ['rotulo' => 'Símbolo de neurodiversidade', 'classe' => 'infinito'],
            'QUEBRA_CABECA' => ['rotulo' => 'Símbolo de autismo escolhido pela família', 'classe' => 'quebra-cabeca'],
        ];
        if (isset($definicoes[$tipo])) {
            $marcadores[$tipo] = $definicoes[$tipo];
        }
    };

    foreach ($codigos as $codigo) {
        switch ($codigo) {
            case 'FISICA':
                $adicionar('ACESSO');
                break;
            case 'VISUAL':
                $adicionar('VISUAL');
                break;
            case 'AUDITIVA':
                $adicionar('AUDITIVA');
                break;
            case 'INTELECTUAL':
            case 'MULTIPLA':
            case 'OUTRA':
                $adicionar('GERAL');
                break;
            case 'OCULTA':
                $adicionar('GIRASSOL');
                break;
            case 'NEURODIVERGENCIA':
                $adicionar('INFINITO');
                break;
            case 'TEA':
                $simboloTea = strtoupper(trim((string)($aluno['SIMBOLO_TEA_INCLUSAO'] ?? 'INFINITO')));
                $adicionar($simboloTea === 'QUEBRA_CABECA' ? 'QUEBRA_CABECA' : 'INFINITO');
                break;
            case 'TDAH':
                $adicionar('INFINITO');
                break;
        }
    }

    return array_values($marcadores);
}

/** SVGs embutidos: não dependem de fontes, CDN ou imagens externas na impressão. */
function aluno_inclusao_svg($classe)
{
    $inicio = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false">';
    $fim = '</svg>';
    switch ($classe) {
        case 'acesso':
            return $inicio . '<circle cx="10" cy="4.2" r="2" fill="currentColor"/><path d="M10 7.3v5.2l4.7 1.4 1.5 3.2h4.1M10.2 9.2h5.2l1.1 3.6M10 12.5l-3.5 3.3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><circle cx="9" cy="17" r="5.1" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M14 17.2h2.1" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>' . $fim;
        case 'visual':
            return $inicio . '<circle cx="11" cy="4.2" r="1.8" fill="currentColor"/><path d="M11 7v5.1m0-3.1-3.5 3.2m3.5-2.6 3.2 2.5M11 12l-3 5.1m3-5.1 3.3 4.3M19.7 7.5 15 21" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>' . $fim;
        case 'auditiva':
            return $inicio . '<path d="M18.8 12.2c0-4.5-2.7-7.5-6.7-7.5-3.6 0-6.1 2.7-6.1 6.4 0 2.5 1.1 3.8 2.5 5.1 1 .9 1.3 1.8 1.5 3 .2 1.2 1 1.9 2.1 1.9 1.5 0 2.5-1.1 2.7-2.8M10 8.6c.6-.8 1.4-1.2 2.4-1.2 1.8 0 3 1.3 3 3.1 0 1.4-.7 2.2-1.6 3-.7.6-1.1 1.2-1.2 2.2M4 4l16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>' . $fim;
        case 'geral':
            return $inicio . '<circle cx="12" cy="4.1" r="2" fill="currentColor"/><path d="M4.2 8.3h15.6M12 8.5v5.1m0-3.1-4.2 3.3m4.2-3.3 4.2 3.3M12 13.5 8.2 21m3.8-7.5 3.8 7.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>' . $fim;
        case 'girassol':
            return $inicio . '<g fill="currentColor"><ellipse cx="12" cy="3.8" rx="1.7" ry="2.5"/><ellipse cx="12" cy="3.8" rx="1.7" ry="2.5" transform="rotate(45 12 11)"/><ellipse cx="12" cy="3.8" rx="1.7" ry="2.5" transform="rotate(90 12 11)"/><ellipse cx="12" cy="3.8" rx="1.7" ry="2.5" transform="rotate(135 12 11)"/><ellipse cx="12" cy="3.8" rx="1.7" ry="2.5" transform="rotate(180 12 11)"/><ellipse cx="12" cy="3.8" rx="1.7" ry="2.5" transform="rotate(225 12 11)"/><ellipse cx="12" cy="3.8" rx="1.7" ry="2.5" transform="rotate(270 12 11)"/><ellipse cx="12" cy="3.8" rx="1.7" ry="2.5" transform="rotate(315 12 11)"/></g><circle cx="12" cy="11" r="3.2" fill="#6b4b16"/><path d="M12 14.5v7m0-3.8-3.4-1.8m3.4 1.8 3.4-1.8" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>' . $fim;
        case 'infinito':
            return $inicio . '<path d="M7.3 8.1c-2.5 0-4.4 1.7-4.4 3.9s1.9 3.9 4.4 3.9c1.8 0 3.1-1.4 4.7-3.9 1.6-2.5 2.9-3.9 4.7-3.9 2.5 0 4.4 1.7 4.4 3.9s-1.9 3.9-4.4 3.9c-1.8 0-3.1-1.4-4.7-3.9-1.6-2.5-2.9-3.9-4.7-3.9z" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"/>' . $fim;
        case 'quebra-cabeca':
            return $inicio . '<path d="M5 3.5h4c-.3 1.7.8 2.9 2.1 2.9s2.4-1.2 2.1-2.9h5.8c1.1 0 2 .9 2 2v4.1c-1.7-.3-2.9.8-2.9 2.1s1.2 2.4 2.9 2.1v5.7c0 1.1-.9 2-2 2h-4.1c.3-1.7-.8-2.9-2.1-2.9s-2.4 1.2-2.1 2.9H5c-1.1 0-2-.9-2-2v-4.1c1.7.3 2.9-.8 2.9-2.1S4.7 11 3 11.3V5.5c0-1.1.9-2 2-2z" fill="currentColor"/>' . $fim;
    }
    return '';
}

/** HTML da faixa frontal, vazio quando não há autorização ou categoria. */
function aluno_inclusao_html_marcadores($aluno)
{
    $marcadores = aluno_inclusao_marcadores($aluno);
    if (!$marcadores) {
        return '';
    }
    $html = '<div class="cracha-inclusao" role="img" aria-label="Símbolo de inclusão autorizado">';
    foreach ($marcadores as $marcador) {
        $classe = htmlspecialchars($marcador['classe'], ENT_QUOTES, 'UTF-8');
        $html .= '<span class="cracha-inclusao-item cracha-inclusao-' . $classe
            . '" aria-hidden="true" title="' . htmlspecialchars($marcador['rotulo'], ENT_QUOTES, 'UTF-8') . '">'
            . aluno_inclusao_svg($marcador['classe']) . '</span>';
    }
    return $html . '</div>';
}
