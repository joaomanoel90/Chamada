SELECT 
    p.ID AS ID_Produto,
    p.Descricao,
    p.Unidade,
    p.Categoria,
    p.Ativo,

    ISNULL(SUM(

        CASE

            WHEN m.TipoMovimento = 'E'
                THEN m.Quantidade

            WHEN m.TipoMovimento = 'S'
                THEN -m.Quantidade

            WHEN m.TipoMovimento = 'A'
                THEN m.Quantidade

            ELSE 0

        END

    ),0) AS SaldoAtual

FROM TB_PRODUTOS p

LEFT JOIN TB_MOVIMENTO m 
    ON p.ID = m.ID_Produto

GROUP BY 
    p.ID,
    p.Descricao,
    p.Unidade,
    p.Categoria,
    p.Ativo