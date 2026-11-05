SELECT 
    IP.ID,
    IP.ID_Pedido,
    IP.ID_Produto,
    IP.Quantidade,
    IP.Liberado,

    P.Descricao AS Produto,
    P.Unidade,

    P.Descricao AS ProdutoCompleto,

    IP.Ativo

FROM TB_ITENS_PEDIDO IP
INNER JOIN TB_PRODUTOS P ON P.ID = IP.ID_Produto