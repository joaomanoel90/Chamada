CREATE TABLE TB_MOVIMENTO (
    ID INT IDENTITY(1,1) PRIMARY KEY,
    ID_Produto INT NOT NULL,
    TipoMovimento CHAR(1) NOT NULL CHECK (TipoMovimento IN ('E', 'S', 'A')),  -- Entrada, Saída ou Ajuste
    Quantidade DECIMAL(10, 2) NOT NULL,
    DataMovimento DATETIME NOT NULL DEFAULT GETDATE(),
    Observacao NVARCHAR(255) NULL,
    ID_Pedido INT NULL,
    ID_Fornecedor INT NULL,
    Nomero_Nota INT,


    CONSTRAINT FK_Movimento_Produto FOREIGN KEY (ID_Produto) REFERENCES TB_PRODUTOS (ID),
    CONSTRAINT FK_Movimento_Pedido  FOREIGN KEY (ID_Pedido) REFERENCES TB_PEDIDOS(ID),
    CONSTRAINT FK_Movimento_Fornecedor  FOREIGN KEY (ID_Fornecedor) REFERENCES TB_FORNECEDORES(ID),



);
