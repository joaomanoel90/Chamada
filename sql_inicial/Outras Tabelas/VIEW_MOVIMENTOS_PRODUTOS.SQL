SELECT 
    m.ID,
    m.ID_Produto,
    p.Descricao AS Produto,
    m.TipoMovimento,
    m.Quantidade,
    f.Fornecedor,
    m.Numero_Nota,
    m.ID_Pedido,
    ped.Objetivo,
    u.unidade AS Unidade,
    m.DataMovimento,
    m.ID_Fornecedor
FROM TB_MOVIMENTO m
LEFT JOIN TB_PRODUTOS p ON p.ID = m.ID_Produto
LEFT JOIN TB_FORNECEDORES f ON f.ID = m.ID_Fornecedor
LEFT JOIN TB_PEDIDOS ped ON ped.ID = m.ID_Pedido
LEFT JOIN TB_UNIDADES u ON u.ID = ped.ID_Unidade