SELECT 
    p.ID AS ID_Pedido,
    p.Objetivo,
    p.Ativo,
    p.ID_Unidade,
    p.Status,
    p.DataCadastro,
    p.DataEnvio,
    p.DataFinalizado,
    u.Unidade
FROM 
    TB_PEDIDOS p
INNER JOIN 
    TB_UNIDADES u ON p.ID_Unidade = u.ID;